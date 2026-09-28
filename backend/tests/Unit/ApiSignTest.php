<?php
declare(strict_types=1);

namespace tests\Unit;

use app\api\service\ApiSignService;
use app\common\exception\ApiException;
use PHPUnit\Framework\TestCase;

/**
 * 开放接口签名校验（可选开关）
 */
class ApiSignTest extends TestCase
{
    private ApiSignService $svc;

    protected function setUp(): void
    {
        $this->svc = ApiSignService::getInstance();
        \think\facade\Config::set(['sign_secret' => '', 'allow_anonymous' => true], 'api');
    }

    protected function tearDown(): void
    {
        \think\facade\Config::set(['sign_secret' => ''], 'api');
        // 清掉测试产生的 nonce 文件
        foreach (glob(runtime_path() . 'api-nonce/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    /** 造一组合法参数（含正确签名） */
    private function signedParams(string $secret, int $tsOffset = 0): array
    {
        \think\facade\Config::set(['sign_secret' => $secret], 'api');
        $params = [
            'app_key' => 'portal',
            'timestamp' => time() + $tsOffset,
            'nonce' => 'n' . bin2hex(random_bytes(6)),
            'page' => 1,
            'limit' => 10,
        ];
        $params['sign'] = ApiSignService::getInstance()->sign($params);
        return $params;
    }

    public function testDisabledByDefault(): void
    {
        $this->assertFalse($this->svc->enabled(), '默认未配密钥时应关闭校验');
        $this->assertTrue($this->svc->verify([]), '关闭时不应拦截请求');
    }

    public function testEnabledAfterSecretConfigured(): void
    {
        \think\facade\Config::set(['sign_secret' => 's3cret'], 'api');
        $this->assertTrue($this->svc->enabled());
    }

    public function testValidSignaturePasses(): void
    {
        $params = $this->signedParams('s3cret');
        $this->assertTrue($this->svc->verify($params));
    }

    public function testMissingAppKeyOrSignRejected(): void
    {
        $params = $this->signedParams('s3cret');
        unset($params['app_key']);
        $this->expectException(ApiException::class);
        $this->svc->verify($params);
    }

    public function testExpiredTimestampRejected(): void
    {
        $params = $this->signedParams('s3cret', -600);   // 10 分钟前
        $this->expectException(ApiException::class);
        $this->svc->verify($params);
    }

    public function testWrongSecretProducesDifferentSignature(): void
    {
        $params = $this->signedParams('right-secret');
        // 换成另一个密钥后，同样的参数算出的签名应不同
        \think\facade\Config::set(['sign_secret' => 'wrong-secret'], 'api');
        $this->expectException(ApiException::class);
        $this->svc->verify($params);
    }

    public function testTamperedParamIsRejected(): void
    {
        $params = $this->signedParams('s3cret');
        $params['limit'] = 999999;                        // 改了业务参数但没改签名
        $this->expectException(ApiException::class);
        $this->svc->verify($params);
    }

    public function testNonceCannotBeReplayed(): void
    {
        $params = $this->signedParams('s3cret');
        $this->assertTrue($this->svc->verify($params), '首次应通过');

        // 同一 nonce 再来一次
        try {
            $this->svc->verify($params);
            $this->fail('重放请求应被拒绝');
        } catch (ApiException $e) {
            $this->assertStringContainsString('nonce', $e->getMessage());
        }
    }

    public function testSignIsDeterministicAndKeyOrderInsensitive(): void
    {
        \think\facade\Config::set(['sign_secret' => 'k'], 'api');
        $a = ['b' => 2, 'a' => 1, 'app_key' => 'x'];
        $b = ['app_key' => 'x', 'a' => 1, 'b' => 2];
        $this->assertSame($this->svc->sign($a), $this->svc->sign($b), '签名应与参数顺序无关');
        $this->assertNotSame($this->svc->sign($a), $this->svc->sign(['app_key' => 'x', 'a' => 1, 'b' => 3]));
    }
}
