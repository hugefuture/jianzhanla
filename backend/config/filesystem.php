<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 上传文件直接落在 public/uploads
            'type' => 'local',
            'root' => app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'uploads',
            'url' => '/uploads',
            'visibility' => 'public',
        ],
    ],
];
