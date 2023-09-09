<?php

namespace app\home\controller;

/**
 * @title 产品升降级
 * @description 接口说明：产品升降级
 */
class UpgradeController extends CommonController
{
    public function index()
    {
        $re = $data = [];
        $params = $this->request->param();
        $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
        if (!$hid) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $currency = isset($params["currencyid"]) ? intval($params["currencyid"]) : "";
        $uid = request()->uid;
        if (!$uid) {
            return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
        }
        $currencyid = priorityCurrency($uid, $currency);
        list($currency) = (new \app\common\logic\Currencies())->getCurrencies("id,code,prefix,suffix", $currencyid);
        $data["currency"] = $currency;
        $upgrade_logic = new \app\common\logic\Upgrade();
        try {
            $upgrade_logic->judgeUpgradeConfigError($hid);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
        $hosts = \think\Db::name("host")->alias("h")->field("pco.linkage_pid,pco.linkage_top_pid")->field("pco.option_name as option_name,pco.id as oid,pco.option_type,pcos.option_name as suboption_name,pcos.id as subid,hco.qty,h.billingcycle,h.flag,pri.*,pco.qty_stage,pco.unit")->leftJoin("host_config_options hco", "h.id = hco.relid")->leftJoin("product_config_options pco", "pco.id = hco.configid")->leftJoin("product_config_options_sub pcos", "pcos.id = hco.optionid")->leftJoin("pricing pri", "pri.relid = pcos.id")->where("h.id", $hid)->where("h.uid", $uid)->where("pri.currency", $currencyid)->where("pri.type", "configoptions")->where("pco.upgrade", 1)->select()->toArray();
        $cart = new \app\common\logic\Cart();
        $product = \think\Db::name("host")->field("productid,billingcycle")->where("id", $hid)->find();
        $cycle = $product["billingcycle"];
        $pid = $product["productid"];
        $configoptions_logic = new \app\common\logic\ConfigOptions();
        $configInfo = $configoptions_logic->getConfigInfo($pid);
        $allOption = $configoptions_logic->configShow($configInfo, $currencyid, $cycle, $uid, true);
        $hostFilters = [];
        $h = [];
        foreach ($hosts as $key => $host) {
            $option_name = explode("|", $host["option_name"]);
            if ($host["option_type"] != 5 && $host["option_type"] != 12 && $option_name[0] != "system_disk_size") {
                $h["oid"] = $host["oid"];
                $h["id"] = $host["oid"];
                $h["flag"] = $host["flag"];
                $h["option_name"] = $option_name[1] ? $option_name[1] : $host["option_name"];
                $h["option_type"] = $host["option_type"];
                $h["qty"] = $host["qty"];
                $h["suboption_name"] = explode("|", $host["suboption_name"])[1] ? explode("|", $host["suboption_name"])[1] : $host["suboption_name"];
                $h["suboption_name"] = implode(" ", explode("^", $h["suboption_name"]));
                $h["suboption_name_first"] = explode("|", $host["suboption_name"])[1] ? explode("|", $host["suboption_name"])[0] : $host["suboption_name"];
                if ($h["option_type"] == 3 && $h["qty"] == 0) {
                    $h["subid"] = 0;
                } else {
                    $h["subid"] = $host["subid"];
                }
                $h["fee"] = $host[$host["billingcycle"]];
                $h["setupfee"] = $host[$cart->changeCycleToupfee($host["billingcycle"])];
                $h["qty_minimum"] = 0;
                $h["qty_maximum"] = 0;
                $h["qty_stage"] = $host["qty_stage"];
                $h["unit"] = $host["unit"];
                $h["linkage_pid"] = $host["linkage_pid"];
                $h["linkage_top_pid"] = $host["linkage_top_pid"];
                $h["sub"] = [];
                foreach ($allOption as $vv) {
                    if ($vv["id"] == $h["oid"]) {
                        $h["qty_minimum"] = $vv["qty_minimum"];
                        $h["qty_maximum"] = $vv["qty_maximum"];
                        if (1 < count($vv["sub"]) || judgeQuantity($host["option_type"]) || judgeYesNo($host["option_type"])) {
                            $sub = $vv["sub"];
                            if ($host["option_type"] == 13) {
                                $subfilter = [];
                                foreach ($sub as $v) {
                                    if (floatval($h["suboption_name_first"]) <= floatval($v["option_name_first"])) {
                                        $subfilter[] = $v;
                                    }
                                }
                            } else {
                                if ($host["option_type"] == 14 || $host["option_type"] == 19) {
                                    $subfilter = [];
                                    $min = 0;
                                    foreach ($sub as &$v) {
                                        if ($h["subid"] == $v["id"]) {
                                            $min = $v["qty_minimum"];
                                            $v["qty_minimum"] = $h["qty"];
                                        }
                                    }
                                    foreach ($sub as $v2) {
                                        if ($min <= $v["qty_minimum"]) {
                                            $subfilter[] = $v2;
                                        }
                                    }
                                } else {
                                    $subfilter = $sub;
                                }
                            }
                            $h["sub"] = $subfilter;
                        }
                    }
                }
                if (!empty($h["sub"])) {
                    $hostFilters[] = array_map(function ($v) {
                        return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
                    }, $h);
                }
            }
        }
        $hostFilters = $this->handleLinkAgeLevel($hostFilters);
        $hostFilters = $this->handleTreeArr($hostFilters);
        $cids = \think\Db::name("product_config_options")->alias("a")->field("a.id")->leftJoin("product_config_links b", "b.gid = a.gid")->leftJoin("product_config_groups c", "a.gid = c.id")->where("b.pid", $pid)->order("a.order", "asc")->order("a.id", "asc")->column("a.id");
        $links = \think\Db::name("product_config_options_links")->whereIN("config_id", $cids)->where("type", "condition")->where("relation_id", 0)->withAttr("sub_id", function ($value) {
            return json_decode($value, true);
        })->select()->toArray();
        if (!empty($links[0])) {
            foreach ($links as &$link) {
                $result = \think\Db::name("product_config_options_links")->where("relation_id", $link["id"])->withAttr("sub_id", function ($value) {
                    return json_decode($value, true);
                })->select()->toArray();
                $link["result"] = $result;
            }
        }
        if ($links) {
            $hostconfigoptions = \think\Db::name("host")->alias("h")->field("hco.qty,hco.configid,hco.optionid,pco.hidden,pco.upgrade")->leftJoin("host_config_options hco", "h.id = hco.relid")->leftJoin("product_config_options pco", "pco.id = hco.configid")->where("h.id", $hid)->where("h.uid", $uid)->select()->toArray();
            $links_config_id = array_column($links, "config_id");
            $links_config_id = array_unique($links_config_id);
            foreach ($hostconfigoptions as $k => $v) {
                if (in_array($v["configid"], $links_config_id) && ($v["hidden"] == 1 || $v["upgrade"] == 0)) {
                    $host_config_options[$k]["configid"] = $v["configid"];
                    $host_config_options[$k]["optionid"] = $v["optionid"];
                    $host_config_options[$k]["qty"] = $v["qty"];
                }
            }
            $data["host_config_options"] = $host_config_options ? $host_config_options : [];
        }
        $data["links"] = $links ? $links : [];
        $data["host"] = $hostFilters;
        $data["pid"] = $pid;
        $re["status"] = 200;
        $re["msg"] = lang("SUCCESS MESSAGE");
        $re["data"] = $data;
        return jsons($re);
    }
    public function handleLinkAgeLevel($data)
    {
        $req = $this->request;
        if (!$data) {
            return $data;
        }
        $data = array_column($data, NULL, "id");
        $configOption = new \app\common\logic\ConfigOptions();
        foreach ($data as $k => $v) {
            if (!($v["option_type"] != 20 || $v["linkage_pid"] != 0)) {
                $req->cid = $cid = $v["id"];
                if ($v["subid"]) {
                    $req->sub_id = $v["subid"];
                }
                $all_list = $configOption->webGetLinkAgeList($req);
                $linkAge = $configOption->webSetLinkAgeListDefaultVal($all_list, $req);
                $linkAge_ids = $linkAge ? array_column($linkAge, "id") : [];
                foreach ($linkAge as $val) {
                    if (isset($data[$val["id"]])) {
                        $data[$val["id"]]["checkSubId"] = $val["checkSubId"];
                    }
                }
                $data = array_filter($data, function ($v) use($linkAge_ids, $cid) {
                    if ($v["option_type"] != 20) {
                        return true;
                    }
                    if ($v["linkage_top_pid"] != $cid) {
                        return true;
                    }
                    if (in_array($v["id"], $linkAge_ids)) {
                        return true;
                    }
                    return false;
                });
            }
        }
        return $configOption->getTree($data);
    }
    public function handleTreeArr($data)
    {
        if (!$data) {
            return $data;
        }
        foreach ($data as $key => $val) {
            if (isset($val["son"]) && $val["son"]) {
                $data[$key]["son"] = changeTwoArr($val["son"]);
            }
        }
        return $data;
    }
    public function upgradeConfigPost()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                cache("upgrade_down_config_" . $hid, NULL);
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid)) {
                    return jsons(["status" => 400, "msg" => lang("当前产品无法升级或降级可配置项")]);
                }
                $configoptions = $params["configoption"];
                if (!$upgrade_logic->checkChange($hid, $configoptions)) {
                    return jsons(["status" => 400, "msg" => lang("请选择配置项")]);
                }
                $data["hid"] = $hid;
                $data["configoptions"] = $configoptions;
                if (!empty($configoptions) && is_array($configoptions)) {
                    cache("upgrade_down_config_" . $hid, $data, 86400);
                    return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
                }
                return jsons(["status" => 400, "msg" => "配置项非数组"]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function getUpgradeConfigPage()
    {
        try {
            $params = $this->request->param();
            $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
            if (!$hid) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $upgrade_logic = new \app\common\logic\Upgrade();
            if (!$upgrade_logic->judgeUpgradeConfigError($hid)) {
                return jsons(["status" => 400, "msg" => lang("当前产品无法升级或降级可配置项")]);
            }
            $data = cache("upgrade_down_config_" . $hid);
            if (!$data) {
                return jsons(["status" => 400, "msg" => "请重新选择配置"]);
            }
            $configoptions = $data["configoptions"];
            $promo_code = $data["promo_code"] ?? "";
            $currencyid = isset($params["currencyid"]) ? intval($params["currencyid"]) : "";
            $uid = request()->uid;
            $currencyid = priorityCurrency($uid, $currencyid);
            $upgrade_logic = new \app\common\logic\Upgrade();
            $re = $upgrade_logic->upgradeConfigCommon($hid, $configoptions, $currencyid, false, $promo_code);
            return jsons($re);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function addPromoCodeToConfig()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid)) {
                    return jsons(["status" => 400, "msg" => lang("优惠码无效")]);
                }
                $promo_code = $params["pormo_code"];
                $result = $upgrade_logic->checkUpgradePromo($promo_code, $hid);
                if ($result["status"] != 200) {
                    $result["msg"] = "优惠码无效";
                    return jsons($result);
                }
                $data = cache("upgrade_down_config_" . $hid);
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "优惠码无效"]);
                }
                $data["promo_code"] = $promo_code;
                cache("upgrade_down_config_" . $hid, $data, 86400);
                return jsons(["status" => 200, "msg" => "应用优惠码成功"]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function removePromoCodeFromConfig()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid)) {
                    return jsons(["status" => 400, "msg" => lang("当前产品无法升级或降级可配置项")]);
                }
                $data = cache("upgrade_down_config_" . $hid);
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "请重新选择配置"]);
                }
                $data["promo_code"] = "";
                cache("upgrade_down_config_" . $hid, $data, 86400);
                return jsons(["status" => 200, "msg" => "移除优惠码成功"]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function checkoutConfigUpgrade()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid)) {
                    return jsons(["status" => 400, "msg" => lang("当前产品无法升级或降级可配置项")]);
                }
                $data = cache("upgrade_down_config_" . $hid);
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "请重新选择配置"]);
                }
                $configoptions = $data["configoptions"];
                $promo_code = $data["promo_code"] ?? "";
                $currencyid = isset($data["currencyid"]) ? intval($data["currencyid"]) : "";
                $uid = request()->uid;
                $currencyid = priorityCurrency($uid, $currencyid);
                $payment = \think\Db::name("host")->where("id", $hid)->value("payment");
                $desc = "";
                if (cache(md5(serialize($data) . "-" . $hid . "-" . get_client_ip()))) {
                    return jsons(["status" => 400, "msg" => "请求过于频繁"]);
                }
                cache(md5(serialize($data) . "-" . $hid . "-" . get_client_ip()), "upgrade config", 20);
                $productid = \think\Db::name("host")->where("id", $hid)->value("productid");
                $configoption_res = \think\Db::name("host_config_options")->where("relid", $hid)->select()->toArray();
                $configoption = [];
                foreach ($configoption_res as $k => $v) {
                    $configoption[$v["configid"]] = $v["qty"] ?: $v["optionid"];
                }
                foreach ($configoptions as $ks => $vs) {
                    $configoption[$ks] = $vs;
                }
                $senior = new \app\common\logic\SeniorConf();
                $msg = $senior->checkConf($productid, $configoption);
                if ($msg) {
                    return jsons(["status" => 400, "msg" => $msg]);
                }
                $percent_value = $params["resource_percent_value"] ?: "";
                if (!empty($configoptions) && is_array($configoptions)) {
                    $re = $upgrade_logic->upgradeConfigCommon($hid, $configoptions, $currencyid, false, $promo_code, $payment, true, $percent_value);
                    return jsons($re);
                }
                return jsons(["status" => 400, "msg" => "配置项非数组"]);
            } else {
                return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
            }
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function upgradeProduct()
    {
        try {
            $re = $data = [];
            $re["status"] = 200;
            $re["msg"] = lang("SUCCESS MESSAGE");
            $params = $this->request->param();
            $hid = isset($params["hid"]) && !empty($params["hid"]) ? intval($params["hid"]) : "";
            if (!$hid) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $upgrade_logic = new \app\common\logic\Upgrade();
            if (!$upgrade_logic->judgeUpgradeConfigError($hid, "product")) {
                return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
            }
            $currency = $params["currencyid"] ?? "";
            $uid = request()->uid;
            $currency_id = priorityCurrency($uid, $currency);
            $oldhost = \think\Db::name("product_groups")->alias("pg")->field("p.name as host,h.domain,p.description,p.id as pid,h.uid,h.flag")->leftJoin("products p", "p.gid = pg.id")->leftJoin("host h", "h.productid = p.id")->where("h.id", $hid)->find();
            if ($oldhost["uid"] != $uid) {
                return json(["status" => 400, "msg" => "非法操作"]);
            }
            $oldhost = array_map(function ($v) {
                return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
            }, $oldhost);
            $host = \think\Db::name("products")->alias("p")->field("p.id as pid,p.name as host,p.description")->leftJoin("product_groups pg", "p.gid = pg.id")->select()->toArray();
            $upgrade_logic = new \app\common\logic\Upgrade();
            $pids = $upgrade_logic->allowUpgradeProducts($oldhost["pid"]);
            $host_filter = [];
            foreach ($host as $k => $product) {
                if ($product["pid"] != $oldhost["pid"] && in_array($product["pid"], $pids)) {
                    $product_model = new \app\common\model\ProductModel();
                    $cycle = $product_model->getProductCycle($product["pid"], $currency_id, "", "", "", $uid, "", "", $host["flag"], 1);
                    $product["cycle"] = $cycle;
                    $host_filter[] = $product;
                }
            }
            list($currency) = (new \app\common\logic\Currencies())->getCurrencies("id,code,prefix,suffix", $currency_id);
            $data["currency"] = $currency;
            $data["old_host"] = $oldhost;
            $data["host"] = $host_filter;
            $re["data"] = $data;
            return jsons($re);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function upgradeProductPost()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) && !empty($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $new_pid = isset($params["pid"]) && !empty($params["pid"]) ? intval($params["pid"]) : "";
                if (!$new_pid) {
                    return jsons(["status" => 400, "msg" => lang("PLEASE_SELECT_THE_PRODUCT")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid, "product")) {
                    return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
                }
                $currency_id = isset($params["currencyid"]) && !empty($params["currencyid"]) ? intval($params["currencyid"]) : "";
                $billingcycle = isset($params["billingcycle"]) && !empty($params["billingcycle"]) ? strtolower(trim($params["billingcycle"])) : "";
                $uid = request()->uid;
                $host = \think\Db::name("host")->field("uid")->where("id", $hid)->find();
                if ($host["uid"] != $uid) {
                    return json(["status" => 400, "msg" => "非法操作"]);
                }
                $data = [];
                $data["hid"] = $hid;
                $data["pid"] = $new_pid;
                $data["billingcycle"] = $billingcycle;
                $data["currencyid"] = $currency_id;
                cache("upgrade_down_product_" . $hid, $data, 86400);
                return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function getUpgradeProductPage()
    {
        try {
            $params = $this->request->param();
            $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
            if (!$hid) {
                return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
            }
            $uid = request()->uid;
            $host = \think\Db::name("host")->field("uid")->where("id", $hid)->find();
            if ($host["uid"] != $uid) {
                return json(["status" => 400, "msg" => "非法操作"]);
            }
            $upgrade_logic = new \app\common\logic\Upgrade();
            if (!$upgrade_logic->judgeUpgradeConfigError($hid, "product")) {
                return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
            }
            $data = cache("upgrade_down_product_" . $hid);
            if (!$data) {
                return jsons(["status" => 400, "msg" => "请重新选择产品"]);
            }
            $hid = $data["hid"];
            $new_pid = $data["pid"];
            $billingcycle = $data["billingcycle"];
            $currency_id = $data["currencyid"];
            $promo_code = $data["promo_code"] ?? "";
            $upgrade_logic = new \app\common\logic\Upgrade();
            $re = $upgrade_logic->upgradeProductCommon($hid, $new_pid, $billingcycle, $currency_id, $promo_code);
            return jsons($re);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function addPromoToProduct()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                $data = cache("upgrade_down_product_" . $hid);
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $uid = request()->uid;
                $host = \think\Db::name("host")->field("uid")->where("id", $hid)->find();
                if ($host["uid"] != $uid) {
                    return json(["status" => 400, "msg" => "非法操作"]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid, $params["upgrade_type"] ?? "product")) {
                    return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
                }
                $promo_code = $params["pormo_code"] ?? "";
                $new_pid = NULL;
                $upgrade_type = "option";
                if ($params["upgrade_type"] == "product") {
                    $new_pid = $data["pid"];
                    $upgrade_type = "product";
                }
                $new_billingcycle = $data["billingcycle"];
                $result = $upgrade_logic->checkUpgradePromo($promo_code, $hid, $new_pid, $new_billingcycle, $upgrade_type);
                if ($result["status"] != 200) {
                    return jsons($result);
                }
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "优惠码无效"]);
                }
                $data["promo_code"] = $promo_code;
                cache("upgrade_down_product_" . $hid, $data, 86400);
                return jsons(["status" => 200, "msg" => "应用优惠码成功"]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function RemovePromoFromProduct()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) ? intval($params["hid"]) : "";
                $data = cache("upgrade_down_product_" . $hid);
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $uid = request()->uid;
                $host = \think\Db::name("host")->field("uid")->where("id", $hid)->find();
                if ($host["uid"] != $uid) {
                    return json(["status" => 400, "msg" => "非法操作"]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid, "product")) {
                    return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
                }
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "请重新选择产品"]);
                }
                \think\Db::name("host")->where("id", $hid)->update(["promoid" => 0]);
                $data["promo_code"] = "";
                cache("upgrade_down_product_" . $hid, $data, 86400);
                return jsons(["status" => 200, "msg" => "移除优惠码成功"]);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
    public function checkoutProductUpgrade()
    {
        try {
            if ($this->request->isPost()) {
                $params = $this->request->param();
                $hid = isset($params["hid"]) && !empty($params["hid"]) ? intval($params["hid"]) : "";
                if (!$hid) {
                    return jsons(["status" => 400, "msg" => lang("ID_ERROR")]);
                }
                $upgrade_logic = new \app\common\logic\Upgrade();
                if (!$upgrade_logic->judgeUpgradeConfigError($hid, "product")) {
                    return jsons(["status" => 400, "msg" => "当前产品无法升级或降级"]);
                }
                $uid = request()->uid;
                $host = \think\Db::name("host")->field("uid")->where("id", $hid)->find();
                if ($host["uid"] != $uid) {
                    return json(["status" => 400, "msg" => "非法操作"]);
                }
                $payment = isset($params["payment"]) && !empty($params["payment"]) ? $params["payment"] : "";
                $data = cache("upgrade_down_product_" . $hid);
                if (!$data) {
                    return jsons(["status" => 400, "msg" => "请重新选择产品"]);
                }
                if (cache(md5(serialize($data) . "-" . $hid . "-" . get_client_ip()))) {
                    return jsons(["status" => 400, "msg" => "请求过于频繁"]);
                }
                cache(md5(serialize($data) . "-" . $hid . "-" . get_client_ip()), "upgrade", 20);
                $newpid = $data["pid"];
                $billingcycle = $data["billingcycle"];
                $currencyid = $data["currencyid"];
                $promocode = $data["promo_code"] ?? "";
                $upgrade_logic = new \app\common\logic\Upgrade();
                $percent_value = $params["resource_percent_value"] ?: "";
                $result = $upgrade_logic->upgradeProductCommon($hid, $newpid, $billingcycle, $currencyid, $promocode, $payment, true, $percent_value);
                return jsons($result);
            }
            return jsons(["status" => 400, "msg" => lang("ERROR MESSAGE")]);
        } catch (\Throwable $e) {
            return jsons(["status" => 400, "msg" => $e->getMessage()]);
        }
    }
}

?>