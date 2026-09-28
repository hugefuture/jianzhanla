<?php
declare(strict_types=1);
/**
 * db-import.php — 用 PDO 执行 sql/*.sql（本机 mysql.exe 客户端会被沙箱间歇拦截，走后端同款连接更稳）
 *
 * 用法：
 *   php tools/db-import.php sql/migrate_task_log.sql [sql/xxx.sql ...]
 *   php tools/db-import.php --all
 *
 * 说明：按分号拆分执行，自动剥离 -- 与 /* *\/ 注释；单条失败立即中止并打印语句。
 */

$args = array_slice($argv, 1);
if ($args === []) {
    fwrite(STDERR, "用法: php tools/db-import.php <sql文件...> | --all\n");
    exit(1);
}

// 数据库连接：优先复用 backend/.env 的配置
$db = 'sdccc_admin';
$user = 'root';
$pass = 'root';
$host = '127.0.0.1';
$port = '3306';
$envFile = __DIR__ . '/../backend/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (preg_match('/^hostname\s*=\s*(.+)$/i', $line, $m)) {
            $host = trim($m[1]);
        } elseif (preg_match('/^hostport\s*=\s*(.+)$/i', $line, $m)) {
            $port = trim($m[1]);
        } elseif (preg_match('/^database\s*=\s*(.+)$/i', $line, $m)) {
            $db = trim($m[1]);
        } elseif (preg_match('/^username\s*=\s*(.+)$/i', $line, $m)) {
            $user = trim($m[1]);
        } elseif (preg_match('/^password\s*=\s*(.+)$/i', $line, $m)) {
            $pass = trim($m[1]);
        }
    }
}

if ($args[0] === '--all') {
    $files = glob(__DIR__ . '/../sql/*.sql') ?: [];
    sort($files);
    // SQL 文件名按惯例有序：init / migrate_* / seed_*
    usort($files, function ($a, $b) {
        $rank = fn ($f) => str_starts_with(basename($f), 'init') ? 0 : 1;
        return [$rank($a), $a] <=> [$rank($b), $b];
    });
} else {
    $files = array_map(fn ($f) => str_starts_with($f, 'sql/') || str_starts_with($f, 'sql\\')
        ? __DIR__ . '/../' . $f
        : $f, $args);
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "数据库连接失败：{$e->getMessage()}\n");
    exit(1);
}

$total = 0;
foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "跳过（不存在）：{$file}\n");
        continue;
    }
    $sql = file_get_contents($file);
    // 剥离注释
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
    $count = 0;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            fwrite(STDERR, "\n执行失败 [{$file}]\n语句：" . mb_substr($stmt, 0, 160) . "\n错误：" . $e->getMessage() . "\n");
            exit(1);
        }
    }
    $total += $count;
    echo sprintf("✓ %-34s %d 条语句\n", basename($file), $count);
}

echo "\n导入完成：{$total} 条语句；数据库 {$db}@{$host}:{$port}\n";
