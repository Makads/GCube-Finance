<?php

namespace app\home\controller;

/**
 * @title 前台工单
 * @description 接口说明
 */
class TicketController extends CommonController
{
    public function getOpenTicketPage(\think\Request $request)
    {
        $uid = $request->uid;
        $can = cmf_get_conf("nologin_send_ticket", 0);
        if (empty($uid) && empty($can)) {
            $result["status"] = 404;
            $result["msg"] = "没有可用数据";
            return json($result);
        }
        $returndata = [];
        $returndata["host_list"] = [];
        if ($uid) {
            $have_user = 1;
            $user_info = \think\Db::name("clients")->field("username,email")->find();
            $returndata["user_info"] = $user_info;
            $host_list = \think\Db::name("host")->where("uid", $uid)->field("h.id,p.name as product_name,h.id,h.domainstatus,h.domain,h.dedicatedip")->alias("h")->leftJoin("products p", "p.id=h.productid")->whereIn("h.domainstatus", ["Pending", "Active", "Suspended"])->select()->toArray();
            $config_domainstatus = config("domainstatus");
            $returndata["host_list"][0] = "无";
            foreach ($host_list as $key => $val) {
                $name_desc = $val["product_name"];
                if ($val["dedicatedip"]) {
                    $name_desc .= " - " . $val["dedicatedip"];
                }
                $name_desc .= "(" . $config_domainstatus[$val["domainstatus"]] . ")";
                $host_list[$key]["name_desc"] = $name_desc;
                $returndata["host_list"][$val["id"]] = $name_desc;
            }
        } else {
            $have_user = 0;
        }
        $returndata["have_user"] = $have_user;
        $priority = ["High" => "高", "Medium" => "中", "Low" => "低"];
        $returndata["priority"] = $priority;
        if (empty($uid)) {
            $depart = \think\Db::name("ticket_department")->field("id,name")->where("only_reg_client", 0)->where("hidden", 0)->select()->toArray();
        } else {
            $depart = \think\Db::name("ticket_department")->field("id,name")->where("hidden", 0)->select()->toArray();
        }
        $returndata["depart"] = $depart;
        return jsons(["status" => 200, "data" => $returndata]);
    }
    public function getTicketCustom(\think\Request $request)
    {
        $param = $request->param();
        $depart_id = (int) $param["depart_id"];
        if (empty($depart_id)) {
            return json(["status" => 200, "data" => []]);
        }
        $ticket_custom_data = \think\Db::name("customfields")->field("id,fieldname,fieldtype,description,fieldoptions,regexpr,required,adminonly")->where("relid", $depart_id)->where("adminonly", 0)->where("type", "ticket")->select()->toArray();
        if (!empty($ticket_custom_data)) {
            foreach ($ticket_custom_data as $key => $val) {
                if ($val["fieldtype"] == "dropdown") {
                    $ticket_custom_data[$key]["child"] = explode(",", $val["fieldoptions"]);
                }
            }
        }
        return jsons(["status" => 200, "data" => $ticket_custom_data]);
    }
    public function getDepartmentList(\think\Request $request)
    {
        $uid = $request->uid;
        $can = cmf_get_conf("nologin_send_ticket", 0);
        if (empty($uid) && empty($can)) {
            $result["status"] = 404;
            $result["msg"] = "没有可用数据";
            return json($result);
        }
        if (empty($uid)) {
            $data = \think\Db::name("ticket_department")->field("id,name,description")->where("only_reg_client", 0)->where("hidden", 0)->select()->toArray();
        } else {
            $data = \think\Db::name("ticket_department")->field("id,name,description")->where("hidden", 0)->select()->toArray();
        }
        $result["status"] = 200;
        $result["msg"] = lang("SUCCESS MESSAGE");
        $result["data"] = $data;
        return jsons($result);
    }
    public function createTicket(\think\Request $request)
    {
        $params = input("post.");
        if ($request->attachment) {
            $params["attachment"] = $request->attachment;
        }
        $rule = ["dptid" => "require|number", "title" => "require", "content" => "require|length:0,10000", "email" => "email", "hostid" => "number"];
        $msg = ["dptid.require" => "请选择工单部门", "dptid.number" => "类型错误", "title.require" => "请输入工单标题", "content.require" => "请输入工单内容", "email.email" => "邮箱格式错误", "hostid.number" => "产品错误"];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return json(["status" => 406, "msg" => $validate->getError()]);
        }
        $uid = $request->uid;
        $can = cmf_get_conf("nologin_send_ticket", 0);
        if (empty($uid) && empty($can)) {
            $result["status"] = 406;
            $result["msg"] = lang("ID_ERROR");
            return json($result);
        }
        $data = [];
        if (!empty($uid)) {
            $user_info = \think\Db::name("clients")->where("id", $uid)->find();
            if (empty($user_info)) {
                $result["status"] = 400;
                $result["msg"] = "illegal user";
                return json($result);
            }
            $data["uid"] = $uid;
            $data["name"] = $user_info["username"];
            $data["email"] = $user_info["email"];
            $department = \think\Db::name("ticket_department")->field("id,name,is_product_order,is_certifi")->where("id", $params["dptid"])->where("hidden", 0)->find();
        } else {
            if (empty($params["name"]) || empty($params["email"])) {
                $result["status"] = 400;
                $result["msg"] = "Name and email address are required";
                return json($result);
            }
            $data["uid"] = 0;
            $data["name"] = $params["name"];
            $data["email"] = $params["email"];
            $department = \think\Db::name("ticket_department")->field("id,name,is_product_order,is_certifi")->where("only_reg_client", 0)->where("hidden", 0)->where("id", $params["dptid"])->find();
        }
        if (empty($department)) {
            $result["status"] = 406;
            $result["msg"] = "部门ID错误";
            return json($result);
        }
        if ($department["is_certifi"] == 1 && !checkCertify($uid)) {
            return json(["status" => 406, "msg" => "请先实名认证"]);
        }
        if ($department["is_product_order"] == 1) {
            $host = \think\Db::name("host")->alias("h")->join("orders o", "o.id=h.orderid")->field("h.id")->where("h.uid", $uid)->find();
            if (empty($host)) {
                $result["status"] = 406;
                $result["msg"] = "您没有购买产品，不能发起工单";
                return json($result);
            }
        }
        $customfields = \think\Db::name("customfields")->where("relid", $department["id"])->where("type", "ticket")->select()->toArray();
        foreach ($customfields as $k => $v) {
            if (!empty($v["required"]) && !isset($params["customfield"][$v["id"]]) && $params["customfield"][$v["id"]] == "") {
                $result["status"] = 406;
                $result["msg"] = $v["fieldname"] . "不能为空";
                return json($result);
            }
        }
        $data["dptid"] = $department["id"];
        $service = intval($params["service"]);
        if (!empty($service)) {
            $service = \think\Db::name("host")->field("id")->where("uid", $uid)->where("id", $params["id"])->find();
            if (!empty($service["id"])) {
                $service = $service["id"];
            } else {
                $service = 0;
            }
        }
        $data["attachment"] = "";
        if (isset($params["attachment"][0])) {
            $upload = new \app\common\logic\Upload();
            $ret = $upload->moveTo($params["attachment"], config("ticket_attachments"));
            if (isset($ret["error"])) {
                $result["status"] = 406;
                $result["msg"] = lang("UPLOAD FAIL");
                return json($result);
            }
            $data["attachment"] = implode(",", $ret);
        }
        switch ($params["select"]) {
            case "高":
                $data["priority"] = "high";
                break;
            case "中":
                $data["priority"] = "medium";
                break;
            case "低":
                $data["priority"] = "low";
                break;
            default:
                $data["create_time"] = time();
                $data["title"] = $params["title"];
                $data["content"] = $params["content"];
                $data["status"] = 1;
                $data["priority"] = !empty($params["priority"]) ? $params["priority"] : $data["priority"];
                $data["admin"] = "";
                $data["admin_unread"] = 1;
                $data["tid"] = model("Ticket")->getTid();
                $data["c"] = cmf_random_str(8, "lower_upper_number");
                $data["last_reply_time"] = time();
                $data["service"] = $service;
                $data["cc"] = "";
                $data["host_id"] = $params["hostid"] ?? 0;
                $data["is_receive"] = isset($params["is_api"]) ? 1 : 0;
                $data["token"] = md5(uniqid() . randStr() . $data["tid"] . mt_rand(100, 999));
                $r = \think\Db::name("ticket")->insertGetId($data);
                if ($r) {
                    ticketDeliver($r);
                    $create_time = time();
                    foreach ($customfields as $v) {
                        if (isset($params["customfield"][$v["id"]])) {
                            if (empty($v["regexpr"]) || preg_match("/" . str_replace("/", "\\/", $v["regexpr"]) . "/", $params["customfield"][$v["id"]])) {
                                if ($v["fieldname"] != "select" || in_array($params["customfield"][$v["id"]], explode(",", $v["fieldoptions"]))) {
                                    $insert = ["fieldid" => $v["id"], "relid" => $r, "value" => $params["customfield"][$v["id"]], "create_time" => $create_time, "update_time" => 0];
                                    $model = \think\Db::name("customfieldsvalues")->insert($insert);
                                }
                            }
                        }
                    }
                    $departmentadmin = \think\Db::name("ticket_department_admin")->field("t.admin_id,u.only_oneself_notice")->alias("t")->leftJoin("User u", "u.id=t.admin_id")->where("t.dptid", $params["dptid"])->select()->toArray();
                    $curl_multi_data = [];
                    foreach ($departmentadmin as $key => $value) {
                        if (!($value["only_oneself_notice"] == 1 && $value["admin_id"] != $user_info["sale_id"])) {
                            $arr_admin = ["relid" => $r, "name" => "【管理员】新工单提示", "type" => "support", "sync" => true, "admin" => true, "adminid" => $value["admin_id"], "ip" => get_client_ip6()];
                            $curl_multi_data[count($curl_multi_data)] = ["url" => "async", "data" => $arr_admin];
                        }
                    }
                    $arr_client = ["relid" => $r, "name" => "工单创建成功", "type" => "support", "sync" => true, "admin" => false, "ip" => get_client_ip6()];
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async", "data" => $arr_client];
                    $message_template_type = array_column(config("message_template_type"), "id", "name");
                    $sms = new \app\common\logic\Sms();
                    $client = check_type_is_use($message_template_type[strtolower("Support_Ticket_Opened")], $data["uid"], $sms);
                    if ($client) {
                        $params = ["ticket_createtime" => date("Y-m-d H:i:s", time()), "ticketnumber_tickettitle" => $data["title"]];
                        $arr_sms = ["name" => $message_template_type[strtolower("Support_Ticket_Opened")], "phone" => $client["phone_code"] . $client["phonenumber"], "params" => json_encode($params), "sync" => false, "uid" => $data["uid"], "username" => $client["username"], "delay_time" => 0, "is_market" => false];
                        $curl_multi_data[count($curl_multi_data)] = ["url" => "async_sms", "data" => $arr_sms];
                    }
                    asyncCurlMulti($curl_multi_data);
                    $data["attachment"] = array_filter(explode(",", $data["attachment"]));
                    foreach ($data["attachment"] as $k => $v) {
                        $data["attachment"][$k] = config("ticket_attachments") . $v;
                    }
                    $hook_data = ["ticketid" => $r, "tid" => $data["tid"], "uid" => $uid, "dptid" => $data["dptid"], "dptname" => $department["name"], "title" => html_entity_decode($data["title"], ENT_QUOTES), "content" => html_entity_decode($data["content"], ENT_QUOTES), "priority" => $data["priority"], "hostid" => $data["host_id"], "attachment" => $data["attachment"] ?: []];
                    hook("ticket_open", $hook_data);
                }
                active_logs(sprintf($this->lang["Ticket_home_createTicket"], $data["tid"], $data["title"]), $uid);
                active_logs(sprintf($this->lang["Ticket_home_createTicket"], $data["tid"], $data["title"]), $uid, "", 2);
                $host_obj = new \app\common\logic\Host();
                $host_obj->createTicket($data["host_id"], $r);
                if ($r) {
                    $ticket_deliver = \think\Db::name("ticket_deliver")->alias("a")->leftJoin("ticket_deliver_products b", "b.tdid = a.id")->leftJoin("ticket_deliver_department c", "c.tdid=b.tdid")->leftJoin("ticket d", "d.dptid=c.dptid")->leftJoin("host e", "e.id=d.host_id")->leftJoin("products f", "f.id=e.productid")->leftJoin("ticket_department_upstream g", "g.dptid=c.dptid AND f.zjmf_api_id=g.api_id")->field("a.is_open_auto_reply,a.bz,a.id")->where("e.productid=b.pid")->where("d.id", $r)->find();
                    $td = \think\Db::name("ticket_department")->where("id", $this->request->dptid)->find();
                    if (!empty($ticket_deliver["id"])) {
                        $td["is_open_auto_reply"] = $ticket_deliver["is_open_auto_reply"];
                        $td["bz"] = $ticket_deliver["bz"];
                    }
                    if ($td["is_open_auto_reply"] == 1) {
                        if ($td["time_type"] == 1) {
                            $td["minutes"] = $td["minutes"] * 60;
                        }
                        $reply["tid"] = $r;
                        $reply["uid"] = $uid;
                        $reply["create_time"] = time();
                        $reply["content"] = $td["bz"];
                        $reply["admin_id"] = 1;
                        $reply["admin"] = "admin";
                        $reply["attachment"] = "";
                        \think\Db::name("ticket_reply")->insertGetId($reply);
                        \think\Db::name("ticket")->where("id", $r)->update(["is_auto_reply" => 1, "admin_unread" => 1, "last_reply_time" => time()]);
                        active_log(sprintf("自动回复工单成功#User ID:%d - Ticket ID:%s - %s", $uid, $r, $params["title"]), $uid);
                    }
                }
                $result["status"] = 200;
                $result["msg"] = "添加成功";
                $result["data"] = ["tid" => $data["tid"], "c" => $data["c"]];
                return jsons($result);
        }
    }
    public function ticketDetail(\think\Request $request)
    {
        $params = input("get.");
        $uid = $request->uid;
        if (empty($uid)) {
            if (!cmf_get_conf("nologin_send_ticket", 0)) {
                $result["status"] = 406;
                $result["msg"] = "没有权限";
                return json($result);
            }
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "c" => $params["c"]]);
        } else {
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "uid" => $uid]);
        }
        if (empty($ticket)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return json($result);
        }
        $evaluate = cmf_get_conf("evaluate_ticket", 0);
        $id = $ticket["id"];
        $department = \think\Db::name("ticket_department")->field("feedback_request")->where("id", $ticket["dptid"])->find();
        \think\Db::name("ticket")->where("id", $id)->update(["client_unread" => 0]);
        $url = $this->request->host() . config("ticket_url");
        $list[] = ["id" => $id, "type" => "t", "star" => $ticket["star"] ?? 0, "uid" => $ticket["uid"], "admin_id" => $ticket["admin_id"], "attachment" => isset($ticket["attachment"][0]) ? array_map(function ($v) {
            return $url . $v;
        }, explode(",", $ticket["attachment"])) : [], "time" => $ticket["create_time"], "format_time" => date("Y-m-d H:i:s", $ticket["create_time"])];
        $list[0]["real_name"] = "";
        if (0 < $ticket["admin_id"]) {
            $list[0]["content"] = htmlspecialchars_decode($ticket["content"]);
            $list[0]["user"] = $ticket["admin"];
            $list[0]["user_type"] = lang("ADMIN");
            $tmp = \think\Db::name("user")->find($list[0]["admin_id"]);
            if (isset($tmp["user_nickname"])) {
                $list[0]["user"] = $tmp["user_nickname"];
                $list[0]["realname"] = $tmp["user_nickname"];
            }
        } else {
            if (0 < $ticket["uid"]) {
                $list[0]["content"] = ticketContent($ticket["content"]);
                $list[0]["user"] = $ticket["name"];
                $list[0]["user_type"] = lang("USER");
                $tmp = \think\Db::name("clients")->find($ticket["uid"]);
                if (isset($tmp["username"])) {
                    $list[0]["user"] = $tmp["username"];
                    $list[0]["realname"] = $tmp["username"];
                }
                $list[0]["avatar"] = base64EncodeImage(config("client_avatar") . $ticket["avatar"]);
            }
        }
        $reply = \think\Db::name("ticket_reply")->order("create_time", "DESC")->where("tid", $id)->select()->toArray();
        foreach ($reply as $v) {
            $v["type"] = "r";
            $v["attachment"] = isset($v["attachment"][0]) ? array_map(function ($v1) {
                return $url . $v1;
            }, explode(",", $v["attachment"])) : [];
            $v["time"] = $v["create_time"];
            $v["format_time"] = date("Y-m-d H:i:s", $v["create_time"]);
            if (!empty($v["admin"])) {
                $v["content"] = htmlspecialchars_decode($v["content"]);
                $v["user_type"] = lang("ADMIN");
                $v["user"] = $v["admin"];
                $v["realname"] = "";
                $tmp = \think\Db::name("user")->find($v["admin_id"]);
                if (isset($tmp["user_nickname"])) {
                    $v["user"] = $tmp["user_nickname"];
                    $v["realname"] = $tmp["user_nickname"];
                }
            } else {
                $v["content"] = ticketContent($v["content"]);
                $v["user"] = $v["name"];
                $v["user_type"] = lang("USER");
                $tmp = \think\Db::name("clients")->find($v["uid"]);
                if (isset($tmp["username"])) {
                    $v["user"] = $tmp["username"];
                    $v["realname"] = $tmp["username"];
                }
                $v["avatar"] = base64EncodeImage(config("client_avatar") . $v["avatar"]);
            }
            $list[] = $v;
        }
        $reply = \think\Db::name("ticket")->order("id", "DESC")->where("merged_ticket_id", $id)->select()->toArray();
        foreach ($reply as $v) {
            $v["type"] = "t";
            $v["attachment"] = isset($v["attachment"][0]) ? array_map(function ($v1) {
                return $url . $v1;
            }, explode(",", $v["attachment"])) : [];
            $v["time"] = $v["create_time"];
            $v["format_time"] = date("Y-m-d H:i:s", $v["create_time"]);
            $v["realname"] = "";
            if (0 < $v["admin_id"]) {
                $v["content"] = htmlspecialchars_decode($v["content"]);
                $v["user_type"] = lang("ADMIN");
                $v["user"] = $v["admin"];
                $tmp = \think\Db::name("user")->find($v["admin_id"]);
                if (isset($tmp["user_nickname"])) {
                    $v["user"] = $tmp["user_nickname"];
                    $v["realname"] = $tmp["user_nickname"];
                }
            } else {
                if (0 < $v["uid"]) {
                    $v["content"] = ticketContent($v["content"]);
                    $v["user"] = $v["name"];
                    $v["user_type"] = lang("USER");
                    $tmp = \think\Db::name("clients")->find($v["uid"]);
                    if (isset($tmp["username"])) {
                        $v["user"] = $tmp["username"];
                        $v["realname"] = $tmp["username"];
                    }
                    $v["avatar"] = base64EncodeImage(config("client_avatar") . $v["avatar"]);
                }
            }
            $list[] = $v;
        }
        if (cmf_get_conf("ticket_reply_order", "asc") == "desc") {
            array_multisort(array_column($list, "time"), SORT_DESC, $list);
        } else {
            array_multisort(array_column($list, "time"), SORT_ASC, $list);
        }
        $ticket["status"] = \think\Db::name("ticket_status")->where("id", $ticket["status"])->find();
        $ticket["department"] = \think\Db::name("ticket_department")->where("id", $ticket["dptid"])->find();
        $ticket["host"] = "";
        $ticket["product"] = "";
        if (0 < $ticket["host_id"]) {
            $host = \think\Db::name("host")->alias("a")->field("b.name,a.domain,a.id")->leftJoin("products b", "a.productid = b.id")->where("a.id", $ticket["host_id"])->find();
            $ticket["host"] = $host["name"] . "-" . $host["domain"];
        }
        $priority = ["high" => "高", "medium" => "中", "low" => "低"];
        $ticket["priority"] = $priority[$ticket["priority"]];
        $result["status"] = 200;
        $result["data"] = ["list" => $list, "ticket" => $ticket, "evaluate" => $evaluate, "feedback_request" => $department["feedback_request"]];
        return jsons($result);
    }
    public function replyTicket(\think\Request $request)
    {
        $params = input("post.");
        $rule = ["tid" => "require", "content" => "require|length:0,10000", "email" => "email"];
        $msg = ["tid.require" => "ID错误", "content.require" => "请输入回复内容", "email.email" => "邮箱格式错误"];
        if ($request->attachment) {
            $params["attachment"] = $request->attachment;
        }
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return json(["status" => 406, "msg" => $validate->getError()]);
        }
        $uid = $request->uid;
        $data = [];
        if (empty($uid)) {
            if (!cmf_get_conf("nologin_send_ticket", 0)) {
                $result["status"] = 406;
                $result["msg"] = "没有权限";
                return json($result);
            }
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "c" => $params["c"]]);
        } else {
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "uid" => $uid]);
        }
        if (empty($ticket)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return json($result);
        }
        $upload = new \app\common\logic\Upload();
        $ret = $upload->moveTo($params["attachment"], config("ticket_attachments"));
        if (isset($ret["error"])) {
            $result["status"] = 406;
            $result["msg"] = lang("UPLOAD FAIL");
            return json($result);
        }
        $data["tid"] = $ticket["id"];
        $data["uid"] = intval($uid);
        $data["create_time"] = time();
        $data["content"] = $params["content"];
        $data["attachment"] = implode(",", $ret) ?: "";
        $data["is_receive"] = isset($params["is_api"]) ? 1 : 0;
        \think\Db::startTrans();
        try {
            $r1 = \think\Db::name("ticket_reply")->insertGetId($data);
            $r2 = \think\Db::name("ticket")->where("id", $data["tid"])->update(["admin_unread" => 1, "last_reply_time" => time(), "status" => 3]);
            if ($r1 && $r2) {
                \think\Db::commit();
                ticketReplyDeliver($r1);
                $result["status"] = 200;
                $result["msg"] = "回复成功";
            } else {
                \think\Db::rollback();
                $result["status"] = 400;
                $result["msg"] = "回复失败";
            }
        } catch (\Exception $e) {
            \think\Db::rollback();
            $result["status"] = 400;
            $result["msg"] = "回复失败" . $e->getMessage();
        }
        $title = \think\Db::name("ticket")->field("title,dptid")->where("id", $data["tid"])->find();
        if ($result["status"] == 200) {
            active_logs(sprintf($this->lang["Ticket_home_replyTicket_success"], $params["tid"], $title["title"]), $uid);
            active_logs(sprintf($this->lang["Ticket_home_replyTicket_success"], $params["tid"], $title["title"]), $uid, "", 2);
            $departmentadmin = \think\Db::name("ticket_department_admin")->field("t.admin_id,u.only_oneself_notice")->alias("t")->leftJoin("User u", "u.id=t.admin_id")->where("t.dptid", $title["dptid"])->select()->toArray();
            $user_info = \think\Db::name("clients")->where("id", $uid)->find();
            $curl_multi_data = [];
            foreach ($departmentadmin as $key => $value) {
                if (!($value["only_oneself_notice"] == 1 && $value["admin_id"] != $user_info["sale_id"])) {
                    $arr_admin = ["relid" => $data["tid"], "name" => "【管理员】工单回复提示", "type" => "support", "sync" => true, "admin" => true, "adminid" => $value["admin_id"], "ip" => get_client_ip6()];
                    $curl_multi_data[count($curl_multi_data)] = ["url" => "async", "data" => $arr_admin];
                }
            }
            asyncCurlMulti($curl_multi_data);
            $hook_data = ["ticketid" => $ticket["id"], "replyid" => $r1, "uid" => $uid, "dptid" => $ticket["dptid"], "dptname" => $ticket["dptname"], "title" => html_entity_decode($ticket["title"], ENT_QUOTES), "content" => html_entity_decode($data["content"], ENT_QUOTES), "priority" => $ticket["priority"], "status" => 3, "status_title" => \think\Db::name("ticket_status")->where("id", 3)->value("title")];
            hook("ticket_user_reply", $hook_data);
            $data["id"] = $r1;
            $host_obj = new \app\common\logic\Host();
            $host_obj->replyTicket($ticket["host_id"], $data);
        }
        return json($result);
    }
    public function closeTicket(\think\Request $request)
    {
        $params = input("post.");
        if (empty($params["tid"])) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return $result;
        }
        $len = strlen($params["tid"]);
        if ($len < 6) {
            $repeat = 6 - $len;
            $params["tid"] = str_repeat(0, $repeat) . $params["tid"];
        }
        $uid = $request->uid;
        if (empty($uid)) {
            if (!cmf_get_conf("nologin_send_ticket", 0)) {
                $result["status"] = 406;
                $result["msg"] = "没有权限";
                return json($result);
            }
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "c" => $params["c"]]);
        } else {
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "uid" => $uid]);
        }
        if (empty($ticket)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return json($result);
        }
        if ($ticket["status"] == 4) {
            $result["status"] = 200;
            $result["msg"] = "工单已关闭";
            return json($result);
        }
        $r = \think\Db::name("ticket")->where("id", $ticket["id"])->update(["status" => 4]);
        if ($r) {
            active_logs(sprintf($this->lang["Ticket_home_closeTicket_success"], $ticket["tid"], $ticket["title"]), $uid);
            active_logs(sprintf($this->lang["Ticket_home_closeTicket_success"], $ticket["tid"], $ticket["title"]), $uid, "", 2);
            hook("ticket_close", ["ticketid" => $ticket["id"]]);
            $result["status"] = 200;
            $result["msg"] = "关闭成功";
            return json($result);
        }
        $result["status"] = 400;
        $result["msg"] = "关闭失败";
        return json($result);
    }
    public function evaluate(\think\Request $request)
    {
        $tid = input("post.tid");
        $type = input("post.type", "r");
        $rid = input("post.rid");
        $star = intval(input("post.star"));
        $uid = $request->uid;
        if (empty($tid)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return json($result);
        }
        if ($star <= 0 || 5 < $star) {
            $result["status"] = 406;
            $result["msg"] = "只能评价1-5星";
            return json($result);
        }
        $ticket = model("Ticket")->getTicket(["tid" => $tid, "uid" => $uid]);
        if (empty($ticket)) {
            $result["status"] = 406;
            $result["msg"] = "您无法评价该工单回复";
            return json($result);
        }
        $department = \think\Db::name("ticket_department")->field("feedback_request")->where("id", $ticket["dptid"])->find();
        if ($department["feedback_request"] == 0) {
            $result["status"] = 406;
            $result["msg"] = "您无法对此工单进行评分";
            return json($result);
        }
        if ($type === "r") {
            $reply = \think\Db::name("ticket_reply")->where("id", $rid)->find();
            if (!isset($reply["id"]) || $reply["tid"] != $ticket["id"] && $reply["id"] != $ticket["id"]) {
                $result["status"] = 406;
                $result["msg"] = "该回复不存在";
                return json($result);
            }
            if (!isset($reply["admin_id"]) || $reply["admin_id"] < 1) {
                $result["status"] = 406;
                $result["msg"] = "该回复不可评价";
                return json($result);
            }
            if (0 < $reply["star"]) {
                $result["status"] = 406;
                $result["msg"] = "该回复已评价";
                return json($result);
            }
            $r = \think\Db::name("ticket_reply")->where("id", $rid)->update(["star" => $star]);
        } else {
            if ($ticket["id"] == $rid) {
                $reply = $ticket;
                if (!isset($reply["id"]) || $reply["id"] != $ticket["id"]) {
                    $result["status"] = 406;
                    $result["msg"] = "该回复不存在";
                    return json($result);
                }
            } else {
                $reply = \think\Db::name("ticket")->where("id", $rid)->find();
                if (!isset($reply["id"]) || $reply["merged_ticket_id"] != $ticket["id"]) {
                    $result["status"] = 406;
                    $result["msg"] = "该回复不存在";
                    return json($result);
                }
            }
            if (!isset($reply["admin_id"]) || $reply["admin_id"] < 1) {
                $result["status"] = 406;
                $result["msg"] = "该回复不可评价";
                return json($result);
            }
            if (0 < $reply["star"]) {
                $result["status"] = 406;
                $result["msg"] = "该回复已评价";
                return json($result);
            }
            $r = \think\Db::name("ticket")->where("id", $rid)->update(["star" => $star]);
        }
        if ($r) {
            active_logs(sprintf($this->lang["Ticket_home_evaluate_success"], $ticket["tid"], $ticket["title"]), $uid);
            active_logs(sprintf($this->lang["Ticket_home_evaluate_success"], $ticket["tid"], $ticket["title"]), $uid, "", 2);
            $result["status"] = 200;
            $result["msg"] = "评价成功";
            return json($result);
        }
        $result["status"] = 406;
        $result["msg"] = "评价失败";
        return json($result);
    }
    public function getList(\think\Request $request)
    {
        $param = $request->param();
        $page = 1 <= $param["page"] ? intval($param["page"]) : config("page");
        $limit = 1 <= $param["limit"] ? intval($param["limit"]) : 20;
        $uid = $request->uid;
        $order = "`status`=4,`status`=1 DESC,`last_reply_time` DESC";
        $data = \think\Db::name("ticket")->alias("a")->field("a.*,b.name department_name,p.name product_name")->leftJoin("ticket_department b", "a.dptid=b.id")->leftJoin("host h", "a.host_id = h.id")->leftJoin("products p", "h.productid = p.id")->where("a.merged_ticket_id", 0)->where("b.hidden", 0)->where("a.uid", $uid)->orderRaw($order)->page($page)->limit($limit)->select()->toArray();
        $count = \think\Db::name("ticket")->alias("a")->leftJoin("ticket_department b", "a.dptid=b.id")->where("a.merged_ticket_id", 0)->where("b.hidden", 0)->where("a.uid", $uid)->group("a.id")->count();
        $status = \think\Db::name("ticket_status")->select()->toArray();
        $tmp = array_column($status, NULL, "id");
        foreach ($data as $k => &$v) {
            unset($v["token"]);
            $v["show_time"] = date("Y-m-d H:i:s", $v["last_reply_time"]);
            $v["status"] = $tmp[$v["status"]] ?? [];
        }
        $result["status"] = 200;
        $result["msg"] = "获取成功";
        $result["data"] = ["limit" => $limit, "page" => $page, "sum" => $count, "list" => $data];
        return jsons($result);
    }
    public function downloadAttachment(\think\Request $request)
    {
        header("Access-Control-Expose-Headers: Content-disposition");
        $params = $request->param();
        $uid = $request->uid;
        if (empty($uid)) {
            if (!cmf_get_conf("nologin_send_ticket", 0)) {
                $result["status"] = 406;
                $result["msg"] = "没有权限";
                return json($result);
            }
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "c" => $params["c"]]);
        } else {
            $ticket = model("Ticket")->getTicket(["tid" => $params["tid"], "uid" => $uid]);
        }
        if (empty($ticket)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
            return json($result);
        }
        $rid = intval($params["rid"]);
        $index = intval($params["index"]);
        $reply = \think\Db::name("ticket_reply")->where("tid", $ticket["id"])->where("id", $rid)->find();
        if (empty($reply)) {
            $result["status"] = 406;
            $result["msg"] = "ID错误";
        }
        $attachment = array_filter(explode(",", $reply["attachment"]));
        if (empty($attachment[$index])) {
            header("HTTP/1.1 404 NOT FOUND");
            exit;
        }
        $file = config("ticket_attachments") . $attachment[$index];
        if (!file_exists($file)) {
            header("HTTP/1.1 404 NOT FOUND");
            exit;
        }
        ob_clean();
        download($file);
        return 1;
    }
    public function download()
    {
        header("Access-Control-Expose-Headers: Content-disposition");
        $params = $this->request->param();
        if (!isset($params["type"][0])) {
            $result["status"] = 406;
            $result["msg"] = "类型为空";
            return json($result);
        }
        if (!isset($params["id"][0])) {
            $result["status"] = 406;
            $result["msg"] = "id为空";
            return json($result);
        }
        switch ($params["type"]) {
            case "t":
                $data = \think\Db::name("ticket")->find($params["id"]);
                break;
            case "r":
                $data = \think\Db::name("ticket_reply")->find($params["id"]);
                $tmp = explode("/", $params["filename"]);
                $filename = end($tmp);
                if (!isset($data["attachment"][0]) || !in_array($filename, explode(",", $data["attachment"]))) {
                    $result["status"] = 406;
                    $result["msg"] = "文件不存在";
                    return json($result);
                }
                $file = config("ticket_attachments") . $filename;
                if (!file_exists($file)) {
                    $result["status"] = 406;
                    $result["msg"] = "文件不存在";
                    return json($result);
                }
                return download($file, end(explode("^", $filename)));
                break;
            default:
                $result["status"] = 406;
                $result["msg"] = "类型不正确";
                return json($result);
        }
    }
    protected function uploadAttachment()
    {
        $validate = cmf_upload_config();
        $data = [];
        $files = request()->file("attachment");
        foreach ($files as $file) {
            $info = $file->validate($validate)->rule(function () {
                return mt_rand(1000, 9999) . "_" . md5(microtime(true));
            })->move(TICKET_DOWN_PATH);
            if ($info) {
                $data[] = $info->getFilename();
            } else {
                foreach ($data["attachment"] as $val) {
                    @unlink(TICKET_DOWN_PATH . $val);
                }
                $result["status"] = 406;
                $result["msg"] = $file->getError();
                return $result;
            }
        }
        $result["status"] = 200;
        $result["data"] = $data;
        return $result;
    }
    protected function formatAttachments($attachment = [])
    {
        $res = [];
        foreach ($attachment as $v) {
            $doc = substr($v, -4);
            if (in_array($doc, [".jpg", ".gif", ".png", "jpeg"])) {
                $img = base64EncodeImage(TICKET_DOWN_PATH . $v);
            } else {
                $img = "";
            }
            $res[] = ["name" => $v, "img" => $img];
        }
        return $res;
    }
}

?>