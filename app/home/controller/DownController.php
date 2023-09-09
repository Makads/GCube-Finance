<?php

namespace app\home\controller;

/**
 * @title 支持选项资源下载
 * @description 接口描述
 */
class DownController extends CommonController
{
    public function productFile(\think\Request $request)
    {
        $userGetCookie = userGetCookie();
        if ($userGetCookie) {
            $header["authorization"] = "JWT " . $userGetCookie;
        } else {
            $jwt = $request->jwt;
            $header["authorization"] = "JWT " . $jwt;
        }
        $type = $request->type;
        $check = new \app\http\middleware\Check();
        $res = $check->checkTokenDownloads($header);
        if ($res["status"] < 1000) {
            $uid = 0;
        } else {
            $uid = $res["id"];
        }
        $id = (int) $request->id;
        if (empty($id)) {
            return jsons(["status" => 406, "msg" => "下载id错误"]);
        }
        $download_data = \think\Db::name("downloads")->where("id", $id)->find();
        if (empty($download_data)) {
            return jsons(["status" => 406, "msg" => "未找到下载文件"]);
        }
        $clientsonly = $download_data["clientsonly"];
        $productdownload = $download_data["productdownload"];
        if ($clientsonly && empty($uid)) {
            return jsons(["status" => 406, "msg" => "请先登录再下载", "type" => 1]);
        }
        if ($productdownload) {
            $product_download_data = \think\Db::name("product_downloads")->field("id,product_id")->where("download_id", $id)->select()->toArray();
            $need_product = array_column($product_download_data, "product_id");
            if (!empty($need_product)) {
                $exists_data = \think\Db::name("host")->field("id, domainstatus")->where("uid", $uid)->whereIn("productid", $need_product[0])->where("domainstatus", "Active")->select()->toArray();
                if (empty($exists_data[0])) {
                    $product_data = \think\Db::name("products")->field("*")->whereIn("id", $need_product[0])->select()->toArray();
                    $currency = $this->currencyPriority("", $uid);
                    $currencyid = $currency["id"];
                    foreach ($product_data as $key => $v) {
                        if (!empty($v)) {
                            $paytype = (array) json_decode($v["pay_type"]);
                            $pricing = \think\Db::name("pricing")->where("type", "product")->where("relid", $v["id"])->where("currency", $currencyid)->find();
                            if (!empty($paytype["pay_ontrial_status"])) {
                                if (0 <= $pricing["ontrial"]) {
                                    $v["product_price"] = $pricing["ontrial"];
                                    $v["setup_fee"] = $pricing["ontrialfee"];
                                    $v["billingcycle"] = "ontrial";
                                    $v["billingcycle_zh"] = lang("ONTRIAL");
                                } else {
                                    $v["product_price"] = number_format(0, 2);
                                    $v["setup_fee"] = number_format(0, 2);
                                    $v["billingcycle"] = "";
                                    $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                                }
                            }
                            if ($paytype["pay_type"] == "free") {
                                $v["product_price"] = number_format(0, 2);
                                $v["setup_fee"] = number_format(0, 2);
                                $v["billingcycle"] = "free";
                                $v["billingcycle_zh"] = lang("FREE");
                            } else {
                                if ($paytype["pay_type"] == "hour") {
                                    if (0 <= $pricing["hour"]) {
                                        $v["product_price"] = $pricing["hour"];
                                        $v["setup_fee"] = $pricing["hsetupfee"];
                                        $v["billingcycle"] = "hour";
                                        $v["billingcycle_zh"] = lang("HOUR");
                                    } else {
                                        $v["product_price"] = number_format(0, 2);
                                        $v["setup_fee"] = number_format(0, 2);
                                        $v["billingcycle"] = "";
                                        $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                                    }
                                } else {
                                    if ($paytype["pay_type"] == "day") {
                                        if (0 <= $pricing["day"]) {
                                            $v["product_price"] = $pricing["day"];
                                            $v["setup_fee"] = $pricing["dsetupfee"];
                                            $v["billingcycle"] = "day";
                                            $v["billingcycle_zh"] = lang("DAY");
                                        } else {
                                            $v["product_price"] = number_format(0, 2);
                                            $v["setup_fee"] = number_format(0, 2);
                                            $v["billingcycle"] = "";
                                            $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                                        }
                                    } else {
                                        if ($paytype["pay_type"] == "onetime") {
                                            if (0 <= $pricing["onetime"]) {
                                                $v["product_price"] = $pricing["onetime"];
                                                $v["setup_fee"] = $pricing["osetupfee"];
                                                $v["billingcycle"] = "onetime";
                                                $v["billingcycle_zh"] = lang("ONETIME");
                                            } else {
                                                $v["product_price"] = number_format(0, 2);
                                                $v["setup_fee"] = number_format(0, 2);
                                                $v["billingcycle"] = "";
                                                $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                                            }
                                        } else {
                                            if (!empty($pricing) && $paytype["pay_type"] == "recurring") {
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
                                                                                                    $v["product_price"] = number_format(0, 2);
                                                                                                    $v["setup_fee"] = number_format(0, 2);
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
                                            } else {
                                                $v["product_price"] = number_format(0, 2);
                                                $v["setup_fee"] = number_format(0, 2);
                                                $v["billingcycle"] = "";
                                                $v["billingcycle_zh"] = lang("PRICE_NO_CONFIG");
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $product_data[$key] = $v;
                    }
                    $product_name_arr = array_column($product_data, "name");
                    $info = "您需要购买并激活产品" . implode(",", $product_name_arr) . "才能下载此文件";
                    return jsons(["status" => 406, "msg" => $info, "type" => 2, "pid" => $need_product[0], "cylcle" => $product_data[0]["billingcycle"]]);
                }
            } else {
                return jsons(["status" => 406, "msg" => "你所下载的文件还未绑定相关产品，暂时无法下载", "data" => ["type" => 3]]);
            }
        }
        if ($type == 1) {
            return jsons(["status" => 200, "msg" => "成功"]);
        }
        $filename = $download_data["location"];
        if ($download_data["filetype"] == "remote") {
            \think\Db::name("downloads")->where("id", $id)->setInc("downloads");
            ob_clean();
            return jsonrule(["status" => 200, "data" => $this->redirect($download_data["locationname"], 302)]);
        }
        if (file_exists(UPLOAD_PATH_DWN . "support/" . $filename)) {
            \think\Db::name("downloads")->where("id", $id)->setInc("downloads");
            ob_clean();
            return download(UPLOAD_PATH_DWN . "support/" . $filename, $download_data["locationname"]);
        }
        return jsons(["status" => 406, "msg" => "资源走丢了"]);
    }
    public function downloadAppFile(\think\Request $request)
    {
        $pid = $request->id;
        $product = \think\Db::name("products")->field("app_file")->where("id", $pid)->find();
        if (empty($product)) {
            return jsons(["status" => 400, "msg" => "应用不存在"]);
        }
        $url = CMF_ROOT . "public" . config("app_file_url");
        $app_file = explode(",", $product["app_file"]);
        if (!empty($app_file[0]) && file_exists($url . $app_file[0])) {
            list($new_name) = explode("^", $app_file[0]);
            return $this->download($url . $app_file[0], $new_name);
        }
        return jsons(["status" => 400, "msg" => "资源走丢了"]);
    }
    public function downloadDeveloperFile(\think\Request $request)
    {
        $userGetCookie = userGetCookie();
        if ($userGetCookie) {
            $header["authorization"] = "JWT " . $userGetCookie;
        } else {
            $jwt = $request->jwt;
            $header["authorization"] = "JWT " . $jwt;
        }
        $check = new \app\http\middleware\Check();
        $res = $check->checkTokenDownloads($header);
        if ($res["status"] < 1000) {
            $uid = 0;
        } else {
            $uid = $res["id"];
        }
        $pid = $request->id;
        $product = \think\Db::name("products")->field("app_file")->where("id", $pid)->where("p_uid", $uid)->find();
        if (empty($product)) {
            return jsons(["status" => 400, "msg" => "应用不存在"]);
        }
        $url = CMF_ROOT . "public" . config("app_file_url");
        $app_file = explode(",", $product["app_file"]);
        if (!empty($app_file[0]) && file_exists($url . $app_file[0])) {
            list($new_name) = explode("^", $app_file[0]);
            return $this->download($url . $app_file[0], $new_name);
        }
        return jsons(["status" => 400, "msg" => "资源走丢了"]);
    }
    public function downloadMarketFile(\think\Request $request)
    {
        $userGetCookie = userGetCookie();
        if ($userGetCookie) {
            $header["authorization"] = "JWT " . $userGetCookie;
        } else {
            $jwt = $request->jwt;
            $header["authorization"] = "JWT " . $jwt;
        }
        $check = new \app\http\middleware\Check();
        $res = $check->checkTokenDownloads($header);
        if ($res["status"] < 1000) {
            $uid = 0;
        } else {
            $uid = $res["id"];
        }
        $pid = $request->id;
        $product = \think\Db::name("products")->field("app_file")->where("id", $pid)->where("p_uid", ">", 0)->where("app_status", 1)->where("retired", 0)->where("hidden", 0)->find();
        if (empty($product)) {
            return jsons(["status" => 400, "msg" => "应用不存在"]);
        }
        $host = \think\Db::name("host")->alias("a")->leftJoin("products b", "b.id=a.productid")->where("b.id", $pid)->where("a.uid", $uid)->where("a.domainstatus", "Active")->where("a.nextduedate=0 OR a.nextduedate>" . time())->column("a.id");
        if (empty($host)) {
            return jsons(["status" => 400, "msg" => "未购买应用不可下载应用文件"]);
        }
        $url = CMF_ROOT . "public" . config("app_file_url");
        $app_file = explode(",", $product["app_file"]);
        if (!empty($app_file[0]) && file_exists($url . $app_file[0])) {
            list($new_name) = explode("^", $app_file[0]);
            return $this->download($url . $app_file[0], $new_name);
        }
        return jsons(["status" => 400, "msg" => "资源走丢了"]);
    }
    private function download($file_url, $new_name = "")
    {
        if (!isset($file_url) || trim($file_url) == "") {
            echo "500";
        }
        if (!file_exists($file_url)) {
            echo "404";
        }
        $filename = $new_name;
        $file = $file_url;
        if (!file_exists($file)) {
            exit("抱歉，文件不存在！");
        }
        $type = filetype($file);
        $today = date("F j, Y, g:i a");
        $time = time();
        header("Content-type: " . $type);
        header("Content-Disposition: attachment;filename=" . $filename);
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Content-Type: application/zip");
        ob_clean();
        flush();
        set_time_limit(0);
        echo readfile($file);
    }
    private function currencyPriority($currencyId = "", $uid = "")
    {
        if (!empty($currencyId)) {
            $currencyId = intval($currencyId);
            $currency = \think\Db::name("currencies")->where("id", $currencyId)->find();
        } else {
            $currency = \think\Db::name("clients")->field("currency")->where("id", $uid)->find();
            if (!empty($currency["currency"])) {
                $currency = \think\Db::name("currencies")->where("id", $currency["currency"])->find();
            } else {
                $currency = \think\Db::name("currencies")->where("default", 1)->find();
            }
        }
        $currency = array_map(function ($v) {
            return is_string($v) ? htmlspecialchars_decode($v, ENT_QUOTES) : $v;
        }, $currency);
        unset($currency["format"]);
        unset($currency["rate"]);
        unset($currency["default"]);
        return $currency;
    }
    public function cates(\think\Request $request)
    {
        $param = $request->param();
        $cate_id = $param["cate_id"] ? intval($param["cate_id"]) : 0;
        $check = new \app\http\middleware\Check();
        $res = $check->checkToken($request);
        if ($res["status"] < 1000) {
            $uid = 0;
        } else {
            $uid = $res["id"];
        }
        $returndata = [];
        $download_logic = new \app\common\logic\Download();
        if ($cate_id == 0) {
            $cats_data = $download_logic->getCatesDownload1(0);
            $cate_id = $cats_data[0]["id"];
        }
        if ($cate_id) {
            $returndata["downloads"] = \app\common\model\DownloadsModel::getAllowDownListHome($cate_id, $uid);
        }
        $cate_data = $download_logic->getClassifiedDownloadRecords($cate_id, $uid);
        $returndata["cate_data"] = $cate_data;
        return jsonrule(["status" => 200, "data" => $returndata]);
    }
    public function search(\think\Request $request)
    {
        if ($request->isPost()) {
            $param = $request->param();
            $uid = cmf_get_current_user_id();
            $search = strval($param["search"]);
            if (empty($search)) {
                return json(["status" => 200, "data" => []]);
            }
            $returndata["downloads"] = \app\common\model\DownloadsModel::seachFileHome($search, $uid);
            return jsonrule(["status" => 200, "data" => $returndata]);
        }
    }
}

?>