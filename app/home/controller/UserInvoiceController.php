<?php

namespace app\home\controller;

/**
 * @title 消费记录
 */
class UserInvoiceController extends CommonController
{
    public function index()
    {
        $uid = request()->uid;
        $params = $this->request->only(["limit", "page", "order", "sort", "status", "keywords"]);
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $status = !empty($params["status"]) ? $params["status"] : "";
        $keywords = !empty($params["keywords"]) ? $params["keywords"] : "";
        $data = [];
        $client_currency = getUserCurrency($uid);
        $suffix = $client_currency["suffix"];
        $data["currency"] = $client_currency;
        $where = "1=1";
        if (isset($keywords[0])) {
            $arr = [];
            foreach (config("invoice_payment_status") as $k => $v) {
                if (strpos($v["name"], $keywords) !== false) {
                    $arr[] = "`status` = \"" . $k . "\"";
                }
            }
            $tmp = ["renew" => "续费", "product" => "产品", "recharge" => "充值", "setup" => "初装费", "upgrade" => "升降级", "discount" => "客户折扣", "credit_limit" => "信用额"];
            foreach ($tmp as $k => $v) {
                if (strpos($v, $keywords) !== false) {
                    $arr[] = "`type` = \"" . $k . "\"";
                }
            }
            unset($tmp);
            $arr[] = "`id` like \"%" . $keywords . "%\"";
            $arr[] = "`paid_time` like \"%" . $keywords . "%\"";
            $arr[] = "`total` like \"%" . $keywords . "%\"";
            $where = implode(" OR ", $arr);
        }
        $gateways = gateway_list();
        $count = \think\Db::name("invoices")->field("id,paid_time,status,payment,subtotal as total,type as invoice_type,subtotal as sub,credit")->where("delete_time", 0)->where("is_delete", 0)->where("uid", $uid)->where(function (\think\db\Query $query) {
            $query->where("type", "product")->whereOr("type", "upgrade");
        })->where(function (\think\db\Query $query) {
            if (!empty($status)) {
                $query->where("status", $status);
            }
        })->whereRaw($where)->count();
        $data["count"] = $count;
        $invoices = \think\Db::name("invoices")->field("id,paid_time,status,payment,subtotal as total,type as invoice_type,subtotal as sub,credit,use_credit_limit")->where("delete_time", 0)->where("is_delete", 0)->where("uid", $uid)->where(function (\think\db\Query $query) {
            $query->where("type", "product")->whereOr("type", "upgrade");
        })->where(function (\think\db\Query $query) {
            if (!empty($status)) {
                $query->where("status", $status);
            }
        })->whereRaw($where)->order($order, $sort)->order("id", "desc")->limit($limit)->page($page)->select()->toArray();
        foreach ($invoices as $k => $invoice) {
            if ($invoice["status"] == "Paid") {
                if ($invoice["use_credit_limit"] == 1) {
                    return "信用额";
                }
                if ($invoice["sub"] == $invoice["credit"]) {
                    $invoices[$k]["payment_zh"] = "余额支付";
                } else {
                    if ($invoice["credit"] < $invoice["sub"] && 0 < $invoice["credit"]) {
                        foreach ($gateways as $v) {
                            if ($v["name"] == $invoice["payment"]) {
                                $invoices[$k]["payment_zh"] = "部分余额支付+" . $v["title"];
                            }
                        }
                    } else {
                        foreach ($gateways as $v) {
                            if ($v["name"] == $invoice["payment"]) {
                                $invoices[$k]["payment_zh"] = $v["title"];
                            }
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $invoice["payment"]) {
                        $invoices[$k]["payment_zh"] = $v["title"];
                    }
                }
            }
            $invoices[$k]["status_zh"] = config("invoice_payment_status")[$invoice["status"]];
            $developer_app_product_type = array_keys(config("developer_app_product_type"));
            if ($invoice["invoice_type"] == "upgrade") {
                $items = \think\Db::name("invoice_items")->alias("a")->field("a.id,c.id as hostid,a.invoice_id,d.name,c.domain,a.amount as total,c.create_time as paid_time,a.type,a.description as status,a.payment as payment_zh,c.dedicatedip,c.regdate,c.nextduedate,d.type as product_type")->join("upgrades b", "a.rel_id = b.id")->join("host c", "b.relid = c.id")->leftJoin("products d", "c.productid = d.id")->where("a.delete_time", 0)->where("a.type", "upgrade")->where("a.rel_id", "<>", 0)->where("a.invoice_id", $invoice["id"])->whereNotIn("d.type", $developer_app_product_type)->withAttr("id", function ($value) use($invoice) {
                    return $invoice["id"] . "-" . $value;
                })->withAttr("invoice_id", function ($value) {
                    return "items";
                })->withAttr("status", function ($value) use($invoice) {
                    return $invoice["status"];
                })->withAttr("paid_time", function ($value) use($invoice) {
                    return $invoice["paid_time"];
                })->withAttr("payment_zh", function ($value) use($invoice, $gateways) {
                    if ($invoice["status"] == "Paid") {
                        if ($invoice["use_credit_limit"] == 1) {
                            return "信用额";
                        }
                        if ($invoice["sub"] == $invoice["credit"]) {
                            return "余额支付";
                        }
                        if ($invoice["credit"] < $invoice["sub"] && 0 < $invoice["credit"]) {
                            foreach ($gateways as $v) {
                                if ($v["name"] == $value) {
                                    return "部分余额支付+" . $v["title"];
                                }
                            }
                        } else {
                            foreach ($gateways as $v) {
                                if ($v["name"] == $value) {
                                    return $v["title"];
                                }
                            }
                        }
                    } else {
                        foreach ($gateways as $v) {
                            if ($v["name"] == $value) {
                                return $v["title"];
                            }
                        }
                    }
                })->withAttr("nextduedate", function ($value, $data) {
                    if ($data["type"] == "setup" || $data["type"] == "promo") {
                        return 0;
                    }
                    return $value;
                })->withAttr("type", function ($value) {
                    if ($value == "host") {
                        return "产品";
                    }
                    if ($value == "upgrade") {
                        return "升降级";
                    }
                    if ($value == "recharge") {
                        return "充值";
                    }
                    if ($value == "discount") {
                        return "客户折扣";
                    }
                    if ($value == "renew") {
                        return "续费";
                    }
                    if ($value == "zjmf_reinstall_times") {
                        return "重装";
                    }
                    if ($value == "zjmf_flow_packet") {
                        return "流量包";
                    }
                    if ($value == "setup") {
                        return "初装费";
                    }
                    if ($value == "promo") {
                        return "优惠码";
                    }
                    if ($value == "contract") {
                        return "合同邮费";
                    }
                    return "";
                })->order("a.id", "desc")->select()->toArray();
            } else {
                $items = \think\Db::name("invoice_items")->alias("a")->field("a.id,b.id as hostid,a.invoice_id,c.name,b.domain,a.amount as total,b.create_time as paid_time,a.type,a.description as status,a.payment as payment_zh,b.dedicatedip,b.regdate,b.nextduedate,c.type as product_type")->join("host b", "b.id = a.rel_id")->leftJoin("products c", "b.productid = c.id")->where("a.delete_time", 0)->where("a.type", "<>", "upgrade")->where("a.rel_id", "<>", 0)->where("a.invoice_id", $invoice["id"])->whereNotIn("c.type", $developer_app_product_type)->withAttr("id", function ($value) use($invoice) {
                    return $invoice["id"] . "-" . $value;
                })->withAttr("invoice_id", function ($value) {
                    return "items";
                })->withAttr("status", function ($value) use($invoice) {
                    return $invoice["status"];
                })->withAttr("paid_time", function ($value) use($invoice) {
                    return $invoice["paid_time"];
                })->withAttr("payment_zh", function ($value) use($invoice, $gateways) {
                    if ($invoice["status"] == "Paid") {
                        if ($invoice["use_credit_limit"] == 1) {
                            return "信用额";
                        }
                        if ($invoice["sub"] == $invoice["credit"]) {
                            return "余额支付";
                        }
                        if ($invoice["credit"] < $invoice["sub"] && 0 < $invoice["credit"]) {
                            foreach ($gateways as $v) {
                                if ($v["name"] == $value) {
                                    return "部分余额支付+" . $v["title"];
                                }
                            }
                        } else {
                            foreach ($gateways as $v) {
                                if ($v["name"] == $value) {
                                    return $v["title"];
                                }
                            }
                        }
                    } else {
                        foreach ($gateways as $v) {
                            if ($v["name"] == $value) {
                                return $v["title"];
                            }
                        }
                    }
                })->withAttr("nextduedate", function ($value, $data) {
                    if ($data["type"] == "setup" || $data["type"] == "promo") {
                        return 0;
                    }
                    return $value;
                })->withAttr("type", function ($value) {
                    if ($value == "host") {
                        return "产品";
                    }
                    if ($value == "upgrade") {
                        return "升降级";
                    }
                    if ($value == "recharge") {
                        return "充值";
                    }
                    if ($value == "renew") {
                        return "续费";
                    }
                    if ($value == "discount") {
                        return "客户折扣";
                    }
                    if ($value == "zjmf_reinstall_times") {
                        return "重装";
                    }
                    if ($value == "zjmf_flow_packet") {
                        return "流量包";
                    }
                    if ($value == "setup") {
                        return "初装费";
                    }
                    if ($value == "promo") {
                        return "优惠码";
                    }
                    if ($value == "credit_limit") {
                        return "信用额";
                    }
                    if ($value == "contract") {
                        return "合同邮费";
                    }
                    return "";
                })->order("a.id", "desc")->select()->toArray();
            }
            if (!empty($items)) {
                $invoices[$k]["items"] = $items;
            } else {
                unset($invoices[$k]);
            }
        }
        $invoices = array_values($invoices);
        $data["invoices"] = $invoices;
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function deleteOrder()
    {
        $params = $this->request->param();
        $uid = $this->request->uid;
        $id = $params["id"];
        if (!$id) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $invoice = \think\Db::name("invoices")->where("delete_time", 0)->where("id", $id)->find();
        if ($invoice["type"] == "credit_limit") {
            return jsons(["status" => 400, "msg" => lang("信用额账单,不可删除!")]);
        }
        if ($invoice["status"] != "Unpaid") {
            return jsons(["status" => 400, "msg" => lang("非未支付账单,不可删除!")]);
        }
        $hosts = \think\Db::name("host")->alias("a")->field("a.id,a.domainstatus")->leftJoin("orders b", "a.orderid = b.id")->where("b.invoiceid", $id)->where("b.delete_time", 0)->select()->toArray();
        foreach ($hosts as $v) {
            if ($v["domainstatus"] != "Pending") {
                return jsons(["status" => 400, "msg" => "产品非待开通,不可删除"]);
            }
        }
        $hids = array_column($hosts, "id");
        \think\Db::startTrans();
        try {
            hook("product_divert_delete", ["id" => $id]);
            \think\Db::name("invoices")->where("delete_time", 0)->where("uid", $uid)->where("id", $id)->update(["status" => "Cancelled", "update_time" => time()]);
            $order = \think\Db::name("orders")->where("uid", $uid)->where("delete_time", 0)->where("invoiceid", $id)->find();
            $order_id = $order["id"];
            \think\Db::name("orders")->where("uid", $uid)->where("id", $order_id)->where("delete_time", 0)->update(["status" => "Cancelled", "update_time" => time()]);
            \think\Db::name("host")->whereIn("id", $hids)->update(["domainstatus" => "Cancelled", "update_time" => time()]);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsons(["status" => 400, "msg" => lang("DELETE FAIL")]);
        }
        active_logs(sprintf($this->lang["UserInvoice_home_deleteOrder_success"], $id, $order_id), $uid);
        hook("invoice_mark_cancelled", ["invoiceid" => $id]);
        return jsons(["status" => 200, "msg" => lang("DELETE SUCCESS")]);
    }
    public function create()
    {
        $uid = input("uid");
        $size = input("size/d") ?? config("page_size");
        $page = input("page/d") ?? config("page_num");
        $field = input("page", "", "htmlspecialchars");
        $sort = input("page/d", "", "htmlspecialchars");
        $status = input("status", "", "htmlspecialchars");
        $data = ["page" => $page, "size" => $size, "field" => $field, "sort" => $sort, "status" => $status];
        $validate = new \app\home\validate\UserInvoiceValidate();
        if (!$validate->check($data)) {
            return jsons($validate->getError(), 400);
        }
        $where = ["uid" => $uid];
        if (input("?status")) {
            $where["i.status"] = input("status", "", "htmlspecialchars");
        }
        $rows = db("invoice_items")->where($where)->field("id,create_time,due_time,description,amount,type,rel_id,")->withAttr("type", function ($value, $data) {
            if ($value == "recharge") {
                return "充值";
            }
            if ($value == "product") {
                return "购买产品";
            }
            if ($value == "renew") {
                return "续费产品";
            }
        })->page($page, $size)->order($field, $sort)->select();
        $count = $rows->count();
        $page = ["page" => $page, "size" => $size, "total" => $count, "total_page" => ceil($count / $size)];
        return jsons(["data" => $rows, "page" => $page, "status" => 200, "msg" => "ok"]);
    }
    public function read($id)
    {
        $uid = input("uid/d");
        $where = ["i.uid" => $uid, "i.id" => $id];
        $row = db("invoices")->alias("i")->join("clients c", "c.id=i.uid")->field("i.id,i.uid,c.username,i.create_time,i.due_time,i.payment,i.credit,i.subtotal,i.status,i.payment,c.username,c.country,c.province,c.city")->where($where)->find();
        if (empty($row)) {
            return jsons(["status" => 400, "msg" => "不存在的订单!"], 400);
        }
        $host = db("invoice_items")->field("id,rel_id,type,description,amount")->where("invoice_id", $row["id"])->select();
        $hosts = [];
        foreach ($host as $k => $v) {
            if (isset($hosts[$v["rel_id"]])) {
                array_push($hosts[$v["rel_id"]], $v);
            } else {
                $hosts[$v["rel_id"]][] = $v;
            }
        }
        $rows = ["invoices" => $row, "host" => $hosts];
        return jsons(["data" => $rows, "status" => 200, "msg" => "ok"]);
    }
    public function sendPay()
    {
        $uid = $this->request->uid;
        $id = input("id/d");
        $where = ["id" => $id, "uid" => $uid];
        $row = db("invoices")->where($where)->find();
        if (empty($row)) {
            return jsons(["msg" => "error", "status" => 400]);
        }
        if ($row["status"] != "Unpaid") {
            return jsons(["msg" => "过期的请求", "status" => 400]);
        }
        $currency = getUserCurrency($uid)["code"];
        if ($row["status"] == "Unpaid") {
            $payData = ["out_trade_no" => $row["id"], "product_name" => "智简魔方", "total_fee" => $row["subtotal"], "attach" => $row["type"] ?? "product@" . $uid . "@" . $row["id"] . "@" . $row["subtotal"], "fee_type" => $currency];
            shook($row["payment"], $payData);
        }
    }
    public function getInvoices()
    {
        $uid = request()->uid;
        $params = $this->request->only(["limit", "page", "order", "sort", "status"]);
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $status = !empty($params["status"]) ? trim($params["status"]) : "";
        if (!in_array($order, ["id", "subtotal", "paid_time", "due_time", "status", "create_time", "type", "delete_time"])) {
            $order = "id";
        }
        $data = [];
        $client_currency = getUserCurrency($uid);
        $fun = function (\think\db\Query $query) use($uid, $status) {
            $invoice_type = config("invoice_type_all");
            $query->where("b.delete_time", 0)->where("b.is_delete", 0)->where("b.status", "<>", "Cancelled")->where("b.uid", $uid);
            if (!empty($status)) {
                $query->where("b.status", $status);
            }
        };
        $total = \think\Db::name("invoice_items")->alias("a")->leftJoin("invoices b", "b.id=a.invoice_id")->where($fun)->group("a.invoice_id")->count();
        $invoice_payment_status = config("invoice_payment_status");
        $gateways = gateway_list();
        $invoice_type = config("invoice_type_all");
        $invoices = \think\Db::name("invoice_items")->alias("a")->field("b.id,b.invoice_num,b.subtotal,b.type,b.paid_time,b.due_time,b.status,b.use_credit_limit,b.subtotal as sub,\n            b.payment,b.create_time as status_zh,b.payment as payment_zh,b.delete_time as type_zh,b.credit")->leftJoin("invoices b", "b.id=a.invoice_id")->where($fun)->withAttr("status_zh", function ($value, $data) use($invoice_payment_status) {
            return $invoice_payment_status[$data["status"]] ?: "";
        })->withAttr("payment_zh", function ($value, $data) use($gateways) {
            if ($data["status"] == "Paid") {
                if ($data["use_credit_limit"] == 1) {
                    return "信用额支付";
                }
                if ($data["sub"] == $data["credit"]) {
                    return "余额支付";
                }
                if ($data["credit"] < $data["sub"] && 0 < $data["credit"]) {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return "部分余额支付+" . $v["title"];
                        }
                    }
                } else {
                    foreach ($gateways as $v) {
                        if ($v["name"] == $value) {
                            return $v["title"];
                        }
                    }
                }
            } else {
                foreach ($gateways as $v) {
                    if ($v["name"] == $value) {
                        return $v["title"];
                    }
                }
            }
            return "";
        })->withAttr("type_zh", function ($value, $data) use($invoice_type) {
            return $invoice_type[$data["type"]] ?: "";
        })->order("b." . $order, $sort)->order("paid_time", "desc")->order("id", "desc")->group("a.invoice_id")->limit($limit)->page($page)->select()->toArray();
        $data["currency"] = $client_currency;
        $data["total"] = $total;
        $data["invoices"] = $invoices;
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getInvoicesDetail()
    {
        $items = config("invoice_type");
        $uid = request()->uid;
        $params = $this->request->param();
        $id = intval($params["id"]);
        $tmp = \think\Db::name("invoices")->where("uid", $uid)->where("id", $id)->where("delete_time", 0)->where("is_delete", 0)->find();
        if (empty($tmp)) {
            return jsons(["status" => 400, "msg" => "账单不存在"]);
        }
        $invoice_payment_status = config("invoice_payment_status");
        $gateways = gateway_list();
        $detail = \think\Db::name("invoices")->alias("a")->field("b.username,b.companyname,a.status,a.status as status_zh,a.paid_time,a.payment,a.payment as payment_zh,a.subtotal,a.total,a.credit,a.id,a.url,b.phonenumber,a.create_time,a.use_credit_limit")->leftJoin("clients b", "a.uid = b.id")->where("a.id", $id)->where("a.uid", $uid)->withAttr("status_zh", function ($value, $data) use($invoice_payment_status) {
            return $invoice_payment_status[$data["status"]] ?: "";
        })->withAttr("payment_zh", function ($value, $data) {
            foreach ($gateways as $v) {
                if ($v["name"] == $data["payment"]) {
                    return $v["title"];
                }
                if ($data["subtotal"] == $data["credit"] && ($data["status"] == "Paid" || $data["status"] == "Refunded")) {
                    return "余额支付";
                }
                if (1 == $data["use_credit_limit"] && ($data["status"] == "Paid" || $data["status"] == "Refunded")) {
                    return "信用额支付";
                }
            }
            return "";
        })->withAttr("credit", function ($value, $data) {
            return bcsub($data["subtotal"], $data["total"], 2);
        })->find();
        $invoice_items = \think\Db::name("invoice_items")->field("type,description,description2,amount,type as type_zh,rel_id")->where("invoice_id", $id)->where("uid", $uid)->withAttr("type_zh", function ($value, $data) use($items) {
            return $items[$data["type"]] ?: "";
        })->order("rel_id", "desc")->select()->toArray();
        $accounts = \think\Db::name("accounts")->alias("a")->field("a.id,a.pay_time,a.gateway,a.trans_id,a.amount_in,a.amount_out,a.fees")->withAttr("gateway", function ($value) {
            foreach ($gateways as $v) {
                if ($value == $v["name"]) {
                    return $v["title"];
                }
            }
            return $value;
        })->where("a.invoice_id", $id)->where("a.delete_time", 0)->select()->toArray();
        $credit_log_uses = \think\Db::name("credit")->field("id,create_time as pay_time,amount as amount_in,description as gateway")->withAttr("gateway", function ($value) {
            if ($value == "Credit Applied to Invoice #" . $id) {
                return lang("余额支付");
            }
            if ($value == "Credit Removed from Invoice #" . $id) {
                return lang("已移除余额");
            }
            if ($value == "Credit Applied to Renew Invoice #" . $id) {
                return lang("余额支付");
            }
        })->where("description", "like", "%Credit Applied to Invoice #" . $id)->whereOr("description", "like", "%Credit Removed from Invoice #" . $id)->whereOr("description", "like", "%Credit Applied to Renew Invoice #" . $id)->select()->toArray();
        foreach ($credit_log_uses as $k => $v) {
            $v["id"] = "";
            $v["trans_id"] = "";
            $v["fees"] = "";
            array_push($accounts, $v);
        }
        if (1 == $detail["use_credit_limit"] && ($detail["status"] == "Paid" || $detail["status"] == "Refunded")) {
            $credit_limit["id"] = "";
            $credit_limit["trans_id"] = "";
            $credit_limit["fees"] = "";
            $credit_limit["amount_in"] = $detail["subtotal"];
            $credit_limit["gateway"] = "信用额支付";
            $credit_limit["pay_time"] = $detail["paid_time"];
            array_push($accounts, $credit_limit);
        }
        foreach ($invoice_items as &$invoice_item) {
            $host = \think\Db::name("host")->alias("a")->field("a.id,b.groupid,a.productid")->leftJoin("products b", "a.productid = b.id")->where("a.id", $invoice_item["rel_id"])->find();
            $nav_val = (new \app\common\logic\Menu())->proGetNavId($host["productid"]);
            $invoice_item["groupid"] = $nav_val["id"] ?: 1;
            $invoice_item["hid"] = $host["id"] ?: 0;
            if ($invoice_item["type"] == "host") {
                $customfields = \think\Db::name("customfields")->where([["relid", "=", $host["productid"], ["type", "=", "product"]]])->select()->toArray();
                $tmp_arr = [];
                if (!empty($customfields[0])) {
                    foreach ($customfields as $v) {
                        if ($v["showorder"]) {
                            $customfieldsvalues = \think\Db::name("customfieldsvalues")->where([["fieldid", "=", $v["id"]], ["relid", "=", $host["id"]]])->value("value");
                            $tmp_arr[] = $v["fieldname"] . "=" . $customfieldsvalues;
                        }
                    }
                }
                if (!empty($invoice_item["description2"])) {
                    $invoice_item["description"] = $invoice_item["description2"] . "\n" . implode("\n", $tmp_arr);
                } else {
                    $invoice_item["description"] .= "\n" . implode("\n", $tmp_arr);
                }
            }
        }
        if ($invoice_items) {
            $host_ids_arr = array_filter($invoice_items, function ($v) {
                return $v["type"] == "host" ? true : false;
            });
            if ($host_ids_arr) {
                $host_ids = array_filter(array_column($host_ids_arr, "rel_id", "id"));
                $host_data = \think\Db::name("host")->whereIn("id", $host_ids)->select()->toArray();
                if ($host_data) {
                    $host_data = array_column($host_data, NULL, "id");
                    foreach ($invoice_items as $key => $m) {
                        if ($m["type"] == "host" && isset($host_data[$m["rel_id"]])) {
                            $new_des = explode("\n", $m["description"]);
                            $new_des_0 = explode("(", $new_des[0]);
                            $new_des[0] = $new_des_0[0] . "-" . $host_data[$m["rel_id"]]["domain"];
                            $new_des[0] .= $host_data[$m["rel_id"]]["dedicatedip"] ? "(" . $host_data[$m["rel_id"]]["dedicatedip"] . ")" : "";
                            if (!empty($new_des_0[1])) {
                                $new_des[0] .= "(" . $new_des_0[1];
                            }
                            $new_des = implode("\n", $new_des);
                            $invoice_items[$key]["description"] = $new_des;
                        }
                    }
                }
            }
        }
        $client_currency = getUserCurrency($uid);
        $data = ["payee" => configuration("company_name"), "detail" => $detail, "invoice_items" => $invoice_items, "currency" => $client_currency, "accounts" => $accounts ?? []];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function accountsRecord()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "a.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $where = function (\think\db\Query $query) use($uid) {
            $query->where("a.uid", $uid)->whereIn("b.type", ["renew", "product", "upgrade", "zjmf_reinstall_times", "zjmf_flow_packet", "combine"])->where("a.refund", 0)->where("a.invoice_id", ">", 0)->where("b.delete_time", 0);
        };
        $items = config("invoice_type_all");
        $gateways = gateway_list();
        $accounts = \think\Db::name("accounts")->alias("a")->leftJoin("invoices b", "a.invoice_id = b.id")->field("a.id,a.invoice_id,a.pay_time,a.gateway,a.gateway as payment_zh,a.description,b.type,b.type as type_zh,a.amount_in,a.trans_id")->where($where)->withAttr("type_zh", function ($value, $data) use($items) {
            if ($value == "combine") {
                return "订购产品";
            }
            return $items[$value] ?: "";
        })->withAttr("payment_zh", function ($value, $data) {
            foreach ($gateways as $v) {
                if ($v["name"] == $value) {
                    return $v["title"];
                }
            }
            return $value;
        })->order($order, $sort)->order("a.pay_time", "desc")->page($page)->limit($limit)->select()->toArray();
        foreach ($accounts as &$vv) {
            $refund = \think\Db::name("accounts")->field("id,amount_out")->where("refund", $vv["id"])->where("invoice_id", $vv["invoice_id"])->where("delete_time", 0)->where("refund", ">", 0)->select()->toArray();
            $vv["refund"] = $refund ?: (object) [];
        }
        $total = \think\Db::name("accounts")->alias("a")->leftJoin("invoices b", "a.invoice_id = b.id")->where($where)->count();
        $client_currency = getUserCurrency($uid);
        $data = ["total" => $total, "accounts" => $accounts, "currency" => $client_currency];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function creditRecord()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "a.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $where = function (\think\db\Query $query) {
            $query->where("a.description", "like", "%Credit Applied to Invoice #%")->whereOr("a.description", "like", "%Credit Applied to Renew Invoice #%");
        };
        $accounts = \think\Db::name("credit")->alias("a")->field("a.id,a.relid,a.create_time,a.description,a.description as type,a.amount")->where("a.uid", $uid)->where($where)->withAttr("description", function ($value, $data) {
            if (preg_match("/Credit Applied to Invoice #/", $value)) {
                return "余额支付";
            }
            if (preg_match("/Credit Applied to Renew Invoice #/", $value)) {
                return "余额支付";
            }
            return $value;
        })->withAttr("type", function ($value) {
            if (preg_match("/Credit Applied to Invoice #/", $value)) {
                return "订购产品";
            }
            if (preg_match("/Credit Applied to Renew Invoice #/", $value)) {
                return "续费";
            }
            return $value;
        })->order($order, $sort)->order("a.create_time", "desc")->page($page)->limit($limit)->select()->toArray();
        foreach ($accounts as &$vv) {
            $refund = \think\Db::name("credit")->alias("a")->field("a.id,a.amount")->where("a.uid", $uid)->where("a.relid", $vv["relid"])->where("a.description", "like", "%Credit Removed from Invoice #%")->select()->toArray();
            $vv["refund"] = $refund ?: (object) [];
        }
        $client_currency = getUserCurrency($uid);
        $total = \think\Db::name("credit")->alias("a")->where("a.uid", $uid)->where($where)->count();
        $data = ["total" => $total, "accounts" => $accounts, "currency" => $client_currency];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function creditLimitRecord()
    {
        $uid = request()->uid;
        $params = $this->request->only(["limit", "page", "order", "sort", "status"]);
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $status = !empty($params["status"]) ? trim($params["status"]) : "";
        $data = [];
        $client_currency = getUserCurrency($uid);
        $fun = function (\think\db\Query $query) use($uid, $status) {
            $query->where("delete_time", 0)->where("is_delete", 0)->where("use_credit_limit", 1)->where("type", "<>", "combine")->where("status", "<>", "Cancelled")->where("uid", $uid);
            if (!empty($status)) {
                $query->where("status", $status);
            }
        };
        $total = \think\Db::name("invoices")->where($fun)->count();
        $invoice_payment_status = config("invoice_payment_status");
        $gateways = gateway_list();
        $invoice_type = config("invoice_type_all");
        $accounts = \think\Db::name("invoices")->field("id,invoice_num,subtotal,type,paid_time,due_time,status,payment,create_time as status_zh,update_time as payment_zh,delete_time as type_zh")->where($fun)->withAttr("status_zh", function ($value, $data) use($invoice_payment_status) {
            return $invoice_payment_status[$data["status"]] ?: "";
        })->withAttr("payment_zh", function ($value, $data) {
            foreach ($gateways as $v) {
                if ($v["name"] == $data["payment"]) {
                    return $v["title"];
                }
            }
            return $value;
        })->withAttr("type_zh", function ($value, $data) use($invoice_type) {
            return $invoice_type[$data["type"]] ?: "";
        })->order($order, $sort)->order("paid_time", "desc")->order("id", "desc")->limit($limit)->page($page)->select()->toArray();
        $data = ["total" => $total, "accounts" => $accounts, "currency" => $client_currency];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function rechargeRecord()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "a.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $where = function (\think\db\Query $query) use($uid) {
            $query->where("a.uid", $uid)->where("a.delete_time", 0)->where("b.type", "recharge");
        };
        $items = config("invoice_type");
        $gateways = gateway_list("gateways", 0);
        $accounts = \think\Db::name("accounts")->alias("a")->field("a.id,a.pay_time,a.gateway,a.gateway as payment_zh,a.description,b.type as type_zh,a.amount_in,a.invoice_id,a.trans_id")->leftJoin("invoices b", "a.invoice_id = b.id")->withAttr("type_zh", function ($value, $data) use($items) {
            return $items[$data["type"]] ?: "";
        })->withAttr("payment_zh", function ($value, $data) {
            foreach ($gateways as $v) {
                if ($v["name"] == $data["gateway"]) {
                    return $v["title"];
                }
            }
            return $value;
        })->where($where)->order($order, $sort)->order("a.pay_time", "desc")->page($page)->limit($limit)->select()->toArray();
        foreach ($accounts as &$vv) {
            $refund = \think\Db::name("accounts")->field("id,amount_out")->where("refund", $vv["id"])->where("invoice_id", $vv["invoice_id"])->where("delete_time", 0)->where("refund", ">", 0)->select()->toArray();
            $vv["refund"] = $refund ?: (object) [];
        }
        $total = \think\Db::name("accounts")->alias("a")->leftJoin("invoices b", "a.invoice_id = b.id")->where($where)->count();
        $client_currency = getUserCurrency($uid);
        $data = ["total" => $total, "accounts" => $accounts, "currency" => $client_currency];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function refundRecord()
    {
        $uid = request()->uid;
        $params = $this->request->param();
        $page = !empty($params["page"]) ? intval($params["page"]) : config("page");
        $limit = !empty($params["limit"]) ? intval($params["limit"]) : config("limit");
        $order = !empty($params["order"]) ? trim($params["order"]) : "a.id";
        $sort = !empty($params["sort"]) ? trim($params["sort"]) : "DESC";
        $where = function (\think\db\Query $query) use($uid) {
            $query->where("a.uid", $uid)->where("a.delete_time", 0)->where("a.refund", ">", 0)->where("a.invoice_id", ">", 0);
        };
        $accounts = \think\Db::name("accounts")->alias("a")->field("a.id,a.pay_time,a.description,a.amount_out,a.invoice_id,a.trans_id")->where($where)->order($order, $sort)->order("a.pay_time", "desc")->page($page)->limit($limit)->select()->toArray();
        $total = \think\Db::name("accounts")->alias("a")->where($where)->count();
        $client_currency = getUserCurrency($uid);
        $data = ["total" => $total, "accounts" => $accounts, "currency" => $client_currency];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function withdrawRecord()
    {
        $uid = request()->uid;
        $page = input("page") ?? config("page");
        $limit = input("limit") ?? config("limit");
        if (input("order")) {
            $order = "a." . input("order");
        } else {
            $order = "a.id";
        }
        $sort = input("sort") ?? "desc";
        $where = function (\think\db\Query $query) use($uid) {
            $query->where("a.uid", $uid)->where("a.status", 2);
        };
        $type = ["1" => "余额", "2" => "仅记录", "3" => "流水支持"];
        $rows = \think\Db::name("affiliates_withdraw")->alias("a")->field("a.id,a.num,a.type,a.create_time,a.status,a.reason,a.reason as des")->where($where)->withAttr("type", function ($value) {
            return $type[$value];
        })->withAttr("reason", function ($value) {
            return "推介计划提现";
        })->page($page)->limit($limit)->order($order, $sort)->select()->toArray();
        $total = \think\Db::name("affiliates_withdraw")->alias("a")->field("a.id,a.num,a.type,a.create_time,a.status")->where($where)->count();
        $data = ["total" => $total, "rows" => $rows, "currency" => getUserCurrency($uid)];
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function getCombineInvoices()
    {
        $param = $this->request->param();
        $ids = $param["ids"];
        if ($ids && !is_array($ids)) {
            $ids = [$ids];
        }
        $items = config("invoice_type");
        $uid = request()->uid;
        $where = function (\think\db\Query $query) use($uid, $ids) {
            $query->where("uid", $uid)->where("delete_time", 0)->where("is_delete", 0)->whereNotIn("type", ["combine", "recharge"])->where("status", "Unpaid")->whereIn("id", $ids);
        };
        $count = \think\Db::name("invoices")->field("id,subtotal,total")->where($where)->count();
        $invoices = \think\Db::name("invoices")->field("id,subtotal,total")->where($where)->select()->toArray();
        $data = [];
        $total = 0;
        foreach ($invoices as &$v) {
            $total += $v["subtotal"];
            $items = \think\Db::name("invoice_items")->field("id,type,description,amount,type as type_zh")->where("invoice_id", $v["id"])->where("uid", $uid)->where("delete_time", 0)->withAttr("type_zh", function ($value, $data) use($items) {
                return $items[$data["type"]] ?: $value;
            })->select()->toArray();
            $v["items"] = $items;
        }
        $data["invoices"] = $invoices;
        $data["total"] = round($total, 2);
        $data["count"] = $count;
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
    public function combineInvoices()
    {
        $param = $this->request->param();
        $ids = $param["ids"];
        if ($ids && !is_array($ids)) {
            $ids = [$ids];
        }
        $uid = request()->uid;
        $payment = \think\Db::name("clients")->where("id", $uid)->value("defaultgateway");
        $where = function (\think\db\Query $query) use($uid, $ids) {
            $query->where("uid", $uid)->where("delete_time", 0)->where("is_delete", 0)->where("status", "Unpaid")->whereNotIn("type", ["combine", "recharge"]);
            if (!empty($ids)) {
                $query->whereIn("id", $ids);
            }
        };
        $invoices = \think\Db::name("invoices")->field("id,subtotal,total,payment")->where($where)->select()->toArray();
        $subtotal = $total = 0;
        foreach ($invoices as $v) {
            $total += $v["total"];
            $subtotal += $v["subtotal"];
        }
        $invoice_data = ["uid" => $uid, "invoice_num" => "", "create_time" => time(), "due_time" => time(), "paid_time" => 0, "last_capture_attempt" => 0, "subtotal" => $subtotal, "credit" => 0, "tax" => 0, "tax2" => 0, "total" => $subtotal, "taxrate" => 0, "taxrate2" => 0, "status" => "Unpaid", "payment" => $payment, "notes" => "", "type" => "combine"];
        \think\Db::startTrans();
        try {
            $ided = \think\Db::name("invoices")->where("type", "combine")->where("uid", $uid)->where("delete_time", 0)->where("status", "Unpaid")->value("id");
            \think\Db::name("invoices")->where("type", "combine")->where("uid", $uid)->where("delete_time", 0)->where("status", "Unpaid")->delete();
            \think\Db::name("invoice_items")->where("type", "combine")->where("invoice_id", $ided)->where("uid", $uid)->where("delete_time", 0)->delete();
            $invoice_id = \think\Db::name("invoices")->insertGetId($invoice_data);
            $insert_all = [];
            foreach ($invoices as $vv) {
                $insert_all[] = ["invoice_id" => $invoice_id, "uid" => $uid, "type" => "combine", "rel_id" => $vv["id"], "description" => "合并账单#Invoice ID " . $vv["id"], "amount" => $vv["subtotal"], "taxed" => 0, "due_time" => 0, "payment" => $vv["payment"], "notes" => "", "delete_time" => 0];
            }
            \think\Db::name("invoice_items")->insertAll($insert_all);
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
            return jsons(["status" => 400, "msg" => lang("FAIL MESSAGE")]);
        }
        $data = ["invoice_id" => $invoice_id];
        if ($subtotal == 0) {
            \think\Db::name("invoices")->where("id", $invoice_id)->update(["status" => "Paid", "paid_time" => time(), "update_time" => time()]);
            $invoice_logic = new \app\common\logic\Invoices();
            $invoice_logic->processPaidInvoice($invoice_id);
            return jsons(["status" => 1001, "msg" => lang("BUY_SUCCESS"), "data" => $data]);
        }
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
}

?>