<?php


namespace app\common\logic;

class Provision
{
    public $modules = [];
    public $is_admin = false;
    protected $hostid = 0;
    protected $params = [];
    protected $HostModel = NULL;
    protected $support = ["CreateAccount" => ["type" => "button", "auth" => "admin", "func" => "create", "des" => "开通"], "SuspendAccount" => ["type" => "button", "auth" => "admin", "func" => "suspend", "des" => "暂停"], "UnsuspendAccount" => ["type" => "button", "auth" => "admin", "func" => "unsuspend", "des" => "解除暂停"], "TerminateAccount" => ["type" => "button", "auth" => "admin", "func" => "terminate", "des" => "删除"], "Renew" => ["auth" => "both", "des" => "续费"], "ChangePackage" => ["des" => "升降级"], "On" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "on", "des" => "开机"], "Off" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "off", "des" => "关机"], "Reboot" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "reboot", "des" => "重启"], "HardOff" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "hard_off", "des" => "硬关机"], "HardReboot" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "hard_reboot", "des" => "硬重启"], "Reinstall" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "reinstall", "des" => "重装系统"], "CrackPassword" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "crack_pass", "des" => "重置密码"], "RescueSystem" => ["type" => "button", "auth" => "both", "place" => "control", "func" => "rescueSystem", "des" => "救援系统"], "Vnc" => ["type" => "button", "auth" => "both", "place" => "console", "func" => "vnc", "des" => "VNC"], "Sync" => ["type" => "button", "auth" => "admin", "place" => "control", "func" => "sync", "des" => "拉取信息"], "Status" => ["auth" => "both", "func" => "status", "des" => "获取状态"], "ManagePanel" => ["type" => "button", "auth" => "admin", "func" => "panel", "des" => "管理面板"]];
    protected $options = ["text" => ["name", "placeholder", "description", "default", "type", "key"], "password" => ["name", "placeholder", "description", "default", "type", "key"], "yesno" => ["name", "description", "default", "type", "key"], "radio" => ["name", "description", "options", "default", "type", "key"], "dropdown" => ["name", "description", "options", "default", "type", "key"], "textarea" => ["name", "placeholder", "description", "default", "rows", "cols", "type", "key"]];
    protected $other_params = [];
    protected $max_option = 23;
    private $dir = NULL;
    private $dir2 = NULL;
    public function __construct()
    {
        $this->getOriginModules();
    }
    public function __call($name, $arguments = [])
    {
        if (isset($this->support[ucfirst($name)])) {
            if ($name == "reinstall" || $name == "Reinstall") {
                list($this->other_params["os"], $this->other_params["os_name"]) = $arguments;
            } else {
                if ($name == "changePackage" || $name == "ChangePackage") {
                    $this->other_params["old_config"] = $arguments[1];
                } else {
                    if ($name == "crackPassword" || $name == "CrackPassword") {
                        $this->other_params["new_pass"] = $arguments[1];
                    }
                }
            }
            return $this->execSupportFunc($name, $arguments[0]);
        }
        return ["status" => "error", "msg" => lang("NO_SUPPORT_FUNCTION"), "no_support_function" => true];
    }
    public function getModules()
    {
        $data = [];
        foreach ($this->modules as $k => $v) {
            $data[] = ["value" => $k, "name" => $v];
        }
        return $data;
    }
    public function getModuleConfigOptions($module = "", $hidden_key = true)
    {
        $result = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return [["name" => "插件未授权"]];
                }
            }
            if (function_exists($module . "_ConfigOptions")) {
                $res = call_user_func($module . "_ConfigOptions");
                if (is_array($res)) {
                    $res = array_slice($res, 0, $this->max_option);
                    foreach ($res as $k => $v) {
                        $type = $v["type"];
                        $one = [];
                        foreach ($this->options[$type] as $kk => $vv) {
                            if (!($hidden_key && $vv == "key")) {
                                if (isset($v[$vv])) {
                                    if ($vv == "options") {
                                        if (is_string($v[$vv])) {
                                            $arr = explode(",", $v[$vv]);
                                            foreach ($arr as $vvv) {
                                                $one[$vv][] = ["value" => $vvv, "name" => $vvv];
                                            }
                                        } else {
                                            if (is_array($v[$vv])) {
                                                foreach ($v[$vv] as $kkk => $vvv) {
                                                    $one[$vv][] = ["value" => $kkk, "name" => $vvv];
                                                }
                                            }
                                        }
                                    } else {
                                        $one[$vv] = $v[$vv];
                                    }
                                } else {
                                    $one[$vv] = "";
                                }
                            }
                        }
                        $result[] = $one;
                    }
                } else {
                    $result = [];
                }
            }
        }
        return $result;
    }
    public function getModuleMetaData($module = "")
    {
        $res = ["APIVersion" => "", "HelpDoc" => ""];
        if ($this->checkAndRequire($module) && function_exists($module . "_MetaData")) {
            $data = call_user_func($module . "_MetaData");
            foreach ($res as $k => $v) {
                if (isset($data[$k])) {
                    $res[$k] = $data[$k];
                }
            }
        }
        return $res;
    }
    public function checkAndRequire($module = "")
    {
        if (!empty($module) && isset($this->modules[$module])) {
            if (file_exists($this->dir . $module . "/" . $module . ".php")) {
                require_once $this->dir . $module . "/" . $module . ".php";
                return true;
            }
            if (file_exists($this->dir2 . $module . "/" . $module . ".php")) {
                require_once $this->dir2 . $module . "/" . $module . ".php";
                return true;
            }
        }
        return false;
    }
    public function clientArea($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $html = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $html;
                }
            }
            $function = $module . "_ClientArea";
            if (function_exists($function)) {
                $data = call_user_func($function, $params);
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if (is_array($v)) {
                            if (file_exists($this->dir . $module . "/" . $v["template"]) || file_exists($this->dir2 . $module . "/" . $v["template"])) {
                                $html[] = ["key" => $k, "name" => $v["name"] ?? $k];
                            } else {
                                if (is_string($v["html"])) {
                                    $html[] = ["key" => $k, "name" => $v["name"] ?? $k];
                                }
                            }
                        } else {
                            $html[] = ["key" => $k, "name" => $k];
                        }
                    }
                }
            }
        }
        return $html;
    }
    public function clientAreaDetail($hostid, $key, $api_url = "")
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $html = "";
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $html;
                }
            }
            $function = $module . "_ClientArea";
            if (function_exists($function)) {
                $data = call_user_func($function, $params);
                $detail_func = $module . "_ClientAreaOutput";
                if (isset($data[$key]) && function_exists($detail_func)) {
                    $res = call_user_func($detail_func, $params, $key);
                    if (is_array($res)) {
                        if (file_exists($this->dir . $module . "/" . $res["template"]) || file_exists($this->dir2 . $module . "/" . $res["template"])) {
                            $view = new \think\View();
                            $view->init("Think");
                            foreach ($res["vars"] as $k => $v) {
                                $view->assign($k, $v);
                            }
                            if (!empty($api_url)) {
                                $view->assign("MODULE_CUSTOM_API", $api_url);
                            } else {
                                $view->assign("MODULE_CUSTOM_API", request()->domain() . request()->rootUrl() . "/provision/custom/" . $hostid);
                            }
                            if (file_exists($this->dir . $module . "/" . $res["template"])) {
                                $html = $view->fetch($this->dir . $module . "/" . $res["template"]);
                            } else {
                                $html = $view->fetch($this->dir2 . $module . "/" . $res["template"]);
                            }
                        }
                    } else {
                        if (is_string($res)) {
                            $html = $res;
                        } else {
                            $html = (string) $res;
                        }
                    }
                } else {
                    $html = "";
                }
            } else {
                $html = "";
            }
        } else {
            $html = "";
        }
        return $html;
    }
    public function adminArea($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $html = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $html;
                }
            }
            $function = $module . "_AdminArea";
            if (function_exists($function)) {
                $arr = call_user_func($function, $params);
                foreach ($arr as $k => $v) {
                    $html[] = ["name" => $k, "content" => (string) $v];
                }
            }
        }
        return $html;
    }
    public function clientButton($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $button = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $button;
                }
            }
            $function = $module . "_ClientButton";
            if (function_exists($function)) {
                $buttons = call_user_func($function, $params);
                if (!is_array($buttons)) {
                    $button = [];
                } else {
                    foreach ($buttons as $k => $v) {
                        if (!is_array($v)) {
                            unset($button[$k]);
                        } else {
                            $button[] = ["type" => "custom", "func" => $k, "name" => $v["name"] ?? $k, "place" => in_array($v["place"], ["control", "console"]) ? $v["place"] : "control", "desc" => $v["desc"] ?: ""];
                        }
                    }
                }
            }
        }
        return $button;
    }
    public function chart($hostid)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $button = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $button;
                }
            }
            $function = $module . "_Chart";
            if (function_exists($function)) {
                $buttons = call_user_func($function, $params);
                if (!is_array($buttons)) {
                    $button = [];
                } else {
                    foreach ($buttons as $k => $v) {
                        if (!is_array($v)) {
                            unset($button[$k]);
                        } else {
                            $button[] = ["type" => $k, "title" => $v["title"] ?: $k, "select" => $v["select"] ?? []];
                        }
                    }
                }
            }
        }
        return $button;
    }
    public function clientButtonOutput($hostid = 0)
    {
        $default_button = $this->defaultButton($hostid, false);
        $custom_button = $this->clientButton($hostid);
        $button = $default_button;
        foreach ($custom_button as $k => $v) {
            $button[$v["place"]][] = ["type" => $v["type"], "func" => $v["func"], "name" => $v["name"], "desc" => $v["desc"] ?: ""];
        }
        if (!isset($button["control"])) {
            $button["control"] = [];
        }
        if (!isset($button["console"])) {
            $button["console"] = [];
        }
        return $button;
    }
    public function execClientButton($hostid = 0, $func = "")
    {
        $params = $this->getParams($hostid);
        if (empty($params)) {
            $result["status"] = "error";
            $result["msg"] = "ID错误";
            return $result;
        }
        if ($params["domainstatus"] != "Active" || request()->uid != $params["uid"]) {
            $result["status"] = "error";
            $result["msg"] = "不能执行该操作";
            return $result;
        }
        if ($params["api_type"] == "zjmf_api") {
            $post_data["id"] = $params["dcimid"];
            $post_data["func"] = $func;
            $result = zjmfCurl($params["zjmf_api_id"], "/provision/button", $post_data);
        } else {
            $module = $params["module_type"];
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return ["status" => "error", "msg" => "插件未授权"];
                }
            }
            $button = $this->clientButton($hostid);
            if (in_array($func, array_filter(array_column($button, "func")))) {
                $function = $module . "_" . $func;
                if (function_exists($function)) {
                    $res = call_user_func($function, $params);
                    if (is_array($res)) {
                        return $res;
                    }
                    if ($res === NULL || $res == "success" || $res == "ok") {
                        return ["status" => "success"];
                    }
                    return ["status" => "error", "msg" => (string) $res];
                }
                return ["status" => "success"];
            }
            $result = ["status" => "error", "msg" => "错误的方法"];
        }
        return $result;
    }
    public function adminButton($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $button = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $button;
                }
            }
            $function = $module . "_AdminButton";
            if (function_exists($function)) {
                $buttons = call_user_func($function, $params);
                if (!is_array($buttons)) {
                    $button = [];
                } else {
                    $hide = $this->adminButtonHide($module, $params);
                    foreach ($buttons as $k => $v) {
                        if (!is_string($v) || $hide[$k] === true) {
                            unset($button[$k]);
                        } else {
                            $button[] = ["type" => "custom", "func" => $k, "name" => $v];
                        }
                    }
                }
            }
        }
        return $button;
    }
    public function defaultButton($hostid = 0, $is_admin = true)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $button = [];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return $button;
                }
            }
            $hide = $this->adminButtonHide($module, $params);
            foreach ($this->support as $k => $v) {
                if (function_exists($module . "_" . $k) && $v["type"] == "button") {
                    if ($is_admin) {
                        if (($v["auth"] == "admin" || $v["auth"] == "both") && $hide[$k] !== true) {
                            if (!(($params["domainstatus"] == "Active" || $params["domainstatus"] == "Suspended") && $k == "CreateAccount")) {
                                $button[] = ["type" => "default", "func" => $v["func"], "name" => $v["des"]];
                            }
                        }
                    } else {
                        if ($v["auth"] == "user" || $v["auth"] == "both") {
                            $button[$v["place"]][] = ["type" => "default", "func" => $v["func"], "name" => $v["des"]];
                        }
                    }
                }
            }
        }
        return $button;
    }
    public function adminButtonHide($module, $params)
    {
        $func = $module . "_AdminButtonHide";
        $result = [];
        if (function_exists($func)) {
            $res = call_user_func($func, $params);
            if (is_array($res)) {
                foreach ($res as $v) {
                    if (is_string($v)) {
                        $result[$v] = true;
                    }
                }
            }
        }
        return $result;
    }
    public function adminButtonOutput($hostid = 0)
    {
        $button = $this->defaultButton($hostid);
        $params = $this->getParams($hostid);
        if ($params["domainstatus"] == "Pending") {
            foreach ($button as $k => $v) {
                if ($v["func"] != "create") {
                    unset($button[$k]);
                }
            }
            $button = array_values($button);
        }
        if ($params["domainstatus"] != "Suspended") {
            foreach ($button as $k => $v) {
                if ($v["func"] == "unsuspend") {
                    unset($button[$k]);
                }
            }
            $button = array_values($button);
        }
        if ($params["domainstatus"] != "Pending") {
            $custom_button = $this->adminButton($hostid);
            foreach ($custom_button as $k => $v) {
                $button[] = ["type" => $v["type"], "func" => $v["func"], "name" => $v["name"]];
            }
        }
        return $button;
    }
    public function clientAreaMainOutput($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return [];
                }
            }
            $func = $module . "_ClientAreaMainOutput";
            if (function_exists($func)) {
                return call_user_func($func, $params) ?: [];
            }
        }
        return [];
    }
    public function adminAreaMainOutput($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return [];
                }
            }
            $func = $module . "_AdminAreaMainOutput";
            if (function_exists($func)) {
                return call_user_func($func, $params) ?: [];
            }
        }
        return [];
    }
    public function execAdminButton($hostid = 0, $func = "")
    {
        $params = $this->getParams($hostid);
        if (empty($params)) {
            $result["status"] = "error";
            $result["msg"] = "ID错误";
            return $result;
        }
        if ($params["api_type"] == "zjmf_api") {
            $post_data["id"] = $params["dcimid"];
            $post_data["func"] = $func;
            $result = zjmfCurl($params["zjmf_api_id"], "/provision/button", $post_data);
        } else {
            $module = $params["module_type"];
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $button = $this->adminButton($hostid);
            if (in_array($func, array_filter(array_column($button, "func")))) {
                $function = $module . "_" . $func;
                if (function_exists($function)) {
                    $res = call_user_func($function, $params);
                    if (is_array($res)) {
                        $result = $res;
                    } else {
                        if ($res === NULL || $res == "success" || $res == "ok") {
                            $result["status"] = "success";
                        } else {
                            $result["status"] = "error";
                            $result["msg"] = $module . "模块错误:" . (string) $res;
                        }
                    }
                } else {
                    $result["status"] = "error";
                    $result["msg"] = lang("NO_SUPPORT_FUNCTION");
                }
            } else {
                $result["status"] = "error";
                $result["msg"] = lang("NO_SUPPORT_FUNCTION");
            }
            if (!empty($module) && !empty($params["hostid"])) {
                if ($result["status"] == "success") {
                    active_log_final(lang("MODULE_EXEC_SUCCESS", [$module, $func, $params["hostid"]]));
                } else {
                    active_log_final(lang("MODULE_EXEC_FAILED", [$module, $func, $params["hostid"], $result["msg"]]));
                }
            }
        }
        return $result;
    }
    public function adminSave($hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return true;
                }
            }
            $function = $module . "_AdminSave";
            if (function_exists($function)) {
                return call_user_func($function, $params);
            }
        }
        return true;
    }
    public function setHost($hostid = 0)
    {
        if (!empty($hostid)) {
            $this->hostid = $hostid;
        }
        return $this;
    }
    protected function getOriginModules()
    {
        if (empty($this->modules)) {
            $modules = [];
            if (is_dir($this->dir) && ($handle = opendir($this->dir))) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != ".." && filetype($this->dir . $file) == "dir" && preg_match("/^[a-z][a-z0-9]+\$/", $file) && file_exists($this->dir . $file . "/" . $file . ".php")) {
                        require_once $this->dir . $file . "/" . $file . ".php";
                        if (function_exists($file . "_MetaData")) {
                            $data = call_user_func($file . "_MetaData");
                            $modules[$file] = $data["DisplayName"] ?: ucfirst($file);
                        } else {
                            $modules[$file] = ucfirst($file);
                        }
                    }
                }
                closedir($handle);
            }
            if (is_dir($this->dir2) && ($handle = opendir($this->dir2))) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != ".." && filetype($this->dir2 . $file) == "dir" && preg_match("/^[a-z][a-z0-9]+\$/", $file) && file_exists($this->dir2 . $file . "/" . $file . ".php")) {
                        require_once $this->dir2 . $file . "/" . $file . ".php";
                        if (function_exists($file . "_MetaData")) {
                            $data = call_user_func($file . "_MetaData");
                            $modules[$file] = $data["DisplayName"] ?: ucfirst($file);
                        } else {
                            $modules[$file] = ucfirst($file);
                        }
                    }
                }
                closedir($handle);
            }
            ksort($modules);
            $this->modules = $modules;
        }
        return $this->modules;
    }
    protected function execSupportFunc($name, $hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if (function_exists($module . "_idcsmartauthorize")) {
            $res = serverModuleIdcsmartauthorize($module);
            if ($res["status"] != 200) {
                $result["status"] = "error";
                $result["msg"] = "插件未授权";
                return $result;
            }
        }
        $define = false;
        $name = ucfirst($name);
        if ($this->checkAndRequire($module)) {
            $function = $module . "_" . $name;
            if (function_exists($function)) {
                if ($name == "Reinstall") {
                    $params["reinstall_os"] = $this->other_params["os"];
                    $params["reinstall_os_name"] = $this->other_params["os_name"];
                } else {
                    if ($name == "ChangePackage") {
                        $params["old_configoptions"] = $this->other_params["old_config"];
                    }
                }
                if ($name == "CrackPassword") {
                    $res = call_user_func($function, $params, $this->other_params["new_pass"]);
                } else {
                    $res = call_user_func($function, $params);
                }
                if (is_array($res)) {
                    $result = $res;
                } else {
                    if ($res === NULL || $res == "success" || $res == "ok") {
                        $result["status"] = "success";
                        $result["msg"] = "操作成功";
                    } else {
                        $result = ["status" => "error", "msg" => (string) $res];
                    }
                }
                $define = true;
            } else {
                $result["status"] = "error";
                $result["msg"] = lang("NO_SUPPORT_FUNCTION");
                $result["no_support_function"] = true;
            }
        } else {
            $result["status"] = "error";
            $result["msg"] = lang("NO_SUPPORT_FUNCTION");
            $result["no_support_function"] = true;
        }
        if ($this->is_admin && $result["status"] != "success" && !empty($module) && !empty($params["hostid"])) {
            $result["msg"] = $module . "模块错误:" . $result["msg"];
        }
        if (!empty($module) && !empty($params["hostid"]) && $define && $name != "Status") {
            if ($result["status"] == "success") {
                active_log_final(lang("MODULE_EXEC_SUCCESS", [$module, $this->support[$name]["des"], $params["hostid"]]), 0, 2, $params["hostid"]);
            } else {
                active_log_final(lang("MODULE_EXEC_FAILED", [$module, $this->support[$name]["des"], $params["hostid"], $result["msg"]]), 0, 2, $params["hostid"]);
            }
        }
        return $result;
    }
    public function moduleFunctionExists($name, $hostid = 0)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $name = ucfirst($name);
        if ($this->checkAndRequire($module)) {
            $function = $module . "_" . $name;
            if (function_exists($function)) {
                return true;
            }
        }
        return false;
    }
    public function execCustomFunc($name, $hostid = 0, $type = "client")
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $define = false;
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $allow_func = $module . "_AllowFunction";
            if (function_exists($allow_func)) {
                $allow = call_user_func($allow_func);
                $default = array_keys($this->support);
                $allow = array_diff($allow, $default);
                $function = $module . "_" . $name;
                if (is_array($allow[$type]) && in_array($name, $allow[$type]) && function_exists($function)) {
                    $res = call_user_func($function, $params);
                    if (is_array($res)) {
                        $result = $res;
                    } else {
                        if ($res == "success" || $res == "ok") {
                            $result["status"] = "success";
                        } else {
                            $result = ["status" => "error", "msg" => (string) $res];
                        }
                    }
                    $define = true;
                } else {
                    $result["status"] = "error";
                    $result["msg"] = lang("NO_SUPPORT_FUNCTION");
                }
            } else {
                $result["status"] = "error";
                $result["msg"] = lang("NO_SUPPORT_FUNCTION");
            }
        } else {
            $result["status"] = "error";
            $result["msg"] = lang("NO_SUPPORT_FUNCTION");
        }
        return $result;
    }
    public function getChartData($hostid = 0, $chart_data = [])
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_ChartData";
            if (function_exists($function)) {
                $params["chart"] = $chart_data;
                $result = call_user_func($function, $params);
            } else {
                $result["status"] = "error";
                $result["msg"] = lang("NO_SUPPORT_FUNCTION");
            }
        } else {
            $result["status"] = "error";
            $result["msg"] = lang("NO_SUPPORT_FUNCTION");
        }
        return $result;
    }
    public function usageUpdate($module, $hostid)
    {
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_UsageUpdate";
            if (function_exists($function)) {
                call_user_func($function, $hostid);
            }
        }
        return $result;
    }
    public function afterFlowPacketPaid($hostid, $packet)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $define = false;
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_FlowPacketPaid";
            if (function_exists($function)) {
                $params["flow_packet"]["capacity"] = $packet["capacity"];
                call_user_func($function, $params);
                $define = true;
            }
        }
        return $result;
    }
    public function testLink($module, $data)
    {
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = 400;
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_TestLink";
            if (function_exists($function)) {
                $res = call_user_func($function, $data);
                $result["status"] = 200;
                if (is_array($res)) {
                    if ($res["status"] == 200 || $res["status"] == "success") {
                        $result["data"]["server_status"] = (int) $res["data"]["server_status"];
                        if (isset($res["data"]["msg"])) {
                            $result["data"]["msg"] = $res["data"]["msg"];
                        }
                    } else {
                        $result["status"] = 400;
                        $result["msg"] = $res["msg"] ?: "连接失败";
                    }
                } else {
                    if ($res == "ok" || $res == "success") {
                        $result["data"]["server_status"] = 1;
                    } else {
                        $result["status"] = 400;
                        $result["data"]["server_status"] = 0;
                        $result["data"]["msg"] = $res ?: "连接失败";
                    }
                }
            } else {
                $result["status"] = 200;
                $result["data"]["server_status"] = 0;
                $result["data"]["msg"] = "模块未定义测试连接方法";
            }
        } else {
            $result["status"] = 200;
            $result["data"]["server_status"] = 0;
            $result["data"]["msg"] = "服务器组未关联模块";
        }
        return $result;
    }
    public function trafficUsage($hostid, $start, $end)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = 400;
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_TrafficUsage";
            if (function_exists($function)) {
                $result = call_user_func($function, $params, $start, $end);
            }
        } else {
            $result["status"] = 400;
            $result["msg"] = "模块未定义该方法";
        }
        return $result;
    }
    public function checkDefineUsage($hostid)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            $function = $module . "_TrafficUsage";
            if (function_exists($function)) {
                $result = true;
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }
    public function checkDefineFunc($hostid, $func = "")
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    return false;
                }
            }
            $function = $module . "_" . $func;
            if (function_exists($function)) {
                if ($module == "nokvm") {
                    if (empty($params["customfields"]["vserverid"]) && $params["domainstatus"] == "Pending") {
                        $result = false;
                    } else {
                        $result = true;
                    }
                } else {
                    $result = true;
                }
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }
    public function createTicket($hostid, $ticket_data)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_CreateTicket";
            $params["ticket"] = $ticket_data;
            call_user_func($function, $params);
        }
    }
    public function replyTicket($hostid, $ticket)
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $function = $module . "_ReplyTicket";
            $params["ticket"] = $ticket["ticket"];
            $params["ticket_reply"] = $ticket["ticket_reply"];
            call_user_func($function, $params);
        }
    }
    public function getParams($hostid = 0)
    {
        if (empty($this->hostModel)) {
            $this->hostModel = new \app\common\model\HostModel();
        }
        $this->setHost($hostid);
        if (!isset($this->params[$this->hostid])) {
            $params = $this->hostModel->getProvisionParams($this->hostid);
            $module_config = $this->getModuleConfigOptions($params["module_type"], false);
            if (!empty($module_config)) {
                $i = 0;
                foreach ($module_config as $v) {
                    $i++;
                    if (!empty($v["key"])) {
                        if (!isset($params["configoptions"][$v["key"]])) {
                            $params["configoptions"][$v["key"]] = $params["config_option" . $i];
                        }
                    }
                }
            }
            $this->params[$this->hostid] = $params;
        }
        return $this->params[$this->hostid] ?: [];
    }
    public function moduleCustomButton($name, $req, $hostid = 0, $type = "client")
    {
        $result["status"] = "error";
        $result["msg"] = lang("NO_SUPPORT_FUNCTION");
        return $result;
    }
    public function sslCertCustomButton($name, $req, $hostid = 0, $type = "client")
    {
        $params = $this->getParams($hostid);
        $module = $params["module_type"];
        $define = false;
        if ($this->checkAndRequire($module)) {
            if (function_exists($module . "_idcsmartauthorize")) {
                $res = serverModuleIdcsmartauthorize($module);
                if ($res["status"] != 200) {
                    $result["status"] = "error";
                    $result["msg"] = "插件未授权";
                    return $result;
                }
            }
            $allow_func = $module . "_AllowFunction";
            if (function_exists($allow_func)) {
                $allow = call_user_func($allow_func);
                $default = array_keys($this->support);
                $allow = array_diff($allow, $default);
                $function = $module . "_" . $name;
                if (is_array($allow[$type]) && in_array($name, $allow[$type]) && function_exists($function)) {
                    $res = call_user_func($function, $params, $req);
                    if (is_array($res)) {
                        $result = $res;
                    } else {
                        if ($res == "success" || $res == "ok") {
                            $result["status"] = "success";
                        } else {
                            $result = ["status" => "error", "msg" => (string) $res];
                        }
                    }
                    $define = true;
                } else {
                    $result["status"] = "error";
                    $result["msg"] = lang("NO_SUPPORT_FUNCTION");
                }
            } else {
                $result["status"] = "error";
                $result["msg"] = lang("NO_SUPPORT_FUNCTION");
            }
        } else {
            $result["status"] = "error";
            $result["msg"] = lang("NO_SUPPORT_FUNCTION");
        }
        return $result;
    }
}

?>