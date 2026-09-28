<?php
declare(strict_types=1);
/**
 * verify-task.php — 定时任务模块验证
 * 用法：php tools/verify-task.php
 * 说明：本机 node 的 child_process 被 EBUSY 拦截，故 CLI 类验证用 PHP 实现
 */
$php  = PHP_BINARY;
$root = dirname(__DIR__);
$backend = $root . '/backend';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . ($ok || $detail === '' ? '' : '  ' . $detail) . PHP_EOL;
}
function think(string $args): string
{
    global $php, $backend;
    exec('"' . $php . '" "' . $backend . '/think" ' . $args . ' 2>&1', $out, $code);
    return implode("\n", $out);
}

echo "== 定时任务模块 ==\n";

// 1. 命令注册
$list = think('list');
check('task:run 已注册', str_contains($list, 'task:run'));
check('task:clean-logs 已注册', str_contains($list, 'task:clean-logs'));
check('task:clean-cache 已注册', str_contains($list, 'task:clean-cache'));
check('admin:password 已注册', str_contains($list, 'admin:password'));

// 2. 强制执行
$out = think('task:run --force');
check('强制执行成功', str_contains($out, '完成') && !str_contains($out, '异常'), substr($out, 0, 120));

// 3. 状态文件与到期判断
check('task-state.json 已生成', is_file($backend . '/runtime/task-state.json'));
$out = think('task:run');
check('未到期时跳过', str_contains($out, '未到期，跳过'), substr($out, 0, 120));

// 4. 单任务过滤
$out = think('task:run --only=cache --force');
check('--only 单任务过滤', str_contains($out, '缓存') && !str_contains($out, '超期日志（logs）完成'));

// 5. 保留天数参数
$out = think('task:clean-logs --days=3650');
check('--days 参数生效', str_contains($out, '保留 3650 天'));

echo "\n结果：{$pass} 通过 / {$fail} 失败\n";
exit($fail ? 1 : 0);
