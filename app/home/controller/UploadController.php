<?php

namespace app\home\controller;

/**
 * @title 文件上传(前台)
 * @description 接口说明
 */
class UploadController extends \cmf\controller\HomeBaseController
{
    public function upload()
    {
        $validate = ["size" => 2097152, "ext" => "jpg,jpeg,png,gif"];
        $file = request()->file("image");
        $info = $file->validate($validate)->rule(function () {
            return mt_rand(1000, 9999) . "_" . md5(microtime(true));
        })->move(config("attachment"));
        if ($info) {
            $res["status"] = 200;
            $res["msg"] = "上传成功";
            $res["data"] = request()->domain() . request()->rootUrl() . config("attachment_url") . $info->getFilename();
        } else {
            $res["status"] = 406;
            $res["msg"] = "上传失败";
        }
        return jsons($res);
    }
    public function uploadImage()
    {
        $image = request()->file("image");
        if (!isset($image)) {
            $image = request()->file("file");
        }
        if (!isset($image)) {
            $image = request()->file("app_file");
        }
        list($str) = explode(pathinfo($image->getInfo()["name"])["extension"], $image->getInfo()["name"]);
        if (preg_match("/['!@^&]|\\/|\\\\|\"/", substr($str, 0, strlen($str) - 1))) {
            $re["status"] = 400;
            $re["msg"] = "文件名不允许包含!@^&\"'/\\";
            return json($re);
        }
        $upload = new \app\common\logic\Upload();
        $re = $upload->uploadHandle($image);
        if (!$re) {
            return json(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        }
        if ($re["status"] == 200) {
            return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "originname" => $re["origin_name"], "savename" => $re["savename"], "tmp" => base64EncodeImage(UPLOAD_DEFAULT . $re["savename"])]);
        }
        return jsons(["status" => 400, "msg" => $re["msg"]]);
    }
    public function uploadFile()
    {
        $filename = request()->file("file");
        if (!isset($filename)) {
            return jsons(["status" => 400, "msg" => "参数错误"]);
        }
        list($str) = explode(pathinfo($filename->getInfo()["name"])["extension"], $filename->getInfo()["name"]);
        if (preg_match("/['!@^&]|\\/|\\\\|\"/", substr($str, 0, strlen($str) - 1))) {
            $re["status"] = 400;
            $re["msg"] = "文件名不允许包含!@^&\"'/\\";
            return json($re);
        }
        $upload = new \app\common\logic\Upload(UPLOAD_DEFAULT);
        $re = $upload->uploadHandle($filename, true);
        $re["tmp"] = base64EncodeImage(UPLOAD_DEFAULT . $re["savename"]);
        if ($re["status"] == 200) {
            return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $re]);
        }
        return jsons(["status" => 400, "msg" => $re["msg"]]);
    }
    private function getUri($type)
    {
        switch ($type) {
            case "avatar":
                $uri = config("client_avatar");
                break;
            case "servers":
                $uri = config("servers");
                break;
            case "email":
                $uri = config("email_attachments");
                break;
            default:
                $uri = UPLOAD_DEFAULT;
                return $uri;
        }
    }
}

?>