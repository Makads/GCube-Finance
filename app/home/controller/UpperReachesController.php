<?php

namespace app\home\controller;

/**
 * @title 上游资源管理模块
 */
class UpperReachesController extends CommonController
{
    public function dcimClientReinstall()
    {
        $params = $this->request->param();
        $id = !empty($params["id"]) ? trim($params["id"]) : "";
        $password = !empty($params["password"]) ? trim($params["password"]) : "";
        $os = !empty($params["os"]) ? intval($params["os"]) : 0;
        $port = !empty($params["port"]) ? intval($params["port"]) : 0;
        $part_type = !empty($params["part_type"]) ? intval($params["part_type"]) : 0;
        $re = \think\Db::name("upper_reaches_res")->where("id", $id)->find();
        if (empty($re)) {
            return jsonrule(["status" => 400, "msg" => "没有此资源配置"]);
        }
        if ($re["control_mode"] != "dcim_client") {
            return jsonrule(["status" => 400, "msg" => "资源配置控制方式有误"]);
        }
        $data = ["password" => $password, "os" => $os, "port" => $port, "part_type" => $part_type];
        $UpperReaches = new \app\common\logic\UpperReaches();
        $UpperReaches->is_admin = true;
        $result = $UpperReaches->dcimClientReinstall($re, $data);
        return jsonrule($result);
    }
    public function dcimClientCrackPass()
    {
        $params = $this->request->param();
        $id = !empty($params["id"]) ? trim($params["id"]) : "";
        $password = !empty($params["password"]) ? trim($params["password"]) : "";
        $other_user = !empty($params["other_user"]) ? intval($params["other_user"]) : 0;
        $user = !empty($params["user"]) ? trim($params["user"]) : "";
        $re = \think\Db::name("upper_reaches_res")->where("id", $id)->find();
        if (empty($re)) {
            return jsonrule(["status" => 400, "msg" => "没有此资源配置"]);
        }
        if ($re["control_mode"] != "dcim_client") {
            return jsonrule(["status" => 400, "msg" => "资源配置控制方式有误"]);
        }
        $data = ["password" => $password, "other_user" => $other_user, "user" => $user];
        $UpperReaches = new \app\common\logic\UpperReaches();
        $UpperReaches->is_admin = false;
        $result = $UpperReaches->dcimClientCrackPass($re, $data);
        return jsonrule($result);
    }
    public function dcimClientCancelReinstall()
    {
        $params = $this->request->param();
        $id = !empty($params["id"]) ? trim($params["id"]) : "";
        $re = \think\Db::name("upper_reaches_res")->where("id", $id)->find();
        if (empty($re)) {
            return jsonrule(["status" => 400, "msg" => "没有此资源配置"]);
        }
        if ($re["control_mode"] != "dcim_client") {
            return jsonrule(["status" => 400, "msg" => "资源配置控制方式有误"]);
        }
        $UpperReaches = new \app\common\logic\UpperReaches();
        $UpperReaches->is_admin = false;
        $result = $UpperReaches->dcimClientCancelReinstall($re);
        return jsonrule($result);
    }
    public function dcimClientReinstallStatus()
    {
        $params = $this->request->param();
        $id = !empty($params["id"]) ? trim($params["id"]) : "";
        $re = \think\Db::name("upper_reaches_res")->where("id", $id)->find();
        if (empty($re)) {
            return jsonrule(["status" => 400, "msg" => "没有此资源配置"]);
        }
        if ($re["control_mode"] != "dcim_client") {
            return jsonrule(["status" => 400, "msg" => "资源配置控制方式有误"]);
        }
        $UpperReaches = new \app\common\logic\UpperReaches();
        $UpperReaches->is_admin = false;
        $result = $UpperReaches->dcimClientReinstallStatus($re);
        return jsonrule($result);
    }
    public function dcimClientGetOs()
    {
        $params = $this->request->param();
        $id = !empty($params["id"]) ? trim($params["id"]) : "";
        $re = \think\Db::name("upper_reaches_res")->where("id", $id)->find();
        if (empty($re)) {
            return jsonrule(["status" => 400, "msg" => "没有此资源配置"]);
        }
        if ($re["control_mode"] != "dcim_client") {
            return jsonrule(["status" => 400, "msg" => "资源配置控制方式有误"]);
        }
        $UpperReaches = new \app\common\logic\UpperReaches();
        $UpperReaches->is_admin = false;
        $result = $UpperReaches->dcimClientGetOs($re);
        return jsonrule($result);
    }
}

?>