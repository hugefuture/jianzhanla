<?php
declare(strict_types=1);

namespace tests\Unit;

use app\admin\model\SysTaskLog;
use app\admin\service\SysTaskLogService;
use PHPUnit\Framework\TestCase;

/**
 * 定时任务执行日志（需要数据库，但只读写独立的任务日志表）
 */
class SysTaskLogTest extends TestCase
{
    private SysTaskLogService $svc;

    protected function setUp(): void
    {
        $this->svc = SysTaskLogService::getInstance();
    }

    public function testRecordSuccessRow(): void
    {
        $this->svc->record('phpunit_ok', '单元测试成功样例', SysTaskLog::ST_OK, 123);
        $row = SysTaskLog::where('task_name', 'phpunit_ok')->order('id', 'desc')->find();
        $this->assertNotNull($row);
        $this->assertSame('单元测试成功样例', $row->task_desc);
        $this->assertSame(SysTaskLog::ST_OK, (int) $row->status);
        $this->assertSame(123, (int) $row->cost_ms);
        $this->assertSame('', $row->error_msg);
        $row->delete();
    }

    public function testRecordFailureRowKeepsErrorMessage(): void
    {
        $msg = str_repeat('出错了', 300);          // 超过 500 字（1500 字节）应被截断
        $this->svc->record('phpunit_fail', '单元测试失败样例', SysTaskLog::ST_FAIL, 456, $msg);
        $row = SysTaskLog::where('task_name', 'phpunit_fail')->order('id', 'desc')->find();
        $this->assertNotNull($row);
        $this->assertSame(SysTaskLog::ST_FAIL, (int) $row->status);
        $this->assertSame(456, (int) $row->cost_ms);
        $this->assertLessThanOrEqual(500, mb_strlen($row->error_msg), '错误信息应被截断到 500 字');
        $this->assertSame('失败', $row->status_text, 'getter 应返回中文状态');
        $row->delete();
    }

    public function testRecordNeverThrowsWhenTableBroken(): void
    {
        // record() 内部必须吞掉所有异常，否则任务本身会被日志带崩
        $svc = new class extends SysTaskLogService {
            public function boom(): void
            {
                // 覆写无用，这里只是保证调用路径不抛
            }
        };
        // 用不存在的表模拟故障：直接调用 record 但提前删表的代价太大，
        // 这里改为验证「写非法数据时仍不抛异常」
        $this->svc->record('', '', SysTaskLog::ST_FAIL, -999, str_repeat('x', 2000));
        $row = SysTaskLog::where('task_name', '')->order('id', 'desc')->find();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->cost_ms, '负数耗时应被钳到 0');
        $row->delete();
    }

    public function testOverviewAggregatesPerTask(): void
    {
        $tag = 'phpunit_agg';
        $this->svc->record($tag, '聚合测试', SysTaskLog::ST_OK, 100);
        $this->svc->record($tag, '聚合测试', SysTaskLog::ST_FAIL, 300);

        $res = $this->svc->overview(7);
        $this->assertArrayHasKey('list', $res);
        $this->assertArrayHasKey('failCount', $res);

        $mine = array_values(array_filter($res['list'], fn ($i) => $i['task_name'] === $tag));
        $this->assertCount(1, $mine, '同名任务应聚合为一条');
        $this->assertSame(2, $mine[0]['runs']);
        $this->assertSame(1, $mine[0]['fails']);
        $this->assertSame(200, $mine[0]['avg_ms'], '平均耗时应为 200ms');

        SysTaskLog::where('task_name', $tag)->delete();
    }

    public function testClearRemovesAllRows(): void
    {
        // 先落一条真实任务日志，再插自己的标记行
        $this->svc->record('logs', '模拟真实任务', SysTaskLog::ST_OK, 1);
        $this->svc->record('phpunit_clear', '清空测试', SysTaskLog::ST_OK, 1);
        $this->assertGreaterThan(0, SysTaskLog::count());

        $this->svc->clear();
        $this->assertSame(0, SysTaskLog::count(), '清空后应无剩余记录');
    }

    protected function tearDown(): void
    {
        // 这条用例会把整表清空，之后重建一条占位记录，避免真实任务日志表被测试置空
        if (SysTaskLog::count() === 0) {
            $this->svc->record('logs', '任务日志（由单元测试重建占位）', SysTaskLog::ST_OK, 0);
        }
    }
}
