<?php


namespace app\common\logic;

class Cart
{
    protected $recurringCycles = NULL;
    public function getRecurringCycles()
    {
        return $this->recurringCycles = array_keys(config("billing_cycle"));
    }
    public function validBillingCycle($cycle)
    {
        return in_array($cycle, $this->getRecurringCycles());
    }
    public function formatAsCurrency($amount)
    {
        if (0 < $amount) {
            $amount += 0;
        }
        $amount = round($amount, 2);
        $amount = sprintf("%01.2f", $amount);
        return $amount;
    }
    public function getProductCycle($pid, $currencyid)
    {
        $product = \think\Db::name("products")->field("id,name,description,pay_type,host,password,allow_qty,stock_control,qty")->where("id", $pid)->find();
        $product = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $product);
        $product["host"] = json_decode($product["host"], true);
        $product["host"]["host"] = generateHostName($product["host"]["prefix"], $product["host"]["rule"], $product["host"]["show"]);
        $product["password"] = json_decode($product["password"], true);
        $product["password"]["password"] = generateHostPassword($product["password"], $product["type"]);
        $paytype = (array) json_decode($product["pay_type"]);
        $pricing = \think\Db::name("pricing")->where("type", "product")->where("relid", $pid)->where("currency", $currencyid)->find();
        $product["cycle"] = $cycle = [];
        if (!empty($paytype["pay_ontrial_status"]) && 0 <= $pricing["ontrial"]) {
            $cycle["product_price"] = $pricing["ontrial"];
            $cycle["setup_fee"] = $pricing["ontrialfee"];
            $cycle["billingcycle"] = "ontrial";
            $cycle["billingcycle_zh"] = lang("ONTRIAL");
            $cycle["pay_ontrial_cycle"] = $paytype["pay_ontrial_cycle"];
            $product["cycle"][] = $cycle;
        }
        $price_type = config("price_type");
        $billingcycles = array_keys($price_type);
        if ($paytype["pay_type"] == "onetime" && 0 <= $pricing["onetime"]) {
            $cycle["product_price"] = $pricing["onetime"];
            $cycle["setup_fee"] = $pricing["osetupfee"];
            $cycle["billingcycle"] = "onetime";
            $cycle["billingcycle_zh"] = lang("ONETIME");
            $product["cycle"][] = $cycle;
        } else {
            if ($paytype["pay_type"] == "free") {
                $cycle["product_price"] = number_format(0, 2);
                $cycle["setup_fee"] = number_format(0, 2);
                $cycle["billingcycle"] = "free";
                $cycle["billingcycle_zh"] = lang("FREE");
                $product["cycle"][] = $cycle;
            } else {
                if ($paytype["pay_type"] == "recurring") {
                    foreach ($billingcycles as $billingcycle) {
                        $cycle1 = [];
                        if ($billingcycle != "ontrial" && $billingcycle != "onetime" && $billingcycle != "free" && 0 <= $pricing[$billingcycle]) {
                            $cycle1["product_price"] = $pricing[$billingcycle];
                            $cycle1["setup_fee"] = $pricing[$price_type[$billingcycle][1]];
                            $cycle1["billingcycle"] = $billingcycle;
                            $cycle1["billingcycle_zh"] = lang(strtoupper($billingcycle));
                            if ($billingcycle == "hour") {
                                $cycle1["hour_cycle"] = $paytype["pay_hour_cycle"];
                                $cycle1["billingcycle_zh"] = $paytype["pay_hour_cycle"] . $cycle1["billingcycle_zh"];
                            } else {
                                if ($billingcycle == "day") {
                                    $cycle1["day_cycle"] = $paytype["pay_day_cycle"];
                                    $cycle1["billingcycle_zh"] = $paytype["pay_day_cycle"] . $cycle1["billingcycle_zh"];
                                }
                            }
                            $product["cycle"][] = $cycle1;
                        }
                    }
                }
            }
        }
        unset($product["pay_type"]);
        return $product;
    }
    public function getProductPricing($pid, $currencyid, $type = NULL)
    {
        $product = \think\Db::name("products")->field("id,name,description,pay_type,host,password,allow_qty")->where("id", $pid)->find();
        $product = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $product);
        $product["host"] = json_decode($product["host"], true);
        $product["host"]["host"] = generateHostName($product["host"]["rule"]);
        $product["password"] = json_decode($product["password"], true);
        $product["password"]["password"] = generateHostPassword($product["password"]);
        $paytype = (array) json_decode($product["pay_type"]);
        $product["pay_type"] = $paytype;
        $pricings = \think\Db::name("pricing")->where("type", "product")->where("relid", $pid)->where("currency", $currencyid)->select();
        foreach ($pricings as $key => $pricing) {
            if (!isset($product["child"][$currencyid])) {
                $product["child"][$currencyid] = [];
            }
            if ($type == "admin") {
                $currency_id = \think\Db::name("currencies")->where("default", 1)->value("id");
                if ($currencyid != $currency_id) {
                    unset($product["child"][$currencyid]);
                }
            }
            if (0 <= $pricing["hour"]) {
                $product["child"][$currencyid][0]["product_price"] = $pricing["hour"];
                $product["child"][$currencyid][0]["setup_fee"] = $pricing["hsetupfee"];
                $product["child"][$currencyid][0]["billingcycle"] = "hour";
                $product["child"][$currencyid][0]["billingcycle_zh"] = "小时付";
            }
            if (0 <= $pricing["day"]) {
                $product["child"][$currencyid][1]["product_price"] = $pricing["day"];
                $product["child"][$currencyid][1]["setup_fee"] = $pricing["dsetupfee"];
                $product["child"][$currencyid][1]["billingcycle"] = "day";
                $product["child"][$currencyid][1]["billingcycle_zh"] = "天付";
            }
            if (0 <= $pricing["monthly"]) {
                $product["child"][$currencyid][2]["product_price"] = $pricing["monthly"];
                $product["child"][$currencyid][2]["setup_fee"] = $pricing["msetupfee"];
                $product["child"][$currencyid][2]["billingcycle"] = "monthly";
                $product["child"][$currencyid][2]["billingcycle_zh"] = "月付";
            }
            if (0 <= $pricing["quarterly"]) {
                $product["child"][$currencyid][3]["product_price"] = $pricing["quarterly"];
                $product["child"][$currencyid][3]["setup_fee"] = $pricing["qsetupfee"];
                $product["child"][$currencyid][3]["billingcycle"] = "quarterly";
                $product["child"][$currencyid][3]["billingcycle_zh"] = "季付";
            }
            if (0 <= $pricing["semiannually"]) {
                $product["child"][$currencyid][4]["product_price"] = $pricing["semiannually"];
                $product["child"][$currencyid][4]["setup_fee"] = $pricing["ssetupfee"];
                $product["child"][$currencyid][4]["billingcycle"] = "semiannually";
                $product["child"][$currencyid][4]["billingcycle_zh"] = "三季付";
            }
            if (0 <= $pricing["annually"]) {
                $product["child"][$currencyid][5]["product_price"] = $pricing["annually"];
                $product["child"][$currencyid][5]["setup_fee"] = $pricing["asetupfee"];
                $product["child"][$currencyid][5]["billingcycle"] = "annually";
                $product["child"][$currencyid][5]["billingcycle_zh"] = "年付";
            }
            if (0 <= $pricing["biennially"]) {
                $product["child"][$currencyid][6]["product_price"] = $pricing["biennially"];
                $product["child"][$currencyid][6]["setup_fee"] = $pricing["bsetupfee"];
                $product["child"][$currencyid][6]["billingcycle"] = "biennially";
                $product["child"][$currencyid][6]["billingcycle_zh"] = "二年付";
            }
            if (0 <= $pricing["triennially"]) {
                $product["child"][$currencyid][7]["product_price"] = $pricing["triennially"];
                $product["child"][$currencyid][7]["setup_fee"] = $pricing["tsetupfee"];
                $product["child"][$currencyid][7]["billingcycle"] = "triennially";
                $product["child"][$currencyid][7]["billingcycle_zh"] = "三年付";
            }
            if (0 <= $pricing["fourly"]) {
                $product["child"][$currencyid][8]["product_price"] = $pricing["fourly"];
                $product["child"][$currencyid][8]["setup_fee"] = $pricing["foursetupfee"];
                $product["child"][$currencyid][8]["billingcycle_zh"] = "四年付";
            }
            if (0 <= $pricing["fively"]) {
                $product["child"][$currencyid][9]["product_price"] = $pricing["fively"];
                $product["child"][$currencyid][9]["setup_fee"] = $pricing["fivesetupfee"];
                $product["child"][$currencyid][9]["billingcycle"] = "fively";
                $product["child"][$currencyid][9]["billingcycle_zh"] = "五年付";
            }
            if (0 <= $pricing["sixly"]) {
                $product["child"][$currencyid][10]["product_price"] = $pricing["sixly"];
                $product["child"][$currencyid][10]["setup_fee"] = $pricing["sixsetupfee"];
                $product["child"][$currencyid][10]["billingcycle"] = "sixly";
                $product["child"][$currencyid][10]["billingcycle_zh"] = "六年付";
            }
            if (0 <= $pricing["sevenly"]) {
                $product["child"][$currencyid][11]["product_price"] = $pricing["sevenly"];
                $product["child"][$currencyid][11]["setup_fee"] = $pricing["sevensetupfee"];
                $product["child"][$currencyid][11]["billingcycle"] = "sevenly";
                $product["child"][$currencyid][11]["billingcycle_zh"] = "七年付";
            }
            if (0 <= $pricing["eightly"]) {
                $product["child"][$currencyid][12]["product_price"] = $pricing["eightly"];
                $product["child"][$currencyid][12]["setup_fee"] = $pricing["eightsetupfee"];
                $product["child"][$currencyid][12]["billingcycle"] = "eightly";
                $product["child"][$currencyid][12]["billingcycle_zh"] = "八年付";
            }
            if (0 <= $pricing["ninely"]) {
                $product["child"][$currencyid][13]["product_price"] = $pricing["ninely"];
                $product["child"][$currencyid][13]["setup_fee"] = $pricing["ninesetupfee"];
                $product["child"][$currencyid][13]["billingcycle"] = "ninely";
                $product["child"][$currencyid][13]["billingcycle_zh"] = "九年付";
            }
            if (0 <= $pricing["tenly"]) {
                $product["child"][$currencyid][14]["product_price"] = $pricing["tenly"];
                $product["child"][$currencyid][14]["setup_fee"] = $pricing["tensetupfee"];
                $product["child"][$currencyid][14]["billingcycle"] = "tenly";
                $product["child"][$currencyid][14]["billingcycle_zh"] = "十年付";
            }
            if (0 <= $pricing["ontrial"]) {
                $product["child"][$currencyid][15]["product_price"] = $pricing["ontrial"];
                $product["child"][$currencyid][15]["setup_fee"] = $pricing["ontrialfee"];
                $product["child"][$currencyid][15]["billingcycle"] = "ontrial";
                $product["child"][$currencyid][15]["billingcycle_zh"] = "试用";
            }
            if (0 <= $pricing["onetime"]) {
                $product["child"][$currencyid][16]["product_price"] = $pricing["onetime"];
                $product["child"][$currencyid][16]["setup_fee"] = $pricing["osetupfee"];
                $product["child"][$currencyid][16]["billingcycle"] = "one time";
                $product["child"][$currencyid][16]["billingcycle_zh"] = "一次性";
            }
        }
        return $product;
    }
    public function changeCycleToupfee($cycle)
    {
        if ($cycle == "hour") {
            $cycle = "hsetupfee";
        } else {
            if ($cycle == "onetime") {
                $cycle = "osetupfee";
            } else {
                if ($cycle == "day") {
                    $cycle = "dsetupfee";
                } else {
                    if ($cycle == "ontrial") {
                        $cycle = "ontrialfee";
                    } else {
                        if ($cycle == "monthly") {
                            $cycle = "msetupfee";
                        } else {
                            if ($cycle == "quarterly") {
                                $cycle = "qsetupfee";
                            } else {
                                if ($cycle == "semiannually") {
                                    $cycle = "ssetupfee";
                                } else {
                                    if ($cycle == "annually") {
                                        $cycle = "asetupfee";
                                    } else {
                                        if ($cycle == "biennially") {
                                            $cycle = "bsetupfee";
                                        } else {
                                            if ($cycle == "triennially") {
                                                $cycle = "tsetupfee";
                                            } else {
                                                if ($cycle == "fourly") {
                                                    $cycle = "foursetupfee";
                                                } else {
                                                    if ($cycle == "fively") {
                                                        $cycle = "fivesetupfee";
                                                    } else {
                                                        if ($cycle == "sixly") {
                                                            $cycle = "sixsetupfee";
                                                        } else {
                                                            if ($cycle == "sevenly") {
                                                                $cycle = "sevensetupfee";
                                                            } else {
                                                                if ($cycle == "eightly") {
                                                                    $cycle = "eightsetupfee";
                                                                } else {
                                                                    if ($cycle == "ninely") {
                                                                        $cycle = "ninesetupfee";
                                                                    } else {
                                                                        if ($cycle == "tenly") {
                                                                            $cycle = "tensetupfee";
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $cycle;
    }
    public function getProductDefaultConfigPrice($pid, $currency, $billingcycle, &$rebate_total, &$setupfee_total = 0)
    {
        $options = \think\Db::name("product_config_options")->alias("a")->field("a.id as cid,a.option_type,a.qty_minimum,a.is_discount,a.option_name,a.is_rebate")->leftJoin("product_config_groups b", "a.gid = b.id")->leftJoin("product_config_links c", "c.gid = b.id")->leftJoin("products d", "c.pid = d.id")->where("d.id", $pid)->where("a.hidden", 0)->select()->toArray();
        if ($options) {
            $options_ids = array_column($options, "cid");
            $field_options = \think\Db::name("product_config_options")->whereIn("id", $options_ids)->select()->toArray();
            $_options = $this->optionHandleLinkAgeLevel($field_options);
            $filter_ids = array_column($_options, "id");
            $options = array_filter($options, function ($v) use($filter_ids) {
                return in_array($v["cid"], $filter_ids);
            });
        }
        $price_type = config("price_type");
        $config_total = 0;
        $edition = getEdition();
        foreach ($options as $option) {
            $pricing = \think\Db::name("pricing")->alias("a")->field("a.*,b.qty_minimum")->leftJoin("product_config_options_sub b", "a.relid = b.id")->where("a.type", "configoptions")->where("a.currency", $currency)->where("b.hidden", 0)->where("b.config_id", $option["cid"])->order("b.sort_order", "ASC")->order("a.relid", "asc")->find();
            $qty_min = $pricing["qty_minimum"];
            if (judgeQuantity($option["option_type"])) {
                if (judgeQuantityStage($option["option_type"])) {
                    $sum = quantityStagePrice($option["cid"], $currency, $qty_min, $billingcycle);
                    $config_total += $sum[0];
                    $config_total += $sum[1];
                    if ($option["is_rebate"] || !$edition) {
                        $rebate_total += $sum[0];
                        $rebate_total += $sum[1];
                    }
                    $setupfee_total += $sum[1];
                } else {
                    $config_total += ($pricing[$price_type[$billingcycle][0]] ?? 0) * ($qty_min < 0 ? 0 : intval($qty_min));
                    if ($option["is_rebate"] || !$edition) {
                        $rebate_total += ($pricing[$price_type[$billingcycle][0]] ?? 0) * ($qty_min < 0 ? 0 : intval($qty_min));
                    }
                    if (0 < $qty_min) {
                        $config_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                        if ($option["is_rebate"] || !$edition) {
                            $rebate_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                        }
                        $setupfee_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                    }
                }
            } else {
                if (judgeYesNo($option["option_type"])) {
                    $config_total += 0;
                    $setupfee_total += 0;
                    if ($option["is_rebate"] || !$edition) {
                        $rebate_total += 0;
                    }
                } else {
                    $config_total += $pricing[$price_type[$billingcycle][0]] ?? 0;
                    $config_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                    if ($option["is_rebate"] || !$edition) {
                        $rebate_total += $pricing[$price_type[$billingcycle][0]] ?? 0;
                        $rebate_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                    }
                    $setupfee_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                }
            }
        }
        return $config_total;
    }
    public function optionHandleLinkAgeLevel($data)
    {
        $req = request();
        if (!$data) {
            return $data;
        }
        $data = array_column($data, NULL, "id");
        $configOption = new ConfigOptions();
        foreach ($data as $k => $v) {
            if (!($v["option_type"] != 20 || $v["linkage_pid"] != 0)) {
                $req->cid = $cid = $v["id"];
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
        return $data;
    }
    public function getProductPriceArea($pid)
    {
        $v = \think\Db::name("products")->where("id", $pid)->find();
        $paytype = json_decode($v["pay_type"], true);
        $currencyid = getDefaultCurrencyId();
        $pricing = \think\Db::name("pricing")->where("type", "product")->where("relid", $v["id"])->where("currency", $currencyid)->find();
        if (!empty($paytype["pay_ontrial_status"])) {
            if (0 <= $pricing["ontrial"]) {
                $v["product_price"] = $pricing["ontrial"];
                $v["setup_fee"] = $pricing["ontrialfee"];
                $v["billingcycle"] = "ontrial";
                $v["billingcycle_zh"] = lang("ONTRIAL");
            } else {
                $v["product_price"] = 0;
                $v["setup_fee"] = 0;
                $v["billingcycle"] = "";
                $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
            }
        }
        if ($paytype["pay_type"] == "free") {
            $v["product_price"] = 0;
            $v["setup_fee"] = 0;
            $v["billingcycle"] = "free";
            $v["billingcycle_zh"] = lang("FREE");
        } else {
            if ($paytype["pay_type"] == "onetime") {
                if (0 <= $pricing["onetime"]) {
                    $v["product_price"] = $pricing["onetime"];
                    $v["setup_fee"] = $pricing["osetupfee"];
                    $v["billingcycle"] = "onetime";
                    $v["billingcycle_zh"] = lang("ONETIME");
                } else {
                    $v["product_price"] = 0;
                    $v["setup_fee"] = 0;
                    $v["billingcycle"] = "";
                    $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                }
            } else {
                if (!empty($pricing) && $paytype["pay_type"] == "recurring") {
                    if (0 <= $pricing["hour"]) {
                        $v["product_price"] = $pricing["hour"];
                        $v["setup_fee"] = $pricing["hsetupfee"];
                        $v["billingcycle"] = "hour";
                        $v["billingcycle_zh"] = lang("HOUR");
                    } else {
                        if (0 <= $pricing["day"]) {
                            $v["product_price"] = $pricing["day"];
                            $v["setup_fee"] = $pricing["dsetupfee"];
                            $v["billingcycle"] = "day";
                            $v["billingcycle_zh"] = lang("DAY");
                        } else {
                            if (0 <= $pricing["monthly"]) {
                                $v["product_price"] = $pricing["monthly"];
                                $v["setup_fee"] = $pricing["msetupfee"];
                                $v["billingcycle"] = "monthly";
                                $v["billingcycle_zh"] = lang("MONTHLY");
                            } else {
                                if (0 <= $pricing["quarterly"]) {
                                    $v["product_price"] = $pricing["quarterly"];
                                    $v["setup_fee"] = $pricing["qsetupfee"];
                                    $v["billingcycle"] = "quarterly";
                                    $v["billingcycle_zh"] = lang("QUARTERLY");
                                } else {
                                    if (0 <= $pricing["semiannually"]) {
                                        $v["product_price"] = $pricing["semiannually"];
                                        $v["setup_fee"] = $pricing["ssetupfee"];
                                        $v["billingcycle"] = "semiannually";
                                        $v["billingcycle_zh"] = lang("SEMIANNUALLY");
                                    } else {
                                        if (0 <= $pricing["annually"]) {
                                            $v["product_price"] = $pricing["annually"];
                                            $v["setup_fee"] = $pricing["asetupfee"];
                                            $v["billingcycle"] = "annually";
                                            $v["billingcycle_zh"] = lang("ANNUALLY");
                                        } else {
                                            if (0 <= $pricing["biennially"]) {
                                                $v["product_price"] = $pricing["biennially"];
                                                $v["setup_fee"] = $pricing["bsetupfee"];
                                                $v["billingcycle"] = "biennially";
                                                $v["billingcycle_zh"] = lang("BIENNIALLY");
                                            } else {
                                                if (0 <= $pricing["triennially"]) {
                                                    $v["product_price"] = $pricing["triennially"];
                                                    $v["setup_fee"] = $pricing["tsetupfee"];
                                                    $v["billingcycle"] = "triennially";
                                                    $v["billingcycle_zh"] = lang("TRIENNIALLY");
                                                } else {
                                                    if (0 <= $pricing["fourly"]) {
                                                        $v["product_price"] = $pricing["fourly"];
                                                        $v["setup_fee"] = $pricing["foursetupfee"];
                                                        $v["billingcycle"] = "fourly";
                                                        $v["billingcycle_zh"] = lang("FOURLY");
                                                    } else {
                                                        if (0 <= $pricing["fively"]) {
                                                            $v["product_price"] = $pricing["fively"];
                                                            $v["setup_fee"] = $pricing["fivesetupfee"];
                                                            $v["billingcycle"] = "fively";
                                                            $v["billingcycle_zh"] = lang("FIVELY");
                                                        } else {
                                                            if (0 <= $pricing["sixly"]) {
                                                                $v["product_price"] = $pricing["sixly"];
                                                                $v["setup_fee"] = $pricing["sixsetupfee"];
                                                                $v["billingcycle"] = "sixly";
                                                                $v["billingcycle_zh"] = lang("SIXLY");
                                                            } else {
                                                                if (0 <= $pricing["sevenly"]) {
                                                                    $v["product_price"] = $pricing["sevenly"];
                                                                    $v["setup_fee"] = $pricing["sevensetupfee"];
                                                                    $v["billingcycle"] = "sevenly";
                                                                    $v["billingcycle_zh"] = lang("SEVENLY");
                                                                } else {
                                                                    if (0 <= $pricing["eightly"]) {
                                                                        $v["product_price"] = $pricing["eightly"];
                                                                        $v["setup_fee"] = $pricing["eightsetupfee"];
                                                                        $v["billingcycle"] = "eightly";
                                                                        $v["billingcycle_zh"] = lang("EIGHTLY");
                                                                    } else {
                                                                        if (0 <= $pricing["ninely"]) {
                                                                            $v["product_price"] = $pricing["ninely"];
                                                                            $v["setup_fee"] = $pricing["ninesetupfee"];
                                                                            $v["billingcycle"] = "ninely";
                                                                            $v["billingcycle_zh"] = lang("NINELY");
                                                                        } else {
                                                                            if (0 <= $pricing["tenly"]) {
                                                                                $v["product_price"] = $pricing["tenly"];
                                                                                $v["setup_fee"] = $pricing["tensetupfee"];
                                                                                $v["billingcycle"] = "tenly";
                                                                                $v["billingcycle_zh"] = lang("TENLY");
                                                                            } else {
                                                                                $v["product_price"] = 0;
                                                                                $v["setup_fee"] = 0;
                                                                                $v["billingcycle"] = "";
                                                                                $v["billingcycle_zh"] = lang("PRICE_CONFIG_ERROR");
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $v["product_price"] = 0;
                    $v["setup_fee"] = 0;
                    $v["billingcycle"] = "";
                    $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                }
            }
        }
        if ($paytype["pay_type"] == "recurring" && in_array($v["type"], array_keys(config("developer_app_product_type"))) && 0 < $pricing["annually"]) {
            $v["product_price"] = $pricing["annually"];
            $v["setup_fee"] = $pricing["asetupfee"];
            $v["billingcycle"] = "annually";
            $v["billingcycle_zh"] = lang("ANNUALLY");
        }
        $v["product_price"] = bcadd($v["setup_fee"], $v["product_price"], 2);
        $cart_logic = new Cart();
        $rebate_total = 0;
        $config_total = $cart_logic->getProductDefaultConfigPrice($v["id"], $currencyid, $v["billingcycle"], $rebate_total);
        $v["product_count"] = bcadd($v["product_price"], $config_total, 2);
        $v["product_price"] = $v["product_count"];
        if ($v["api_type"] == "zjmf_api" && 0 < $v["upstream_version"] && $v["upstream_price_type"] == "percent") {
            $v["product_price"] = bcmul($v["product_price"], $v["upstream_price_value"], 2) / 100;
        }
        $v["product_price"] = bcsub($v["product_price"], 0, 2);
        return [$v["product_price"], $v["billingcycle_zh"]];
    }
    public function getProductPriceArea1($pid)
    {
        $product = \think\Db::name("products")->where("id", $pid)->find();
        $pay_type = json_decode($product["pay_type"], true);
        if ($pay_type["pay_type"] == "free") {
            return [bcsub(0, 0, 2), bcsub(0, 0, 2)];
        }
        $currency = \think\Db::name("currencies")->where("default", 1)->value("id");
        $allow = $this->getProductCycle($pid, $currency);
        $cycles = $allow["cycle"];
        $price_type = config("price_type");
        $options = \think\Db::name("product_config_options")->alias("a")->field("a.id as cid,a.option_type,a.qty_minimum,a.is_discount,a.option_name,a.is_rebate")->leftJoin("product_config_groups b", "a.gid = b.id")->leftJoin("product_config_links c", "c.gid = b.id")->leftJoin("products d", "c.pid = d.id")->where("d.id", $pid)->where("a.hidden", 0)->select()->toArray();
        $range = [];
        foreach ($cycles as $cycle) {
            $billingcycle = $cycle["billingcycle"];
            $sub_min = $sub_max = $cycle["product_price"] ? bcadd($cycle["product_price"], $cycle["setup_fee"], 2) : 0;
            foreach ($options as $option) {
                $option_type = $option["option_type"];
                $pricings = \think\Db::name("pricing")->alias("a")->field("a.*,b.qty_minimum,b.qty_maximum")->leftJoin("product_config_options_sub b", "a.relid = b.id")->where("a.type", "configoptions")->where("a.currency", $currency)->where("b.hidden", 0)->where("b.config_id", $option["cid"])->select()->toArray();
                $area = [];
                foreach ($pricings as $pricing) {
                    $sub_total = $other_sub_total = 0;
                    if (judgeQuantity($option_type)) {
                        $qty_min = $pricing["qty_minimum"];
                        $qty_max = $pricing["qty_maximum"];
                        if (judgeQuantityStage($option_type)) {
                            $sum = quantityStagePrice($option["cid"], $currency, $qty_min, $billingcycle);
                            $sub_total += $sum[0];
                            $sub_total += $sum[1];
                            $sum_max = quantityStagePrice($option["cid"], $currency, $qty_max, $billingcycle);
                            $other_sub_total += $sum_max[0];
                            $other_sub_total += $sum_max[1];
                        } else {
                            $sub_total += ($pricing[$price_type[$billingcycle][0]] ?? 0) * ($qty_min < 0 ? 0 : intval($qty_min));
                            if (0 < $qty_min) {
                                $sub_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                            }
                            $other_sub_total += ($pricing[$price_type[$billingcycle][0]] ?? 0) * ($qty_max < 0 ? 0 : intval($qty_max));
                            if (0 < $qty_max) {
                                $other_sub_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                            }
                        }
                    } else {
                        if (judgeYesNo($option_type)) {
                            $sub_total += 0;
                            $other_sub_total += $pricing[$price_type[$billingcycle][0]] ?? 0;
                            $other_sub_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                        } else {
                            $sub_total += $pricing[$price_type[$billingcycle][0]] ?? 0;
                            $sub_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                            $other_sub_total += $pricing[$price_type[$billingcycle][0]] ?? 0;
                            $other_sub_total += $pricing[$price_type[$billingcycle][1]] ?? 0;
                        }
                    }
                    $area[] = $sub_total;
                    $area[] = $other_sub_total;
                }
                $sub_min += min($area);
                $sub_max += max($area);
            }
            $range[] = $sub_min;
            $range[] = $sub_max;
        }
        $min = bcsub(floatval(min($range)), 0, 2);
        $max = bcsub(floatval(max($range)), 0, 2);
        return [$min, $max];
    }
}

?>