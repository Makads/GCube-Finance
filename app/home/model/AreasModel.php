<?php


namespace app\home\model;

class AreasModel extends \think\Model
{
    protected $pk = "area_id";
    public function listQuery($pid = 0)
    {
        $pid = 0 < $pid ? $pid : (int) input("pid");
        return $this->where(["show" => 1, "data_flag" => 1, "pid" => $pid])->field("area_id,name,pid")->order("sort desc")->select();
    }
}

?>