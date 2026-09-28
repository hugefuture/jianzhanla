<?php
declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\ExcelService;
use app\common\exception\ApiException;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\TestCase;

/**
 * Excel 流式导出（OpenSpout）
 * 覆盖：结果正确性、大数据量可用、自动分 sheet、特殊字符、往返导入、超限拒绝
 */
class ExcelStreamTest extends TestCase
{
    private ExcelService $svc;
    private string $publicRoot;

    protected function setUp(): void
    {
        $this->svc = new ExcelService();
        $this->publicRoot = dirname(__DIR__, 2) . '/public';
    }

    /** 用 OpenSpout Reader 读取导出的文件，返回每个 sheet 的行（二维数组） */
    private function readSheets(string $absPath): array
    {
        $reader = new Reader();
        $reader->open($absPath);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCells() as $cell) {
                    $v = $cell->getValue();
                    $cells[] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v;
                }
                $rows[] = $cells;
            }
            $out[] = ['name' => $sheet->getName(), 'rows' => $rows];
        }
        $reader->close();
        return $out;
    }

    public function testSmallExportKeepsHeaderOrderAndChinese(): void
    {
        $res = $this->svc->export(
            ['title' => '公告标题', 'status_text' => '状态'],
            [['title' => '国庆放假通知', 'status_text' => '已发布'], ['title' => '系统维护', 'status_text' => '草稿']],
            'phpunit_stream_small'
        );
        $this->assertSame(2, $res['rows']);
        $this->assertSame(1, $res['sheets']);

        $sheets = $this->readSheets($this->publicRoot . $res['url']);
        $this->assertCount(1, $sheets);
        $this->assertSame(['公告标题', '状态'], $sheets[0]['rows'][0], '首行应为表头');
        $this->assertSame(['国庆放假通知', '已发布'], $sheets[0]['rows'][1]);
        $this->assertSame(['系统维护', '草稿'], $sheets[0]['rows'][2]);
        @unlink($this->publicRoot . $res['url']);
    }

    public function testEmptyDataStillProducesHeaderOnlyFile(): void
    {
        $res = $this->svc->export(['title' => '标题'], [], 'phpunit_stream_empty');
        $this->assertSame(0, $res['rows']);
        $this->assertSame(1, $res['sheets'], '无数据也应生成含表头的文件');

        $sheets = $this->readSheets($this->publicRoot . $res['url']);
        $this->assertSame([['标题']], $sheets[0]['rows']);
        @unlink($this->publicRoot . $res['url']);
    }

    public function testGeneratorSourceExportsSameResultAsArray(): void
    {
        $rows = [];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = ['name' => '用户' . $i, 'seq' => $i];
        }
        $gen = (function () use ($rows) {
            foreach ($rows as $r) {
                yield $r;
            }
        })();

        $res = $this->svc->export(['name' => '姓名', 'seq' => '序号'], $gen, 'phpunit_stream_gen');
        $this->assertSame(500, $res['rows']);

        $sheets = $this->readSheets($this->publicRoot . $res['url']);
        $this->assertSame('用户1', $sheets[0]['rows'][1][0]);
        $this->assertEquals(500, $sheets[0]['rows'][500][1], '第 500 行序号应为 500');
        @unlink($this->publicRoot . $res['url']);
    }

    public function testCallableReturningGeneratorIsSupported(): void
    {
        $res = $this->svc->export(
            ['name' => '姓名'],
            function () {
                yield ['name' => '甲'];
                yield ['name' => '乙'];
            },
            'phpunit_stream_callable'
        );
        $this->assertSame(2, $res['rows']);
        @unlink($this->publicRoot . $res['url']);
    }

    public function testLargeExportStayWithinMemoryAndAutoSplitsSheets(): void
    {
        // 单个 sheet 上限在 ExcelService::SHEET_ROWS（10 万），这里用反射临时压低到 1000 以便测试分表
        $total = 2500;
        $this->withTemporarySheetLimit(1000);

        $before = memory_get_peak_usage(true);
        $res = $this->svc->export(
            ['name' => '姓名', 'seq' => '序号'],
            (function () use ($total) {
                for ($i = 1; $i <= $total; $i++) {
                    yield ['name' => '用户' . $i, 'seq' => $i];
                }
            })(),
            'phpunit_stream_big'
        );
        $used = memory_get_peak_usage(true) - $before;

        $this->assertSame($total, $res['rows']);
        $this->assertSame(3, $res['sheets'], '2500 行 / 每表 1000 行 = 3 个 sheet');

        $sheets = $this->readSheets($this->publicRoot . $res['url']);
        $this->assertCount(3, $sheets);
        $this->assertSame(['数据', '数据2', '数据3'], array_column($sheets, 'name'), 'sheet 命名应递增');
        foreach ($sheets as $i => $s) {
            $expected = $i === 2 ? 501 : 1001;   // 前两个 sheet 各 1000 行数据 + 表头；末尾剩 500 行 + 表头
            $this->assertCount($expected, $s['rows'], "第 " . ($i + 1) . " 个 sheet 行数不符");
            $this->assertSame(['姓名', '序号'], $s['rows'][0], '每个 sheet 都应带表头');
        }
        $this->assertLessThan(64 * 1024 * 1024, $used, '流式写入内存占用应远低于 64MB');

        @unlink($this->publicRoot . $res['url']);
        unset($refClass);
    }

    public function testSpecialCharactersSurviveRoundTrip(): void
    {
        $rows = [
            ['title' => '含 <script>alert(1)</script> 特殊字符', 'seq' => 1],
            ['title' => "换行\n制表\t混合", 'seq' => 2],
            ['title' => '日本語テスト 🎉 emoji', 'seq' => 3],
            ['title' => null, 'seq' => 4],
            ['title' => 123.45, 'seq' => 5],
            ['title' => true, 'seq' => 6],
        ];
        $res = $this->svc->export(['title' => '标题', 'seq' => '序号'], $rows, 'phpunit_stream_special');
        $this->assertSame(6, $res['rows']);

        $sheets = $this->readSheets($this->publicRoot . $res['url']);
        $flat = $sheets[0]['rows'];
        $this->assertSame('标题', $flat[0][0]);
        $this->assertStringContainsString('<script>', $flat[1][0], '尖括号应被正确转义而非破坏文件');
        $this->assertSame("换行\n制表\t混合", $flat[2][0]);
        $this->assertSame('日本語テスト 🎉 emoji', $flat[3][0], '多字节与 emoji 应保持完整');
        $this->assertSame('', $flat[4][0], 'null 应写成空单元格');
        $this->assertEquals(123.45, $flat[5][0], '数值应保留数值类型');
        $this->assertSame('是', $flat[6][0], 'true 应转成中文「是」');
        $this->assertEquals(4, $flat[4][1], '同行其他列不受影响');
        @unlink($this->publicRoot . $res['url']);
    }

    public function testRoundTripThroughImport(): void
    {
        $rows = [['title' => '往返测试A'], ['title' => '往返测试B']];
        $res = $this->svc->export(['title' => '公告标题'], $rows, 'phpunit_stream_rt');

        $parsed = $this->svc->import($this->publicRoot . $res['url'], ['公告标题' => 'title']);
        $this->assertSame(2, $parsed['total']);
        $this->assertSame('往返测试A', $parsed['rows'][0]['title']);
        $this->assertSame('往返测试B', $parsed['rows'][1]['title']);
        @unlink($this->publicRoot . $res['url']);
    }

    public function testTooManyRowsIsRejected(): void
    {
        // static:: 后期静态绑定 → 子类覆写 MAX_EXPORT_ROWS 生效
        $svc = eval('return new class extends \\app\\admin\\service\\ExcelService { const MAX_EXPORT_ROWS = 100; };');
        $rows = [];
        for ($i = 0; $i < 101; $i++) {
            $rows[] = ['title' => 'r' . $i];
        }
        $this->expectException(ApiException::class);
        $svc->export(['title' => '标题'], $rows, 'phpunit_stream_over');
    }

    public function testEmptyHeaderIsRejected(): void
    {
        $this->expectException(ApiException::class);
        $this->svc->export([], [['a' => 1]], 'phpunit_stream_noheader');
    }

    public function testUnsupportedSourceIsRejected(): void
    {
        $this->expectException(ApiException::class);
        $this->svc->export(['title' => '标题'], 'im-not-iterable', 'phpunit_stream_bad');
    }

    // ---------- 辅助：临时改写私有常量 ----------

    /** 构造覆写了常量的子类实例（依赖 export() 里的 static:: 后期静态绑定） */
    private function withTemporarySheetLimit(int $limit): ExcelService
    {
        $this->svc = eval('return new class extends \\app\\admin\\service\\ExcelService { const SHEET_ROWS = ' . $limit . '; };');
        return $this->svc;
    }
}
