<?php

use think\facade\Env;

return [
    // 默认缓存驱动
    'default' => Env::get('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores' => [
        'file' => [
            'type' => 'file',
            'path' => '',
            'prefix' => '',
            'expire' => 0,
            'tag_prefix' => 'tag:',
            'serialize' => [],
        ],
        'redis' => [
            'type' => 'redis',
            'host' => Env::get('redis.host', '127.0.0.1'),
            'port' => (int) Env::get('redis.port', 6379),
            'password' => Env::get('redis.password', ''),
            'select' => 0,
            'timeout' => 0,
            'expire' => 0,
            'prefix' => '',
        ],
    ],
];
