<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;
use think\facade\Config;

/**
 * JWT Token 服务（支持续期、强制下线、在线会话管理）
 *
 * 载荷：iss / iat / exp / uid / jti / ver
 *  - jti：单次会话唯一 ID，用于「踢掉指定会话」与在线列表
 *  - ver：用户 token 版本号，bump 后该用户所有已签发 token 立即失效（强制下线）
 *
 * 缓存键：
 *  - token:ver:{uid}        用户 token 版本号
 *  - token:blacklist:{jti}  被踢出的会话
 *  - online:index           全部在线会话 [jti => 元信息]
 */
class TokenService
{
    use Instance;

    /** 过期后仍允许续期的宽限期（秒），默认 2 小时 */
    private const REFRESH_GRACE = 7200;

    /** 签发 token，返回 [token, expire_at, jti] */
    public function create(int $userId): array
    {
        $expire = (int) Config::get('token.expire', 604800);
        $now = time();
        $jti = bin2hex(random_bytes(8));
        $payload = [
            'iss' => Config::get('token.issuer', 'jianzhanla-admin'),
            'iat' => $now,
            'exp' => $now + $expire,
            'uid' => $userId,
            'jti' => $jti,
            'ver' => $this->version($userId),
        ];
        $token = JWT::encode($payload, $this->key(), (string) Config::get('token.algorithm', 'HS256'));
        $this->recordOnline($jti, $userId, $now + $expire);
        return ['token' => $token, 'expire_at' => $now + $expire, 'jti' => $jti, 'expire' => $expire];
    }

    /**
     * 解析 token
     * @param bool $allowExpired 是否允许已过期（用于续期）
     * @return array{uid:int, jti:string, ver:int, exp:int}
     */
    public function parse(string $token, bool $allowExpired = false): array
    {
        try {
            $payload = JWT::decode($token, new Key($this->key(), (string) Config::get('token.algorithm', 'HS256')));
        } catch (ExpiredException $e) {
            if (!$allowExpired) {
                throw ApiException::unauthorized('登录已过期，请重新登录');
            }
            // 宽限期内允许续期
            $payload = $e->getPayload();
            if (!$payload || ($payload->exp ?? 0) + self::REFRESH_GRACE < time()) {
                throw ApiException::unauthorized('登录已过期，请重新登录');
            }
        } catch (\Throwable) {
            throw ApiException::unauthorized('无效的登录凭证');
        }

        $uid = (int) ($payload->uid ?? 0);
        $jti = (string) ($payload->jti ?? '');
        if ($uid <= 0) {
            throw ApiException::unauthorized();
        }

        // 会话被踢出
        if ($jti !== '' && Cache::has('token:blacklist:' . $jti)) {
            throw ApiException::unauthorized('该登录已被强制下线');
        }
        // 版本号不一致（用户被强制下线 / 改密等）
        $ver = (int) ($payload->ver ?? 0);
        if ($ver !== $this->version($uid)) {
            throw ApiException::unauthorized('账号已在其他地点登录或已被强制下线');
        }

        return [
            'uid' => $uid,
            'jti' => $jti,
            'ver' => $ver,
            'exp' => (int) ($payload->exp ?? 0),
        ];
    }

    /** 续期：用旧 token 换新 token（旧 token 立即失效） */
    public function refresh(string $token): array
    {
        $old = $this->parse($token, true);
        // 旧会话作废
        if ($old['jti'] !== '') {
            Cache::set('token:blacklist:' . $old['jti'], 1, 86400);
            $this->removeOnline($old['jti']);
        }
        return $this->create($old['uid']);
    }

    /** 强制下线：用户所有会话失效 */
    public function kickUser(int $userId): void
    {
        Cache::set('token:ver:' . $userId, $this->version($userId) + 1, 0);
        $this->removeOnlineByUser($userId);
    }

    /** 强制下线：踢掉单个会话 */
    public function kickToken(string $jti): void
    {
        Cache::set('token:blacklist:' . $jti, 1, 86400);
        $this->removeOnline($jti);
    }

    /** 在线会话列表 */
    public function onlineList(): array
    {
        $index = Cache::get('online:index');
        $index = is_array($index) ? $index : [];
        $now = time();
        $list = [];
        foreach ($index as $jti => $item) {
            if (($item['exp'] ?? 0) < $now) {
                unset($index[$jti]);
                continue;
            }
            $list[] = $item;
        }
        Cache::set('online:index', $index, 0);
        usort($list, fn($a, $b) => ($b['login_time'] ?? '') <=> ($a['login_time'] ?? ''));
        return array_values($list);
    }

    /** 从请求头提取 token */
    public function fromRequest(\think\Request $request): ?string
    {
        $auth = $request->header('authorization', '');
        if ($auth === '') {
            $auth = (string) $request->header('token', '');
        }
        if (str_starts_with(strtolower($auth), 'bearer ')) {
            $auth = trim(substr($auth, 7));
        }
        if ($auth !== '') {
            return $auth;
        }
        // 文件下载等场景：window.open 无法自定义请求头，允许 GET 请求用 ?token= 传令牌
        if (strtolower($request->method()) === 'get') {
            $q = (string) $request->param('token', '');
            if ($q !== '') {
                return $q;
            }
        }
        return null;
    }

    // ---------- 内部 ----------

    private function key(): string
    {
        return (string) Config::get('token.key');
    }

    private function version(int $userId): int
    {
        return (int) (Cache::get('token:ver:' . $userId) ?? 0);
    }

    /** 记录在线会话 */
    private function recordOnline(string $jti, int $userId, int $exp): void
    {
        $index = Cache::get('online:index');
        $index = is_array($index) ? $index : [];
        $username = '';
        try {
            $username = (string) (\app\admin\model\SysUser::where('id', $userId)->value('username') ?? '');
        } catch (\Throwable) {
        }
        $index[$jti] = [
            'jti' => $jti,
            'user_id' => $userId,
            'username' => $username,
            'ip' => request()->ip(),
            'browser' => mb_substr((string) request()->header('user-agent', ''), 0, 120),
            'login_time' => date('Y-m-d H:i:s'),
            'exp' => $exp,
        ];
        Cache::set('online:index', $index, 0);
    }

    private function removeOnline(string $jti): void
    {
        $index = Cache::get('online:index');
        if (is_array($index) && isset($index[$jti])) {
            unset($index[$jti]);
            Cache::set('online:index', $index, 0);
        }
    }

    private function removeOnlineByUser(int $userId): void
    {
        $index = Cache::get('online:index');
        if (!is_array($index)) {
            return;
        }
        foreach ($index as $jti => $item) {
            if ((int) ($item['user_id'] ?? 0) === $userId) {
                unset($index[$jti]);
            }
        }
        Cache::set('online:index', $index, 0);
    }
}
