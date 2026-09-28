<?php
declare(strict_types=1);
/**
 * PHPUnit 引导：初始化 ThinkPHP 应用但不连数据库做写入
 *
 * 说明：本机没有 composer（见 README），phpunit 以 tools/phpunit.phar 方式引入，
 * 这里手动完成 think\App 的初始化，让被测代码可以正常使用 config()/Cache 等 Facade。
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$app = new \think\App();
$app->initialize();

// 测试写入独立的缓存目录，避免污染开发环境运行时
$testRuntime = dirname(__DIR__) . '/runtime/test';
if (!is_dir($testRuntime)) {
    @mkdir($testRuntime, 0755, true);
}
$app->setRuntimePath($testRuntime);
