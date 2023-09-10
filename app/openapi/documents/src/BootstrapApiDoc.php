<?php

namespace itxq\apidoc;

/**
 * BootstrapAPI文档生成
 * Class BootstrapApiDoc
 * @package itxq\apidoc
 */
class BootstrapApiDoc extends ApiDoc
{
    /**
     * @var string - Bootstrap CSS文件路径
     */
    private $bootstrapCss = NULL;
    /**
     * @var string - Bootstrap JS文件路径
     */
    private $bootstrapJs = NULL;
    /**
     * @var string - jQuery Js文件路径 
     */
    private $jQueryJs = NULL;
    /**
     * @var string - 自定义CSS
     */
    private $customCss = "<style type=\"text/css\">\n\t\tbody{font-size:14px;}\n        ::-webkit-scrollbar {width: 5px;}\n        .navbar-collapse.collapse.show::-webkit-scrollbar {width: 0; height: 0;background-color: rgba(255, 255, 255, 0);}\n        ::-webkit-scrollbar-track {background-color: rgba(255, 255, 255, 0.2);-webkit-border-radius: 2em;-moz-border-radius: 2em;border-radius: 2em;}\n        ::-webkit-scrollbar-thumb {background-color: rgba(0, 0, 0, 0.8);-webkit-border-radius: 2em;-moz-border-radius: 2em;border-radius: 2em;}\n        ::-webkit-scrollbar-button {-webkit-border-radius: 2em;-moz-border-radius: 2em;border-radius: 2em;height: 0;background-color: rgba(0, 0, 0, 0.9);}\n        ::-webkit-scrollbar-corner {background-color: rgba(0, 0, 0, 0.9);}\n        #list-tab-left-nav{display: none;}\n\t\t.class-item .class-title {text-indent: 0.6em;border-left: 5px solid lightseagreen;font-size: 24px;margin: 15px 0;}\n\t\t.navbar{height:60px;}\n\t\t.side-nav {\n        position: absolute;\n    width: 300px;\n    left: 0;\n    bottom: 0px;\n    top: 60px;\n    overflow: auto;\n}\n.side-content{\n\tposition: absolute;\n    right: 0;   \n    bottom: 0px;\n    top: 60px;\n    overflow-y: auto;\n}\n        .side-nav-item {\ndisplay: block;\npadding: 10px 15px 10px 15px;\nbackground-color: #FFFFFF;\ncursor: pointer;\nbox-shadow: 0 1px 1px rgba(0, 0, 0, .05);\n-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .05);\n}\n\n.item-title {\nbackground-color: #F5F5F5;\nborder-top-left-radius: 3px;\nborder-top-right-radius: 3px;\nborder-bottom: 1px solid #DDDDDD;\n}\n\n.panel-heading {\nmargin-top: 5px;\npadding: 0;\nborder-radius: 3px;\nborder: 1px solid transparent;\nborder-color: #DDDDDD;\n}\n\n.item-body {\npadding: 10px 15px 5px 15px;\nborder-bottom: 1px solid #DDDDDD;\n}\n\n.item-second {\nmargin-top: 5px;\ncursor: pointer;\n}\n\n.item-second a {\ndisplay: block;\nheight: 100%;\nwidth: 100%;\n}\n.at{ color:red;}\n.container-fluid{padding-right:0px;}\n.list-unstyled {\n    padding-left: 10px;\n    list-style: none;\n\tfont-size:13px;\n}\n.child{display:inline-block;width:20px; height:20px; line-height: 10px;text-align: center;margin-right: 10px;cursor: pointer; font-size: 20px;color: #888;}\n.child_1{display:inline-block;width:20px; height:20px; color: #999;line-height: 15px;text-align: center;margin-right: 10px;font-size: 16px;}\n    </style>";
    /**
     * @var string - 自定义JS
     */
    private $customJs = "<script type=\"text/javascript\">\n         \$(document).ready(function(){\nvar path=window.location.pathname;  //先得到地址栏内容\nvar regExp=/[\\/\\.\\?]+/;\nstr=path.split(regExp);\nvar node=str.slice(-2,-1);   //截取地址栏信息得到文件名\n//\$(#'+node+' a').addClass('at');  //提前写好对应的id,菜单加亮\n//\$(\"#\"+node).parent().parent().parent().addClass(\"in\"); //id父级的父级的父级添加展开class \n\$(\".table .child\").click(function(){\n\tvar clas=\$(this).parent().parent().next().attr(\"class\");\n\tif(\$(\".\"+clas).is(\":hidden\")){\n\t\t\$(\".\"+clas).show();\n\t\t\$(this).html(\" _ \");\n\t}else{\n\t\tif(\$(this).parent().parent().data(\"child\")==\"child\"){\n\t\t\tvar child=\$(this).parent().parent().next().data(\"child\");\n\t\t\t\n\t\t\tvar tr=\$(this).parent().parent().siblings();\n\t\t\ttr.each(function(i){\n\t\t\t\tif(\$(this).data(\"child\")==child){\n\t\t\t\t\t\$(this).hide();\n\t\t\t\t\t\$(this).find(\".child\").html(\" + \");\n\t\t\t\t}\n\t\t\t});\n\t\t}\n\t\t\$(\".\"+clas).hide();\n\t\t\$(this).html(\" + \");\n\t}\n});\n})\n\n    </script>";
    public function __construct($config)
    {
        parent::__construct($config);
        $this->bootstrapJs = lib\Tools::getSubValue("bootstrap_js", $config, $this->bootstrapJs);
        $this->jQueryJs = lib\Tools::getSubValue("jquery_js", $config, $this->jQueryJs);
        $this->customJs .= lib\Tools::getSubValue("custom_js", $config, "");
        $this->bootstrapCss = lib\Tools::getSubValue("bootstrap_css", $config, $this->bootstrapCss);
        $this->customCss .= lib\Tools::getSubValue("custom_css", $config, "");
        $this->_getCss();
        $this->_getJs();
        $this->config = (include __DIR__ . "/../config.php");
    }
    public function getHtml($type = \ReflectionMethod::IS_PUBLIC)
    {
        $_readDir = $this->config;
        $docTitle = [];
        $doc = [];
        foreach ($_readDir as $key => $classFile) {
            $file = __DIR__ . "/../json/" . $classFile . ".php";
            if (file_exists($file)) {
                $docClass = (include $file);
                $name = $classFile;
                if (is_array($docClass["class"])) {
                    foreach ($docClass["class"] as $class) {
                        $file2 = __DIR__ . "/../json/" . $class . ".php";
                        if (file_exists($file2)) {
                            $docClass["itemArr"][] = (include $file2);
                        }
                    }
                }
                unset($docClass["class"]);
                $doc[$name] = $docClass;
            }
        }
        $html = "        <!DOCTYPE html>\n        <html lang=\"zh-CN\">\n        <head>\n            <meta charset=\"utf-8\">\n            <meta name=\"renderer\" content=\"webkit\">\n            <meta http-equiv=\"X-UA-Compatible\" content=\"IE=Edge,chrome=1\">\n            <!-- 禁止浏览器初始缩放 -->\n            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no, maximum-scale=1, user-scalable=0\">\n            <title>魔方财务API文档</title>\n            " . $this->customCss . "\n        </head>\n        <body>\n\t\t<nav class=\"navbar navbar-expand-sm navbar-dark bg-dark\">\n\t\t\t<a class=\"navbar-brand\" href=\"#\">魔方财务API文档</a>\n\t\t\t<button class=\"navbar-toggler\" type=\"button\" data-toggle=\"collapse\" data-target=\"#navbarColor01\" >\n\t\t\t   <span class=\"navbar-toggler-icon\"></span>\n\t\t\t</button>\n        </nav>\n        <div class=\"container-fluid\">\t\n\t\t\t<div class=\"row\">\n\t\t\t\t<div class=\"col-md-2 side-nav\">" . $this->_getTopNavList($doc) . "</div>\n\t\t\t\t<div class=\"col-md-10 side-content\">" . $this->_getDocList($doc) . "</div>\n\t\t\t</div>\n        </div>\n        " . $this->customJs . "\n        </body>\n        </html>";
        if (isset($_GET["download"]) && $_GET["download"] === "api_doc_php") {
            lib\Tools::downloadFile($html);
            return true;
        }
        return $html;
    }
    private function _getReturnData($data = [], $class_action_Name)
    {
        $html = "";
        if (!is_array($data) || count($data) < 1) {
            return $html;
        }
        $html .= "<div class=\"table-item col-md-12\"><p class=\"table-title\"><span class=\"btn  btn-sm btn-success\">返回参数</span></p>";
        $html .= "<table class=\"table table-bordered\"><tr><td width=\"350\">参数</td><td width=\"100\">类型</td><td width=\"100\">验证规则</td><td width=\"100\">最大长度</td><td>描述</td><td>示例</td></tr>";
        $html .= _docHtml($data, $class_action_Name . "_return");
        $html .= "</table></div>";
        return $html;
    }
    private function _getParamData($data = [], $class_action_Name)
    {
        $html = "";
        if (!is_array($data) || count($data) < 1) {
            return $html;
        }
        $html .= "<div class=\"table-item col-md-12\"><p class=\"table-title\"><span class=\"btn  btn-sm btn-danger\">请求参数</span></p>";
        $html .= "<table class=\"table table-bordered\"><tr><td width=\"350\">参数</td><td width=\"100\">类型</td><td width=\"100\">验证规则</td><td width=\"100\">最大长度</td><td>描述</td><td>示例</td></tr>";
        $html .= _docHtml($data, $class_action_Name . "_param");
        $html .= "</table></div>";
        return $html;
    }
    private function _getBasiceData($data = [], $class_action_Name)
    {
        $html = "";
        if (!is_array($data) || count($data) < 1) {
            return $html;
        }
        $html .= "<div class=\"table-item col-md-12\"><p class=\"table-title\"><span class=\"btn  btn-sm btn-primary\">参数说明</span></p>";
        $html .= "<table class=\"table table-bordered\"><tr><td width=\"350\">参数</td><td width=\"100\">类型</td><td width=\"100\">验证规则</td><td width=\"100\">最大长度</td><td>描述</td><td>示例</td></tr>";
        $html .= _docHtml($data, $class_action_Name . "_param");
        $html .= "</table></div>";
        return $html;
    }
    private function _getCodeData($data = [], $class_action_Name)
    {
        $html = "";
        if (!is_array($data) || count($data) < 1) {
            return $html;
        }
        $html .= "<div class=\"table-item col-md-12\"><p class=\"table-title\"><span class=\"btn  btn-sm btn-warning\">状态码说明</span></p>";
        $html .= "<table class=\"table table-bordered\"><tr><td>状态码</td><td>描述</td></tr>";
        foreach ($data as $v) {
            $html .= "<tr>\n                        <td>" . $v["name"] . "</td>\n                        <td>" . $v["desc"] . "</td>\n                      </tr>";
        }
        $html .= "</table></div>";
        return $html;
    }
    private function _getActionItem($className, $actionName, $actionItem)
    {
        $html = "";
        if ($className == "Basice") {
            if ($actionItem["method"]) {
                $html .= "<p>请求方式：<span class=\"btn btn-info btn-sm\">" . $actionItem["method"] . "</span></p>";
            }
            if ($actionItem["desc"]) {
                $html .= "<p>描述：" . $actionItem["desc"] . "</p>";
            }
            if ($actionItem["version"]) {
                $html .= "<p>版本：" . $actionItem["version"] . "</p>";
            }
        } else {
            $html .= "<p>描述：" . $actionItem["desc"] . "</p>";
            $html .= "<p>请求方式：<span class=\"btn btn-info btn-sm\">" . $actionItem["method"] . "</span></p>";
            $html .= "<p>请求地址：<span>" . $actionItem["url"] . "</span></p>";
            $html .= "<p>版本：" . $actionItem["version"] . "</p>";
            $html .= "<p>内部API调用方法名：" . $className . "_" . $actionName . "</p>";
        }
        $html = "                <div class=\"list-group-item list-group-item-action action-item  col-md-12\" id=\"" . $className . "_" . $actionName . "\">\n                    <div class=\"table-item col-md-12\">\n\t\t\t\t\t<h4 class=\"action-title\">API - " . $actionItem["title"] . "</h4>\n\t\t\t\t\t" . $html . "\n\t\t\t\t\t</div>\n                    " . $this->_getBasiceData($actionItem["basice"], $className . "_" . $actionName) . "\n                    " . $this->_getParamData($actionItem["param"], $className . "_" . $actionName) . "\n                    " . $this->_getReturnData($actionItem["return"], $className . "_" . $actionName) . "\n                    " . $this->_getCodeData($actionItem["code"], $className . "_" . $actionName) . "\n                </div>";
        return $html;
    }
    private function _getClassItem($className, $classItem, $action)
    {
        $title = $classItem["title"];
        $actionHtml = "";
        $i = 0;
        if (is_array($classItem["itemArr"])) {
            foreach ($classItem["itemArr"] as $itemArr) {
                foreach ($itemArr["item"] as $actionName => $actionItem) {
                    if ($action == $actionName) {
                        $actionHtml .= $this->_getActionItem($className, $actionName, $actionItem);
                    } else {
                        if (empty($action) && $i == 0) {
                            $actionHtml .= $this->_getActionItem($className, $actionName, $actionItem);
                            $i = 1;
                        }
                    }
                }
            }
        } else {
            foreach ($classItem["item"] as $actionName => $actionItem) {
                if ($action == $actionName) {
                    $actionHtml .= $this->_getActionItem($className, $actionName, $actionItem);
                } else {
                    if (empty($action) && $i == 0) {
                        $actionHtml .= $this->_getActionItem($className, $actionName, $actionItem);
                        $i = 1;
                    }
                }
            }
        }
        $html = "                    <div class=\"class-item\" id=\"" . $className . "\">\n                        <h2 class=\"class-title\">" . $title . "</h2>\n                        <div class=\"list-group\">" . $actionHtml . "</div>\n                    </div>";
        return $html;
    }
    private function _getDocList($data)
    {
        $html = "";
        if (count($data) < 1) {
            return $html;
        }
        $html .= "<div class=\"doc-content\">";
        $module = $_GET["module"];
        $action = $_GET["action"];
        $i = 0;
        foreach ($data as $className => $classItem) {
            if ($module == $className) {
                $html .= $this->_getClassItem($className, $classItem, $action);
            } else {
                if (empty($module) && $i == 0) {
                    $html .= $this->_getClassItem($className, $classItem, $action);
                    $i = 1;
                }
            }
        }
        $html .= "</div>";
        return $html;
    }
    private function _getTopNavList($data)
    {
        $html = "<div class=\"panel-group\" id=\"accordion\">";
        $module = $_GET["module"];
        $i = 0;
        foreach ($data as $className => $classItem) {
            $show = "";
            if ($module == $className) {
                $show = "show";
            } else {
                if (empty($module) && $i == 0) {
                    $show = "show";
                    $i = 1;
                }
            }
            $html .= "<div class=\"panel-heading panel\">";
            $html .= "<a data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#item-" . $className . "\" class=\"side-nav-item item-title\">" . $classItem["title"] . "</a>";
            $html .= "<div id=\"item-" . $className . "\" class=\"panel-collapse collapse " . $show . "\"><div class=\"item-body\"><ul class=\"list-unstyled\">";
            if (is_array($classItem["itemArr"])) {
                foreach ($classItem["itemArr"] as $itemArr) {
                    $html .= "<li class=\"item-second\"><strong>" . $itemArr["title"] . "</strong></li>";
                    foreach ($itemArr["item"] as $actionName => $actionItem) {
                        $id = "module=" . $className . "&action=" . $actionName;
                        $html .= "<li class=\"item-second\"><a href=\"?" . $id . "\"> " . $actionItem["title"] . "</a></li>";
                    }
                }
            } else {
                foreach ($classItem["item"] as $actionName => $actionItem) {
                    $id = "module=" . $className . "&action=" . $actionName;
                    $html .= "<li class=\"item-second\"><a href=\"?" . $id . "\">" . $actionItem["title"] . "</a></li>";
                }
            }
            $html .= "</ul></div></div></div>";
        }
        $html .= "</div>";
        return $html;
    }
    private function _getCss()
    {
        $path = realpath($this->bootstrapCss);
        if (!$path || !is_file($path)) {
            return $this->customCss;
        }
        $bootstrapCss = file_get_contents($path);
        if (empty($bootstrapCss)) {
            return $this->customCss;
        }
        $this->customCss = "<style type=\"text/css\">" . $bootstrapCss . "</style>" . $this->customCss;
        return $this->customCss;
    }
    private function _getJs()
    {
        $bootstrapJs = realpath($this->bootstrapJs);
        $jQueryJs = realpath($this->jQueryJs);
        if (!$bootstrapJs || !$jQueryJs || !is_file($bootstrapJs) || !is_file($jQueryJs)) {
            $this->customJs = "";
            return $this->customCss;
        }
        $bootstrapJs = file_get_contents($bootstrapJs);
        $jQueryJs = file_get_contents($jQueryJs);
        if (empty($bootstrapJs) || empty($jQueryJs)) {
            $this->customJs = "";
            return $this->customJs;
        }
        $js = "<script type=\"text/javascript\">" . $jQueryJs . "</script>" . "<script type=\"text/javascript\">" . $bootstrapJs . "</script>";
        $this->customJs = $js . $this->customJs;
        return $this->customJs;
    }
}
function _docRreadDir($dir = "", $type = "fileDisk")
{
    if (!is_dir($dir)) {
        return false;
    }
    $handle = opendir($dir);
    $readDir = [];
    while (($file = readdir($handle)) !== false) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dir . "/" . $file) && $type != "fileDir") {
                if ($type == "fileDisk") {
                    $readDir[] = $dir . "/" . $file;
                } else {
                    if ($type == "fileName") {
                        $readDir[] = (string) $file;
                    }
                }
            } else {
                if ($type == "fileDir") {
                    $readDir[] = $dir . "/" . $file;
                }
            }
        }
    }
    if (!readdir($handle)) {
        closedir($handle);
    }
    return $readDir;
}
function _docHtml($data, $class_action_Name, $type = 0, $key = 0)
{
    $html = "";
    foreach ($data as $k => $v) {
        if (is_array($v["child"]) && 0 < count($v["child"])) {
            $type = $type ?: 1;
            $class = $class_action_Name . "_type_" . $key . "_" . $type;
            $nbsp = "";
            for ($i = 0; $i <= $type; $i++) {
                $nbsp .= "&nbsp;";
            }
            if (1 < $type) {
                $child = "<span class=\"child_1\">L</span>";
                $class2 = $class_action_Name . "_type_" . $key;
            } else {
                $style = "";
                $child = "";
                $key = $k;
                $class2 = "child";
            }
            $html .= "<tr style=\"" . $style . "\" class=\"" . $class . "\" data-child=\"" . $class2 . "\">\n\t\t\t<td>" . $nbsp . $child . "<span class=\"child\">_</span>" . $v["name"] . "</td>\n\t\t\t<td>" . $v["type"] . "</td>\n\t\t\t<td>" . $v["require"] . "</td>\n\t\t\t<td>" . $v["max"] . "</td>\n\t\t\t<td>" . $v["desc"] . "</td>\n\t\t\t<td>" . $v["example"] . "</td>\n\t\t\t</tr>";
            $html .= _docHtml($v["child"], $class_action_Name, $type + 1, $key);
        } else {
            $child = $nbsp = "";
            for ($i = 0; $i <= $type; $i++) {
                $nbsp .= "&nbsp;&nbsp;&nbsp;";
            }
            if ($type == 1) {
                $nbsp = "&nbsp;&nbsp;&nbsp;";
            }
            if (1 < $type) {
                $child = "<span class=\"child_1\">L</span>";
            } else {
                $style = "";
                $child = "";
                $key = $k;
            }
            $class = $class_action_Name . "_type_" . $key . "_" . $type;
            $class2 = $class_action_Name . "_type_" . $key;
            $html .= "<tr style=\"" . $style . "\" class=\"" . $class . "\" data-child=\"" . $class2 . "\">\n\t\t\t<td>" . $nbsp . $child . $v["name"] . "</td>\n\t\t\t<td>" . $v["type"] . "</td>\n\t\t\t<td>" . $v["require"] . "</td>\n\t\t\t<td>" . $v["max"] . "</td>\n\t\t\t<td>" . $v["desc"] . "</td>\n\t\t\t<td>" . $v["example"] . "</td>\n\t\t\t</tr>";
        }
    }
    return $html;
}

?>