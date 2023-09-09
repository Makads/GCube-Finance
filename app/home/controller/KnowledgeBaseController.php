<?php

namespace app\home\controller;

/**
 * @title 前台帮助中心
 * @description 接口说明
 */
class KnowledgeBaseController extends CommonController
{
    public function index()
    {
        $data = $this->request->param();
        $categories = \think\Db::name("knowledge_base_links")->alias("kbl")->field("kbc.id,name,description,count(kbl.category_id) as num")->leftJoin("knowledge_base_cats kbc", "kbl.category_id = kbc.id")->where("")->where("hidden", 0)->group("kbl.category_id")->select();
        $tags = \think\Db::name("knowledge_base_tags")->field("tag,count(tag) as num")->group("tag")->select();
        foreach ($tags as $key => $tag) {
            $tags[$key] = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $tag);
        }
        if (isset($data["id"]) && !empty($data["id"])) {
            $cid = $data["id"];
        } else {
            $cid = $categories[0]["id"];
        }
        $article = \think\Db::name("knowledge_base_links")->alias("kbl")->field("kb.id,title,article,views,useful,public_by,public_time")->leftJoin("knowledge_base kb", "kbl.article_id = kb.id")->leftJoin("knowledge_base_cats kbc", "kbl.category_id = kbc.id")->where("kbc.hidden", 0)->where("kb.hidden", 0)->where("kbl.category_id", $cid)->where(function (\think\db\Query $query) {
            $uid = request()->uid;
            if (!$uid) {
                $query->where("login_view", 0);
            }
            $hostcount = \think\Db::name("host")->where("domainstatus", "Active")->where("uid", $uid)->count();
            if (!$hostcount) {
                $query->where("host_view", 0);
            }
        })->order("kb.order asc")->select();
        return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "categories" => $categories, "tags" => $tags, "article" => $article]);
    }
    public function searchArticle()
    {
        if ($this->request->isPost()) {
            $keyword = trim($this->request->param("keyword"));
            $result = \think\Db::name("knowledge_base")->field("id,title,article,views,useful,public_by,public_time")->where("title|article", "like", "%" . $keyword . "%")->where("hidden", 0)->where(function (\think\db\Query $query) {
                $uid = request()->uid;
                if (!$uid) {
                    $query->where("login_view", 0);
                }
                $hostcount = \think\Db::name("host")->where("domainstatus", "Active")->where("uid", $uid)->count();
                if (!$hostcount) {
                    $query->where("host_view", 0);
                }
            })->select();
            foreach ($result as $key => $value) {
                $value["article"] = mb_substr($value["article"], 0, 20);
                $value = array_map(function ($v) {
                    return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
                }, $value);
                $result[$key] = $value;
            }
            if (!empty($result[0])) {
                return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "result" => $result]);
            }
            return json(["status" => 400, "msg" => lang("KNOWLEDGE_NO_SIMILAR_ARTICLE")]);
        } else {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
    }
    public function tagsList()
    {
        $params = $this->request->param();
        $tag = trim($params["tag"]);
        $article = \think\Db::name("knowledge_base_tags")->alias("kbt")->field("kb.id,title,article,views,useful,public_by,public_time")->leftJoin("knowledge_base kb", "kb.id = kbt.article_id")->where("tag", $tag)->select();
        foreach ($article as $key => $value) {
            $value["article"] = mb_substr($value["article"], 0, 20);
            $value = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $value);
            $article[$key] = $value;
        }
        if (!empty($article[0])) {
            return json(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "article" => $article]);
        }
        return json(["status" => 400, "msg" => lang("KNOWLEDGE_NO_SIMILAR_ARTICLE")]);
    }
    public function viewArticle()
    {
        $data = $this->request->param();
        $id = isset($data["id"]) && !empty($data["id"]) ? intval($data["id"]) : "";
        if (!$id) {
            return json(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $categories = \think\Db::name("knowledge_base_cats")->field("id,name")->where("hidden", 0)->select();
        $article = \think\Db::name("knowledge_base")->field("id,title,article,views,useful,public_by,public_time")->where("id", $id)->where("hidden", 0)->where(function (\think\db\Query $query) {
            $uid = request()->uid;
            if (!$uid) {
                $query->where("login_view", 0);
            }
            $hostcount = \think\Db::name("host")->where("domainstatus", "Active")->where("uid", $uid)->count();
            if (!$hostcount) {
                $query->where("host_view", 0);
            }
        })->find();
        $article = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $article);
        return json(["status" => 200, "msg" => "请求成功", "categories" => $categories, "article" => $article]);
    }
}

?>