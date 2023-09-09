<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

namespace app\common\logic;

class RunMap
{
    public function saveMap($data, $status, $from_type, $active_type, $from_active = 3)
    {
        $jwt = userGetCookie();
        $user_id = \think\facade\Cache::get("client_user_login_token_" . $jwt);
        $admin_id = cmf_get_current_admin_id();
        $host_id = $data["host_id"];
        $description = $data["description"];
        $host_uid = \think\Db::name("host")->where("id", $host_id)->value("uid");
        $host_user_data = \think\Db::name("clients")->where("id", $host_uid)->field("id,username")->find();
        if (isset($host_uid) && empty($host_user_data)) {
            $from_active = NULL;
            $host_user_data["id"] = $host_uid;
            $host_user_data["username"] = "此用户:" . $host_uid . " 不存在";
            $active_user = "System";
        }
        switch ($from_active) {
            case 1:
                $active_user = \think\Db::name("user")->where("id", $admin_id)->value("user_login");
                if (empty($active_user)) {
                    $active_user = "System";
                }
                break;
            case 2:
                $active_user = \think\Db::name("clients")->where("id", $user_id)->value("username");
                if (empty($active_user)) {
                    $active_user = $host_user_data["username"];
                }
                break;
            case 3:
                $active_user = "System";
                break;
            default:
                $data_i["user_id"] = $host_user_data["id"];
                $data_i["user"] = $host_user_data["username"];
                $data_i["host_id"] = $host_id;
                $data_i["description"] = $description;
                $data_i["from_type"] = $from_type;
                $data_i["active_user"] = $active_user;
                $data_i["active_type"] = $active_type;
                $data_i["active_type_param"] = json_encode($data["active_type_param"]);
                $data_i["status"] = $status ? 1 : 0;
                $rm_model = new \app\admin\model\RunMapModel();
                try {
                    $res = $rm_model->checkOnlyOne($data_i["host_id"], $data_i["active_type"]);
                    if ($res) {
                        $rm_model->add($data_i);
                    } else {
                        $rm_model->edit($data_i);
                    }
                } catch (\Exception $e) {
                    return false;
                }
                return true;
        }
    }
    public function cronSuccess($time, $cron_type, $unique_id)
    {
        if (empty($cron_type)) {
            return false;
        }
        $rm_model = new \app\admin\model\RunMapModel();
        $unique_tab = date("Ymd", $time) . $cron_type . $unique_id;
        $rm_model->cronEditStatus($unique_tab);
        return true;
    }
}

?>