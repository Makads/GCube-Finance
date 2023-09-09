<?php

namespace app\home\controller;

/**
 * @title 前台子账户管理
 * @description 接口说明: 子账户管理
 */
class ContactsController extends CommonController
{
    public function index(\think\Request $request)
    {
        $param = $request->param();
        $uid = $request->uid;
        $cid = $param["cid"];
        $contact_list = \think\Db::name("contacts")->field("id,username,email")->where("uid", $uid)->select()->toArray();
        if (empty($cid) && !empty($contact_list[0])) {
            $cid = $contact_list[0]["id"];
        }
        $returndata = [];
        $returndata["contact_list"] = $contact_list;
        if (!empty($cid)) {
            $contact_data = \think\Db::name("contacts")->field("password,create_time,update_time", true)->where("id", $cid)->where("uid", $uid)->find();
            $permissions = $contact_data["permissions"];
            if (!empty($permissions)) {
                $contact_data["permissions_arr"] = explode(",", $permissions);
            }
            $returndata["contact_data"] = $contact_data;
            $returndata["cid"] = $cid;
        }
        $returndata["permissions"] = config("contact_permissions");
        return json(["status" => 200, "data" => $returndata]);
    }
    public function save(\think\Request $request)
    {
        if ($request->isPost()) {
            $param = $request->param();
            $uid = $request->uid;
            $cid = $param["cid"];
            $rule = ["cid" => "number", "username" => "chsDash", "sex" => "in:0,1,2", "email" => "require|email", "postcode" => "number", "phonenumber" => "mobile", "generalemails" => "in:0,1", "invoiceemails" => "in:0,1", "productemails" => "in:0,1", "supportemails" => "in:0,1", "status" => "in:1,0,2", "permissions" => "array"];
            $msg = ["cid.number" => lang("CONTACTS_SAVE_VERIFY_CID_NUMBER"), "username.chsDash" => lang("CONTACTS_SAVE_VERIFY_UNAME_CHADASH"), "sex.in" => lang("CONTACTS_SAVE_VERIFY_SEX_IN"), "email.require" => lang("CONTACTS_SAVE_EMAIL_REQUIRE"), "email.email" => lang("CONTACTS_SAVE_EMAIL_EMAIL"), "postcode.number" => lang("CONTACTS_SAVE_POSTCODE_NUMBER"), "phonenumber.mobile" => lang("CONTACTS_SAVE_PNUM_MOBILE")];
            $validate = new \think\Validate($rule, $msg);
            $result = $validate->check($param);
            if (!$result) {
                return json(["status" => 400, "msg" => $validate->getError()]);
            }
            $user_data = \think\Db::name("clients")->field("id,username")->find($uid);
            if (empty($user_data)) {
                return json(["status" => 400, "msg" => "用户id错误"]);
            }
            $udata = [];
            $udata = ["uid" => $uid, "username" => $param["username"] ?: "", "sex" => $param["sex"] ?: 0, "avatar" => $param["avatar"] ?: "", "companyname" => $param["companyname"] ?: "", "email" => $param["email"], "wechat_id" => $param["wechat_id"], "country" => $param["country"] ?: "", "province" => $param["province"] ?: "", "city" => $param["city"] ?: "", "region" => $param["region"] ?: "", "address1" => $param["address1"] ?: "", "address2" => $param["address2"] ?: "", "postcode" => $param["postcode"] ?: 0, "phonenumber" => $param["phonenumber"] ?: "", "generalemails" => $param["generalemails"] ?: 0, "invoiceemails" => $param["invoiceemails"] ?: 0, "productemails" => $param["productemails"] ?: 0, "supportemails" => $param["supportemails"] ?: 0, "status" => $param["status"] ?: 0];
            $permissions = $param["permissions"];
            if (is_array($permissions) && !empty($permissions)) {
                $udata["permissions"] = implode(",", $permissions);
            }
            if (!empty($param["password"])) {
                $udata["password"] = cmf_password($param["password"]);
            }
            if (!empty($cid)) {
                $contact_exists = \think\Db::name("contacts")->where("email", $param["email"])->find();
                $client_exists = \think\Db::name("clients")->where("email", $param["email"])->find();
                if (!empty($contact_exists) && $contact_exists["id"] != $cid) {
                    return json(["status" => 400, "msg" => lang("CONTACTS_EMAIL_IS_EXISTS")]);
                }
                if (!empty($client_exists)) {
                    return json(["status" => 400, "msg" => lang("CONTACTS_EMAIL_IS_EXISTS")]);
                }
                $udata["update_time"] = time();
                \think\Db::name("contacts")->where("id", $cid)->update($udata);
            } else {
                $contact_exists = \think\Db::name("contacts")->where("email", $param["email"])->find();
                $client_exists = \think\Db::name("clients")->where("email", $param["email"])->find();
                if (!empty($contact_exists) || !empty($client_exists)) {
                    return json(["status" => 400, "msg" => lang("CONTACTS_EMAIL_IS_EXISTS")]);
                }
                $udata["create_time"] = time();
                $iid = \think\Db::name("contacts")->insertGetId($udata);
                if ($request->subaccountid) {
                    active_log("添加联系人 - Contacts ID:" . $iid);
                } else {
                    active_log("添加联系人 - Contacts ID:" . $iid);
                }
            }
            return json(["status" => 200, "msg" => lang("ADD SUCCESS")]);
        }
    }
    public function delete(\think\Request $request)
    {
        $param = $request->param();
        $uid = $request->uid;
        $cid = $param["cid"];
        if (empty($uid)) {
            return json(["status" => 400, "msg" => lang("CONTACTS_USER_NOT_FOUND")]);
        }
        if (empty($cid)) {
            return json(["status" => 400, "msg" => lang("CONTACTS_SON_USER_NOT_FOUND")]);
        }
        $contact_data = \think\Db::name("contacts")->where("id", $cid)->where("uid", $uid)->find();
        if (empty($contact_data)) {
            return json(["status" => 400, "msg" => lang("CONTACTS_SON_USER_NOT_FOUND")]);
        }
        \think\Db::name("contacts")->where("id", $cid)->where("uid", $uid)->delete();
        active_log("删除联系人 - Contacts ID:" . $cid);
        return json(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
}

?>