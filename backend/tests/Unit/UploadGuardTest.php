<?php
declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\UploadService;
use app\common\exception\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * 上传加固（DB-free）：后缀白名单、大小上限、真实 MIME 校验
 *
 * 说明：limits()/assertRealMime() 是 protected，这里用可测试的子类暴露出来，
 * 不改动被测类的可见性。
 */
class UploadGuardTest extends TestCase
{
    private TestableUploadService $svc;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->svc = new TestableUploadService();
        $this->tmpDir = sys_get_temp_dir() . '/sdccc_upload_test';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // 还原可能被测用例改过的配置
        \think\facade\Config::set([], 'upload');
    }

    // ---------- 大小上限 ----------

    public function testImageSizeLimitFollowsConfigWhenSmaller(): void
    {
        \think\facade\Config::set(['max_size' => 2], 'upload');
        $limit = $this->svc->exposeLimits('image');
        $this->assertSame(2 * 1024 * 1024, $limit['maxSize'], '配置更小时应以配置为准');
    }

    public function testImageSizeLimitCappedByHardMaximum(): void
    {
        \think\facade\Config::set(['max_size' => 9999], 'upload');
        $limit = $this->svc->exposeLimits('image');
        $this->assertSame(10 * 1024 * 1024, $limit['maxSize'], '配置过大时仍受内置硬上限约束');
    }

    public function testFileSizeLimitIsLargerThanImage(): void
    {
        $image = $this->svc->exposeLimits('image');
        $file = $this->svc->exposeLimits('file');
        $this->assertGreaterThan($image['maxSize'], $file['maxSize']);
    }

    // ---------- 后缀白名单 ----------

    public function testDefaultExtListsAreDisjointAsExpected(): void
    {
        $image = $this->svc->exposeLimits('image');
        $file = $this->svc->exposeLimits('file');
        $this->assertContains('png', $image['ext']);
        $this->assertContains('zip', $file['ext']);
        $this->assertNotContains('php', $file['ext'], '绝不允许脚本后缀');
        $this->assertNotContains('exe', $file['ext']);
    }

    public function testConfigExtIntersectsWithBuiltinSoCannotWiden(): void
    {
        // 即便配置里塞了 php，也不能把脚本后缀放进来
        \think\facade\Config::set(['allow_ext' => 'png,php,exe'], 'upload');
        $limit = $this->svc->exposeLimits('image');
        $this->assertSame(['png'], $limit['ext'], '白名单取交集，配置写错只会更严不会更松');
    }

    public function testConfigExtCanNarrowDown(): void
    {
        \think\facade\Config::set(['allow_ext' => 'JPG,PNG'], 'upload');
        $limit = $this->svc->exposeLimits('image');
        $this->assertSame(['jpg', 'png'], array_values($limit['ext']), '应恰好收窄为配置的两项');
        $this->assertNotContains('gif', $limit['ext']);
    }

    // ---------- 真实 MIME 校验 ----------

    public function testRealImagePasses(): void
    {
        $file = $this->writeTmp('real.png', $this->pngBytes());
        $this->svc->exposeAssertRealMime($file, 'png');   // 不抛异常即通过
        $this->assertTrue(is_file($file));
        @unlink($file);
    }

    public function testScriptDisguisedAsImageIsRejected(): void
    {
        $file = $this->writeTmp('shell.png', "<?php system(\$_GET[0]); ?>");
        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/有效图片|有效文件/u');
        try {
            $this->svc->exposeAssertRealMime($file, 'png');
        } finally {
            @unlink($file);
        }
    }

    public function testTextExtKeepsPlainText(): void
    {
        $file = $this->writeTmp('a.txt', 'hello sdccc');
        $this->svc->exposeAssertRealMime($file, 'txt');
        $this->assertTrue(true);
        @unlink($file);
    }

    public function testUnknownBinaryDoesNotFalsePositive(): void
    {
        // 复合格式（zip/老版 doc/xls）finfo 常识别为 octet-stream，应放行而不是误杀
        $file = $this->writeTmp('a.zip', "PK\x03\x04" . str_repeat("\x00", 64));
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if ($detected === 'application/octet-stream') {
            $this->svc->exposeAssertRealMime($file, 'zip');
            $this->assertTrue(true, '识别不了的类型应放行');
        } else {
            $this->svc->exposeAssertRealMime($file, 'zip');
            $this->assertContains($detected, ['application/zip', 'application/x-zip-compressed']);
        }
        @unlink($file);
    }

    // ---------- 辅助 ----------

    private function writeTmp(string $name, string $content): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }
}

/** 暴露 protected 方法的测试替身 */
class TestableUploadService extends UploadService
{
    public function exposeLimits(string $kind): array
    {
        return $this->limits($kind);
    }

    public function exposeAssertRealMime(string $realPath, string $ext): void
    {
        $this->assertRealMime($realPath, $ext);
    }
}
