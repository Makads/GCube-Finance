<?php

namespace itxq\apidoc;

/**
 * ApiDoc生成
 * Class ApiDoc
 * @package itxq\apidoc
 */
class ApiDoc
{
    /**
     * @var array - 结构化的数组
     */
    private $ApiTree = [];
    /**
     * @var array - 要生成API的Class类名
     */
    private $class = [];
    /**
     * @var array - 忽略生成的类方法名
     */
    private $filterMethod = ["__construct"];
    public function __construct($config)
    {
        if (isset($config["class"])) {
            $this->class = array_merge($this->class, $config["class"]);
        }
        if (isset($config["filter_method"])) {
            $this->filterMethod = array_merge($this->filterMethod, $config["filter_method"]);
        }
    }
    public function getApiDoc($type = \ReflectionMethod::IS_PUBLIC)
    {
        foreach ($this->class as $classItem) {
            $actionInfo = $this->_getActionComment($classItem, $type);
            if (1 <= count($actionInfo)) {
                $this->ApiTree[$classItem] = $this->_getClassComment($classItem);
                $this->ApiTree[$classItem]["action"] = $actionInfo;
            }
        }
        return $this->ApiTree;
    }
    private function _getClassComment($class)
    {
        try {
            $reflection = new \ReflectionClass($class);
            $classDocComment = $reflection->getDocComment();
        } catch (\Exception $exception) {
            return [];
        }
        $parse = new lib\ParseComment();
        return $parse->parseCommentToArray($classDocComment);
    }
    private function _getActionComment($class, $type = \ReflectionMethod::IS_PUBLIC)
    {
        try {
            $reflection = new \ReflectionClass($class);
            $method = $reflection->getMethods($type);
        } catch (\Exception $exception) {
            return [];
        }
        $comments = [];
        foreach ($method as $action) {
            try {
                $parse = new lib\ParseComment();
                $actionComments = $parse->parseCommentToArray($action->getDocComment());
                if (1 <= count($actionComments) && !in_array($action->name, $this->filterMethod)) {
                    $comments[$action->name] = $actionComments;
                }
            } catch (\Exception $exception) {
            }
        }
        return $comments;
    }
}

?>