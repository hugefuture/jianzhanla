<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as StreamWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel 导入导出服务
 *
 * 导出：基于 OpenSpout 的**真流式写入**（内存恒定，不随行数增长），超过 sheetRows 自动分 sheet。
 * 数据来源支持：二维数组（兼容旧调用），或 \Generator / callable 分批产出，避免调用方一次性取出全表。
 *
 * 用法：
 *   $url = ExcelService::getInstance()->export($headers, $list, '通知公告');
 *   $url = ExcelService::getInstance()->export($headers, fn() => $gen, '大数据');   // 生成器
 *
 * 导入：仍用 PhpSpreadsheet（需要随机访问单元格，且导入量有 5000 行上限，内存可控）。
 *
 * 依赖：OpenSpout 为免 composer 安装，源码放在 backend/extend/OpenSpout（composer.json 的 PSR-0
 *       fallback 已映射到 extend/ 目录，命名空间的类会自动加载）。有 composer 时可改回
 *       composer require openspout/openspout 更稳妥。
 */
class ExcelService
{
    use Instance;

    /** 单个 sheet 最大数据行，超出自动新建 sheet（Excel 上限约 104 万行，这里留足余量） */
    // 可见性必须是 protected：export() 里用 static:: 后期静态绑定读取，
    // private 常量在子类解析时会报 Undefined constant
    protected const SHEET_ROWS = 100000;

    /** 单次导出总行数上限，防止无限增长打满磁盘 */
    protected const MAX_EXPORT_ROWS = 500000;

    /** 单次导入最大行数 */
    private const MAX_IMPORT_ROWS = 5000;

    /** 导出目录（相对项目 public） */
    protected function exportDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/public/uploads/excel';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * 流式导出 Excel
     *
     * @param array  $headers 字段 => 表头中文名
     * @param mixed  $rows    二维数组、\Generator、或返回它们的 callable
     * @param string $title   文件名前缀
     * @return array{url: string, rows: int, sheets: int, bytes: int} 可访问 URL 与统计
     */
    public function export(array $headers, $rows, string $title = 'export'): array
    {
        $fields = array_keys($headers);
        $labels = array_values($headers);
        if (!$fields) {
            throw ApiException::badRequest('导出表头不能为空');
        }

        $safe = preg_replace('/[\\\\\/:*?"<>|\s]+/', '_', $title) ?: 'export';
        $file = $this->exportDir() . '/' . $safe . '_' . date('YmdHis') . '.xlsx';

        $writer = new StreamWriter();
        $writer->openToFile($file);

        $headerStyle = (new Style())->setFontBold();

        $count = 0;
        $sheets = 0;
        $sheetRow = 0;
        $needNewSheet = false;   // 延迟到确有余下数据时再开新 sheet，避免末尾多出空表
        foreach ($this->iterate($rows) as $row) {
            // 用 static:: 而非 self:: —— 后期静态绑定，子类/测试可覆写这两个上限
            if ($count >= static::MAX_EXPORT_ROWS) {
                $writer->close();
                @unlink($file);
                throw ApiException::badRequest('导出数据超出 ' . static::MAX_EXPORT_ROWS . ' 行，请缩小范围');
            }
            if ($needNewSheet) {
                $writer->addNewSheetAndMakeItCurrent();
                $needNewSheet = false;
                $sheetRow = 0;
            }
            // 每个新 sheet 先写表头
            if ($sheetRow === 0) {
                $sheets++;
                $sheet = $writer->getCurrentSheet();
                if ($sheet !== null) {
                    $sheet->setName($sheets === 1 ? '数据' : '数据' . $sheets);
                }
                $writer->addRow(Row::fromValues($labels, $headerStyle));
                $sheetRow = 1;   // 表头占一行
            }

            $writer->addRow(Row::fromValues($this->toCells($fields, $row)));
            $count++;
            $sheetRow++;

            if ($sheetRow > static::SHEET_ROWS) {
                $needNewSheet = true;
            }
        }
        $writer->close();
        clearstatcache();

        if ($sheets === 0) {
            // 一条数据都没有：也要给出只有表头的文件，避免用户拿到空文件困惑
            $writer = new StreamWriter();
            $writer->openToFile($file);
            $writer->addRow(Row::fromValues($labels, $headerStyle));
            $writer->close();
            $sheets = 1;
        }

        return [
            'url'    => '/uploads/excel/' . basename($file),
            'rows'   => $count,
            'sheets' => $sheets,
            'bytes'  => (int) filesize($file),
        ];
    }

    /**
     * 生成导入模板（只有表头 + 一行示例）
     * @param array $headers 中文表头 => 示例值
     * @return string 可访问 URL
     */
    public function template(array $headers, string $title = 'template'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('模板');

        $col = 1;
        foreach ($headers as $label => $sample) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', (string) $label);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($col) . '1')->getFont()->setBold(true);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '2', (string) $sample);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            $col++;
        }
        return $this->save($spreadsheet, $title . '_模板');
    }

    /**
     * 解析 Excel 为二维数组
     * @param string $filePath  文件绝对路径
     * @param array $headerMap  中文表头 => 字段名
     * @return array{rows: array, errors: array, total: int}
     */
    public function import(string $filePath, array $headerMap): array
    {
        if (!is_file($filePath)) {
            throw ApiException::badRequest('导入文件不存在');
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw ApiException::badRequest('仅支持 xlsx / xls / csv 文件');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = min($sheet->getHighestRow(), self::MAX_IMPORT_ROWS + 1);
        $maxCol = $sheet->getHighestColumn();

        // 第一行：表头 -> 字段下标
        $colField = [];
        for ($c = 1; $c <= Coordinate::columnIndexFromString($maxCol); $c++) {
            $title = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue());
            if ($title !== '' && isset($headerMap[$title])) {
                $colField[$c] = $headerMap[$title];
            }
        }
        if (!$colField) {
            throw ApiException::badRequest('表头不匹配，请先下载导入模板');
        }

        $rows = [];
        $errors = [];
        for ($r = 2; $r <= $maxRow; $r++) {
            $item = [];
            $empty = true;
            foreach ($colField as $c => $field) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue();
                if ($value !== null && $value !== '') {
                    $empty = false;
                }
                $item[$field] = is_object($value) ? (string) $value : $value;
            }
            if ($empty) {
                continue;
            }
            $item['_row'] = $r;
            $rows[] = $item;
        }
        $spreadsheet->disconnectWorksheets();

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * 统一把入参转成可迭代对象（支持 array / Generator / callable）
     * @return iterable<array>
     */
    protected function iterate($rows): iterable
    {
        if ($rows instanceof \Generator || $rows instanceof \Traversable) {
            return $rows;
        }
        if (is_callable($rows) && !is_array($rows)) {
            return $this->iterate($rows());
        }
        if (is_array($rows)) {
            return $rows;
        }
        throw ApiException::badRequest('导出数据格式不支持');
    }

    /**
     * 一行数据 -> 单元格值列表（顺序与 $fields 一致）
     * @return list<null|string|int|float>
     */
    protected function toCells(array $fields, array $row): array
    {
        $cells = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? '';
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? '是' : '否';
            } elseif ($value instanceof \Stringable) {
                $value = (string) $value;
            } elseif (is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $cells[] = is_scalar($value) ? $value : (string) $value;
        }
        return $cells;
    }

    /** PhpSpreadsheet 保存（模板/旧逻辑）并返回可访问 URL */
    protected function save(Spreadsheet $spreadsheet, string $name): string
    {
        $safe = preg_replace('/[\\\\\/:*?"<>|\s]+/', '_', $name) ?: 'export';
        $file = $this->exportDir() . '/' . $safe . '.xlsx';
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();
        clearstatcache();
        return '/uploads/excel/' . basename($file);
    }
}
