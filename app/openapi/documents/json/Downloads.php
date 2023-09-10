<?php

return ["title" => "资源下载", "item" => ["getDownloads" => ["title" => "获取资源下载列表", "desc" => "获取资源下载列表", "url" => "v1/downloads", "method" => "GET", "auth" => "智简魔方", "version" => "v1", "param" => [], "return" => [["name" => "cate", "type" => "array[]", "require" => "", "max" => "-", "desc" => "资源分类", "example" => "", "child" => [["name" => "id", "type" => "int", "require" => "", "max" => "-", "desc" => "资源分类ID", "example" => "1", "child" => []], ["name" => "name", "type" => "string", "require" => "", "max" => "-", "desc" => "资源分类名称", "example" => "example", "child" => []], ["name" => "description", "type" => "string", "require" => "", "max" => "-", "desc" => "描述", "example" => "example", "child" => []], ["name" => "count", "type" => "int", "require" => "", "max" => "-", "desc" => "资源数量", "example" => "1", "child" => []]]], ["name" => "downloads", "type" => "array[]", "require" => "", "max" => "-", "desc" => "资源下载", "example" => "", "child" => [["name" => "id", "type" => "int", "require" => "", "max" => "-", "desc" => "资源ID", "example" => "1", "child" => []], ["name" => "category", "type" => "int", "require" => "", "max" => "-", "desc" => "分类ID", "example" => "1", "child" => []], ["name" => "type", "type" => "int", "require" => "", "max" => "-", "desc" => "类型", "example" => "1", "child" => []], ["name" => "title", "type" => "string", "require" => "", "max" => "-", "desc" => "资源标题", "example" => "example", "child" => []], ["name" => "description", "type" => "string", "require" => "", "max" => "-", "desc" => "描述", "example" => "example", "child" => []], ["name" => "downloads", "type" => "int", "require" => "", "max" => "-", "desc" => "资源下载次数", "example" => "1", "child" => []], ["name" => "update_time", "type" => "int", "require" => "", "max" => "-", "desc" => "资源更新时间", "example" => "1648433070", "child" => []], ["name" => "link", "type" => "string", "require" => "", "max" => "-", "desc" => "文件下载链接", "example" => "1", "child" => []]]]]], "downloads" => ["title" => "资源下载", "desc" => "资源下载", "url" => "v1/downloads/:id", "method" => "GET", "auth" => "智简魔方", "version" => "v1", "param" => [], "return" => []]]];

?>