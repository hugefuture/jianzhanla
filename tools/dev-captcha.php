<?php
/**
 * 测试辅助脚本（仅本地冒烟用）：生成一个验证码并回读答案
 * 用法：php tools/dev-captcha.php  -> {"id":"...","answer":"...."}
 */
require __DIR__ . '/../backend/vendor/autoload.php';

use app\admin\service\LoginGuardService;
use think\facade\Cache;

$app = new think\App();
$app->initialize();

// 传数量参数可一次生成多个（用于连续失败锁定测试）
$count = max(1, (int) ($argv[1] ?? 1));
$list = [];
for ($i = 0; $i < $count; $i++) {
    $captcha = LoginGuardService::getInstance()->createCaptcha();
    $list[] = [
        'id' => $captcha['id'],
        'answer' => Cache::get('login:captcha:' . $captcha['id']),
    ];
}
echo json_encode($count === 1 ? $list[0] : $list, JSON_UNESCAPED_UNICODE);
