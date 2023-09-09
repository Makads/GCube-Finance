<?php


namespace app\common\logic;

class Log
{
    public static function activeLog($description, $userid = 0)
    {
        $uid = request()->uid ?: $userid;
        $contact_id = request()->contactid;
        $remote_ip = get_client_ip();
        $admin_id = cmf_get_current_admin_id();
        $username = "";
        if (!is_null($admin_id)) {
            $admin_name = \think\Db::name("user")->where("id", $admin_id)->value("user_login");
            $username = $admin_name;
        } else {
            if (!is_null($uid) && !is_null($contact_id)) {
                $username = "Sub-Account" . $contact_id;
            } else {
                if (!is_null($uid)) {
                    $username = "Client";
                } else {
                    $username = "System";
                }
            }
        }
        if (strpos($description, "password") !== false) {
            $description = preg_replace("/(password(?:hash)?`=')(.*)(',|' )/", "\${1}--REDACTED--\${3}", $description);
        }
        $idata = ["create_time" => time(), "description" => $description, "user" => $username, "uid" => $uid, "ipaddr" => $remote_ip];
        \think\Db::name("activity_log")->insert($idata);
        hook("log_activity", ["description" => $description, "user" => $username, "uid" => (int) $uid, "ipaddress" => $remote_ip]);
    }
    public static function adminLog()
    {
        $admin_id = cmf_get_current_admin_id();
        $session_id = session_id();
        $remote_ip = get_client_ip();
        $username = "";
        if (empty($admin_id) || empty($session_id)) {
            return NULL;
        }
        $admin_name = \think\Db::name("user")->where("id", $admin_id)->value("user_login");
        $username = $admin_name;
        $exists_data = \think\Db::name("admin_log")->where("sessionid", $session_id)->find();
        if (!empty($exists_data) && empty($exists_data["logouttime"])) {
            \think\Db::name("admin_log")->where("sessionid", $session_id)->update(["lastvisit" => time(), "logouttime" => time()]);
        } else {
            $idata = ["admin_username" => $username, "logintime" => time(), "ipaddress" => $remote_ip, "sessionid" => $session_id];
            \think\Db::name("admin_log")->insert($idata);
        }
    }
    public static function notifyLog($message = "", $to = "", $type = "email", $subject = "", $userid = 0, $cc = "", $bcc = "")
    {
        $uid = request()->uid ?: $userid;
        $idata = ["create_time" => time(), "to" => $to, "message" => $message, "type" => $type, "subject" => $subject, "uid" => $uid, "cc" => $cc, "bcc" => $bcc];
        \think\Db::name("notify_log")->insertGetId($idata);
    }
    public static function gatewayLog($gateway = "", $data = "", $result = "")
    {
        $idata = ["create_time" => time(), "gateway" => $gateway, "data" => $data, "result" => $result];
        \think\Db::name("gateway_log")->insertGetId($idata);
    }
}

?>