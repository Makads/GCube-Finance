<?php


namespace app\home\controller;

/**
 * @title 发票管理
 * @description 接口说明：发票管理
 */
class VoucherController extends CommonController
{
    private $type = ["person" => "个人", "company" => "公司"];
    private $voucher_type = ["common" => "增值税普通发票", "dedicated" => "增值税专用发票"];
    public function getAreaList()
    {
        $areas = \think\Db::name("areas")->field("area_id,pid,name")->where("show", 1)->where("data_flag", 1)->select()->toArray();
        $areas = getStructuredTree($areas);
        $data = ["areas" => $areas];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getCurrency()
    {
        $uid = request()->uid;
        $data = ["currency" => getUserCurrency($uid)];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherList()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "a.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $voucher_status = config("voucher_status");
        $voucher = \think\Db::name("voucher")->alias("a")->field("a.id,a.invoice_id,a.create_time,b.title,b.issue_type,b.issue_type as issue_type_zh,e.subtotal as amount,a.status,a.status as status_zh,c.province,c.city,c.region,c.detail,d.name,a.notes")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->leftJoin("invoices e", "a.invoice_id = e.id")->where("a.uid", $uid)->withAttr("issue_type_zh", function ($value, $data) {
            return $this->type[$value];
        })->withAttr("status_zh", function ($value, $data) {
            return $voucher_status[$value];
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $total = \think\Db::name("voucher")->alias("a")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->where("a.uid", $uid)->count();
        foreach ($voucher as &$v) {
            $invoice_ids = \think\Db::name("voucher_invoices")->where("voucher_id", $v["id"])->column("invoice_id");
            $invoice_ids = array_unique($invoice_ids);
            $invoices_subtotal = \think\Db::name("invoices")->whereIn("id", $invoice_ids)->where("uid", $uid)->where("delete_time", 0)->sum("subtotal");
            $v["invoices_subtotal"] = $invoices_subtotal;
        }
        $data = ["voucher" => $voucher, "total" => $total];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherDetail()
    {
        $param = $this->request->param();
        $id = intval($param["id"]);
        $uid = request()->uid;
        $type = config("invoice_type");
        unset($type["recharge"]);
        unset($type["combine"]);
        unset($type["voucher"]);
        unset($type["express"]);
        $type = array_keys($type);
        $taxed = 0 < configuration("voucher_rate") ? floatval(configuration("voucher_rate")) : 0;
        $voucher_status = config("voucher_status");
        $voucher = \think\Db::name("voucher")->alias("a")->field("a.id,a.create_time,b.title,b.issue_type,b.issue_type as issue_type_zh,a.amount,a.status,a.status as status_zh,c.province,c.city,c.region,c.detail,d.name,a.notes,d.price")->leftJoin("voucher_type b", "a.type_id = b.id")->leftJoin("voucher_post c", "a.post_id = c.id")->leftJoin("express d", "a.express_id = d.id")->withAttr("issue_type_zh", function ($value, $data) {
            return $this->type[$value];
        })->withAttr("status_zh", function ($value, $data) {
            return $voucher_status[$value];
        })->where("a.id", $id)->where("a.uid", $uid)->find();
        $invoice_ids = \think\Db::name("voucher_invoices")->where("voucher_id", $id)->column("invoice_id");
        $invoice_ids = array_unique($invoice_ids);
        $invoices = \think\Db::name("invoices")->field("id,tax as taxed,subtotal,subtotal as taxed_amount")->withAttr("taxed", function ($value, $data) use($taxed) {
            return $taxed . "%";
        })->withAttr("taxed_amount", function ($value, $data) use($taxed) {
            return $taxed / 100 * $data["subtotal"];
        })->whereIn("id", $invoice_ids)->where("uid", $uid)->where("delete_time", 0)->select()->toArray();
        $voucher_amount = 0;
        foreach ($invoices as &$invoice) {
            $voucher_amount += $invoice["taxed_amount"];
            $invoice["taxed_amount"] = round($invoice["taxed_amount"], 2);
            $items = \think\Db::name("invoice_items")->field("id,description")->where("invoice_id", $invoice["id"])->whereIn("type", $type)->select()->toArray();
            $invoice["items"] = $items;
        }
        $data = ["voucher" => $voucher, "invoices" => $invoices, "voucher_amount" => bcsub($voucher_amount, 0, 2)];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherRequest()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $keywords = $params["keywords"];
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $type = config("invoice_type_all");
        unset($type["recharge"]);
        unset($type["combine"]);
        unset($type["voucher"]);
        $where = function (\think\db\Query $query) use($uid, $type, $keywords) {
            $query->where("uid", $uid)->where("delete_time", 0)->where("status", "Paid")->whereIn("type", array_keys($type));
            if ($keywords) {
                $query->where("id", $keywords);
            }
        };
        $invoices = \think\Db::name("invoices")->field("id,subtotal,type,type as type_zh,paid_time")->withAttr("type_zh", function ($value, $data) {
            return $type[$value];
        })->where($where)->order($order, $sort)->select()->toArray();
        $total = \think\Db::name("invoices")->where($where)->count();
        $invoice_filter = [];
        $i = 0;
        foreach ($invoices as $invoice) {
            $voucher_ids = \think\Db::name("voucher_invoices")->where("invoice_id", $invoice["id"])->column("voucher_id");
            $count = \think\Db::name("voucher")->whereIn("id", $voucher_ids)->where("status", "<>", "Reject")->find();
            if (!empty($count)) {
                $i++;
            } else {
                $invoice_filter[] = $invoice;
            }
        }
        $invoice_filter = array_slice($invoice_filter, ($page - 1) * $limit, $limit);
        $data = ["invoices" => $invoice_filter, "total" => $total - $i];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getIssueVoucher()
    {
        $param = $this->request->param();
        $uid = request()->uid;
        $invoice_ids = $param["invoice_ids"];
        if (!is_array($invoice_ids)) {
            $invoice_ids = [$invoice_ids];
        }
        $express = \think\Db::name("express")->field("id,name,price")->select()->toArray();
        $post = \think\Db::name("voucher_post")->field("id,province,city,region,default")->where("uid", $uid)->select()->toArray();
        $title = \think\Db::name("voucher_type")->field("id,title,issue_type")->where("uid", $uid)->select()->toArray();
        $title_filter = [];
        foreach ($title as $v) {
            $title_filter[$v["issue_type"]][] = $v;
        }
        $taxed = 0 < configuration("voucher_rate") ? floatval(configuration("voucher_rate")) : 0;
        $invoices = \think\Db::name("invoices")->field("id,tax as taxed,subtotal,subtotal as taxed_amount,type")->withAttr("taxed", function ($value, $data) use($taxed) {
            return $taxed . "%";
        })->withAttr("taxed_amount", function ($value, $data) use($taxed) {
            return $taxed / 100 * $data["subtotal"];
        })->whereIn("id", $invoice_ids)->where("uid", $uid)->where("delete_time", 0)->select()->toArray();
        $type = config("invoice_type");
        unset($type["recharge"]);
        unset($type["combine"]);
        unset($type["voucher"]);
        unset($type["express"]);
        $type = array_keys($type);
        $voucher_amount = 0;
        foreach ($invoices as &$invoice) {
            $voucher_amount += $invoice["taxed_amount"];
            $invoice["taxed_amount"] = round($invoice["taxed_amount"], 2);
            $items = \think\Db::name("invoice_items")->field("id,description")->where("invoice_id", $invoice["id"])->whereIn("type", $type)->withAttr("description", function ($value) {
                return str_replace("|", " ", $value);
            })->select()->toArray();
            $invoice["items"] = $items;
        }
        $data = ["type" => $this->type, "express" => $express, "post" => $post, "title" => $title_filter, "invoices" => $invoices, "voucher_amount" => bcsub($voucher_amount, 0, 2)];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postIssueVoucher()
    {
        $uid = request()->uid;
        $param = $this->request->param();
        $type_id = intval($param["type_id"]);
        $tmp1 = \think\Db::name("voucher_type")->where("uid", $uid)->where("id", $type_id)->find();
        if (empty($tmp1)) {
            return jsons(["status" => 400, "msg" => "抬头信息错误"]);
        }
        $post_id = intval($param["post_id"]);
        $tmp2 = \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $post_id)->find();
        if (empty($tmp2)) {
            return jsons(["status" => 400, "msg" => "邮寄地址错误"]);
        }
        $express_id = intval($param["express_id"]);
        $tmp3 = \think\Db::name("express")->where("id", $express_id)->find();
        if (empty($tmp3)) {
            return jsons(["status" => 400, "msg" => "快递信息错误"]);
        }
        $invoice_ids = $param["invoice_ids"];
        if (!is_array($invoice_ids)) {
            $invoice_ids = [$invoice_ids];
        }
        $invoice_ids = array_unique($invoice_ids);
        \think\Db::startTrans();
        try {
            $total = 0;
            $invoice_items = [];
            $taxed = 0 < configuration("voucher_rate") ? floatval(configuration("voucher_rate")) : 0;
            $payment = \think\Db::name("clients")->where("id", $uid)->value("defaultgateway");
            $amount = \think\Db::name("invoices")->whereIn("id", $invoice_ids)->sum("subtotal");
            $voucher_price = $amount * $taxed / 100;
            $total += $voucher_price;
            $total += $tmp3["price"];
            $total = 0 < $total ? floatval($total) : 0;
            $invoice_data = ["uid" => $uid, "create_time" => time(), "subtotal" => $total, "credit" => 0, "total" => $total, "status" => "Unpaid", "payment" => $payment, "type" => "voucher", "url" => "invoicelist"];
            $invoice_id = \think\Db::name("invoices")->insertGetId($invoice_data);
            $voucher_data = ["uid" => $uid, "invoice_id" => $invoice_id, "post_id" => $post_id, "type_id" => $type_id, "express_id" => $express_id, "amount" => $total, "create_time" => time(), "check_time" => 0, "update_time" => 0, "status" => "Unpaid", "notes" => ""];
            $voucher_id = \think\Db::name("voucher")->insertGetId($voucher_data);
            $links = [];
            foreach ($invoice_ids as $v) {
                $links[] = ["voucher_id" => $voucher_id, "invoice_id" => $v];
            }
            \think\Db::name("voucher_invoices")->insertAll($links);
            $invoice_items[] = ["uid" => $uid, "type" => "voucher", "rel_id" => $voucher_id, "description" => "开具发票税费", "amount" => $voucher_price, "payment" => $payment];
            $invoice_items[] = ["uid" => $uid, "type" => "express", "rel_id" => $voucher_id, "description" => "发票快递费", "amount" => $tmp3["price"], "payment" => $payment];
            foreach ($invoice_items as &$vvv) {
                $vvv["invoice_id"] = $invoice_id;
            }
            \think\Db::name("invoice_items")->insertAll($invoice_items);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsons(["status" => 400, "msg" => lang("FAIL MESSAGE") . $e->getMessage()]);
        }
        $data = ["invoice_id" => $invoice_id];
        if ($total == 0) {
            \think\Db::name("invoices")->where("id", $invoice_id)->update(["status" => "Paid", "paid_time" => time(), "update_time" => time()]);
            $invoice_logic = new \app\common\logic\Invoices();
            $invoice_logic->processPaidInvoice($invoice_id);
            return jsons(["status" => 1001, "msg" => lang("BUY_SUCCESS")]);
        }
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherInfoList()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $voucher_type = \think\Db::name("voucher_type")->field("id,title,issue_type,issue_type as issue_type_zh,voucher_type,voucher_type as voucher_type_zh,tax_id")->where("uid", $uid)->withAttr("issue_type_zh", function ($value, $data) {
            return $this->type[$value];
        })->withAttr("voucher_type_zh", function ($value, $data) {
            return $this->voucher_type[$value];
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $total = \think\Db::name("voucher_type")->where("uid", $uid)->count();
        $data = ["voucher_type" => $voucher_type ?: [], "total" => $total];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherInfo()
    {
        $param = $this->request->param();
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("voucher_type")->field("id,title,issue_type,voucher_type,tax_id,bank,account,address,phone")->where("id", $id)->find();
            if (empty($tmp)) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
        }
        $data = ["issue_type" => $this->type, "voucher_type" => $this->voucher_type, "voucher_info" => $tmp ?: []];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postVoucherInfo()
    {
        $uid = request()->uid;
        $param = $this->request->only(["id", "issue_type", "title", "voucher_type", "tax_id", "bank", "account", "address", "phone"]);
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("voucher_type")->where("id", $id)->find();
            if (empty($tmp)) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
        }
        $validate = new \app\home\validate\VoucherValidate();
        if ($param["issue_type"] == "person") {
            if (!$validate->scene("voucher_info_person")->check($param)) {
                return jsons(["status" => 400, "msg" => $validate->getError()]);
            }
            $data = ["uid" => $uid, "title" => $param["title"], "issue_type" => $param["issue_type"]];
        } else {
            if (!$validate->scene("voucher_info_company")->check($param)) {
                return jsons(["status" => 400, "msg" => $validate->getError()]);
            }
            $data = ["uid" => $uid, "title" => $param["title"], "issue_type" => $param["issue_type"], "voucher_type" => $param["voucher_type"], "tax_id" => $param["tax_id"], "bank" => $param["bank"], "account" => $param["account"], "address" => $param["address"], "phone" => $param["phone"]];
        }
        if ($id) {
            $data["update_time"] = time();
            $res = \think\Db::name("voucher_type")->where("id", $id)->update($data);
        } else {
            $data["create_time"] = time();
            $res = \think\Db::name("voucher_type")->insert($data);
        }
        if ($res) {
            return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsons(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
    }
    public function deleteVoucherInfo()
    {
        $uid = request()->uid;
        $param = $this->request->param();
        $id = intval($param["id"]);
        $tmp = \think\Db::name("voucher_type")->where("uid", $uid)->where("id", $id)->find();
        if (empty($tmp)) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $count = \think\Db::name("voucher")->where("uid", $uid)->where("type_id", $id)->count();
        if (0 < $count) {
            return jsons(["status" => 400, "msg" => lang("发票信息已被使用,不可删除")]);
        }
        \think\Db::name("voucher_type")->where("uid", $uid)->where("id", $id)->delete();
        return jsons(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function getVoucherPostList()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $voucher_post = \think\Db::name("voucher_post")->field("id,username,phone,province,city,region,detail,post,default")->where("uid", $uid)->order("default", "desc")->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $total = \think\Db::name("voucher_post")->where("uid", $uid)->count();
        $data = ["voucher_post" => $voucher_post, "total" => $total];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getVoucherPost()
    {
        $uid = request()->uid;
        $param = $this->request->param();
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("voucher_post")->field("id,username,province,city,region,detail,post,default,phone")->where("uid", $uid)->where("id", $id)->find();
            if (empty($tmp)) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
        }
        $data = ["voucher_post" => $tmp ?: []];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function postVoucherPost()
    {
        $uid = request()->uid;
        $param = $this->request->param();
        if (isset($param["id"])) {
            $id = intval($param["id"]);
            $tmp = \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $id)->find();
            if (empty($tmp)) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
        } else {
            $tmp = ["username" => $param["username"], "province" => $param["province"], "city" => $param["city"], "region" => $param["region"], "detail" => $param["detail"], "phone" => $param["phone"], "post" => $param["post"], "default" => $param["default"]];
            $repeat = serialize($tmp);
            if ($repeat == cache("voucher_address_post_" . $uid)) {
                return jsons(["status" => 200, "msg" => "请求成功"]);
            }
            if (!cache("voucher_address_post_" . $uid)) {
                cache("voucher_address_post_" . $uid, $repeat, 10);
            }
        }
        $validate = new \app\home\validate\VoucherValidate();
        if (!$validate->scene("voucher_post")->check($param)) {
            return jsons(["status" => 400, "msg" => $validate->getError()]);
        }
        $default = \think\Db::name("voucher_post")->where(function (\think\db\Query $query) {
            if ($id) {
                $query->where("id", "<>", $id);
            }
        })->where("default", 1)->where("uid", $uid)->find();
        $data = ["uid" => $uid, "username" => $param["username"], "province" => $param["province"], "city" => $param["city"], "region" => $param["region"], "detail" => $param["detail"], "phone" => $param["phone"], "post" => $param["post"], "default" => $param["default"]];
        if ($id) {
            $data["update_time"] = time();
            $res = \think\Db::name("voucher_post")->where("id", $id)->update($data);
        } else {
            $data["create_time"] = time();
            $res = \think\Db::name("voucher_post")->insert($data);
        }
        if ($res) {
            if (!empty($default) && $param["default"] == 1) {
                \think\Db::name("voucher_post")->where("id", $default["id"])->update(["default" => 0, "update_time" => time()]);
            }
            return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
        }
        return jsons(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
    }
    public function deleteVoucherPost()
    {
        $param = $this->request->param();
        $uid = request()->uid;
        $id = intval($param["id"]);
        $tmp = \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $id)->find();
        if (empty($tmp)) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $count = \think\Db::name("voucher")->where("uid", $uid)->where("post_id", $id)->count();
        if (0 < $count) {
            return jsons(["status" => 400, "msg" => lang("收货地址已被使用,不可删除")]);
        }
        \think\Db::name("voucher_post")->where("id", $id)->where("uid", $uid)->delete();
        $default = \think\Db::name("voucher_post")->where("uid", $uid)->where("default", 1)->find();
        if (empty($default)) {
            $new_default = \think\Db::name("voucher_post")->where("uid", $uid)->order("id", "desc")->find();
            \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $new_default["id"])->update(["default" => 1, "update_time" => time()]);
        }
        return jsons(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function postVoucherDefaultPost()
    {
        $param = $this->request->param();
        $uid = request()->uid;
        $id = intval($param["id"]);
        $tmp = \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $id)->find();
        if (empty($tmp)) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $default = intval($param["default"]);
        $exist = \think\Db::name("voucher_post")->where("id", "<>", $id)->where("default", 1)->where("uid", $uid)->find();
        if ($default == 1) {
            if (!empty($exist)) {
                \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $exist["id"])->update(["default" => 0, "update_time" => time()]);
            }
            \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $id)->update(["default" => 1, "update_time" => time()]);
        } else {
            if (!empty($exist)) {
                \think\Db::name("voucher_post")->where("uid", $uid)->where("id", $id)->update(["default" => 0, "update_time" => time()]);
            } else {
                return jsons(["status" => 400, "msg" => "至少一个默认地址,不可更改"]);
            }
        }
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
}

?>