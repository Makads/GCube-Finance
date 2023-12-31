<?php

namespace app\admin\controller;

/**
 * @title 站点公告
 * @description 接口说明
 */
class AnnounceController extends AdminBaseController
{
    public function getList()
    {
        $data = $this->request->param();
        $order = isset($data["order"]) ? trim($data["order"]) : "id";
        $sort = isset($data["sort"]) ? trim($data["sort"]) : "DESC";
        $list = \think\Db::name("announce_ments")->field("id,date,title,hidden")->where("parentid", 0)->order($order, $sort)->select()->toArray();
        $returndata = [];
        $returndata["list"] = $list;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function deleteList(\think\Request $request)
    {
        if ($request->isDelete()) {
            $param = $request->param();
            $id = (int) $param["id"];
            if (empty($id)) {
                return jsonrule(["status" => 406, "msg" => "未找到公告"]);
            }
            $announce_data = \think\Db::name("announce_ments")->where("parentid", 0)->where("id", $id)->find();
            if (empty($announce_data)) {
                return jsonrule(["status" => 406, "msg" => "未找到公告"]);
            }
            \think\Db::startTrans();
            try {
                \think\Db::name("announce_ments")->where("id", $id)->whereOr("parentid", $id)->delete();
                \think\Db::commit();
                return jsonrule(["status" => 200, "msg" => "删除成功"]);
            } catch (Exception $e) {
                \think\Db::rollbakc();
                return jsonrule(["status" => 406, "msg" => "删除失败"]);
            }
        }
    }
    public function getManage(\think\Request $request)
    {
        $param = $request->param();
        $id = (int) $param["id"];
        $returndata = [];
        $language_list = get_language_list();
        $returndata["language_list"] = $language_list;
        if (!empty($id)) {
            $announce_data = \think\Db::name("announce_ments")->field("create_time,update_time", true)->where("id", $id)->find();
            if (empty($announce_data)) {
                return jsonrule(["status" => 406, "msg" => "该公告数据未找到"]);
            }
            $mulil_data = \think\Db::name("announce_ments")->field("create_time,update_time", true)->where("parentid", $id)->select()->toArray();
            $returndata["announce_data"] = $announce_data;
            $returndata["mulil_data"] = $mulil_data;
        } else {
            $returndata["announce_data"]["date"] = time();
        }
        return jsonrule(["status" => 406, "data" => $returndata]);
    }
    public function postSave(\think\Request $request)
    {
        if ($request->isPost()) {
            $param = $request->param();
            $id = $param["id"];
            $time = time();
            $date = $param["date"] ?: time();
            $title = $param["title"];
            if (empty($title)) {
                return jsonrule(["status" => 406, "msg" => "标题不能为空"]);
            }
            $announcement = $param["announcement"];
            $hidden = !empty($param["hidden"]) ? 1 : 0;
            $multilang_title = $param["multilang_title"];
            $multilang_announcement = $param["multilang_announcement"];
            $udata = ["date" => $date, "title" => $title, "announcement" => $announcement, "hidden" => $hidden];
            if (!empty($id)) {
                $announce_data = \think\Db::name("announce_ments")->where("id", $id)->find();
                if (empty($announce_data)) {
                    return jsonrule(["status" => 406, "msg" => "该公告数据未找到"]);
                }
                $udata["update_time"] = $time;
                \think\Db::name("announce_ments")->where("id", $id)->update($udata);
            } else {
                $udata["create_time"] = $time;
                $id = \think\Db::name("announce_ments")->insertGetId($udata);
            }
            if (!empty($multilang_title) && is_array($multilang_title)) {
                $language_list = get_language_list();
                foreach ($multilang_title as $lang => $val) {
                    if (array_key_exists($lang, $language_list)) {
                        $exists_data = \think\Db::name("announce_ments")->where("parentid", $id)->where("language", $lang)->find();
                        if (!empty($exists_data)) {
                            \think\Db::name("announce_ments")->where("id", $exists_data["id"])->update(["title" => $val, "announcement" => $multilang_announcement[$lang], "update_time" => $time]);
                        } else {
                            \think\Db::name("announce_ments")->insert(["title" => $val, "announcement" => $multilang_announcement[$lang], "language" => $lang, "parentid" => $id, "create_time" => $time]);
                        }
                    }
                }
            }
            return jsonrule(["status" => 200, "msg" => "保存公告成功"]);
        }
    }
}

?>