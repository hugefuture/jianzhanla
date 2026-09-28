<?php
/**
 * 测试辅助脚本：生成一个用于导入测试的 xlsx 文件
 * 用法：php tools/dev-excel-file.php [输出路径]
 */
require __DIR__ . '/../backend/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$out = $argv[1] ?? (__DIR__ . '/.import-test.xlsx');

$sheet = (new Spreadsheet())->getActiveSheet();
$sheet->setCellValue('A1', '公告标题');
$sheet->setCellValue('B1', '类型');
$sheet->setCellValue('C1', '内容');
$sheet->setCellValue('D1', '备注');
$sheet->setCellValue('A2', '导入测试A');
$sheet->setCellValue('B2', 1);
$sheet->setCellValue('C2', '来自 Excel 导入');
$sheet->setCellValue('D2', 'verify');
$sheet->setCellValue('A3', '导入测试B');
$sheet->setCellValue('B3', 2);
$sheet->setCellValue('C3', '第二条');
$sheet->setCellValue('D3', 'verify');
// 第 4 行故意缺标题，用于验证失败统计
$sheet->setCellValue('C4', '没有标题的行');

(new Xlsx($sheet->getParent()))->save($out);
echo $out, PHP_EOL;
