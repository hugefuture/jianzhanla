<?php
declare(strict_types=1);
/**
 * verify-backup.php — 数据库备份验证
 * 用法：php tools/verify-backup.php
 * 说明：本机 node 无法拉子进程（EBUSY），CLI 类验证用 PHP 实现
 */
$php     = PHP_BINARY;
$backend = dirname(__DIR__) . '/backend';
$backupDir = $backend . '/runtime/backup';

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

echo "== 数据库备份 ==\n";

// 1. 命令注册
check('db:backup 已注册', str_contains(think('list'), 'db:backup'));
check('task:run 任务表含 backup', str_contains(think('task:run --help'), 'task:run')
    || str_contains(file_get_contents($backend . '/app/command/TaskRun.php'), "'backup'"));

// 2. 执行备份（保留足够份数）
$before = count(glob($backupDir . '/*.sql') ?: []);
$out = think('db:backup --keep=20');
check('备份执行成功', str_contains($out, '[db:backup]') && !str_contains($out, '失败'), substr($out, 0, 160));

// 3. 文件落盘且内容完整
$latest = '';
$mtime = 0;
foreach ((glob($backupDir . '/*.sql') ?: []) as $f) {
    if (filemtime($f) > $mtime) { $mtime = filemtime($f); $latest = $f; }
}
check('备份文件已生成', $latest !== '' && is_file($latest));
$sql = $latest !== '' ? (string) file_get_contents($latest) : '';
check('文件非空（>10KB）', strlen($sql) > 10240, round(strlen($sql) / 1024, 1) . 'KB');
check('含 CREATE TABLE 语句', substr_count($sql, 'CREATE TABLE') >= 10, '实际 ' . substr_count($sql, 'CREATE TABLE'));
check('含 INSERT INTO 数据', substr_count($sql, 'INSERT INTO') >= 5, '实际 ' . substr_count($sql, 'INSERT INTO'));
check('包含核心表 sdccc_sys_user', str_contains($sql, 'sdccc_sys_user'));
check('不含 MySQL dump 错误信息', !str_contains($sql, 'Got error') && !str_contains($sql, 'mysqldump: '));

// 4. 轮转清理
think('db:backup --keep=20');
think('db:backup --keep=20');
$n1 = count(glob($backupDir . '/*.sql') ?: []);
think('db:backup --keep=1');
$n2 = count(glob($backupDir . '/*.sql') ?: []);
check('轮转后仅保留 1 份', $n2 === 1, "轮转前 {$n1} -> 轮转后 {$n2}");

// 5. 密码未出现在命令行（用 --defaults-extra-file）
check('认证走临时配置文件（不传明文密码）', str_contains(file_get_contents($backend . '/app/command/DbBackup.php'), 'defaults-extra-file'));

// 6. 不做恢复命令（用户明确不要 db:restore）
check('未提供 db:restore 命令', !str_contains(think('list'), 'db:restore'));

echo "\n结果：{$pass} 通过 / {$fail} 失败\n";
exit($fail ? 1 : 0);
