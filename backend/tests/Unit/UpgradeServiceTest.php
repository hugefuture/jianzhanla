<?php
declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\UpgradeService;
use app\common\exception\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * 远程升级：版本比较、require 表达式、路径白名单、SQL 安全检查
 */
class UpgradeServiceTest extends TestCase
{
    private UpgradeService $svc;

    protected function setUp(): void
    {
        $this->svc = new UpgradeService();
    }

    public function testCompareVersion(): void
    {
        $this->assertSame(1, UpgradeService::compareVersion('1.1.0', '1.0.0'));
        $this->assertSame(-1, UpgradeService::compareVersion('1.0.0', '1.1.0'));
        $this->assertSame(0, UpgradeService::compareVersion('1.0.0', '1.0.0'));
        $this->assertSame(1, UpgradeService::compareVersion('1.10.0', '1.9.0'));
        $this->assertSame(-1, UpgradeService::compareVersion('0.9.9', '1.0.0'));
    }

    public function testMatchRequires(): void
    {
        $this->assertTrue($this->svc->matchRequires('>=1.0.0', '1.0.0'));
        $this->assertTrue($this->svc->matchRequires('>=1.0.0', '1.2.0'));
        $this->assertFalse($this->svc->matchRequires('>=1.0.0', '0.9.0'));
        $this->assertTrue($this->svc->matchRequires('>=1.0.0,<2.0.0', '1.9.9'));
        $this->assertFalse($this->svc->matchRequires('>=1.0.0,<2.0.0', '2.0.0'));
        // 非法表达式一律拒绝
        $this->assertFalse($this->svc->matchRequires('rm -rf /', '1.0.0'));
    }

    public function testVersionBaselineFile(): void
    {
        $info = $this->svc->versionInfo();
        $this->assertSame('1.0.0', $info['version']);
        $this->assertSame('stable', $info['channel']);
    }

    public function testAllowedPathPassed(): void
    {
        $this->assertSame('backend/app/admin/service/Demo.php', $this->svc->assertTargetAllowed('backend/app/admin/service/Demo.php'));
        $this->assertSame('frontend/src/views/x.vue', $this->svc->assertTargetAllowed('frontend/src/views/x.vue'));
        $this->assertSame('docs/README.md', $this->svc->assertTargetAllowed('docs/README.md'));
    }

    /**
     * @dataProvider badPathProvider
     */
    public function testBadPathRejected(string $path): void
    {
        $this->expectException(ApiException::class);
        $this->svc->assertTargetAllowed($path);
    }

    public static function badPathProvider(): array
    {
        return [
            '穿越'     => ['docs/../../evil.php'],
            '绝对盘符' => ['C:/Windows/evil.php'],
            '根之外'   => ['evil.txt'],
            'runtime' => ['backend/runtime/evil.php'],
            'env'     => ['backend/.env'],
            'dist'    => ['frontend/dist/index.html'],
        ];
    }

    public function testSqlSafety(): void
    {
        $this->assertNull($this->svc->sqlSafetyError('UPDATE sdccc_sys_config SET value = 1;'));
        $this->assertNotNull($this->svc->sqlSafetyError('DROP DATABASE sdccc_admin;'));
        $this->assertNotNull($this->svc->sqlSafetyError('create database x;'));
        $this->assertNotNull($this->svc->sqlSafetyError('GRANT ALL ON *.* TO u;'));
        $this->assertNotNull($this->svc->sqlSafetyError(str_repeat('x', 600 * 1024)));
    }
}
