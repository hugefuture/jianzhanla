<?php
declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\ExcelService;
use PHPUnit\Framework\TestCase;

/**
 * Excel 导入侧（PhpSpreadsheet）
 *
 * 说明：导出已改为 OpenSpout 流式实现，见 ExcelStreamTest；
 * 这里专注「别人/别的系统产出的文件能否被正确解析」——用 PhpSpreadsheet 造样本文件再导入。
 */
class ExcelServiceTest extends TestCase
{
    private ExcelService $svc;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->svc = new ExcelService();
        $this->tmpDir = sys_get_temp_dir() . '/sdccc_excel_test';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
    }

    public function testImportRoundTripKeepsChineseAndMapping(): void
    {
        // 先由本服务导出（流式），再用导入侧读回来，验证两端 JSON/编码一致
        $res = $this->svc->export(
            ['title' => '公告标题', 'type' => '类型'],
            [['title' => '国庆放假通知', 'type' => 1], ['title' => '系统维护', 'type' => 2]],
            'phpunit_roundtrip'
        );
        $file = dirname(__DIR__, 2) . '/public' . $res['url'];

        $parsed = $this->svc->import($file, ['公告标题' => 'title', '类型' => 'type']);
        $this->assertSame(2, $parsed['total']);
        $this->assertSame('国庆放假通知', $parsed['rows'][0]['title']);
        $this->assertEquals(1, $parsed['rows'][0]['type']);
        $this->assertSame('系统维护', $parsed['rows'][1]['title']);
        @unlink($file);
    }

    public function testImportSkipsEmptyRows(): void
    {
        $file = $this->tmpDir . '/spaces.xlsx';
        $this->writeSheet($file, [['公告标题', '类型'], ['有效行', 1], ['', ''], [null, null]]);
        $parsed = $this->svc->import($file, ['公告标题' => 'title', '类型' => 'type']);
        $this->assertSame(1, $parsed['total'], '空行应被跳过');
        @unlink($file);
    }

    public function testMismatchedHeaderIsRejected(): void
    {
        $file = $this->tmpDir . '/wrong.xlsx';
        $this->writeSheet($file, [['完全无关的表头'], ['值']]);
        $this->expectException(\app\common\exception\ApiException::class);
        $this->svc->import($file, ['公告标题' => 'title']);
    }

    public function testImportMissingFileIsRejected(): void
    {
        $this->expectException(\app\common\exception\ApiException::class);
        $this->svc->import($this->tmpDir . '/not-exist.xlsx', ['公告标题' => 'title']);
    }

    public function testImportUnsupportedExtIsRejected(): void
    {
        $file = $this->tmpDir . '/a.pdf';
        file_put_contents($file, '%PDF-1.4');
        $this->expectException(\app\common\exception\ApiException::class);
        $this->svc->import($file, ['公告标题' => 'title']);
    }

    /** 直接写一个二维数组到 xlsx */
    private function writeSheet(string $file, array $data): void
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $sheet->getActiveSheet();
        foreach ($data as $r => $row) {
            foreach (array_values($row) as $c => $v) {
                $ws->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . ($r + 1), $v);
            }
        }
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($file);
        $sheet->disconnectWorksheets();
    }
}
