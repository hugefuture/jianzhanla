<?php
declare(strict_types=1);

namespace tests\Unit;

use app\common\exception\ApiException;
use app\common\service\TokenService;
use PHPUnit\Framework\TestCase;
use think\facade\Cache;

/**
 * Token 签发/解析/踢下线（DB-free，只依赖 JWT 与 Cache）
 */
class TokenServiceTest extends TestCase
{
    private TokenService $svc;

    protected function setUp(): void
    {
        Cache::clear();
        $this->svc = new TokenService();
    }

    public function testCreateReturnsTokenAndJti(): void
    {
        $res = $this->svc->create(1);
        $this->assertNotEmpty($res['token']);
        $this->assertNotEmpty($res['jti']);
        $this->assertGreaterThan(time(), $res['expire_at']);
    }

    public function testParseRoundTripKeepsUidAndJti(): void
    {
        $res = $this->svc->create(42);
        $p = $this->svc->parse($res['token']);
        $this->assertSame(42, $p['uid']);
        $this->assertSame($res['jti'], $p['jti']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $res = $this->svc->create(1);
        $this->expectException(ApiException::class);
        $this->svc->parse($res['token'] . 'tampered');
    }

    public function testExpiredTokenRejectedUnlessAllowExpired(): void
    {
        $svc = new TokenService();
        $res = $svc->create(1);
        // 手工构造一个已过期的 token
        $past = $this->forgeExpiredToken(1, 'abcd1234abcd1234');
        $this->expectException(ApiException::class);
        $svc->parse($past);
    }

    public function testKickUserInvalidatesAllSessions(): void
    {
        $a = $this->svc->create(7);
        $b = $this->svc->create(7);
        $this->svc->parse($a['token']);  // 踢之前可用

        $this->svc->kickUser(7);
        $this->expectException(ApiException::class);
        $this->svc->parse($b['token']);
    }

    public function testKickTokenOnlyKillsTargetSession(): void
    {
        $a = $this->svc->create(8);
        $b = $this->svc->create(8);
        $this->svc->kickToken($a['jti']);

        $thrown = false;
        try {
            $this->svc->parse($a['token']);
        } catch (ApiException) {
            $thrown = true;
        }
        $this->assertTrue($thrown, '被踢的会话应当失效');

        // 同用户的另一会话仍有效
        $this->assertSame(8, $this->svc->parse($b['token'])['uid']);
    }

    public function testOnlineListContainsCreatedSession(): void
    {
        $res = $this->svc->create(9);
        $list = $this->svc->onlineList();
        $this->assertNotEmpty($list);
        $jtis = array_column($list, 'jti');
        $this->assertContains($res['jti'], $jtis);
    }

    /** 手工签发一个已过期 token */
    private function forgeExpiredToken(int $uid, string $jti): string
    {
        $now = time();
        $payload = [
            'iss' => 'jianzhanla-admin',
            'iat' => $now - 7200,
            'exp' => $now - 60,
            'uid' => $uid,
            'jti' => $jti,
            'ver' => 0,
        ];
        return \Firebase\JWT\JWT::encode($payload, (string) config('token.key'), 'HS256');
    }
}
