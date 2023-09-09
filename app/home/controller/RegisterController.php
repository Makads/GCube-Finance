<?php

namespace app\home\controller;

/**
 * @title 前台注册和密码重置
 * @description 接口说明
 */
class RegisterController extends \cmf\controller\HomeBaseController
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
    public function registerPhoneSend()
    {
        $ip = $this->request->ip();
        $key = "home_client_register_phone_" . $ip;
        if (\think\facade\Cache::has($key)) {
            \think\facade\Cache::inc($key);
            $tmp = \think\facade\Cache::get($key);
            if (10 <= $tmp) {
                return json(["status" => 400, "msg" => "五分钟只能发送五次"]);
            }
        } else {
            \think\facade\Cache::set($key, 1, 300);
        }
        unset($ip);
        unset($key);
        unset($tmp);
        if (!checkPhoneRegister()) {
            return json(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        $data = $this->request->param();
        if (!captcha_check($data["captcha"], "allow_register_phone_captcha") && configuration("allow_register_phone_captcha") == 1 && configuration("is_captcha") == 1) {
            return json(["status" => 400, "msg" => "图形验证码有误"]);
        }
        $agent = $this->request->header("user-agent");
        if (strpos($agent, "Mozilla") === false) {
            return json(["status" => 400, "msg" => "短信发送失败"]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:5,13"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位"]);
            if (cookie("msfntk") != $data["mk"] || !cookie("msfntk")) {
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            $rangeTypeCheck = rangeTypeCheck($phone_code . $mobile);
            if ($rangeTypeCheck["status"] == 400) {
                return jsonrule($rangeTypeCheck);
            }
            if ($this->checkRegister($mobile, $phone_code)) {
                return json(["status" => 400, "msg" => lang("账号已存在，请前往登录")]);
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
            if (cmf_check_mobile($phone)) {
                if (\think\facade\Cache::has("registertime_" . $mobile . "_time")) {
                    return json(["status" => 400, "msg" => lang("CODE_SENDED")]);
                }
                $code = mt_rand(100000, 999999);
                if (60 <= time() - session("registertime" . $mobile)) {
                    $params = ["code" => $code, "username" => ""];
                    $sms = new \app\common\logic\Sms();
                    $ret = sendmsglimit($phone);
                    if ($ret["status"] == 400) {
                        return json(["status" => 400, "msg" => lang("SEND FAIL") . ":" . $ret["msg"]]);
                    }
                    $result = $sms->sendSms(8, $phone, $params);
                    session("registertime" . $mobile, time());
                    if ($result["status"] == 200) {
                        $data = ["ip" => get_client_ip6(), "phone" => $phone, "time" => time()];
                        \think\Db::name("sendmsglimit")->insertGetId($data);
                        cache("registertel" . $mobile, $code, 300);
                        \think\facade\Cache::set("registertime_" . $mobile . "_time", $code, 60);
                        return json(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
                    }
                    $msg = lang("CODE_SEND_FAIL");
                    $tmp = config()["public"]["ali_sms_error_code"];
                    if (isset($tmp[$result["data"]["Code"]])) {
                        $msg = $tmp[$result["data"]["Code"]];
                    }
                    return json(["status" => 400, "msg" => $msg]);
                }
                return json(["status" => 400, "msg" => lang("CODE_SENDED")]);
            }
            return json(["status" => 400, "msg" => "请输入正确的手机号"]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function registerEmailSend()
    {
        if (configuration("shd_allow_email_send") == 0) {
            return jsonrule(["status" => 400, "msg" => "邮箱发送功能已关闭"]);
        }
        $ip = $this->request->ip();
        $key = "home_client_register_email_" . $ip;
        if (\think\facade\Cache::has($key)) {
            \think\facade\Cache::inc($key);
            $tmp = \think\facade\Cache::get($key);
            if (10 <= $tmp) {
                return json(["status" => 400, "msg" => "五分钟只能发送五次"]);
            }
        } else {
            \think\facade\Cache::set($key, 1, 300);
        }
        $data = $this->request->param();
        if (!captcha_check($data["captcha"], "allow_register_email_captcha") && configuration("allow_register_email_captcha") == 1 && configuration("is_captcha") == 1) {
            return json(["status" => 400, "msg" => "图形验证码有误"]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["email" => "require"]);
            $validate->message(["email.require" => "邮箱不能为空"]);
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $email = trim($data["email"]);
            if ($this->checkEmailRegister($email)) {
                return json(["status" => 400, "msg" => lang("账号已存在，请前往登录")]);
            }
            if (\think\facade\Validate::isEmail($email)) {
                $code = mt_rand(100000, 999999);
                if (60 <= time() - session("registertime" . $email)) {
                    $email_logic = new \app\common\logic\Email();
                    $result = $email_logic->sendEmailCode($email, $code);
                    session("registertime" . $email, time());
                    if ($result) {
                        cache("registeremail" . $email, $code, 600);
                        return json(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
                    }
                    return json(["status" => 400, "msg" => lang("CODE_SEND_FAIL")]);
                }
                return json(["status" => 400, "msg" => lang("CODE_SENDED")]);
            }
            return json(["status" => 400, "msg" => lang("EMAIL_ERROR")]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function registerPhone()
    {
        if (!checkPhoneRegister()) {
            return json(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:4,11", "code" => "require", "password" => "require|min:6|max:32", "qq" => "max:20", "username" => "max:20", "companyname" => "max:50", "address1" => "max:100"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位", "code.require" => "验证码必填", "qq.max" => "qq不超过20个字符", "username.max" => "用户名不超过20个字符", "companyname.max" => "公司名不超过20个字符", "address1.max" => "地址不超过20个字符"]);
            $data = $this->request->param();
            if (isset($data["repassword"])) {
                $data["checkPassword"] = $data["repassword"];
            }
            if ($data["password"] != $data["checkPassword"]) {
                return json(["status" => 400, "msg" => "两次密码不一致"]);
            }
            $login_register = configuration("login_register_custom_require") ? json_decode(configuration("login_register_custom_require"), true) : [];
            if (!empty($login_register[0])) {
                $allow = config("login_register_custom_require");
                foreach ($login_register as $v) {
                    if ($v["require"] && empty($data[$v["name"]])) {
                        return json(["status" => 400, "msg" => $allow[$v["name"]] . "必填"]);
                    }
                }
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            if (isset($data["fields"]) && is_array($data["fields"]) && !empty($data["fields"])) {
                $fields = $data["fields"];
                foreach ($fields as $k => $v) {
                    $tmp = \think\Db::name("customfields")->where("id", $k)->find();
                    if (empty($tmp)) {
                        return json(["status" => 400, "msg" => "参数错误"]);
                    }
                }
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            if ($this->checkRegister($mobile, $phone_code)) {
                return json(["status" => 400, "msg" => lang("账号已存在，请前往登录")]);
            }
            if ($phone_code == "+86" || $phone_code == "86" || empty($phone_code)) {
                $phone = $mobile;
                $phone_code = "86";
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $phone = substr($phone_code, 1) . "-" . $mobile;
                } else {
                    $phone = $phone_code . "-" . $mobile;
                }
            }
            if (cmf_check_mobile($phone)) {
                $user["phone_code"] = $phone_code;
                $user["phonenumber"] = $mobile;
                $user["password"] = $data["password"];
                $user["qq"] = $data["qq"] ?? "";
                $user["username"] = $data["username"] ?? "";
                $user["companyname"] = $data["companyname"] ?? "";
                $user["address1"] = $data["address1"] ?? "";
                $user["fields"] = $fields ?? [];
                $rand = rand(1, 20);
                if ($rand < 10) {
                    $user["avatar"] = "用户头像2-0" . $rand . ".jpg";
                } else {
                    $user["avatar"] = "用户头像2-" . $rand . ".jpg";
                }
                $code = cache("registertel" . $mobile);
                if (empty($code)) {
                    return json(["status" => 400, "msg" => "验证码已过期"]);
                }
                if (trim($data["code"]) == $code) {
                    $clientsModel = new \app\home\model\ClientsModel();
                    $data["sale_id"] = $this->getSalerId($data["sale_id"]);
                    $user["sale_id"] = $data["sale_id"];
                    $log = $clientsModel->register($user, 1);
                    return $log;
                }
                return json(["status" => 400, "msg" => "验证码错误"]);
            }
            return json(["status" => 400, "msg" => "请输入正确的手机号"]);
        } else {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function registerEmail()
    {
        if (!checkEmailRegister()) {
            return json(["status" => 400, "msg" => lang("未开启邮箱登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["email" => "require", "password" => "require|min:6|max:32", "qq" => "max:20", "username" => "max:20", "companyname" => "max:50", "address1" => "max:100"]);
            $validate->message(["email.require" => "邮箱不能为空", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位", "code.require" => "验证码必填", "qq.max" => "qq不超过20个字符", "username.max" => "用户名不超过20个字符", "companyname.max" => "公司名不超过20个字符", "address1.max" => "地址不超过20个字符"]);
            $data = $this->request->param();
            if (isset($data["repassword"])) {
                $data["checkPassword"] = $data["repassword"];
            }
            if ($data["password"] != $data["checkPassword"]) {
                return json(["status" => 400, "msg" => "两次密码不一致"]);
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $login_register = configuration("login_register_custom_require") ? json_decode(configuration("login_register_custom_require"), true) : [];
            if (!empty($login_register[0])) {
                $allow = config("login_register_custom_require");
                foreach ($login_register as $v) {
                    if ($v["require"] && empty($data[$v["name"]])) {
                        return json(["status" => 400, "msg" => $allow[$v["name"]] . "必填"]);
                    }
                }
            }
            if (isset($data["fields"]) && is_array($data["fields"]) && !empty($data["fields"])) {
                $fields = $data["fields"];
                foreach ($fields as $k => $v) {
                    $tmp = \think\Db::name("customfields")->where("id", $k)->find();
                    if (empty($tmp)) {
                        return json(["status" => 400, "msg" => "参数错误"]);
                    }
                }
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $user["password"] = trim($data["password"]);
            $user["email"] = $email = trim($data["email"]);
            $user["qq"] = $data["qq"] ?? "";
            $user["username"] = $data["username"] ?? "";
            $user["companyname"] = $data["companyname"] ?? "";
            $user["address1"] = $data["address1"] ?? "";
            $user["fields"] = $fields ?? [];
            $rand = rand(1, 20);
            if ($rand < 10) {
                $user["avatar"] = "用户头像2-0" . $rand . ".jpg";
            } else {
                $user["avatar"] = "用户头像2-" . $rand . ".jpg";
            }
            if ($this->checkEmailRegister($email)) {
                return json(["status" => 400, "msg" => lang("账号已存在，请前往登录")]);
            }
            if (\think\facade\Validate::isEmail($email)) {
                if (configuration("allow_email_register_code")) {
                    $code = cache("registeremail" . $email);
                    if (empty($code)) {
                        return json(["status" => 400, "msg" => "验证码已过期"]);
                    }
                    if (trim($data["code"]) == $code) {
                        $data["sale_id"] = $this->getSalerId($data["sale_id"]);
                        $user["sale_id"] = $data["sale_id"];
                        $log = $clientsModel->register($user, 2);
                        return $log;
                    }
                    return json(["status" => 400, "msg" => "验证码错误"]);
                }
                $data["sale_id"] = $this->getSalerId($data["sale_id"]);
                $user["sale_id"] = $data["sale_id"];
                $log = $clientsModel->register($user, 2);
                return $log;
            }
            return json(["status" => 400, "msg" => "请输入正确的邮箱"]);
        } else {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function resetPhoneSend()
    {
        if (!checkPhoneLogin()) {
            return json(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        $agent = $this->request->header("user-agent");
        if (strpos($agent, "Mozilla") === false) {
            return json(["status" => 400, "msg" => "短信发送失败"]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:4,11"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位"]);
            $data = $this->request->param();
            if (!captcha_check($data["captcha"], "allow_phone_forgetpwd_captcha") && configuration("allow_phone_forgetpwd_captcha") == 1 && configuration("is_captcha") == 1) {
                return json(["status" => 400, "msg" => "图形验证码有误"]);
            }
            if (cookie("msfntk") != $data["mk"] || !cookie("msfntk")) {
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            $rangeTypeCheck = rangeTypeCheck($phone_code . $mobile);
            if ($rangeTypeCheck["status"] == 400) {
                return jsonrule($rangeTypeCheck);
            }
            if (!$this->checkRegister($mobile, $phone_code)) {
                return json(["status" => 400, "msg" => lang("账号未注册，请先注册")]);
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
                if (\think\facade\Cache::has("resettime_" . $mobile . "_time")) {
                    return json(["status" => 400, "msg" => lang("CODE_SENDED")]);
                }
                $code = mt_rand(100000, 999999);
                if (60 <= time() - session("resettime" . $mobile)) {
                    $params = ["code" => $code];
                    $sms = new \app\common\logic\Sms();
                    $ret = sendmsglimit($phone);
                    if ($ret["status"] == 400) {
                        return json(["status" => 400, "msg" => lang("SEND FAIL") . ":" . $ret["msg"]]);
                    }
                    $result = $sms->sendSms(8, $phone, $params, false, $cli["id"]);
                    session("resettime" . $mobile, time());
                    if ($result["status"] == 200) {
                        $data = ["ip" => get_client_ip6(), "phone" => $phone, "time" => time()];
                        \think\Db::name("sendmsglimit")->insertGetId($data);
                        cache("resettel" . $mobile, $code, 300);
                        \think\facade\Cache::set("resettime_" . $mobile . "_time", $code, 60);
                        return json(["status" => 200, "msg" => lang("CODE_SEND_SUCCESS")]);
                    }
                    $msg = lang("CODE_SEND_FAIL");
                    $tmp = config()["public"]["ali_sms_error_code"];
                    if (isset($tmp[$result["data"]["Code"]])) {
                        $msg = $tmp[$result["data"]["Code"]];
                    }
                    return json(["status" => 400, "msg" => $msg]);
                }
                return json(["status" => 400, "msg" => lang("CODE_SENDED")]);
            }
            return json(["status" => 400, "msg" => "请输入正确的手机号"]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function resetEmailSend()
    {
        if (!checkEmailLogin()) {
            return json(["status" => 400, "msg" => lang("未开启邮箱登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["email" => "require"]);
            $validate->message(["email.require" => "邮箱不能为空"]);
            $data = $this->request->param();
            if (!captcha_check($data["captcha"], "allow_email_forgetpwd_captcha") && configuration("allow_email_forgetpwd_captcha") == 1 && configuration("is_captcha") == 1) {
                return json(["status" => 400, "msg" => "图形验证码有误"]);
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $email = trim($data["email"]);
            $key = "home_client_" . $email;
            if (\think\facade\Cache::has($key)) {
                return json(["status" => 200, "msg" => "发送中，请稍等"]);
            }
            \think\facade\Cache::set($key, 1, 5);
            if (!$this->checkEmailRegister($email)) {
                return json(["status" => 400, "msg" => lang("账号未注册，请先注册")]);
            }
            if (\think\facade\Validate::isEmail(trim($data["email"]))) {
                $code = mt_rand(100000, 999999);
                if (60 <= time() - session("resettime" . $email)) {
                    $email_logic = new \app\common\logic\Email();
                    $result = $email_logic->sendEmailCode($email, $code, "find password");
                    session("resettime" . $email, time());
                    if ($result) {
                        cache("resetemail" . $email, $code, 600);
                        return json(["status" => 200, "msg" => "验证码发送成功"]);
                    }
                    return json(["status" => 400, "msg" => "验证码发送失败"]);
                }
                return json(["status" => 400, "msg" => "验证码已发送"]);
            }
            return json(["status" => 400, "msg" => "请输入正确的邮箱"]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function passPhoneReset()
    {
        if (!checkPhoneLogin()) {
            return json(["status" => 400, "msg" => lang("未开启手机登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["phone" => "require|length:4,11", "code" => "require", "password" => "require|min:6|max:32"]);
            $validate->message(["phone.require" => "手机号不能为空", "phone.length" => "手机长度为4-11位", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位", "code.require" => "验证码必填"]);
            $data = $this->request->param();
            $data["checkPassword"] = isset($data["checkPassword"]) ? $data["checkPassword"] : $data["password"];
            if ($data["password"] != $data["checkPassword"]) {
                return json(["status" => 400, "msg" => "两次密码不一致"]);
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $phone_code = trim($data["phone_code"]);
            $mobile = trim($data["phone"]);
            if (!$this->checkRegister($mobile, $phone_code)) {
                return json(["status" => 400, "msg" => lang("账号未注册，请先注册")]);
            }
            if ($phone_code == "+86" || $phone_code == "86" || empty($phone_code)) {
                $phone = $mobile;
                $phone_code = "86";
            } else {
                if (substr($phone_code, 0, 1) == "+") {
                    $phone = substr($phone_code, 1) . "-" . $mobile;
                } else {
                    $phone = $phone_code . "-" . $mobile;
                }
            }
            if (cmf_check_mobile($phone)) {
                $user["phone_code"] = $phone_code;
                $user["phonenumber"] = $mobile;
                $user["password"] = $data["password"];
                $code = cache("resettel" . $mobile);
                if (empty($code)) {
                    return json(["status" => 400, "msg" => "验证码已过期"]);
                }
                if (trim($data["code"]) == $code) {
                    $clientsModel = new \app\home\model\ClientsModel();
                    $log = $clientsModel->pwReset($user, 1);
                    return $log;
                }
                return json(["status" => 400, "msg" => "验证码错误"]);
            }
            return json(["status" => 400, "msg" => "请输入正确的手机号"]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function passEmailReset()
    {
        if (!checkEmailLogin()) {
            return json(["status" => 400, "msg" => lang("未开启邮箱登录注册功能，不能发送验证码")]);
        }
        if ($this->request->isPost()) {
            $validate = new \think\Validate(["email" => "require", "code" => "require", "password" => "require|min:6|max:32"]);
            $validate->message(["email.require" => "邮箱不能为空", "password.require" => "密码必填", "password.min" => "密码至少6位", "password.max" => "密码最多32位", "code.require" => "验证码必填"]);
            $data = $this->request->param();
            $data["checkPassword"] = isset($data["checkPassword"]) ? $data["checkPassword"] : $data["password"];
            if ($data["password"] != $data["checkPassword"]) {
                return json(["status" => 400, "msg" => "两次密码不一致"]);
            }
            if (!$validate->check($data)) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $clientsModel = new \app\home\model\ClientsModel();
            $user["password"] = trim($data["password"]);
            $user["email"] = $email = trim($data["email"]);
            if (!$this->checkEmailRegister($email)) {
                return json(["status" => 400, "msg" => lang("账号未注册，请先注册")]);
            }
            if (\think\facade\Validate::isEmail($email)) {
                $code = cache("resetemail" . $email);
                if (empty($code)) {
                    return json(["status" => 400, "msg" => "验证码已过期"]);
                }
                if (trim($data["code"]) == $code) {
                    $log = $clientsModel->pwReset($user, 2);
                    return $log;
                }
                return json(["status" => 400, "msg" => "验证码错误"]);
            }
            return json(["status" => 400, "msg" => "请输入正确的邮箱"]);
        }
        return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
    }
    public function getSalerId($sale_id)
    {
        if (!$sale_id) {
            $sale_reg_setting = configuration("sale_reg_setting");
            if ($sale_reg_setting == 0) {
                return jsons(["status" => 200, "msg" => "设定成功"]);
            }
            if ($sale_reg_setting == 1) {
                $sale_auto_setting = configuration("sale_auto_setting");
                if ($sale_auto_setting == 1) {
                    $data = db("user")->field("id,user_nickname,user_email")->where("is_sale", 1)->select()->toArray();
                    $num = rand(0, count($data) - 1);
                    if (count($data) == 1) {
                        $num = 0;
                    }
                    $sale_id = $data[$num]["id"];
                } else {
                    $setsalerinc = configuration("setsalerinc") ?? 0;
                    $data = db("user")->field("id")->where("is_sale", 1)->where("id", ">", $setsalerinc)->order("id", "asc")->find();
                    if (empty($data)) {
                        $data = db("user")->field("id")->where("is_sale", 1)->order("id", "asc")->find();
                    }
                    $sale_id = $data["id"];
                    updateConfiguration("setsalerinc", $sale_id);
                }
            }
        }
        return $sale_id;
    }
}

?>