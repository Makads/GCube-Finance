<?php

namespace app\common\model;

class TicketModel
{
    public function getTid()
    {
        $len = 6;
        $max_len = 8;
        $times = 10;
        for ($tid = cmf_random_str($len, "number"); $len < $max_len; $len++) {
            $times = ($len - 5) * 10;
            $i = 0;
            while ($i < $times) {
                if (\think\Db::name("ticket")->where("tid", $tid)->where("merged_ticket_id", 0)->find()) {
                    $tid = cmf_random_str($len, "number");
                    $i++;
                }
            }
        }
        return $tid;
    }
    public function getUser($id = 0)
    {
        $ticket = \think\Db::name("ticket")->alias("a")->field("a.uid,a.name,a.email,b.username,b.email user_email")->leftJoin("clients b", "a.uid=b.id")->where("a.id", $id)->find();
        if (0 < $ticket["uid"]) {
            $data["uid"] = $ticket["uid"];
            $data["name"] = $ticket["username"];
            $data["email"] = $ticket["user_email"];
        } else {
            $data["uid"] = 0;
            $data["name"] = $ticket["name"];
            $data["email"] = $ticket["email"];
        }
        return $data;
    }
    public function parse($content = "", $array = [])
    {
        foreach ($array as $k => $v) {
            $content = str_replace("[" . $k . "]", $v, $content);
        }
        return $content;
    }
    public function getTicket($params)
    {
        if (empty($params["uid"])) {
            $ticket = \think\Db::name("ticket")->alias("t")->field("t.*,td.no_auto_reply,td.name dptname")->leftJoin("ticket_department td", "t.dptid=td.id")->where("t.tid", $params["tid"])->where("t.c", $params["c"])->where("t.uid", 0)->where("t.merged_ticket_id", 0)->where("td.hidden", 0)->where("td.only_reg_client", 0)->find();
        } else {
            $ticket = \think\Db::name("ticket")->alias("t")->field("t.*,td.no_auto_reply,td.name dptname")->leftJoin("ticket_department td", "t.dptid=td.id")->where("t.tid", $params["tid"])->where("t.uid", $params["uid"])->where("t.merged_ticket_id", 0)->where("td.hidden", 0)->find();
        }
        unset($ticket["token"]);
        return $ticket;
    }
}

?>