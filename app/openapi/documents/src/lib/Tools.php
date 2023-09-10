<?php

namespace itxq\apidoc\lib;

/**
 * 工具类
 * Class Tools
 * @package itxq\apidoc\lib
 */
class Tools
{
    public static function underlineToHump($str, $isFirst = false)
    {
        $str = preg_replace_callback("/([\\-\\_]+([a-z]{1}))/i", function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
        if ($isFirst) {
            $str = ucfirst($str);
        }
        return $str;
    }
    public static function humpToUnderline($str)
    {
        $str = preg_replace_callback("/([A-Z]{1})/", function ($matches) {
            return "_" . strtolower($matches[0]);
        }, $str);
        $str = preg_replace("/^\\_/", "", $str);
        return $str;
    }
    public static function getSubValue($name, $data, $default = "")
    {
        if (is_object($data)) {
            $value = isset($data->{$name}) ? $data->{$name} : $default;
        } else {
            if (is_array($data)) {
                $value = isset($data[$name]) ? $data[$name] : $default;
            } else {
                $value = $default;
            }
        }
        return $value;
    }
    public static function downloadFile($docHtml)
    {
        set_time_limit(0);
        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Content-Disposition: attachment; filename=api-doc_" . date("Y-m-d") . ".html");
        echo $docHtml;
        exit;
    }
}

?>