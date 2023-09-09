<?php

namespace app\home\controller;

/**
 * @title 前台API管理
 * @description 接口说明:前台API管理
 */
class ZjmfFinanceApiController extends CommonController
{
    public function resetApiPwd()
    {
        $uid = request()->uid;
        \think\Db::name("clients")->where("id", $uid)->update(["api_password" => aesPasswordEncode(randStrToPass(12, 0))]);
        return jsons(["status" => 200, "msg" => lang("SUCCESS MESSAGE")]);
    }
    public function apiOpen()
    {
        if (!configuration("allow_resource_api")) {
            return jsons(["status" => 400, "msg" => "暂未开启API功能"]);
        }
        $param = $this->request->param();
        $uid = request()->uid;
        $up = ["api_open" => intval($param["api_open"])];
        if ($param["api_open"] == 1) {
            $up["api_create_time"] = time();
        }
        \think\Db::name("clients")->where("id", $uid)->update($up);
        if ($param["api_open"] == 1) {
            return jsons(["status" => 200, "msg" => "开启成功"]);
        }
        return jsons(["status" => 200, "msg" => "关闭成功"]);
    }
    public function summary()
    {
        $uid = request()->uid;
        $client = \think\Db::name("clients")->field("api_password,api_create_time,api_open,lock_reason,api_lock_time")->where("id", $uid)->find();
        if (!judgeApi($uid)) {
            return jsons(["status" => 400, "msg" => "暂未开通API功能"]);
        }
        $client["api_password"] = aesPasswordDecode($client["api_password"]);
        $agent_pids = \think\Db::name("api_resource_log")->where("uid", $uid)->where("pid", "<>", 0)->field("pid")->distinct(true)->column("pid");
        $host_count = \think\Db::name("host")->whereIn("productid", $agent_pids)->where("uid", $uid)->count();
        $active_count = \think\Db::name("host")->where("domainstatus", "Active")->whereIn("productid", $agent_pids)->where("uid", $uid)->count();
        $client["agent_count"] = count($agent_pids);
        $client["host_count"] = $host_count;
        $client["active_count"] = $active_count;
        $yesterday_start = strtotime(date("Y-m-d", time()));
        $yesterday_end = $yesterday_start + 86400;
        $api_count = \think\Db::name("api_resource_log")->where("uid", $uid)->whereBetweenTime("create_time", $yesterday_start, $yesterday_end)->count();
        $client["api_count"] = $api_count;
        $before_yesterday_start = strtotime(date("Y-m-d", strtotime("-1 days")));
        $before_yesterday_end = $before_yesterday_start + 86400;
        $api_count2 = \think\Db::name("api_resource_log")->where("uid", $uid)->whereBetweenTime("create_time", $before_yesterday_start, $before_yesterday_end)->count();
        $ratio1 = bcdiv($api_count, $api_count2, 2) * 100;
        $client["ratio"] = $ratio1 . "%";
        $before_yesterday_start2 = strtotime(date("Y-m-d", strtotime("-2 days")));
        $before_yesterday_end2 = $before_yesterday_start2 + 86400;
        $api_count3 = \think\Db::name("api_resource_log")->where("uid", $uid)->whereBetweenTime("create_time", $before_yesterday_start2, $before_yesterday_end2)->count();
        $ratio2 = bcdiv($api_count3, $api_count2, 2) * 100;
        $client["up"] = 0;
        if ($ratio2 <= $ratio1) {
            $client["up"] = 1;
        }
        $form_api = $this->getEveryDayTotal(strtotime(date("Y-m-d", strtotime("-6 days"))));
        $free_products = \think\Db::name("api_user_product")->field("a.id,b.name,a.ontrial,a.qty")->alias("a")->leftJoin("products b", "a.pid = b.id")->where("uid", $uid)->select()->toArray();
        $data = ["client" => $client, "form_api" => $form_api, "free_products" => $free_products];
        $result = ["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data];
        return jsons($result);
    }
    private function getTotal($start, $end)
    {
        $total = \think\Db::name("api_resource_log")->where("uid", request()->uid)->whereBetweenTime("create_time", $start, $end)->count();
        return intval($total);
    }
    private function getEveryDayTotal($month_start)
    {
        $days = 7;
        $month_every_day_total = [];
        for ($i = 0; $i <= $days - 1; $i++) {
            ${$i + 1 . "_start"} = strtotime("+" . $i . " days", $month_start);
            ${$i + 1 . "_end"} = strtotime("+" . ($i + 1) . " days -1 seconds", $month_start);
            ${$i + 1 . "_total"} = $this->getTotal(${$i + 1 . "_start"}, ${$i + 1 . "_end"});
            array_push($month_every_day_total, ${$i + 1 . "_total"});
        }
        return $month_every_day_total;
    }
    public function apiLog()
    {
        $param = $this->request->param();
        $page = !empty($param["page"]) ? intval($param["page"]) : config("page");
        $limit = !empty($param["limit"]) ? intval($param["limit"]) : config("limit");
        $order = !empty($param["order"]) ? trim($param["order"]) : "a.id";
        $sort = !empty($param["sort"]) ? trim($param["sort"]) : "DESC";
        $where = function (\think\db\Query $query) use($param) {
            $query->where("a.uid", request()->uid);
            if (!empty($param["keywords"])) {
                $keyword = $param["keywords"];
                $query->where("a.ip|a.description|b.username|a.port", "like", "%" . $keyword . "%");
            }
        };
        $logs = \think\Db::name("api_resource_log")->alias("a")->field("a.id,a.create_time,a.description,a.ip,b.username,a.port")->leftJoin("clients b", "a.uid = b.id")->where($where)->withAttr("description", function ($value, $data) {
            $pattern = "/(?P<name>\\w+ ID):(?P<digit>\\d+)/";
            preg_match_all($pattern, $value, $matches);
            $name = $matches["name"];
            $digit = $matches["digit"];
            if (!empty($name)) {
                if (defined("VIEW_TEMPLATE_WEBSITE") && VIEW_TEMPLATE_WEBSITE) {
                    foreach ($name as $k => $v) {
                        $relid = $digit[$k];
                        $str = $v . ":" . $relid;
                        if ($v == "Invoice ID") {
                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/billing\"><span>" . $str . "</span></a>";
                            $value = str_replace($str, $url, $value);
                        } else {
                            if ($v == "User ID") {
                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/details\"><span>" . $str . "</span></a>";
                                $value = str_replace($str, $url, $value);
                            } else {
                                if ($v == "Host ID") {
                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/servicedetail?id=" . $relid . "\"><span>" . $str . "</span></a>";
                                    $value = str_replace($str, $url, $value);
                                } else {
                                    if ($v == "Order ID") {
                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/billing\"><span>" . $str . "</span></a>";
                                        $value = str_replace($str, $url, $value);
                                    } else {
                                        if ($v == "Ticket ID") {
                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/viewticket?tid=" . $relid . "\"><span>" . $str . "</span></a>";
                                            $value = str_replace($str, $url, $value);
                                        } else {
                                            if ($v == "Transaction ID") {
                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"/billing\"><span>" . $str . "</span></a>";
                                                $value = str_replace($str, $url, $value);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    foreach ($name as $k => $v) {
                        $relid = $digit[$k];
                        $str = $v . ":" . $relid;
                        if ($v == "Invoice ID") {
                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/finance\"><span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
                            $value = str_replace($str, $url, $value);
                        } else {
                            if ($v == "User ID") {
                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/personal-center\"><span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
                                $value = str_replace($str, $url, $value);
                            } else {
                                if ($v == "Host ID") {
                                    $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/server/log?id=" . $relid . "\"><span class=\"el-link--inner\" style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
                                    $value = str_replace($str, $url, $value);
                                } else {
                                    if ($v == "Order ID") {
                                        $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/finance?id=" . $relid . "\"><span class=\"el-link--inner\">" . $str . "</span></a>";
                                        $value = str_replace($str, $url, $value);
                                    } else {
                                        if ($v == "Ticket ID") {
                                            $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/tickets/viewticket?tid=" . $relid . "\"><span class=\"el-link--inner\"  style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
                                            $value = str_replace($str, $url, $value);
                                        } else {
                                            if ($v == "Transaction ID") {
                                                $url = "<a class=\"el-link el-link--primary is-underline\" href=\"#/finance\"><span class=\"el-link--inner\"  style=\"display: block;height: 24px;line-height: 24px;\">" . $str . "</span></a>";
                                                $value = str_replace($str, $url, $value);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $value;
            } else {
                return $value;
            }
        })->withAttr("ip", function ($value, $data) {
            if (empty($data["port"])) {
                return $value;
            }
            return $value .= ":" . $data["port"];
        })->order($order, $sort)->page($page)->limit($limit)->select()->toArray();
        $count = \think\Db::name("api_resource_log")->alias("a")->leftJoin("clients b", "a.uid = b.id")->where($where)->count();
        $data = ["logs" => $logs, "count" => $count];
        return jsonrule(["status" => 200, "msg" => lang("SUCCESS MESSAGE"), "data" => $data]);
    }
}

?>