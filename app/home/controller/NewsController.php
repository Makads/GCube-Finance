<?php

namespace app\home\controller;

/**
 * @title 前台新闻
 * @description 接口说明
 */
class NewsController extends \cmf\controller\HomeBaseController
{
    protected $upload_path = "";
    protected $upload_url = "";
    protected function initialize()
    {
        parent::initialize();
        $this->upload_url = $this->request->host() . "/upload/news/";
        $this->upload_path = CMF_ROOT . "/public/upload/news/";
    }
    public function newsCate()
    {
        return $this->cateList(1);
    }
    public function helpCate()
    {
        return $this->cateList(2);
    }
    public function cateList($parent_id)
    {
        $list = \think\Db::name("news_type")->field("id,title")->where(["hidden" => 0, "parent_id" => $parent_id])->order("id", "DESC")->select()->toArray();
        $data = [];
        foreach ($list as $k => $v) {
            $data[$k] = $v;
        }
        return $data;
    }
    public function getList(\think\Request $request)
    {
        $returndata = [];
        $pagecount = 9;
        $param = $request->param();
        $page = intval($param["page"]) ?: 1;
        $limit = intval($param["limit"]) ?: 10;
        $where = [["hidden", "=", 0]];
        if (isset($param["parent_id"]) && 0 < $param["parent_id"]) {
            $tmp = \think\Db::name("news_type")->find($param["parent_id"]);
            if (isset($tmp["id"]) && $tmp["parent_id"] == 0) {
                $tmp_list = \think\Db::name("news_type")->where(["hidden" => 0, "parent_id" => $tmp["id"]])->select()->toArray();
                $tmp_ids = isset($tmp_list[0]) ? array_column($tmp_list, "id") : [];
                $where[] = ["parent_id", "in", array_merge($tmp_ids, [$tmp["id"]])];
            } else {
                $where[] = ["parent_id", "=", $param["parent_id"]];
            }
        } else {
            $tmp_list = \think\Db::name("news_type")->where("hidden", 0)->select()->toArray();
            $tmp_ids = array_column($tmp_list, "id");
            $where[] = ["parent_id", "in", $tmp_ids];
        }
        if (isset($param["search"][0])) {
            $where[] = ["content|title", "LIKE", "%" . $param["search"] . "%", "OR"];
        }
        $news_menu = \think\Db::name("news_menu as m")->field("m.*,news.content")->leftJoin("news", "m.id=news.relid")->where($where)->withAttr("news.content", function ($value) {
            return mb_substr($value, 0, 50);
        })->order("push_time", "DESC")->page($page, $limit)->select()->toArray();
        $count = \think\Db::name("news_menu as m")->leftJoin("news", "m.id=news.relid")->where($where)->count();
        $url = $this->upload_url;
        $returndata["list"] = array_map(function ($v) use($url) {
            $v["head_img"] = isset($v["head_img"][0]) ? $url . $v["head_img"] : "";
            return $v;
        }, $news_menu);
        $returndata["pagecount"] = $pagecount;
        $returndata["page"] = $page;
        $returndata["search"] = $param["search"];
        $returndata["total_page"] = ceil($count / $pagecount);
        $returndata["count"] = $count;
        return json(["status" => 200, "data" => $returndata]);
    }
    public function getNotice(\think\Request $request)
    {
        $list = \think\Db::name("news_type")->where(["parent_id" => 2, "hidden" => 0])->select()->toArray();
        foreach ($list as &$v) {
            $v["list"] = \think\Db::name("news_menu")->where(["parent_id" => $v["id"], "hidden" => 0])->limit(0, 5)->select()->toArray();
        }
        return json(["status" => 200, "data" => $list]);
    }
    public function getContent(\think\Request $request)
    {
        $id = $request->param("id");
        $id = intval($id);
        if (empty($id)) {
            return json(["status" => 406, "msg" => "新闻未找到"]);
        }
        $new_content = \think\Db::name("news_menu")->field("m.*,n.content")->alias("m")->leftJoin("news n", "n.relid=m.id")->where("m.id", $id)->find();
        if (empty($new_content)) {
            return json(["status" => 406, "msg" => "新闻未找到"]);
        }
        $new_content["head_img"] = isset($new_content["head_img"][0]) ? $this->upload_url . $new_content : "";
        $new_content["content"] = htmlspecialchars_decode(htmlspecialchars_decode($new_content["content"]));
        $returndata["new_content"] = $new_content;
        $where = [["hidden", "=", 0]];
        $parent = \think\Db::name("news_type")->find($new_content["parent_id"]);
        if (isset($parent["parent_id"]) && $parent["parent_id"] == 1) {
            $tmp = \think\Db::name("news_type")->where([["parent_id", "=", 1], ["hidden", "=", 0]])->select();
            if (isset($tmp[0]["id"])) {
                $tmp_ids = array_column($tmp->toArray(), "id");
                $tmp_ids[] = 1;
                $where[] = ["parent_id", "in", $tmp_ids];
            } else {
                $where[] = ["parent_id", "=", $parent["id"]];
            }
        } else {
            $where[] = ["parent_id", "=", $parent["id"]];
        }
        $returndata["next"] = \think\Db::name("news_menu")->where(array_merge($where, [["push_time", ">", $new_content["push_time"]]]))->order("push_time", "ASC")->find();
        $returndata["prev"] = \think\Db::name("news_menu")->where(array_merge($where, [["push_time", "<", $new_content["push_time"]]]))->order("push_time", "DESC")->find();
        $returndata["relevant"] = \think\Db::name("news_menu")->where(array_merge($where, [["id", "<>", $new_content["id"]]]))->order("push_time", "DESC")->limit(3)->page(1)->select()->toArray();
        return json(["status" => 200, "data" => $returndata]);
    }
    public function getCateList()
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param["status"])) {
            $where[] = ["status", "=", (int) $param["status"]];
        }
        if (isset($param["parent_id"])) {
            $where[] = ["parent_id", "=", (int) $param["parent_id"]];
        }
        $list = \think\Db::name("news_type")->where($where)->order("parent_id", "ASC")->select();
        $data = [];
        foreach ($list as $v) {
            if (0 < $v["parent_id"] && isset($data[$v["parent_id"]])) {
                $v["count"] = \think\Db::name("news_menu")->where("parent_id", $v["id"])->count();
                $data[$v["parent_id"]]["list"] = [];
                $data[$v["parent_id"]]["list"][] = $v;
            } else {
                $v["count"] = \think\Db::name("news_menu")->where("parent_id", $v["id"])->count();
                $data[$v["id"]] = $v;
            }
        }
        return jsonrule(["status" => 200, "data" => array_values($data)]);
    }
    private function getCats()
    {
        $cat_data = \think\Db::name("news_type")->select()->toArray();
        return $cat_data;
    }
    public function getNoticeList(\think\Request $request)
    {
        $returndata = [];
        $pagecount = 9;
        $param = $request->param();
        $page = intval($param["page"]) ?: 1;
        $limit = intval($param["limit"]) ?: 10;
        $where = [["hidden", "=", 0]];
        if (isset($param["parent_id"]) && 0 < $param["parent_id"]) {
            $tmp = \think\Db::name("news_type")->find($param["parent_id"]);
            if (isset($tmp["id"]) && $tmp["parent_id"] == 0) {
                $tmp_list = \think\Db::name("news_type")->where(["hidden" => 0, "parent_id" => $tmp["id"]])->select()->toArray();
                $tmp_ids = isset($tmp_list[0]) ? array_column($tmp_list, "id") : [];
                $where[] = ["parent_id", "in", array_merge($tmp_ids, [$tmp["id"]])];
            } else {
                $where[] = ["parent_id", "=", $param["parent_id"]];
            }
        } else {
            $tmp_list = \think\Db::name("news_type")->where("hidden", 0)->select()->toArray();
            $tmp_ids = array_column($tmp_list, "id");
            $where[] = ["parent_id", "in", $tmp_ids];
        }
        if (isset($param["search"][0])) {
            $where[] = ["content|title", "LIKE", "%" . $param["search"] . "%", "OR"];
        }
        $news_menu = \think\Db::name("news_menu as m")->field("m.*,news.content")->leftJoin("news", "m.id=news.relid")->where($where)->withAttr("news.content", function ($value) {
            return mb_substr($value, 0, 50);
        })->order("push_time", "DESC")->page($page, $limit)->select()->toArray();
        $count = \think\Db::name("news_menu as m")->leftJoin("news", "m.id=news.relid")->where($where)->count();
        $url = $this->upload_url;
        $returndata["list"] = array_map(function ($v) use($url) {
            $v["content"] = strip_tags(htmlspecialchars_decode(htmlspecialchars_decode($v["content"])));
            $v["head_img"] = isset($v["head_img"][0]) ? $url . $v["head_img"] : "";
            return $v;
        }, $news_menu);
        $returndata["pagecount"] = $pagecount;
        $returndata["page"] = $page;
        $returndata["search"] = $param["search"];
        $returndata["total_page"] = ceil($count / $pagecount);
        $returndata["count"] = $count;
        return json(["status" => 200, "data" => $returndata]);
    }
    public function getNoticeContent(\think\Request $request)
    {
        $id = $request->param("id");
        $id = intval($id);
        if (empty($id)) {
            return json(["status" => 406, "msg" => "新闻未找到"]);
        }
        $new_content = \think\Db::name("news_menu")->field("m.*,n.content")->alias("m")->leftJoin("news n", "n.relid=m.id")->where("m.id", $id)->find();
        if (empty($new_content)) {
            return json(["status" => 406, "msg" => "新闻未找到"]);
        }
        $new_content["head_img"] = isset($new_content["head_img"][0]) ? $this->upload_url . $new_content : "";
        $new_content["content"] = htmlspecialchars_decode(htmlspecialchars_decode($new_content["content"]));
        $returndata["new_content"] = $new_content;
        $where = [["hidden", "=", 0]];
        $parent = \think\Db::name("news_type")->find($new_content["parent_id"]);
        if (isset($parent["parent_id"]) && $parent["parent_id"] == 1) {
            $tmp = \think\Db::name("news_type")->where([["parent_id", "=", 1], ["hidden", "=", 0]])->select();
            if (isset($tmp[0]["id"])) {
                $tmp_ids = array_column($tmp->toArray(), "id");
                $tmp_ids[] = 1;
                $where[] = ["parent_id", "in", $tmp_ids];
            } else {
                $where[] = ["parent_id", "=", $parent["id"]];
            }
        } else {
            $where[] = ["parent_id", "=", $parent["id"]];
        }
        $returndata["next"] = \think\Db::name("news_menu")->where(array_merge($where, [["push_time", ">", $new_content["push_time"]]]))->order("push_time", "ASC")->find();
        $returndata["prev"] = \think\Db::name("news_menu")->where(array_merge($where, [["push_time", "<", $new_content["push_time"]]]))->order("push_time", "DESC")->find();
        return json(["status" => 200, "data" => $returndata]);
    }
}

?>