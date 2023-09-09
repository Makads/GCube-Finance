<?php

namespace app\home\controller;

/**
 * @title 前台登录
 * @description 接口说明
 */
class LoginController extends \cmf\controller\HomeBaseController
{
    private function checkRegister($phone, $phone_code)
    {
        $clients = \think\Db::name("clients");
        if (sendGlobal() == 1) {
            if (empty($phone_code)) {
                $phone_code = "86";
            }
            $clients->where("phone_code", $phone_code);
        }
        $clients->where("phonenumber", $phone);
        $count = $clients->count();
        return 0 < $count ? true : false;
    }
    private function checkEmailRegister($email)
    {
        $count = \think\Db::name("clients")->where("email", $email)->count();
        return 0 < $count ? true : false;
    }
    private function checkIdRegister($id)
    {
        $count = \think\Db::name("clients")->where("id", $id)->count();
        return 0 < $count ? true : false;
    }
    public function LoginRegisterIndex()
    {
        $data = [];
        $data["allow_phone"] = !configuration("allow_phone") ? 0 : 1;
        $data["allow_email"] = !configuration("allow_email") ? 0 : 1;
        $data["allow_wechat"] = !configuration("allow_wechat") ? 0 : 1;
        $data["allow_email_register_code"] = !configuration("allow_email_register_code") ? 0 : 1;
        $data["clients_profoptional"] = explode(",", configuration("clients_profoptional")) ?? [];
        $allow_phone = !configuration("allow_phone") ? 0 : 1;
        $allow_id = !configuration("allow_id") ? 0 : 1;
        $allow_email = !configuration("allow_email") ? 0 : 1;
        $allow_wechat = !configuration("allow_wechat") ? 0 : 1;
        $data["allow_register_phone"] = configuration("allow_register_phone") == NULL ? $allow_phone : intval(configuration("allow_register_phone"));
        $data["allow_register_email"] = configuration("allow_register_email") == NULL ? $allow_email : intval(configuration("allow_register_email"));
        $data["allow_register_wechat"] = configuration("allow_register_wechat") == NULL ? $allow_wechat : intval(configuration("allow_register_wechat"));
        $data["allow_login_phone"] = configuration("allow_login_phone") == NULL ? $allow_phone : intval(configuration("allow_login_phone"));
        $data["allow_login_email"] = configuration("allow_login_email") == NULL ? $allow_email : intval(configuration("allow_login_email"));
        $data["allow_login_wechat"] = configuration("allow_login_wechat") == NULL ? $allow_wechat : intval(configuration("allow_login_wechat"));
        $data["allow_id"] = configuration("allow_id") == NULL ? $allow_wechat : intval(configuration("allow_id"));
        $data["server_clause_url"] = configuration("server_clause_url") ?? "";
        $data["privacy_clause_url"] = configuration("privacy_clause_url") ?? "";
        $data["saler"] = db("user")->field("id,user_nickname,user_email")->where("is_sale", 1)->where("sale_is_use", 1)->select()->toArray();
        $data["setsaler"] = configuration("sale_reg_setting") ? configuration("sale_reg_setting") : 0;
        $data["allow_second_verify"] = configuration("second_verify_home") ?? 0;
        $data["second_verify_action_home"] = explode(",", configuration("second_verify_action_home"));
        $data["cart_product_description"] = configuration("cart_product_description") ?: "";
        $customfields = new \app\common\logic\Customfields();
        $fields = $customfields->getClientCustomField();
        $data["fields"] = $fields;
        $data["login_register_custom_require"] = configuration("login_register_custom_require") ? json_decode(configuration("login_register_custom_require"), true) : [];
        $data["allow_login_register_sms_global"] = sendGlobal();
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function mobileSend()
    {
        if (!checkPhoneLogin()) {
            return jsons(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        $agent = $this->request->header("user-agent");
        if (strpos($agent, "Mozilla") === false) {
            return json(["status" => 400, "msg" => "短信发送失败"]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:4,11"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位"]);
            $data = $this->request->param();
            if (!captcha_check($data["captcha"], "allow_login_code_captcha") && configuration("allow_login_code_captcha") == 1 && configuration("is_captcha") == 1) {
                return json(["status" => 400, "msg" => "图形验证码有误"]);
            }
            if (cookie("msfntk") != $data["mk"] || !cookie("msfntk")) {
            }
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => $validate->getError()]);
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            $rangeTypeCheck = rangeTypeCheck($phone_code . $mobile);
            if ($rangeTypeCheck["status"] == 400) {
                return jsonrule($rangeTypeCheck);
            }
            if (!$this->checkRegister($mobile, $phone_code)) {
                return jsons(["status" => 400, "msg" => lang("手机号未注册")]);
            }
            if ($phone_code == "+86" || $phone_code == "86" || empty($phone_code)) {
                $phone = $mobile;
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $phone = substr($phone_code, 1) . "-" . $mobile;
                } else {
                    $phone = $phone_code . "-" . $mobile;
                }
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $cli = $clientsModel->getUser($mobile);
            if ($cli["phone_code"] != "86" && sendGlobal() == 0) {
                $phone = $cli["phone_code"] . "-" . $mobile;
            }
            if (cmf_check_mobile($phone)) {
                if (\think\facade\Cache::has("logintel_" . $mobile . "_time")) {
                    return jsons(["status" => 400, "msg" => lang("CODE_SENDED")]);
                }
                $code = mt_rand(100000, 999999);
                $params = ["code" => $code];
                $sms = new \app\common\logic\Sms();
                $ret = sendmsglimit($phone);
                if ($ret["status"] == 400) {
                    return json(["status" => 400, "msg" => lang("SEND FAIL") . ":" . $ret["msg"]]);
                }
                $result = $sms->sendSms(8, $phone, $params, false, $cli["id"]);
                session_start();
                session("logintime" . $mobile, time());
                session_write_close();
                if ($result["status"] == 200) {
                    $data = ["ip" => get_client_ip6(), "phone" => $phone, "time" => time()];
                    \think\Db::name("sendmsglimit")->insertGetId($data);
                    cache("logintel" . $mobile, $code, 300);
                    \think\facade\Cache::set("logintel_" . $mobile . "_time", $code, 60);
                    return jsons(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
                }
                return jsons(["status" => 400, "msg" => lang("CODE_SEND_FAIL")]);
            }
            return jsons(["status" => 400, "msg" => "请输入正确的手机号"]);
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function mobileLoginVerifyPage()
    {
        if (!checkPhoneLogin()) {
            return jsons(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => getCountryCode()]);
    }
    public function mobileLoginVerify()
    {
        if (!checkPhoneLogin()) {
            return jsons(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:4,11", "code" => "require"]);
            $validate->message(["phone.require" => "手机号不能为空", "code.require" => "验证码不能为空"]);
            $data = $this->request->param();
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => "登录失败"]);
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            if (!$this->checkRegister($mobile, $phone_code)) {
                return jsons(["status" => 400, "msg" => lang("手机号未注册")]);
            }
            if ($phone_code == "+86" || $phone_code == "86" || empty($phone_code)) {
                $phone = $mobile;
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $phone = substr($phone_code, 1) . "-" . $mobile;
                } else {
                    $phone = $phone_code . "-" . $mobile;
                }
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $cli = $clientsModel->getUser($mobile);
            if (sendGlobal() == 0) {
                $phone = $cli["phone_code"] . "-" . $mobile;
                $phone_code = $cli["phone_code"];
            }
            $code = trim($data["code"]);
            $user["phone_code"] = $phone_code;
            $user["phonenumber"] = $mobile;
            $user["code"] = $code;
            $tmp = $clientsModel->login_get(get_client_ip(0, true));
            if ($tmp["status"] == 400) {
                return jsons($tmp);
            }
            if (cmf_check_mobile($phone)) {
                $result = $clientsModel->mobileCodeVerify($user);
                return $result;
            }
            return jsons(["status" => 400, "msg" => lang("请输入正确的手机号")]);
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function phonePassLogin()
    {
        if (!checkPhoneLogin()) {
            return jsons(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $check_code = $this->checkCode($data);
            if ($check_code["status"] == 400) {
                return jsons($check_code);
            }
            $validate = new \think\Validate(["phone" => "require|length:4,11", "password" => "require|min:6|max:32"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位"]);
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => "账号或密码错误"]);
            }
            if (!captcha_check($data["captcha"], "allow_login_phone_captcha") && configuration("allow_login_phone_captcha") == 1 && configuration("is_captcha") == 1) {
                return json(["status" => 400, "msg" => "图形验证码有误"]);
            }
            $phone = trim($data["phone"]);
            $phone_code = trim($data["phone_code"]);
            if (!$this->checkRegister($phone, $phone_code)) {
                return jsons(["status" => 400, "msg" => lang("手机号未注册")]);
            }
            if ($phone_code == "+86" || $phone_code == "86") {
                $mobile = $phone;
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $mobile = substr($phone_code, 1) . "-" . $phone;
                } else {
                    $mobile = $phone_code . "-" . $phone;
                }
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $tmp = $clientsModel->login_get(get_client_ip(0, true));
            if ($tmp["status"] == 400) {
                return jsons($tmp);
            }
            $user["password"] = trim($data["password"]);
            $user["phonenumber"] = $phone;
            $user["code"] = $data["code"] ?? "";
            $result = $clientsModel->mobileVerify($user);
            return $result;
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function checkCode($param)
    {
        $user = \think\Db::name("clients");
        $code = $param["code"];
        $code_type = $param["code_type"];
        if (!$code && !$code_type) {
            return ["status" => 200, "msg" => "不验证验证码"];
        }
        if ($param["code_type"] == "phone" || $param["phone"]) {
            $user = $user->where("phonenumber", $param["phone"]);
        }
        if ($param["code_type"] == "email" || $param["email"]) {
            $user = $user->where("email", $param["email"]);
        }
        $user = $user->find();
        if (!$user) {
            return ["status" => 400, "msg" => "账号不存在"];
        }
        $key = $code_type == "email" ? $user["email"] : $user["phonenumber"];
        if (!cache("login_" . $key)) {
            return ["status" => 400, "msg" => "验证码已过期"];
        }
        if (cache("login_" . $key) != $code) {
            return ["status" => 400, "msg" => "验证码错误"];
        }
        return ["status" => 200, "msg" => "验证通过"];
    }
    public function emailLogin()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $validate = new \think\Validate(["email" => "require", "password" => "require|min:6|max:32"]);
            $validate->message(["email.require" => "邮箱不能为空", "password.require" => "密码不能为空", "password.min" => "密码至少6位", "password.max" => "密码最多32位"]);
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => $validate->getError()]);
            }
            $email = trim($data["email"]);
            if (0 < strpos($email, "@")) {
                if (!checkEmailLogin()) {
                    return jsons(["status" => 400, "msg" => lang("未开启邮箱登录功能")]);
                }
                $check_code = $this->checkCode($data);
                if ($check_code["status"] == 400) {
                    return jsons($check_code);
                }
                $validate->message(["email.require" => "邮箱不能为空", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位"]);
                if (!captcha_check($data["captcha"], "allow_login_email_captcha") && configuration("allow_login_email_captcha") == 1 && configuration("is_captcha") == 1) {
                    return json(["status" => 400, "msg" => "图形验证码有误"]);
                }
                if (!$this->checkEmailRegister($email)) {
                    return jsons(["status" => 400, "msg" => lang("邮箱未注册")]);
                }
            } else {
                if (!configuration("allow_id")) {
                    return jsons(["status" => 400, "msg" => lang("未开启ID登录功能")]);
                }
                $validate->message(["email.require" => "ID不能为空", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位"]);
                if (!captcha_check($data["captcha"], "allow_login_email_captcha") && configuration("allow_login_email_captcha") == 1 && configuration("is_captcha") == 1) {
                    return json(["status" => 400, "msg" => "图形验证码有误"]);
                }
                if (!$this->checkIdRegister($email)) {
                    return jsons(["status" => 400, "msg" => lang("ID账号未注册")]);
                }
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $user["password"] = trim($data["password"]);
            $user["code"] = $data["code"] ?? "";
            $tmp = $clientsModel->login_get(get_client_ip(0, true));
            if ($tmp["status"] == 400) {
                return jsons($tmp);
            }
            if (0 < strpos($email, "@")) {
                if (\think\facade\Validate::isEmail($email)) {
                    $user["email"] = $email;
                    $result = $clientsModel->emailVerify($user);
                    return $result;
                }
                return jsons(["status" => 400, "msg" => "邮箱格式错误"]);
            }
            $user["id"] = $email;
            $result = $clientsModel->idVerify($user);
            return $result;
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function aff()
    {
        $identy = $this->request->param("identy");
        $affi = cookie("AffiliateID");
        $is_open = configuration("affiliate_enabled");
        if (empty($affi) && $is_open) {
            $res = \think\Db::name("affiliates")->where("url_identy", $identy)->setInc("visitors", 1);
            $days = configuration("affiliate_cookie");
            setcookie("AffiliateID", $identy, time() + 86400 * $days, "/");
        }
        $url = configuration("system_url");
        $this->redirect($url);
    }
    public function zjmfApiLogin()
    {
        if (!judgeApiIs()) {
            return jsons(["status" => 400, "msg" => "无法访问"]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["username" => "require|length:4,20", "password" => "require"]);
            $validate->message(["username.require" => "用户不能为空", "username.length" => "用户名4-20位", "password.require" => "密码不能为空"]);
            $data = $this->request->param();
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => "鉴权失败"]);
            }
            $username = trim($data["username"]);
            $password = trim($data["password"]);
            $result = $this->checkApiUser($username);
            if (empty($result)) {
                return jsons(["status" => 400, "msg" => lang("鉴权失败")]);
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $tmp = $clientsModel->login_get(get_client_ip(0, true));
            if ($tmp["status"] == 400) {
                return jsons($tmp);
            }
            if ($result["api_open"] != 1) {
                return jsons(["status" => 400, "msg" => lang("供应商api功能已关闭")]);
            }
            if (aesPasswordEncode($password) == $result["api_password"]) {
                $data = ["lastlogin" => time(), "lastloginip" => get_client_ip(0, true)];
                \think\Db::name("clients")->where("id", $result["id"])->update($data);
                $userinfo["id"] = $result["id"];
                $userinfo["username"] = $result["username"];
                $userinfo["is_api"] = 1;
                active_logs(sprintf($this->lang["User_api_login"], "api鉴权登录", $username, $userinfo["id"]), $userinfo["id"], 1);
                active_logs(sprintf($this->lang["User_api_login"], "api鉴权登录", $username, $userinfo["id"]), $userinfo["id"], 1, 2);
                hook("client_api_login", ["uid" => $result["id"], "name" => $result["username"], "ip" => $data["lastloginip"]]);
                $desc = "客户User ID:" . $result["id"] . "在" . date("Y-m-d H:i:s") . "调取/zjmf_api_login接口,api鉴权登录";
                return jsons(["jwt" => createJwt($userinfo), "status" => 200, "msg" => "鉴权成功"]);
            }
            return jsons(["status" => 400, "msg" => lang("鉴权失败")]);
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    private function checkApiUser($username)
    {
        if (strpos($username, "@") !== false) {
            $exist = \think\Db::name("clients")->where("email", $username)->find();
        } else {
            $exist = \think\Db::name("clients")->where("phonenumber", $username)->find();
        }
        if (empty($exist)) {
            return [];
        }
        return $exist;
    }
    public function resourceLogin()
    {
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["username" => "require|length:4,20", "password" => "require"]);
            $validate->message(["username.require" => "用户不能为空", "username.length" => "用户名4-20位", "password.require" => "密码不能为空"]);
            $data = $this->request->param();
            if (!$validate->check($data)) {
                return jsons(["status" => 400, "msg" => "鉴权失败"]);
            }
            $username = trim($data["username"]);
            $password = trim($data["password"]);
            $result = $this->checkApiUser($username);
            if (empty($result)) {
                return jsons(["status" => 400, "msg" => lang("鉴权失败")]);
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $tmp = $clientsModel->login_get(get_client_ip(0, true));
            if ($tmp["status"] == 400) {
                return jsons($tmp);
            }
            if ($password == $result["password"]) {
                $data = ["password" => $data["token"] ?: $password, "lastlogin" => time(), "lastloginip" => get_client_ip(0, true)];
                \think\Db::name("clients")->where("id", $result["id"])->update($data);
                $userinfo["id"] = $result["id"];
                $userinfo["username"] = $result["username"];
                $userinfo["is_api"] = 1;
                return jsons(["jwt" => createJwt($userinfo), "status" => 200, "msg" => "鉴权成功"]);
            }
            return jsons(["status" => 400, "msg" => "账号或密码错误"]);
        }
        return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function getProuductlistPage()
    {
        $param = $this->request->param();
        $type = $param["type"];
        $where = [["type", "not in", ["dcim", "dcimcloud"]]];
        $group_type = 1;
        if ($type === "dcim") {
            $where = [["type", "=", "dcim"]];
            $group_type = 2;
        }
        if ($type === "dcimcloud") {
            $where = [["type", "=", "dcimcloud"]];
            $group_type = 3;
        }
        $group_data = \think\Db::name("product_groups")->where("type", $group_type)->order("order", "asc")->select()->toArray();
        $total = \think\Db::name("product_groups")->where("type", $group_type)->count();
        foreach ($group_data as $k => $v) {
            $product_data = \think\Db::name("products")->field("id,name,gid,type,pay_method as type_zh,pay_type,qty,auto_setup,hidden")->where("gid", $v["id"])->where($where)->withAttr("type_zh", function ($value, $data) {
                return config("product_type")[$data["type"]];
            })->withAttr("pay_type", function ($value, $data) {
                return config("product_paytype")[json_decode($value, true)["pay_type"]] ?? "";
            })->limit(3)->order("order", "acs")->select()->toArray();
            $re["data"][$k] = $v;
            $re["data"][$k]["products"] = $product_data;
        }
        $re["total"] = $total;
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        return jsonrule($re);
    }
    public function getSecondVerifyPage()
    {
        $params = $this->request->param();
        $username = $params["username"] ? trim($params["username"]) : "";
        $password = $params["password"] ? trim($params["password"]) : "";
        if (strpos($username, "@") === false) {
            $client = \think\Db::name("clients")->field("phone_code,phonenumber,email,password")->where("phonenumber", $username)->find();
        } else {
            $client = \think\Db::name("clients")->field("phone_code,phonenumber,email,password")->where("email", $username)->find();
        }
        if (empty($client)) {
            return jsons(["status" => 400, "msg" => "账号或密码错误"]);
        }
        if (!cmf_compare_password($password, $client["password"])) {
            return jsons(["status" => 400, "msg" => "登录失败"]);
        }
        $type = explode(",", configuration("second_verify_action_home_type"));
        $all_type = config("second_verify_action_home_type");
        $allow_type = [];
        foreach ($all_type as $v) {
            foreach ($type as $vv) {
                if ($vv == $v["name"]) {
                    if ($v["name"] == "email") {
                        $v["account"] = !empty($client["email"]) ? str_replace(substr($client["email"], 3, 4), "****", $client["email"]) : "未绑定邮箱";
                    } else {
                        if ($v["name"] == "phone") {
                            $v["account"] = !empty($client["phonenumber"]) ? str_replace(substr($client["phonenumber"], 3, 4), "****", $client["phonenumber"]) : "未绑定手机";
                        }
                    }
                    $allow_type[] = $v;
                }
            }
        }
        $data = ["allow_type" => $allow_type];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function secondVerifySend()
    {
        $params = $this->request->param();
        $username = $params["username"] ? trim($params["username"]) : "";
        $password = $params["password"] ? trim($params["password"]) : "";
        if (cmf_check_mobile($username)) {
            $client = \think\Db::name("clients")->field("phone_code,phonenumber,email,password")->where("phonenumber", $username)->find();
        } else {
            $client = \think\Db::name("clients")->field("phone_code,phonenumber,email,password")->where("email", $username)->find();
        }
        if (empty($client)) {
            return jsons(["status" => 400, "msg" => "发送失败"]);
        }
        if (!cmf_compare_password($password, $client["password"])) {
            return jsons(["status" => 400, "msg" => "发送失败"]);
        }
        $type = $params["type"] ? trim($params["type"]) : "";
        $allow_type = explode(",", configuration("second_verify_action_home_type"));
        if (!in_array($type, $allow_type)) {
            return jsons(["status" => 400, "msg" => "发送方式错误"]);
        }
        $action = $params["action"] ? trim($params["action"]) : "";
        if (!in_array($action, array_column(config("second_verify_action_home"), "name"))) {
            return jsons(["status" => 400, "msg" => "非法操作"]);
        }
        $code = mt_rand(100000, 999999);
        if ($type == "phone") {
            if (empty($client["phonenumber"])) {
                return jsons(["status" => 400, "msg" => "短信发送失败"]);
            }
            $agent = $this->request->header("user-agent");
            if (strpos($agent, "Mozilla") === false) {
                return jsons(["status" => 400, "msg" => "短信发送失败"]);
            }
            $phone_code = trim($client["phone_code"]);
            $mobile = trim($client["phonenumber"]);
            $rangeTypeCheck = rangeTypeCheck($phone_code . $mobile);
            if ($rangeTypeCheck["status"] == 400) {
                return jsonrule($rangeTypeCheck);
            }
            if (\think\facade\Cache::has($action . "_" . $mobile . "_time")) {
                return jsons(["status" => 400, "msg" => lang("CODE_SENDED")]);
            }
            if ($phone_code == "+86" || $phone_code == "86") {
                $phone = $mobile;
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $phone = substr($phone_code, 1) . "-" . $mobile;
                } else {
                    $phone = $phone_code . "-" . $mobile;
                }
            }
            $params = ["code" => $code];
            $sms = new \app\common\logic\Sms();
            $result = $sms->sendSms(8, $phone, $params, false, $client["id"]);
            if ($result["status"] == 200) {
                cache($action . "_" . $mobile, $code, 300);
                \think\facade\Cache::set($action . "_" . $mobile . "_time", $code, 60);
                return jsons(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
            }
            return jsons(["status" => 400, "msg" => lang("CODE_SEND_FAIL")]);
        }
        if ($type == "email") {
            if (empty($client["email"])) {
                return jsons(["status" => 400, "msg" => "发送失败"]);
            }
            $email = $client["email"];
            if (!\think\facade\Cache::has($action . "_" . $email . "_time")) {
                $email_logic = new \app\common\logic\Email();
                $result = $email_logic->sendEmailCode($email, $code);
                if ($result) {
                    cache($action . "_" . $email, $code, 300);
                    \think\facade\Cache::set($action . "_" . $email . "_time", $code, 60);
                    return jsons(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
                }
                return jsons(["status" => 400, "msg" => lang("CODE_SEND_FAIL")]);
            }
            return jsons(["status" => 400, "msg" => lang("CODE_SENDED")]);
        }
        return jsons(["status" => 400, "msg" => "发送失败"]);
    }
    public function verify()
    {
        $param = $this->request->param();
        $data["is_captcha"] = !configuration("is_captcha") ? 0 : 1;
        $login_error_log = 0;
        if (configuration("login_error_switch")) {
            $login_error_max_num = configuration("login_error_max_num");
            if ($login_error_max_num) {
                $login_error_log = $login_error_max_num < intval(cookie("login_error_log")) ? 1 : 0;
            }
        }
        $data["is_captcha"] = $login_error_log;
        !$data["is_captcha"] && $data["is_captcha"];
        if ($data["is_captcha"] == 1) {
            $data[$param["name"]] = !configuration($param["name"]) ? 0 : 1;
            $data[$param["name"]] = $login_error_log;
            !$data[$param["name"]] && $data[$param["name"]];
            if ($data[$param["name"]] != 1) {
                return jsons(["status" => 400, "msg" => "未开启验证码"]);
            }
            $data["captcha_length"] = configuration("captcha_length");
            $data["captcha_combination"] = configuration("captcha_combination");
            $config = json_decode(configuration("captcha_configuration"), true) ?: [];
            $captcha = new \think\captcha\Captcha($config);
            if ($data["captcha_combination"] == 1) {
                $captcha->__set("codeSet", "2345678");
            } else {
                if ($data["captcha_combination"] == 2) {
                    $captcha->__set("codeSet", "2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY");
                } else {
                    if ($data["captcha_combination"] == 3) {
                        $captcha->__set("codeSet", "abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY");
                    }
                }
            }
            $captcha->__set("length", $data["captcha_length"]);
            return $captcha->entry($param["name"]);
        }
        return jsons(["status" => 400, "msg" => "未开启验证码"]);
    }
}

?>