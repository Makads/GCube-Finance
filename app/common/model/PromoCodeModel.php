<?php

namespace app\common\model;

class PromoCodeModel extends \think\Model
{
    public function getPromoCodeList()
    {
        $type = input("get.type");
        $db = db("promo_code")->field("id,code,type,recurring,max_times,used,start_time,expiration_time");
        $now = time();
        if ($type != "all") {
            if ($type == "expired") {
                $db = $db->where("(expiration_time>0 AND expiration_time<" . $now . ") OR (max_times>0 && used>=max_times)");
            } else {
                $db = $db->where("(expiration_time=0 OR expiration_time>" . $now . ") AND (max_times=0 || used<max_times)");
            }
        }
        $data = $db->select()->toArray();
        foreach ($data as $k => $v) {
            $data[$k]["start_time"] = $v["start_time"] == 0 ? "-" : date("Y-m-d H:i:s", $v["start_time"]);
            $data[$k]["expiration_time"] = $v["expiration_time"] == 0 ? "-" : date("Y-m-d H:i:s", $v["expiration_time"]);
        }
        return $data;
    }
    public function getPromoPrice($promocode, $pid, $billingcycle, $uid, $upgarde = false)
    {
        $price = [];
        if ($promocode) {
            $promo = $this->field("")->where("code", $promocode)->find();
            if (!$promo) {
                return $price;
            }
            if (empty($promo["appliesto"]) || !in_array($pid, explode(",", $promo["appliesto"]))) {
                return $price;
            }
            if (empty($promo["cycles"]) || !in_array($billingcycle, explode(",", $promo["cycles"]))) {
                return $price;
            }
            if (0 < $promo["one_time"] && $promo["used"] == 1) {
                return $price;
            }
            $user_use_count = \think\Db::name("orders")->where("uid", $uid)->where("promo_code", $promo["code"])->count();
            if (0 < $promo["once_per_client"] && 1 < $user_use_count) {
                return $price;
            }
            if (0 < $promo["max_times"] && $promo["max_times"] <= $promo["used"]) {
                return $price;
            }
            $use_order_count = \think\Db::name("orders")->where("uid", $uid)->count();
            if (0 < $promo["only_new_client"] && 0 < $use_order_count) {
                return $price;
            }
            if (0 < $promo["only_old_client"] && $use_order_count == 0) {
                return $price;
            }
            if (0 < $promo["start_time"] && time() < $promo["start_time"] || 0 < $promo["expiration_time"] && $promo["expiration_time"] < time()) {
                return $price;
            }
            $price["type"] = $promo["type"];
            $price["recurring"] = $promo["recurring"];
            $price["value"] = $promo["value"];
            $price["requires"] = $promo["requires"];
            $price["requires_exist"] = $promo["requires_exist"];
            $price["lifelong"] = $promo["lifelong"];
            $price["recurfor"] = $promo["recurfor"];
            return $price;
        }
        return $price;
    }
}

?>