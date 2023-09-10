<?php

namespace itxq\apidoc\lib;

/**
 * 注释解析
 * Class ParseComment
 * @package itxq\apidoc\lib
 */
class ParseComment
{
    /**
     * @var array - 注释解析后的数组
     */
    private $commentParams = [];
    public function parseCommentToArray($comment)
    {
        $comments = [];
        if (empty($comment)) {
            return $comments;
        }
        if (preg_match("#^/\\*\\*(.*)\\*/#s", $comment, $matches) === false) {
            return $comments;
        }
        $matches = trim($matches[1]);
        if (preg_match_all("#^\\s*\\*(.*)#m", $matches, $lines) === false) {
            return $comments;
        }
        $comments = $lines[1];
        foreach ($comments as $k => $v) {
            $comments[$k] = $v = trim($v);
            if (strpos($v, "@") === 0) {
                $_parse = $this->_parseCommentLine($v);
                if ($_parse) {
                    $_type = $_parse["type"];
                    $_content = isset($_parse["content"]) ? $_parse["content"] : "";
                    if (in_array($_type, ["param", "code", "return"])) {
                        if (!isset($this->commentParams[$_type])) {
                            $this->commentParams[$_type] = [];
                        }
                        unset($_parse["type"]);
                        $this->commentParams[$_type][] = $_parse;
                    } else {
                        $this->commentParams[$_type] = $_content;
                    }
                }
            }
        }
        return $this->commentParams;
    }
    private function _parseCommentLine($line)
    {
        $line = explode(" ", $line);
        $line[0] = substr($line[0], 1);
        $class = new ParseLine();
        $action = "parseLine" . Tools::underlineToHump($line[0]);
        if (!method_exists($class, $action)) {
            $action = "parseLineTitle";
        }
        return $class->{$action}($line);
    }
}

?>