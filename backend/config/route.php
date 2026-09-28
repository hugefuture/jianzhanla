<?php
// 路由配置
return [
    // pathinfo 分隔符
    'pathinfo_depr' => '/',
    // URL 伪静态后缀
    'url_html_suffix' => 'html',
    // URL 普通方式参数
    'url_common_param' => true,
    // 是否开启路由延迟解析
    'url_lazy_route' => false,
    // 是否强制使用路由
    'url_route_must' => false,
    // 合并路由规则
    'route_rule_merge' => false,
    // 路由完全匹配
    'route_complete_match' => false,
    // 请求缓存
    'request_cache' => false,
    // 默认的跳转页面
    'dispatch_success_tmpl' => app()->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'dispatch_jump.tpl',
    'dispatch_error_tmpl' => app()->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'dispatch_jump.tpl',
];
