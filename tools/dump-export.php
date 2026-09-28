<?php
declare(strict_types=1);
/** dump-export.php — 读取最新导出的 xlsx，检查表头与数据行是否正确 */
require __DIR__ . '/../backend/vendor/autoload.php';

$dir = __DIR__ . '/../backend/public/uploads/excel';
$files = glob($dir . '/*.xlsx');
usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$file = $files[0];
echo "文件: " . basename($file) . " (" . filesize($file) . " bytes)\n";

$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
foreach ($sheet->getRowIterator() as $r) {
    $out = [];
    foreach ($r->getCellIterator() as $cell) {
        $v = $cell->getValue();
        $out[] = is_object($v) ? (string) $v : (is_null($v) ? '' : mb_substr((string) $v, 0, 24));
    }
    echo '  [' . $r->getRowIndex() . '] ' . implode(' | ', $out) . "\n";
    if ($r->getRowIndex() >= 5) { echo "  ...\n"; break; }
}
