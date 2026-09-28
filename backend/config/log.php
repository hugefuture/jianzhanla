<?php
// 日志配置
return [
    // 默认日志通道
    'default' => env('log.channel', 'file'),
    'channels' => [
        'file' => [
            'type' => 'file',
            'path' => app()->getRuntimePath() . 'log',
            'single' => false,
            'file_size' => 2097152,
            'time_format' => 'Y-m-d H:i:s',
            'apart_level' => ['error', 'sql'],
            'max_files' => 30,
            'json' => false,
        ],
    ],
];
