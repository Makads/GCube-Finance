<?php

namespace app\home\controller;

/**
 * @title 前台对接DCIM管理
 * @description 接口说明：前台产品功能及接口
 */
class DcimController extends CommonController
{
    public function initialize()
    {
        parent::initialize();
        if (!intval(request()->is_api)) {
            $action = request()->action();
            if ($action == "hardoff") {
                $action = "hard_off";
            } else {
                if ($action == "hardreboot") {
                    $action = "hard_reboot";
                } else {
                    if ($action == "crackpass") {
                        $action = "crack_pass";
                    }
                }
            }
            $client = \think\Db::name("clients")->where("id", request()->uid)->find();
            $mobile = $client["phonenumber"];
            $email = $client["email"];
            if (isSecondVerify($action)) {
                $action = cmf_parse_name($action, 0);
                $code = request()->code;
                if (empty($code)) {
                    echo json_encode(["status" => 400, "msg" => lang("DCIM_CODE_REQUIRE")]);
                    exit;
                }
                if ($code != cache($action . "_" . $mobile) && $code != cache($action . "_" . $email)) {
                    echo json_encode(["status" => 400, "msg" => "验证码错误"]);
                    exit;
                }
            }
            cache($action . "_" . $mobile, NULL);
            cache($action . "_" . $email, NULL);
        }
        if (request()->id) {
            $is_certifi = \think\Db::name("host")->alias("a")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", request()->uid)->where("a.id", intval(request()->id))->value("c.is_certifi");
        } else {
            $is_certifi = "";
        }
        $is_certifi = json_decode($is_certifi, true) ?: [];
        if (!empty($is_certifi)) {
            $action = request()->action();
            if ($action == "hardoff") {
                $action = "hard_off";
            } else {
                if ($action == "hardreboot") {
                    $action = "hard_reboot";
                } else {
                    if ($action == "crackpass") {
                        $action = "crack_pass";
                    }
                }
            }
            if ($is_certifi[$action] == 1 && !checkCertify(request()->uid)) {
                echo json_encode(["status" => 400, "msg" => lang("DCIM_CHECK_CERTIFY_ERROR")]);
                exit;
            }
        }
    }
    public function buyFlowPacket(\think\Request $request)
    {
        $id = input("post.id", 0, "intval");
        $fid = input("post.fid", 0, "intval");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.productid,a.dcimid,a.serverid,a.bwlimit,b.api_type,b.zjmf_api_id,b.upstream_price_type,b.upstream_price_value,b.name as productname,a.domain,a.dedicatedip")->leftJoin("products b", "a.productid=b.id")->where("a.uid", $uid)->where("a.id", $id)->whereIn("a.domainstatus", ["Active", "Suspended"])->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        if ($host["api_type"] == "zjmf_api") {
            $upstream_header = zjmfCurl($host["zjmf_api_id"], "/host/header", ["host_id" => $host["dcimid"]], 30, "GET");
            if ($upstream_header["status"] == 400) {
                $result["status"] = 400;
                $result["msg"] = lang("DCIM_GET_UPSTREAM_HEADER_ERROR");
                return json($result);
            }
            $flow_packet = [];
            foreach ($upstream_header["data"]["dcim"]["flowpacket"] as $v) {
                if ($v["id"] == $fid) {
                    $flow_packet = $v;
                    if (empty($flow_packet)) {
                        $result["status"] = 400;
                        $result["msg"] = lang("DCIM_GET_UPSTREAM_HEADER_ERROR");
                        return json($result);
                    }
                    if ($flow_packet["leave"] == 0) {
                        $result["status"] = 400;
                        $result["msg"] = lang("DCIM_FLOW_PACKET_LEAVE_ERROR");
                        return json($result);
                    }
                    if ($host["upstream_price_type"] == "percent") {
                        $flow_packet["price"] = round($flow_packet["price"] * $host["upstream_price_value"] / 100, 2);
                    }
                }
            }
        } else {
            if ($host["bwlimit"] == 0) {
                $result["status"] = 400;
                $result["msg"] = lang("DCIM_HOST_BWLIMIT");
                return json($result);
            }
            $flow_packet = \think\Db::name("dcim_flow_packet")->where("id", $fid)->where("status", 1)->whereRaw("FIND_IN_SET('" . $host["productid"] . "', allow_products)")->find();
            if (empty($flow_packet)) {
                $result["status"] = 400;
                $result["msg"] = lang("DCIM_GET_UPSTREAM_HEADER_ERROR");
                return json($result);
            }
            if (0 < $flow_packet["stock"] && $flow_packet["stock"] <= $flow_packet["sale_times"]) {
                $result["status"] = 400;
                $result["msg"] = lang("DCIM_FLOW_PACKET_LEAVE_ERROR");
                return json($result);
            }
        }
        $invoice_data = ["uid" => $uid, "price" => $flow_packet["price"], "relid" => $id, "description" => "流量包订购，大小：" . $flow_packet["capacity"] . "Gb;" . $host["productname"] . "(" . $host["domain"] . "),IP(" . $host["dedicatedip"] . ")", "type" => "zjmf_flow_packet"];
        $r = add_custom_invoice($invoice_data);
        if ($r["status"] != 200) {
            return json($r);
        }
        $invoiceid = $r["invoiceid"];
        $data = ["uid" => $uid, "relid" => $fid, "name" => $flow_packet["name"], "price" => $flow_packet["price"], "status" => 0, "create_time" => time(), "capacity" => $flow_packet["capacity"], "invoiceid" => $invoiceid, "type" => "flow_packet", "hostid" => $id];
        $record = \think\Db::name("dcim_buy_record")->insertGetId($data);
        if ($record) {
            active_log_final(sprintf($this->lang["Dcim_home_buyFlowPacket"], $flow_packet["name"], $flow_packet["capacity"], $id, $invoiceid), $uid, 2, $id, 2);
            hook("flow_packet_invoice_create", ["invoiceid" => $invoiceid, "hostid" => $id, "price" => $flow_packet["price"], "name" => $flow_packet["name"], "capacity" => $flow_packet["capacity"], "flowpacketid" => $fid]);
            $result["status"] = 200;
            $result["msg"] = lang("DCIM_MAKE_PAY_SUCCESS");
            $result["data"]["invoiceid"] = $invoiceid;
        } else {
            $result["status"] = 400;
            $result["msg"] = lang("DCIM_MAKE_PAY_SUCCESS_ERROR", [$invoiceid]);
        }
        return json($result);
    }
    public function buyReinstallTimes(\think\Request $request)
    {
        $id = input("post.id", 0, "intval");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.productid,a.dcimid,a.serverid,a.reinstall_info,b.type,b.api_type,b.zjmf_api_id,b.upstream_price_type,b.upstream_price_value,b.config_option1,c.reinstall_times,c.buy_times,c.reinstall_price,c.auth")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->whereIn("b.type", "dcim,dcimcloud")->where("a.domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $buy = false;
        if ($host["api_type"] == "zjmf_api") {
            $res = zjmfCurl($host["zjmf_api_id"], "/dcim/check_reinstall", ["id" => $host["dcimid"]]);
            if ($res["status"] == 400 && 0 < $res["price"]) {
                $buy = true;
                if ($host["upstream_price_type"] == "percent") {
                    $host["reinstall_price"] = round($res["price"] * $host["upstream_price_value"] / 100, 2);
                } else {
                    $host["reinstall_price"] = $res["price"];
                }
            } else {
                $result["status"] = 400;
                $result["msg"] = "不能购买次数";
                return json($result);
            }
        } else {
            if ($host["buy_times"] == 0 || $host["reinstall_price"] < 0) {
                $result["status"] = 400;
                $result["msg"] = "不能购买次数";
                return json($result);
            }
            if ($host["reinstall_times"] == 0) {
                $result["status"] = 400;
                $result["msg"] = "不需要购买次数";
                return json($result);
            }
            $reinstall_info = json_decode($host["reinstall_info"], true);
            $num = $reinstall_info["num"] ?? 0;
            if (empty($reinstall_info) || strtotime($reinstall_info["date"]) < strtotime("this week Monday")) {
                $num = 0;
            }
            if ($host["buy_times"] == 1) {
                $buy_times = get_buy_reinstall_times($uid, $id);
            } else {
                $buy_times = 0;
            }
            $buy = 0 < $host["reinstall_times"] && $host["reinstall_times"] + $buy_times <= $num;
        }
        if ($buy) {
            $invoice_data = ["uid" => $uid, "price" => $host["reinstall_price"], "relid" => $id, "description" => "购买重装次数", "type" => "zjmf_reinstall_times"];
            $r = add_custom_invoice($invoice_data);
            if ($r["status"] != 200) {
                return json($r);
            }
            $invoiceid = $r["invoiceid"];
            $data = ["uid" => $uid, "relid" => 0, "name" => "重装次数", "price" => $host["reinstall_price"], "status" => 0, "create_time" => time(), "capacity" => 1, "invoiceid" => $invoiceid, "type" => "reinstall_times", "hostid" => $id];
            $record = \think\Db::name("dcim_buy_record")->insertGetId($data);
            if ($record) {
                active_log_final(sprintf($this->lang["Dcim_home_buyReinstallTimes"], $id, $invoiceid), $uid, 2, $id, 2);
                $result["status"] = 200;
                $result["msg"] = "生成支付账单成功，请前往支付";
                $result["data"]["invoiceid"] = $invoiceid;
            } else {
                $result["status"] = 400;
                $result["msg"] = "购买重装次数错误，请联系客服，不要支付生成的账单，ID为：" . $invoiceid;
            }
        } else {
            $result["status"] = 400;
            $result["msg"] = "不需要购买次数";
        }
        return json($result);
    }
    public function checkReinstall(\think\Request $request)
    {
        $id = input("post.id", 0, "intval");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.productid,a.serverid,a.reinstall_info,a.dcimid,b.type,b.api_type,b.zjmf_api_id,b.upstream_price_type,b.upstream_price_value,b.config_option1,c.reinstall_times,c.buy_times,c.reinstall_price,c.auth")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->whereIn("b.type", "dcim,dcimcloud")->whereIn("a.domainstatus", ["Active"])->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        if ($host["api_type"] == "zjmf_api") {
            $result = zjmfCurl($host["zjmf_api_id"], "/dcim/check_reinstall", ["id" => $host["dcimid"]]);
            if ($result["status"] == 400 && 0 < $result["price"] && $host["upstream_price_type"] == "percent") {
                $result["price"] = round($result["price"] * $host["upstream_price_value"] / 100, 2);
            }
        } else {
            if ($host["type"] == "dcim" && $host["config_option1"] != "bms") {
                $auth = json_decode($host["auth"], true);
                if ($auth["reinstall"] != "on") {
                    $result["status"] = 403;
                    $result["msg"] = "没有权限";
                    return json($result);
                }
            }
            if ($host["reinstall_times"] == 0) {
                $result["status"] = 200;
                $result["msg"] = "可以重装";
                $result["max_times"] = 0;
                return json($result);
            }
            $reinstall_info = json_decode($host["reinstall_info"], true);
            $num = $reinstall_info["num"] ?? 0;
            if (empty($reinstall_info) || strtotime($reinstall_info["date"]) < strtotime("this week Monday")) {
                $num = 0;
            }
            if ($host["buy_times"] == 1) {
                $buy_times = get_buy_reinstall_times($uid, $id);
            } else {
                $buy_times = 0;
            }
            if (0 < $host["reinstall_times"] && $host["reinstall_times"] + $buy_times <= $num) {
                if (0 < $host["buy_times"]) {
                    $result["status"] = 400;
                    $result["msg"] = "可以购买重装次数";
                    $result["price"] = $host["reinstall_price"];
                } else {
                    $result["status"] = 400;
                    $result["msg"] = "本周重装次数已达最大限额，请下周重试或联系技术支持";
                }
                return json($result);
            }
            $result["status"] = 200;
            $result["msg"] = "可以重装";
            $result["num"] = $num;
            $result["max_times"] = $host["reinstall_times"] + $buy_times;
        }
        return json($result);
    }
    public function on(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "on");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->on($id);
        return json($result);
    }
    public function off(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "off");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->off($id);
        return json($result);
    }
    public function reboot(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "reboot");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->reboot($id);
        return json($result);
    }
    public function bmc(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "bmc");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->bmc($id);
        if ($result["status"] == 400) {
            $result["msg"] = "重置失败";
        }
        return json($result);
    }
    public function kvm(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "kvm");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->kvm($id);
        return json($result);
    }
    public function ikvm(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $check = check_dcim_auth($id, $uid, "ikvm");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->ikvm($id);
        return json($result);
    }
    public function download()
    {
        $token = input("get.token");
        if (empty($token)) {
            return json(["status" => 400, "msg" => "禁止操作"]);
        }
        $token = aesPasswordDecode($token);
        $arr = explode("|", $token);
        if (count($arr) == 2 && $arr[1] == "zjmf" && time() - $arr[0] < 30) {
            $name = input("get.name");
            $name = str_replace("/", "", $name);
            header("Access-Control-Expose-Headers: Content-disposition");
            $file = UPLOAD_PATH . "common/default/" . $name . ".jnlp";
            if (file_exists($file)) {
                $length = filesize($file);
                $showname = $name . ".jnlp";
                $expire = 1800;
                header("Pragma: public");
                header("Cache-control: max-age=" . $expire);
                header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expire) . "GMT");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . "GMT");
                header("Content-Disposition: attachment; filename=" . $showname);
                header("Content-Length: " . $length);
                header("Content-type: text/x-java-source");
                header("Content-Encoding: none");
                header("Content-Transfer-Encoding: binary");
                readfile($file);
                sleep(2);
                unlink($file);
            } else {
                return \think\Response::create()->code(404);
            }
        } else {
            return json(["status" => 400, "msg" => "禁止操作"]);
        }
    }
    public function reinstall(\think\Request $request)
    {
        $params = input("post.");
        $id = $params["id"];
        $uid = $request->uid;
        $validate = new \app\common\validate\DcimValidate();
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return json(["status" => 406, "msg" => $validate->getError()]);
        }
        $check = check_dcim_auth($id, $uid, "reinstall");
        if ($check["status"] != 200) {
            return json($check);
        }
        $data = ["rootpass" => $params["password"], "action" => $params["action"], "mos" => $params["os"], "mcon" => $params["mcon"], "port" => $params["port"], "disk" => $params["disk"] ?? 0, "check_disk_size" => $params["check_disk_size"] ?? 0, "part_type" => $params["part_type"] ?? 0];
        $dcim = new \app\common\logic\Dcim();
        $dcim->is_admin = false;
        $result = $dcim->reinstall($id, $data);
        return json($result);
    }
    public function getReinstallStatus(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("get.id", 0, "intval");
        $host = \think\Db::name("host")->alias("a")->field("a.domainstatus")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->reinstallStatus($id);
        return json($result);
    }
    public function rescue(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id", 0, "intval");
        $system = input("post.system", 0, "intval");
        $check = check_dcim_auth($id, $uid, "rescue");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->rescue($id, $system);
        return json($result);
    }
    public function crackPass(\think\Request $request)
    {
        $params = input("post.");
        $id = $params["id"];
        $uid = $request->uid;
        $data = ["crack_password" => $params["password"], "other_user" => intval($params["other_user"]), "user" => $params["user"] ?? "", "action" => $params["action"] ?? ""];
        $product = \think\Db::name("host")->alias("a")->field("a.serverid,a.dcimid,a.show_last_act_message,a.uid,b.config_option1,b.api_type,b.zjmf_api_id,b.password,a.productid")->leftJoin("products b", "a.productid=b.id")->where("b.type", "dcim")->where("a.id", $id)->find();
        if (empty($product)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return $result;
        }
        if ($product["api_type"] == "whmcs") {
            $dcimid = \think\Db::name("customfieldsvalues")->alias("a")->leftJoin("customfields b", "a.fieldid=b.id")->where("a.relid", $id)->where("b.type", "product")->where("b.relid", $product["productid"])->where("b.fieldname", "hostid")->value("value");
            $product["dcimid"] = $dcimid;
        }
        if (empty($product["dcimid"])) {
            $result["status"] = 400;
            $result["msg"] = "服务器ID错误";
            return $result;
        }
        $check_pass = (new \app\common\logic\Shop($product["uid"]))->checkHostPassword($data["crack_password"], $product["productid"]);
        if ($check_pass["status"] == 400) {
            return json($check_pass);
        }
        $check = check_dcim_auth($id, $uid, "crack_pass");
        if ($check["status"] != 200) {
            return json($check);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->crackPass($id, $data);
        return json($result);
    }
    public function getTrafficUsage(\think\Request $request)
    {
        $id = input("get.id");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->whereIn("domainstatus", "Active,Suspended")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $end = input("get.end");
        $start = input("get.start");
        $end = strtotime($end) ? date("Y-m-d", strtotime($end)) : date("Y-m-d");
        $start = strtotime($start) ? date("Y-m-d", strtotime($start)) : date("Y-m-d", strtotime("-30 days"));
        if (str_replace("-", "", $start) < str_replace("-", "", date("Y-m-d", $host["regdate"]))) {
            $start = date("Y-m-d", $host["regdate"]);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->getTrafficUsage($id, $start, $end);
        return json($result);
    }
    public function cancelReinstall(\think\Request $request)
    {
        $id = input("post.id");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->cancelReinstall($id);
        return json($result);
    }
    public function unsuspendReload(\think\Request $request)
    {
        $id = input("post.id");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->unsuspendReload($id, input("post.disk_part"));
        return json($result);
    }
    public function refreshPowerStatus(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id");
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.dcimid,c.hostname,c.username,c.password,c.secure,c.port,b.api_type,b.zjmf_api_id")->leftJoin("products b", "a.productid=b.id")->leftJoin("servers c", "a.serverid=c.id")->where("a.uid", $uid)->whereIn("a.id", $id)->where("b.type", "dcim")->where("a.domainstatus", "Active")->select()->toArray();
        $result["data"] = [];
        if (!empty($host)) {
            $data = [];
            $zjmf_api = [];
            foreach ($host as $v) {
                if ($v["api_type"] == "zjmf_api") {
                    $zjmf_api[$v["zjmf_api_id"]][$v["dcimid"]] = $v["id"];
                } else {
                    $protocol = $v["secure"] == 1 ? "https://" : "http://";
                    $url = $protocol . $v["hostname"];
                    if (!empty($v["port"])) {
                        $url .= ":" . $v["port"];
                    }
                    $data[$v["id"]] = ["url" => $url . "/index.php?m=api&a=ipmiPowerSync", "data" => ["username" => $v["username"], "password" => aesPasswordDecode($v["password"]) ?? "", "id" => $v["dcimid"]]];
                }
            }
            $res = [];
            if (!empty($data)) {
                $res = batch_curl_post($data, 20);
                foreach ($res as $k => $v) {
                    $one["id"] = $k;
                    if ($v["http_code"] != 200) {
                        $one["status"] = "error";
                        $one["msg"] = $v["msg"] ?? "获取失败";
                    } else {
                        if ($v["data"]["status"] == "success") {
                            if ($v["data"]["msg"] == "on") {
                                $one["status"] = "on";
                            } else {
                                if ($v["data"]["msg"] == "off") {
                                    $one["status"] = "off";
                                } else {
                                    $one["status"] = "error";
                                }
                            }
                        } else {
                            if ($v["data"]["msg"] == "nonsupport") {
                                $one["status"] = "not_support";
                            } else {
                                $one["status"] = "error";
                            }
                        }
                        $one["msg"] = $v["data"]["power_msg"] ?? "";
                    }
                    $result["data"][] = $one;
                }
            }
            if (empty($result["data"])) {
                $result["data"] = [];
            }
            foreach ($zjmf_api as $k => $v) {
                $r = zjmfCurl($k, "/dcim/refresh_all_power_status", ["id" => array_keys($v)]);
                if ($r["status"] == 200) {
                    foreach ($r["data"] as $vv) {
                        $result["data"][] = ["id" => $v[$vv["id"]], "msg" => $vv["msg"] ?: "获取失败", "status" => $vv["status"]];
                    }
                } else {
                    foreach ($v as $vv) {
                        $result["data"][] = ["id" => $vv, "msg" => "获取失败", "status" => "error"];
                    }
                }
            }
        }
        $result["status"] = 200;
        return json($result);
    }
    public function traffic(\think\Request $request)
    {
        $id = input("post.id");
        $uid = $request->uid;
        $params = input("post.");
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $check = check_dcim_auth($id, $uid, "traffic");
        if ($check["status"] != 200) {
            return json($check);
        }
        if (empty($params["end_time"])) {
            $params["end_time"] = time() . "000";
        }
        if (empty($params["start_time"])) {
            $params["start_time"] = strtotime("-7 days") . "000";
        }
        if ($params["end_time"] < $params["start_time"]) {
            $result["status"] = 400;
            $result["msg"] = "开始时间不能晚于结束时间";
        }
        $start_time = date("Ymd", $params["start_time"] / 1000);
        if ($start_time < date("Ymd", $host["regdate"])) {
            $params["start_time"] = $host["regdate"] . "000";
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->traffic($id, $params);
        return json($result);
    }
    public function novnc(\think\Request $request)
    {
        $id = input("post.id");
        $restart = input("post.restart", 0, "intval");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
        } else {
            $check = check_dcim_auth($id, $uid, "novnc");
            if ($check["status"] != 200) {
                return json($check);
            }
            $dcim = new \app\common\logic\Dcim();
            $result = $dcim->novnc($id, $restart);
        }
        return json($result);
    }
    public function novncPage()
    {
        $password = input("get.password");
        $url = input("get.url");
        $url = base64_decode(urldecode($url));
        $host_token = input("get.host_token");
        $type = input("get.type");
        $data = ["url" => $url, "password" => $password, "host_token" => !empty($host_token) ? aesPasswordDecode($host_token) : "", "restart_vnc" => "", "id" => input("get.id", 0, "intval")];
        if (!empty($host_token)) {
            $data["paste_button"] = "<div id=\"pastePassword\">粘贴密码</div>";
        } else {
            $data["paste_button"] = "";
        }
        if ($type == "dcim") {
            $data["restart_vnc"] = "<div id=\"restart_vnc\">强制刷新vnc</div>";
        }
        return view("./vendor/dcim/novnc.html")->assign($data);
    }
    public function checkAllReinstallStatus(\think\Request $request)
    {
        $uid = $request->uid;
        $id = input("post.id");
        $host = \think\Db::name("host")->alias("a")->field("a.id,a.dcimid,c.hostname,c.username,c.password,c.secure,c.port")->leftJoin("products b", "a.productid=b.id")->leftJoin("servers c", "a.serverid=c.id")->where("a.uid", $uid)->whereIn("a.id", $id)->where("b.type", "dcim")->where("a.dcimid", ">", 0)->where("a.domainstatus", "Active")->select()->toArray();
        $result["data"] = [];
        if (!empty($host)) {
            $data = [];
            foreach ($host as $v) {
                $protocol = $v["secure"] == 1 ? "https://" : "http://";
                $url = $protocol . $v["hostname"];
                if (!empty($v["port"])) {
                    $url .= ":" . $v["port"];
                }
                $data[$v["id"]] = ["url" => $url . "/index.php?m=api&a=getReinstallStatus", "data" => ["username" => $v["username"], "password" => aesPasswordDecode($v["password"]) ?? "", "id" => $v["dcimid"], "hostid" => $v["id"]]];
            }
            $res = batch_curl_post($data, 20);
            foreach ($res as $k => $v) {
                if ($v["data"]["status"] == "success" && $v["http_code"] == 200 && !empty($v["data"]["data"]) && !$v["data"]["data"]["windows_finish"]) {
                    $result["data"][] = $k;
                }
            }
        }
        $result["status"] = 200;
        return json($result);
    }
    public function detail(\think\Request $request)
    {
        $id = input("get.id");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host)) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->detail($id);
        return json($result);
    }
    public function hideLastResult(\think\Request $request)
    {
        $id = input("post.id");
        $uid = $request->uid;
        $host = \think\Db::name("host")->alias("a")->field("a.regdate,a.dcimid,b.api_type,b.zjmf_api_id")->leftJoin("products b", "a.productid=b.id")->leftJoin("dcim_servers c", "a.serverid=c.serverid")->where("a.uid", $uid)->where("a.id", $id)->where("b.type", "dcim")->where("domainstatus", "Active")->find();
        if (empty($host) || empty($host["dcimid"])) {
            $result["status"] = 400;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        if ($host["api_type"] == "zjmf_api") {
            $post_data["id"] = $host["dcimid"];
            $result = zjmfCurl($host["zjmf_api_id"], "/dcim/hide_result", $post_data);
        } else {
            \think\Db::name("host")->where("id", $id)->update(["show_last_act_message" => 0]);
            $result["status"] = 200;
        }
        return json($result);
    }
    public function refreshServerPowerStatus()
    {
        $id = input("post.id", 0, "intval");
        $dcim = new \app\common\logic\Dcim();
        $result = $dcim->refreshPowerStatus($id);
        return json($result);
    }
}

?>