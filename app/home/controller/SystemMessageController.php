<?php

namespace app\home\controller;

/**
 * @title 系统消息
 * Class SystemMessage
 * @package app\home\controller
 */
class SystemMessageController extends \cmf\controller\HomeBaseController
{
    private $system_message_type = ["1" => "work_order_message", "2" => "product_news", "3" => "on_site_news", "4" => "event_news"];
    public function getMessageList(\think\Request $request)
    {
        $params = $data = $this->request->param();
        $page = $params["page"] ?? config("page");
        $limit = $params["limit"] ?? config("limit");
        $list = \think\Db::name("system_message")->alias("sm")->join("clients c", "c.id = sm.uid")->where(function (\think\db\Query $query) use($params) {
            if (0 < $params["type"]) {
                $query->where("sm.type", $params["type"]);
            }
            $query->where("sm.delete_time", 0);
            $query->where("sm.uid", $params["uid"]);
        })->page($page)->limit($limit)->field("sm.*,c.username,c.phonenumber,c.email")->order("sm.id", "desc")->select()->toArray();
        $count = \think\Db::name("system_message")->alias("sm")->join("clients c", "c.id = sm.uid")->where(function (\think\db\Query $query) use($params) {
            if (0 < $params["type"]) {
                $query->where("sm.type", $params["type"]);
            }
            $query->where("sm.delete_time", 0);
            $query->where("sm.uid", $params["uid"]);
        })->count();
        if ($list) {
            foreach ($list as &$item) {
                $item["content"] = htmlspecialchars_decode(htmlspecialchars_decode($item["content"]));
                $item["create_time"] = date("Y-m-d H:i:s", $item["create_time"]);
                $item["type_text"] = $this->system_message_type[$item["type"]];
                if ($item["attachment"]) {
                    $attachment = explode(",", $item["attachment"]);
                    $item["attachment"] = [];
                    foreach ($attachment as &$attachment_item) {
                        if ($item["type"] == "3") {
                            $temp = [];
                            $temp["path"] = $_SERVER["REQUEST_SCHEME"] . "://" . $request->host() . config("system_message_url") . $attachment_item;
                            $attachment_item = explode("^", $attachment_item);
                            $temp["name"] = $attachment_item[1];
                            $item["attachment"][] = $temp;
                        }
                    }
                }
            }
        }
        $system_message_type = $this->system_message_type;
        foreach ($system_message_type as $key => $type_item) {
            $temp_message["id"] = $key;
            $temp_message["name"] = $type_item;
            $temp_message["unread_num"] = \think\Db::name("system_message")->where("delete_time", 0)->where("read_time", 0)->where("type", $key)->where("uid", $params["uid"])->count();
            $unread_count[] = $temp_message;
        }
        $data["list"] = $list;
        $data["count"] = $count;
        $data["unread_count"] = $unread_count;
        return jsonrule(["status" => 200, "msg" => "成功", "data" => $data]);
    }
    public function getUnreadList()
    {
        $params = $data = $this->request->param();
        $unread_count = [];
        $unread_count_num = 0;
        $system_message_type = $this->system_message_type;
        foreach ($system_message_type as $key => $type_item) {
            $temp_message["id"] = $key;
            $temp_message["name"] = $type_item;
            $temp_message["unread_num"] = \think\Db::name("system_message")->where("delete_time", 0)->where("read_time", 0)->where("type", $key)->where("uid", $params["uid"])->count();
            $unread_count_num += $temp_message["unread_num"];
            $unread_count[] = $temp_message;
        }
        return jsonrule(["status" => 200, "msg" => "成功", "data" => ["unread_nav" => $unread_count, "unread_num" => $unread_count_num]]);
    }
    public function readSystemMessage()
    {
        $param = $this->request->param();
        $ids = $param["ids"];
        $type = $param["type"] ?? 0;
        $user_message_ids = \think\Db::name("system_message")->where("uid", $param["uid"])->column("id");
        if (empty($user_message_ids)) {
            return jsonrule(["status" => 400, "msg" => "暂无可阅读消息"]);
        }
        if ($ids) {
            foreach ($ids as $item) {
                if (!in_array($item, $user_message_ids)) {
                    return jsonrule(["status" => 400, "msg" => "参数错误，只能阅读自己的消息"]);
                }
            }
        }
        $result = \think\Db::name("system_message")->where("uid", $param["uid"])->where(function (\think\db\Query $query) use($ids) {
            if ($ids) {
                $query->where("id", "in", $ids);
            }
            if ($type) {
                $query->where("type", $type);
            }
        })->update(["read_time" => time()]);
        if ($result === false) {
            return jsonrule(["status" => 400, "msg" => "阅读失败"]);
        }
        return jsonrule(["status" => 200, "msg" => "阅读成功"]);
    }
    public function deleteSystemMessage()
    {
        $param = $this->request->param();
        $ids = $param["ids"];
        $type = $param["type"] ?? 0;
        $user_message_ids = \think\Db::name("system_message")->where("uid", $param["uid"])->column("id");
        if (empty($user_message_ids)) {
            return jsonrule(["status" => 400, "msg" => "暂无可删除消息"]);
        }
        if ($ids) {
            foreach ($ids as $item) {
                if (!in_array($item, $user_message_ids)) {
                    return jsonrule(["status" => 400, "msg" => "参数错误，只能删除自己的消息"]);
                }
            }
        }
        $result = \think\Db::name("system_message")->where("uid", $param["uid"])->where(function (\think\db\Query $query) use($ids) {
            if ($ids) {
                $query->where("id", "in", $ids);
            }
            if ($type) {
                $query->where("type", $type);
            }
        })->update(["delete_time" => time()]);
        if ($result === false) {
            return jsonrule(["status" => 400, "msg" => "删除失败"]);
        }
        return jsonrule(["status" => 200, "msg" => "删除成功"]);
    }
}

?>