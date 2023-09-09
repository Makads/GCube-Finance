<?php

namespace app\home\controller;

/**
 * @title bot前台
 */
class InterflowController extends CommonController
{
    public function interflowAccountBindInfo()
    {
        $params = $this->request->param();
        $data = \think\Db::name("robot_clients")->field("qq")->where("uid", $params["uid"])->find();
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data ?: []]);
    }
    public function interflowAccountBind()
    {
        $params = $this->request->param();
        $data = \think\Db::name("robot_clients")->where("uid", $params["uid"])->find();
        $robot_clients["uid"] = $params["uid"];
        $robot_clients["qq"] = trim(str_replace("，", ",", $params["qq"]), ",") . ",";
        if ($data) {
            $keyword["update_time"] = time();
            \think\Db::name("robot_clients")->where(["uid" => $params["uid"]])->update($robot_clients);
        } else {
            $keyword["create_time"] = time();
            \think\Db::name("robot_clients")->insert($robot_clients);
        }
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => []]);
    }
    public function interflowAccountBindRelieve()
    {
        $params = $this->request->param();
        $mode = new \app\home\model\InterflowClientsModel();
        $mode->deleteData("id", $params["id"], ["uid", $params["uid"]]);
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => []]);
    }
}

?>