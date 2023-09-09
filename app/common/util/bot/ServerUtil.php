<?php

namespace app\common\util\bot;

class ServerUtil
{
    public function restart($params = [])
    {
        return "完成重启";
    }
    public function power_on($ip)
    {
        return "完成开机";
    }
    public function power_off($ip)
    {
        return "完成关机";
    }
}

?>