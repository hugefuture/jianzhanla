<?php
// 会话配置（API 模式下主要依赖 JWT，session 仅用于验证码等场景）
return [
    'type' => 'file',
    'name' => 'PHPSESSID',
    'auto_start' => false,
    'prefix' => '',
    'expire' => 1440,
];
