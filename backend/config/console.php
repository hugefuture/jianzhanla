<?php
// 控制台命令注册
return [
    'commands' => [
        \app\command\AdminPassword::class,
        \app\command\CleanLogs::class,
        \app\command\CleanCache::class,
        \app\command\TaskRun::class,
        \app\command\DbBackup::class,
    ],
];
