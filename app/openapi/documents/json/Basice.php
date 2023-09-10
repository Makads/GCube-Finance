<?php

return ["title" => "基础信息", "item" => ["jwt" => ["title" => "头部公共请求参数", "desc" => "登录后请求的接口需要在header中增加jwt参数，jwt有效时间2小时，过时需要重新登陆", "version" => "v1", "basice" => [["name" => "authorization", "type" => "string", "require" => "要验证登陆的接口必填该参数", "max" => "-", "desc" => "在请求的头部传该参数，<font style=\"color:#f00;\">注意：JWT后面有个空格</font>", "example" => "<div style=\"width:300px;word-wrap:break-word\">authorization:JWT eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyaW5mbyI6eyJpZCI6MSwidXNlcm5hbWUiOiJcdTcxOGFcdTcwNzVcdTUxNDMifSwiaXNzIjoid3d3LmlkY1NtYXJ0LmNvbSIsImF1ZCI6Ind3dy5pZGNTbWFydC5jb20iLCJpcCI6IjEyNy4wLjAuMSIsImlhdCI6MTY0MDg0NTA1NiwibmJmIjoxNjQwODQ1MDU2LCJleHAiOjE2NDA4NTIyNTZ9.sMFtkIhPOlTJkozw3b0_8zdj-AL6pf-vQ0SlNAHers0</div>"]]], "pages" => ["title" => "分页和搜索参数", "desc" => "分页和搜索参数", "version" => "v1", "basice" => [["name" => "page", "type" => "string", "require" => "必填", "max" => "-", "desc" => "页数", "example" => "1"], ["name" => "limit", "type" => "string", "require" => "必填", "max" => "-", "desc" => "分页条数", "example" => "20"], ["name" => "orderby", "type" => "string", "require" => "", "max" => "-", "desc" => "排序字段", "example" => ""], ["name" => "sort", "type" => "string", "require" => "", "max" => "-", "desc" => "DESC降序，ASC升序，只有这有这两个值", "example" => "DESC"], ["name" => "keywords", "type" => "string", "require" => "", "max" => "-", "desc" => "搜索", "example" => ""]]], "result" => ["title" => "接口通用返回说明", "desc" => "接口通用返回信息，状态码以及返回信息说明", "return" => [["name" => "msg", "type" => "string", "require" => "", "max" => "-", "desc" => "成功或失败信息", "example" => ""]], "code" => [["name" => "200", "desc" => "成功"], ["name" => "400", "desc" => "失败"]]]]];

?>