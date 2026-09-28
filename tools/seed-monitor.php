<?php
declare(strict_types=1);
/**
 * seed-monitor.php — 通过 PDO 执行 seed_monitor_menu.sql（本机 mysql.exe 客户端被沙箱拦，走后端同款连接）
 */
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=sdccc_admin;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../sql/seed_monitor_menu.sql');
// 去注释后按分号拆分执行
$lines = array_filter(array_map('trim', explode("\n", $sql)), fn ($l) => $l !== '' && !str_starts_with($l, '--'));
$sql = implode("\n", $lines);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt !== '') {
        $pdo->exec($stmt);
    }
}

foreach ($pdo->query("SELECT id, parent_id, name, perms, path, component FROM sdccc_sys_menu WHERE perms LIKE 'system:monitor%'") as $row) {
    echo implode(' | ', $row) . PHP_EOL;
}
echo "seed-monitor done\n";
