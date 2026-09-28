<?php
// +----------------------------------------------------------------------
// | 全局应用配置
// +----------------------------------------------------------------------

return [
    // 默认应用（未匹配到应用名时）
    'default_app' => 'index',
    // 自动多应用模式：URL 第一段即应用名，如 /admin /api
    'auto_multi_app' => true,
    // 应用映射
    'app_map' => [],
    // 域名绑定
    'domain_bind' => [],
    // 禁止 URL 访问的应用
    'deny_app_list' => ['common'],

    // 异常页模板
    'exception_tmpl' => app()->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'think_exception.tpl',

    // 错误显示信息（非 debug 模式）
    'error_message' => '页面错误！请稍后再试～',
    'show_error_msg' => false,
];
