<?php


namespace app\admin\controller;

/**
 * @title 文档下载
 * @description 接口说明
 */
class DownloadsController extends AdminBaseController
{
    public function getList(\think\Request $request)
    {
        $id = $request->param("id");
        $id = intval($id) ?: 0;
        $logic_download = new \app\common\logic\Download();
        $returndata = [];
        if (empty($id)) {
            $cats_data = $logic_download->getCatesDownload1(0);
            if (!empty($cats_data[0])) {
                $returndata["files"] = \think\Db::name("downloads")->where("category", $cats_data[0]["id"])->order("create_time", "DESC")->select()->toArray();
            } else {
                $returndata["files"] = [];
            }
        } else {
            $returndata["files"] = \think\Db::name("downloads")->field("*")->where("category", $id)->order("create_time", "DESC")->select()->toArray();
        }
        foreach ($returndata["files"] as $k => $v) {
            switch ($v["type"]) {
                case 1:
                    $returndata["files"][$k]["type"] = "压缩包";
                    break;
                case 2:
                    $returndata["files"][$k]["type"] = "图片类";
                    break;
                case 3:
                    $returndata["files"][$k]["type"] = "文本类";
                    break;
            }
        }
        $id = 0;
        $returndata["level_data"] = $logic_download->getCatesDownload($id);
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function postCreate(\think\Request $request)
    {
        $rule = ["id" => "require|number", "name" => "require|max:255"];
        $msg = ["id.require" => "父id不能为空", "id.number" => "父id必须为数字", "name.require" => "分类名称不能为空", "name.max" => "名称最多不能超过255个字符"];
        $param = $request->param();
        $validate = new \think\Validate($rule, $msg);
        $result = $validate->check($param);
        if (!$result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $id = empty($param["id"]) ? intval($param["id"]) : 0;
        $catname = $param["name"];
        $hidden = !empty($param["hidden"]) ? 1 : 0;
        $description = $param["description"] ?: "";
        $idata = ["parentid" => $id, "name" => $catname, "description" => $description, "hidden" => $hidden, "create_time" => time()];
        $id = \think\Db::name("downloadcats")->insertGetId($idata);
        if ($id) {
            \think\Db::name("downloadcats")->where("id", $id)->update(["sort" => $id]);
        }
        if (!empty($id)) {
            active_log(sprintf($this->lang["Download_admin_createcates"], $id, $catname));
            return jsonrule(["status" => 200, "msg" => "添加成功"]);
        }
        return jsonrule(["status" => 406, "msg" => "添加失败"]);
    }
    public function getEdit()
    {
        $param = $this->request->param();
        $id = $param["id"];
        $id = intval($id);
        if (strlen($id) <= 0) {
            return jsonrule(["status" => 406, "msg" => "未找到该分类"]);
        }
        $cats_data = \think\Db::name("downloadcats")->field("id,parentid,name,description,hidden")->where("id", $id)->find();
        if (empty($cats_data)) {
            return jsonrule(["status" => 406, "msg" => "未找到该分类"]);
        }
        $all_cats_data = \think\Db::name("downloadcats")->field("id,name")->where("id", "<>", $id)->select()->toArray();
        array_unshift($all_cats_data, ["id" => 0, "name" => "顶层分类"]);
        $returndata = [];
        $returndata["cats_data"] = $cats_data;
        $returndata["all_cats_data"] = $all_cats_data;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function postUpdate(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["id" => "require|number", "name" => "require|max:255"];
            $msg = ["id.require" => "当前分类id为空", "id.number" => "当前分类id必须为数字", "name.require" => "分类名称不能为空", "name.max" => "名称最多不能超过255个字符"];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $cat_id = $param["id"];
            $desc = "";
            $cats = \think\Db::name("downloadcats")->where("id", $cat_id)->find();
            if ($cats["name"] != $param["name"]) {
                $desc .= "分类名由:“" . $cats["name"] . "”改为“" . $param["name"] . "”，";
            }
            if ($cats["description"] != $param["description"]) {
                $desc .= "分类描述由:“" . $cats["description"] . "”改为“" . $param["description"] . "”，";
            }
            if ($cats["hidden"] != $param["hidden"]) {
                if ($param["hidden"] == 1) {
                    $desc .= "分类隐藏由:“关闭”改为“开启”，";
                } else {
                    $desc .= "分类隐藏由:“开启”改为“关闭”，";
                }
            }
            $param["parentcategory"] = 0;
            $udata = ["parentid" => $param["parentcategory"], "name" => $param["name"], "description" => $param["description"] ?: "", "hidden" => !empty($param["hidden"]) ? 1 : 0, "update_time" => time()];
            if (empty($desc)) {
                $desc .= "什么都没改";
            }
            active_log(sprintf($this->lang["Download_admin_updatecates"], $cat_id, $desc));
            \think\Db::name("downloadcats")->where("id", $cat_id)->update($udata);
            return jsonrule(["status" => 200, "msg" => "修改成功"]);
        }
    }
    public function postUpdateSort(\think\Request $request)
    {
        if ($request->isPost()) {
            try {
                $rule = ["id" => "require|number"];
                $msg = ["id.require" => "当前分类id为空", "id.number" => "当前分类id必须为数字"];
                $param = $request->param();
                $validate = new \think\Validate($rule, $msg);
                $result = $validate->check($param);
                if (!$result) {
                    return jsonrule(["status" => 406, "msg" => $validate->getError()]);
                }
                $cat_id = $param["id"];
                $type = $param["type"];
                $pre_id = $param["pre_id"];
                $desc = "";
                $sort = \think\Db::name("downloadcats")->where("id", $cat_id)->find();
                if (empty($sort)) {
                    return jsonrule(["status" => 400, "msg" => "没有这个分类"]);
                }
                if ($type == 1) {
                    $min_order = \think\Db::name("downloadcats")->min("sort");
                    $udata = ["sort" => $min_order - 1];
                    \think\Db::name("downloadcats")->where("id", $cat_id)->update($udata);
                    $desc .= "置顶";
                } else {
                    if ($type == 2) {
                        $max_order = \think\Db::name("downloadcats")->max("sort");
                        $udata = ["sort" => $max_order + 1];
                        \think\Db::name("downloadcats")->where("id", $cat_id)->update($udata);
                        $desc .= "置底";
                    } else {
                        if ($type == 3) {
                            $pre_order = \think\Db::name("downloadcats")->where("id", $pre_id)->column("sort");
                            $cat_order = \think\Db::name("downloadcats")->where("id", $cat_id)->column("sort");
                            if ($pre_order[0] == $cat_order[0] && (int) $pre_order[0] === 0 && (int) $cat_order[0] === 0) {
                                \think\Db::name("downloadcats")->where("id", $pre_id)->update(["sort" => $pre_id]);
                                \think\Db::name("downloadcats")->where("id", $cat_id)->update(["sort" => $cat_id]);
                                $pre_order[0] = $pre_id;
                                $cat_order[0] = $cat_id;
                            }
                            if (0 < strlen($pre_order[0])) {
                                $udata1 = ["sort" => $cat_order[0]];
                                \think\Db::name("downloadcats")->where("id", $pre_id)->update($udata1);
                                $udata = ["sort" => $pre_order[0]];
                                \think\Db::name("downloadcats")->where("id", $cat_id)->update($udata);
                                $desc .= "上移";
                            } else {
                                return jsonrule(["status" => 400, "msg" => "上一个分类不存在"]);
                            }
                        } else {
                            $pre_order = \think\Db::name("downloadcats")->where("id", $pre_id)->column("sort");
                            $cat_order = \think\Db::name("downloadcats")->where("id", $cat_id)->column("sort");
                            if ($pre_order[0] == $cat_order[0] && (int) $pre_order[0] === 0 && (int) $cat_order[0] === 0) {
                                \think\Db::name("downloadcats")->where("id", $pre_id)->update(["sort" => $pre_id]);
                                \think\Db::name("downloadcats")->where("id", $cat_id)->update(["sort" => $cat_id]);
                                $pre_order[0] = $pre_id;
                                $cat_order[0] = $cat_id;
                            }
                            if (0 < strlen($pre_order[0])) {
                                $udata1 = ["sort" => $cat_order[0]];
                                \think\Db::name("downloadcats")->where("id", $pre_id)->update($udata1);
                                $udata = ["sort" => $pre_order[0]];
                                \think\Db::name("downloadcats")->where("id", $cat_id)->update($udata);
                                $desc .= "下移";
                            } else {
                                return jsonrule(["status" => 400, "msg" => "下一个分类不存在"]);
                            }
                        }
                    }
                }
                active_log(sprintf($this->lang["Download_admin_postupdatesort"], $cat_id, $desc));
            } catch (\think\Exception $e) {
                var_dump($e->getMessage());
            }
            return jsonrule(["status" => 200, "msg" => "重新排序成功"]);
        }
    }
    public function deleteCat(\think\Request $request)
    {
        if ($request->isDelete()) {
            $id = $request->id;
            $id = intval($id);
            if (empty($id)) {
                return jsonrule(["status" => 406, "msg" => "未传入要删除的分类id"]);
            }
            $cats_data = \think\Db::name("downloadcats")->field("id,parentid,name,description,hidden")->select()->toArray();
            $logic_download = new \app\common\logic\Download();
            $child_data = $logic_download->getChildTwoDimensional($cats_data, $id);
            $delete_ids = array_column($child_data, "id");
            array_unshift($delete_ids, $id);
            $cats_data1 = \think\Db::name("downloadcats")->field("name")->whereIn("id", $delete_ids)->find();
            \think\Db::startTrans();
            try {
                active_log(sprintf($this->lang["Download_admin_delcates"], $id, $cats_data1["name"]));
                \think\Db::name("downloadcats")->whereIn("id", $delete_ids)->delete();
                $delete_down = \think\Db::name("downloads")->field("id,type,location")->whereIn("category", $delete_ids)->select()->toArray();
                \think\Db::name("downloads")->whereIn("category", $delete_ids)->delete();
                $download_ids = array_column($delete_down, "id");
                \think\Db::name("product_downloads")->whereIn("download_id", $download_ids)->delete();
                $fileArr = array_column($delete_down, "location");
                $logic_download->deleteFile($fileArr);
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                return jsonrule(["status" => 406, "msg" => "删除失败"]);
            }
            return jsonrule(["status" => 200, "msg" => "删除成功"]);
        }
    }
    public function postAddFile(\think\Request $request)
    {
        try {
            if ($request->isPost()) {
                $rule = ["cateid" => "require|number", "type" => "require", "title" => "require", "filetype" => "require|in:manual,upload,remote", "clientsonly" => "in:0,1", "productdownload" => "in:0,1", "hidden" => "in:0,1"];
                $msg = ["cateid.require" => "分类id不能为空", "cateid.number" => "分类id必须为数字", "type.require" => "文件类型不能为空", "title.require" => "标题不能为空", "filetype.require" => "请选择上传还是通过FTP手动上传至downloads 目录", "filetype.in" => "请选择上传还是通过FTP手动上传至downloads 目录"];
                $param = $request->param();
                $validate = new \think\Validate($rule, $msg);
                $result = $validate->check($param);
                if (!$result) {
                    return jsonrule(["status" => 406, "msg" => $validate->getError()]);
                }
                $cateid = $param["cateid"];
                $type = $param["type"];
                $title = $param["title"];
                $description = $param["description"];
                $filetype = $param["filetype"];
                $filename = $param["filename"];
                $uploadfile = $param["uploadfile"];
                $uploadfilename = $param["uploadfilename"];
                $clientsonly = $param["clientsonly"];
                $productdownload = $param["productdownload"];
                $hidden = $param["hidden"];
                $idata = [];
                $idata = ["category" => $cateid, "type" => $type, "title" => $title, "description" => $description, "downloads" => 0, "clientsonly" => $clientsonly, "hidden" => $hidden, "productdownload" => $productdownload, "filetype" => $filetype, "create_time" => time()];
                if ($filetype == "manual") {
                    $idata["location"] = $filename;
                    $idata["locationname"] = $filename;
                    if (!file_exists(UPLOAD_PATH_DWN . "support/" . $filename)) {
                        return jsonrule(["status" => 400, "data" => "public/upload/support目录下不存在此文件，请检查文件名是否一致"]);
                    }
                } else {
                    if ($filetype == "remote") {
                        $idata["location"] = $filename;
                        $idata["locationname"] = $filename;
                    } else {
                        $idata["location"] = $uploadfile;
                        $idata["locationname"] = $uploadfilename;
                    }
                }
                if (empty($idata["location"])) {
                    return jsonrule(["status" => 400, "data" => "文件未上传成功，请等待"]);
                }
                if (strpos(config("download_ext")[$type], pathinfo($idata["location"])["extension"]) === false && $filetype != "remote") {
                    return jsonrule(["status" => 400, "data" => "文件类型不匹配"]);
                }
                $res = \think\Db::name("downloads")->insertGetId($idata);
                $idatas["url"] = $request->domain() . "/download/product_file?id=" . $res;
                \think\Db::name("downloads")->where("id", $res)->update($idatas);
                if ($res) {
                    active_log(sprintf($this->lang["Download_admin_postaddfile"], $res, $title));
                    return jsonrule(["status" => 200, "data" => "添加文件成功"]);
                }
                @unlink(UPLOAD_PATH_DWN . "support/" . $uploadfile);
                return jsonrule(["status" => 400, "data" => "添加文件失败"]);
            }
        } catch (\Throwable $e) {
            return json(["data" => ["msg" => $e->getMessage(), "line" => $e->getLine()]]);
        }
    }
    public function getFilePage()
    {
        $id = $this->request->param("id");
        $data = [];
        $data["type"] = [["id" => 1, "name" => "压缩包"], ["id" => 2, "name" => "图片类"], ["id" => 3, "name" => "文本类"]];
        foreach ($data["type"] as $k => $v) {
            $data["type_info"][] = ["name" => $v["name"], "value" => config("download_ext")[$v["id"]]];
        }
        if (empty($id)) {
            $cat_data = \think\Db::name("downloadcats")->field("id,name")->select()->toArray();
            $data["cat_data"] = $cat_data;
        } else {
            $id = intval($id);
            $file_info = \think\Db::name("downloads")->where("id", $id)->find();
            if (empty($file_info)) {
                return jsonrule(["status" => 406, "msg" => "文件数据未找到"]);
            }
            $cat = \think\Db::name("downloadcats")->field("id,name")->where("id", $file_info["category"])->find();
            $file_info["category"] = $cat["name"];
            $data["file_info"] = $file_info;
            $cat_data = \think\Db::name("downloadcats")->field("id,name")->select()->toArray();
            $data["cat_data"] = $cat_data;
        }
        return jsonrule(["status" => 200, "data" => $data]);
    }
    public function postSaveFile(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["cateid" => "require|number", "id" => "require|number", "type" => "require", "title" => "require", "downloads" => "number", "clientsonly" => "in:0,1", "productdownload" => "in:0,1", "hidden" => "in:0,1"];
            $msg = ["cateid.require" => "分类id不能为空", "cateid.number" => "分类id必须为数字", "type.require" => "文件类型不能为空", "title.require" => "标题不能为空"];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $id = $param["id"];
            $down_data = \think\Db::name("downloads")->where("id", $id)->find();
            if (empty($down_data)) {
                return jsonrule(["status" => 406, "msg" => "该文件信息不存在"]);
            }
            $desc = "";
            $cateid = $param["cateid"];
            $type = $param["type"];
            $title = $param["title"];
            $description = $param["description"];
            $downloads = $param["downloads"];
            $clientsonly = $param["clientsonly"];
            $productdownload = $param["productdownload"];
            $hidden = $param["hidden"];
            $filetype = $param["filetype"];
            $filename = $param["filename"];
            $uploadfile = $param["uploadfile"];
            $uploadfilename = $param["uploadfilename"];
            $udata = [];
            $udata = ["category" => $cateid, "type" => $type, "title" => $title, "description" => $description, "downloads" => $downloads, "clientsonly" => $clientsonly, "hidden" => $hidden, "productdownload" => $productdownload, "filetype" => $filetype, "update_time" => time()];
            if ($filetype == "manual") {
                $udata["location"] = $filename;
                $udata["locationname"] = $filename;
                if (!file_exists(UPLOAD_PATH_DWN . "support/" . $filename)) {
                    return jsonrule(["status" => 400, "data" => "downloads/support目录下不存在此文件，请检查文件名是否一致"]);
                }
            } else {
                $udata["location"] = $uploadfile;
                $udata["locationname"] = $uploadfilename;
            }
            if (empty($udata["location"])) {
                return jsonrule(["status" => 400, "data" => "文件未上传成功，请等待"]);
            }
            if (strpos(config("download_ext")[$type], pathinfo($udata["location"])["extension"]) === false) {
                return jsonrule(["status" => 400, "data" => "文件类型不匹配"]);
            }
            $downloads = \think\Db::name("downloads")->where("id", $id)->find();
            if ($downloads["category"] != $cateid) {
                $cat1 = \think\Db::name("downloadcats")->where("id", $downloads["category"])->find();
                $cat2 = \think\Db::name("downloadcats")->where("id", $cateid)->find();
                $desc .= "分类由“" . $cat1["name"] . "”改为“" . $cat1["name"] . "”，";
            }
            if ($downloads["title"] != $title) {
                $desc .= "文件标题由“" . $downloads["title"] . "”改为“" . $title . "”，";
            }
            if ($downloads["description"] != $title) {
                $desc .= "文件描述由“" . $downloads["description"] . "”改为“" . $description . "”，";
            }
            if ($downloads["downloads"] != $title) {
                $desc .= "文件下载次数由“" . $downloads["downloads"] . "”改为“" . $downloads . "”，";
            }
            if ($downloads["type"] != $type) {
                $typearr = [["1" => "压缩包"], ["2" => "图片类"], ["3" => "文本类"]];
                $desc .= "文件类型由“" . $typearr[$downloads["type"]] . "”改为“" . $typearr[$type] . "”，";
            }
            if ($downloads["hidden"] != $hidden) {
                if ($downloads["hidden"] == 1) {
                    $desc .= "文件隐藏由:“关闭”改为“开启”，";
                } else {
                    $desc .= "文件隐藏由:“开启”改为“关闭”，";
                }
            }
            if ($downloads["clientsonly"] != $clientsonly) {
                if ($downloads["clientsonly"] == 1) {
                    $desc .= "客户必须登录才能下载由:“关闭”改为“开启”，";
                } else {
                    $desc .= "客户必须登录才能下载由:“开启”改为“关闭”，";
                }
            }
            if ($downloads["productdownload"] != $productdownload) {
                if ($downloads["productdownload"] == 1) {
                    $desc .= "必须购买产品才能下载由:“关闭”改为“开启”，";
                } else {
                    $desc .= "必须购买产品才能下载由:“开启”改为“关闭”，";
                }
            }
            if (empty($desc)) {
                $desc .= "什么都没改";
            }
            $res = \think\Db::name("downloads")->where("id", $id)->update($udata);
            if ($res) {
                active_log(sprintf($this->lang["Download_admin_postsavefile"], $id, $desc));
                return jsonrule(["status" => 200, "data" => "保存成功"]);
            }
            @unlink(UPLOAD_PATH_DWN . "support/" . $uploadfile);
            return jsonrule(["status" => 400, "data" => "保存失败"]);
        }
    }
    public function postUploadFile(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["uploadfile" => "file"];
            $msg = [];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $file = request()->file("uploadfile");
            if (empty($file)) {
                return jsonrule(["status" => 400, "msg" => "uploadfile不能为空"]);
            }
            list($str) = explode(pathinfo($file->getInfo()["name"])["extension"], $file->getInfo()["name"]);
            if (preg_match("/[ ',:;*?~`!@#\$%^&+=)(<>{}]|\\]|\\[|\\/|\\\\|\"|\\|/", substr($str, 0, strlen($str) - 1))) {
                $re["status"] = 400;
                $re["msg"] = "文件名只允许数字，字母，还有汉字";
                return json($re);
            }
            if (is_dir(UPLOAD_PATH_DWN . "support")) {
                $arr = get_file_list(UPLOAD_PATH_DWN . "support", $file->getInfo()["name"]);
                if (!$arr) {
                    return jsonrule(["status" => 400, "msg" => "public/upload/support目录下已经存在此文件,不可重复上传"]);
                }
            }
            $upload = new \app\common\logic\Upload(UPLOAD_PATH_DWN . "support/");
            $re = $upload->uploadHandles($file, true);
            if ($re["status"] == 200 && is_file(UPLOAD_PATH_DWN . "support/" . $re["savename"])) {
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $re]);
            }
            return jsonrule(["status" => 400, "msg" => $re["msg"]]);
        }
    }
    public function deleteFile(\think\Request $request)
    {
        if ($request->isDelete()) {
            $param = $request->param();
            $id = $param["id"];
            if (empty($id)) {
                return jsonrule(["status" => 406, "msg" => "文件id错误"]);
            }
            if (is_array($id)) {
                $ids = "";
                foreach ($id as $k => $v) {
                    if ($k == 0) {
                        $ids .= $v;
                    } else {
                        $ids .= "," . $v;
                    }
                }
            } else {
                $ids = $id;
            }
            $file_data = \think\Db::name("downloads")->where("id", "in", $ids)->select()->toArray();
            if (empty($file_data[0])) {
                return jsonrule(["status" => 406, "msg" => "文件未找到"]);
            }
            $del_file = [];
            foreach ($file_data as $k => $v) {
                $filename = $v["location"];
                $del_file[] = $filename;
                \think\Db::name("downloads")->where("id", $v["id"])->delete();
                \think\Db::name("product_downloads")->where("download_id", $v["id"])->delete();
                active_log(sprintf($this->lang["Download_admin_deletefile"], $v["id"], $v["title"]));
            }
            $logic_download = new \app\common\logic\Download();
            $logic_download->deleteFile($del_file);
            return jsonrule(["status" => 200, "msg" => "删除文件成功"]);
        }
    }
    public function getFile(\think\Request $request)
    {
        $param = $request->param();
        $id = intval($param["id"]);
        $download_data = \think\Db::name("downloads")->where("id", $id)->find();
        $filename = $download_data["location"];
        if ($download_data["filetype"] == "remote") {
            \think\Db::name("downloads")->where("id", $id)->setInc("downloads");
            ob_clean();
            return jsonrule(["status" => 200, "data" => $this->redirect($download_data["locationname"], 302)]);
        }
        if (file_exists(UPLOAD_PATH_DWN . "support/" . $filename)) {
            ob_clean();
            header("Access-Control-Expose-Headers: Content-disposition");
            return download(UPLOAD_PATH_DWN . "support/" . $filename, explode("^", $filename)[1]);
        }
        return jsons(["status" => 406, "msg" => "资源走丢了"]);
    }
    public function getUserDownList(\think\Request $request)
    {
        $params = $data = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $uid = $params["uid"];
        $uid = intval($uid) ?: 0;
        $userdownloadcount = \think\Db::name("userdownloads")->alias("u")->field("u.id")->where("u.uid", $uid)->leftJoin("user c", "c.id=u.adminid")->count();
        $userdownloads = \think\Db::name("userdownloads")->alias("u")->field("u.*,c.user_nickname")->leftJoin("user c", "c.id=u.adminid")->where("u.uid", $uid)->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        return jsonrule(["status" => 200, "data" => $userdownloads, "total" => $userdownloadcount]);
    }
    public function getUserFile(\think\Request $request)
    {
        $param = $request->param();
        $id = intval($param["id"]);
        $uid = intval($param["uid"]);
        $download_data = \think\Db::name("userdownloads")->where("id", $id)->find();
        $filename = $download_data["url"];
        if (file_exists(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $filename)) {
            ob_clean();
            header("Access-Control-Expose-Headers: Content-disposition");
            return download(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $filename, explode("^", $filename)[1]);
        }
        return jsons(["status" => 406, "msg" => "资源走丢了"]);
    }
    public function postUploadUserFile(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["uploadfile" => "file"];
            $msg = [];
            $param = $request->param();
            $uid = $param["uid"];
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $file = request()->file("uploadfile");
            if (empty($file)) {
                return jsonrule(["status" => 400, "msg" => "uploadfile不能为空"]);
            }
            list($str) = explode(pathinfo($file->getInfo()["name"])["extension"], $file->getInfo()["name"]);
            if (preg_match("/[ ',:;*?~`!@#\$%^&+=)(<>{}]|\\]|\\[|\\/|\\\\|\"|\\|/", substr($str, 0, strlen($str) - 1))) {
                $re["status"] = 400;
                $re["msg"] = "文件名只允许数字，字母，还有汉字";
                return json($re);
            }
            $upload = new \app\common\logic\Upload(UPLOAD_PATH_DWN . "clients/" . $uid . "/");
            $re = $upload->uploadHandles1($file, true);
            if ($re["status"] == 200 && is_file(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $re["savename"])) {
                return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $re]);
            }
            return jsonrule(["status" => 400, "msg" => $re["msg"]]);
        }
    }
    public function deleteUserFile(\think\Request $request)
    {
        $param = $request->param();
        $id = $param["id"];
        if (empty($id)) {
            return jsonrule(["status" => 406, "msg" => "文件id错误"]);
        }
        if (is_array($id)) {
            $ids = "";
            foreach ($id as $k => $v) {
                if ($k == 0) {
                    $ids .= $v;
                } else {
                    $ids .= "," . $v;
                }
            }
        } else {
            $ids = $id;
        }
        $file_data = \think\Db::name("userdownloads")->where("id", "in", $ids)->select()->toArray();
        if (empty($file_data[0])) {
            return jsonrule(["status" => 406, "msg" => "文件未找到"]);
        }
        $del_file = [];
        foreach ($file_data as $k => $v) {
            $filename = $v["url"];
            $del_file[] = ["url" => $filename, "uid" => $v["uid"]];
            \think\Db::name("userdownloads")->where("id", $v["id"])->delete();
            active_logs(sprintf($this->lang["Download_admin_deleteuserfile"], $v["id"], $v["name"] . "文件名:" . explode("^", $filename)[1] . "备注:" . $v["remarks"]), $v["uid"]);
            active_logs(sprintf($this->lang["Download_admin_deleteuserfile"], $v["id"], $v["name"] . "文件名:" . explode("^", $filename)[1] . "备注:" . $v["remarks"]), $v["uid"], "", 2);
        }
        $logic_download = new \app\common\logic\Download();
        $logic_download->deleteFile1($del_file);
        return jsonrule(["status" => 200, "msg" => "删除文件成功"]);
    }
    public function postAddUserFile(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["uid" => "require|number", "name" => "require"];
            $msg = ["uid.require" => "用户id不能为空", "name.require" => "附件名不能为空"];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $filename = $param["filename"];
            $uid = $param["uid"];
            $name = $param["name"];
            $remarks = $param["remarks"] ?: "";
            $idata = [];
            $idata = ["uid" => $uid, "adminid" => session("ADMIN_ID"), "name" => $name, "remarks" => $remarks, "create_time" => time()];
            list($idata["downame"]) = explode("^", $filename);
            $idata["url"] = $filename;
            if (!file_exists(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $filename)) {
                return jsonrule(["status" => 400, "data" => "public/upload/clients/" . $uid . "/" . "目录下不存在此文件，请检查文件名是否一致"]);
            }
            if (empty($idata["url"])) {
                return jsonrule(["status" => 400, "data" => "文件未上传成功，请等待"]);
            }
            $res = \think\Db::name("userdownloads")->insertGetId($idata);
            if ($res) {
                active_logs(sprintf($this->lang["Download_admin_postadduserfile"], $res, $name . "文件名:" . $idata["downame"] . "备注:" . $remarks), $uid);
                active_logs(sprintf($this->lang["Download_admin_postadduserfile"], $res, $name . "文件名:" . $idata["downame"] . "备注:" . $remarks), $uid, "", 2);
                return jsonrule(["status" => 200, "data" => "添加文件成功"]);
            }
            @unlink(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $filename);
        }
    }
    public function getUserFilePage()
    {
        $id = $this->request->param("id");
        $id = intval($id);
        $file_info = \think\Db::name("userdownloads")->where("id", $id)->find();
        if (empty($file_info)) {
            return jsonrule(["status" => 406, "msg" => "文件数据未找到"]);
        }
        return jsonrule(["status" => 200, "data" => $file_info]);
    }
    public function postSaveUserFile(\think\Request $request)
    {
        if ($request->isPost()) {
            $rule = ["uid" => "require|number", "name" => "require"];
            $msg = ["uid.require" => "用户id不能为空", "name.require" => "附件名不能为空"];
            $param = $request->param();
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return jsonrule(["status" => 406, "msg" => $validate->getError()]);
            }
            $id = $param["id"];
            $down_data = \think\Db::name("userdownloads")->where("id", $id)->find();
            if (empty($down_data)) {
                return jsonrule(["status" => 406, "msg" => "该文件信息不存在"]);
            }
            $desc = "";
            $filename = $param["filename"];
            $uid = $param["uid"];
            $name = $param["name"];
            $remarks = $param["remarks"];
            $idata = [];
            $idata = ["uid" => $uid, "name" => $name, "remarks" => $remarks];
            $downloads = \think\Db::name("userdownloads")->where("id", $id)->find();
            if ($downloads["uid"] != $uid) {
                $d1 = \think\Db::name("clients")->field("username")->where("id", $uid)->find();
                $d2 = \think\Db::name("clients")->field("username")->where("id", $downloads["uid"])->find();
                $desc .= "附件用户由“" . $d2["username"] . "”改为“" . $d1["username"] . "”，";
            }
            if ($downloads["remarks"] != $remarks) {
                $desc .= "附件描述由“" . $downloads["remarks"] . "”改为“" . $remarks . "”，";
            }
            if ($downloads["name"] != $name) {
                $desc .= "附件名字由“" . $downloads["name"] . "”改为“" . $name . "”，";
            }
            if (empty($desc)) {
                $desc .= "什么都没改";
            }
            $res = \think\Db::name("userdownloads")->where("id", $id)->update($idata);
            if ($res) {
                active_logs(sprintf($this->lang["Download_admin_postsaveuserfile"], $id, $desc), $uid);
                active_logs(sprintf($this->lang["Download_admin_postsaveuserfile"], $id, $desc), $uid, "", 2);
                return jsonrule(["status" => 200, "data" => "保存成功"]);
            }
            @unlink(UPLOAD_PATH_DWN . "clients/" . $uid . "/" . $filename);
            return jsonrule(["status" => 400, "data" => "保存失败"]);
        }
    }
    public function downloadsConfig()
    {
        $params = input("post.");
        $rule = [];
        $msg = [];
        $validate = new \think\Validate($rule, $msg);
        $validate_result = $validate->check($params);
        if (!$validate_result) {
            return jsonrule(["status" => 406, "msg" => $validate->getError()]);
        }
        $downloads_config = config("downloads_config");
        $data = getConfig(array_keys($downloads_config));
        $data = array_merge($downloads_config, $data);
        $dec = "";
        foreach ($data as $k => $v) {
            if (isset($params[$k]) && $v != $params[$k]) {
                $company_name = configuration($k);
                updateConfiguration($k, $params[$k]);
                if ($k == "enable_file_download") {
                    if ($params[$k] == 1) {
                        $dec .= " -  启用文件下载功能";
                    } else {
                        $dec .= " -  禁用文件下载功能";
                    }
                } else {
                    $dec .= " - " . $k . ":" . $company_name . "改为" . $params[$k];
                }
            }
        }
        active_log(sprintf($this->lang["Downloads_admin_editCron"], $dec));
        unset($dec);
        unset($company_name);
        $result["status"] = 200;
        $result["msg"] = lang("UPDATE SUCCESS");
        return jsonrule($result);
    }
}

?>