<?php

use think\facade\Env;

return [
    // 默认使用的数据库连接
    'default' => Env::get('database.driver', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    'auto_timestamp' => true,
    'datetime_format' => 'Y-m-d H:i:s',

    'connections' => [
        'mysql' => [
            // 数据库类型
            'type' => Env::get('database.type', 'mysql'),
            // 主机地址
            'hostname' => Env::get('database.hostname', '127.0.0.1'),
            // 数据库名
            'database' => Env::get('database.database', 'sdccc_admin'),
            // 用户名
            'username' => Env::get('database.username', 'root'),
            // 密码
            'password' => Env::get('database.password', 'root'),
            // 端口
            'hostport' => Env::get('database.hostport', '3306'),
            // 连接参数
            'params' => [
                \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ],
            // 编码
            'charset' => Env::get('database.charset', 'utf8mb4'),
            // 表前缀 —— 全库统一使用 sdccc_ 前缀
            'prefix' => Env::get('database.prefix', 'sdccc_'),
            // 部署模式
            'deploy' => 0,
            // 读写分离
            'rw_separate' => false,
            // 严格模式：字段不存在时抛异常
            'fields_strict' => true,
            // 缓存字段信息
            'fields_cache' => false,
            // 触发器事务
            'trigger_sql' => true,
            // 监听 SQL
            'trigger_sql_listen' => false,
        ],
    ],
];
