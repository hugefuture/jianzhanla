<?php
declare(strict_types=1);

namespace app\api\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;

/**
 * 开放接口签名校验（可选）
 *
 * 与后台 JWT 完全分离的轻量鉴权，用于门户 / 第三方系统调用公开只读接口。
 *
 * 开关：config/api.php 的 sign_secret。
 *   - 为空           → 不校验（本地开发、内网信任环境）
 *   - 非空           → 必须带 app_key + timestamp + nonce + sign
 *
 * 签名算法（与常见开放平台一致）：
 *   1. 除 sign 外的所有请求参数按键名升序排列
 *   2. 拼成 key1=value1&key2=value2
 *   3. 前后包 app_secret，做 md5：md5(secret + str + secret)
 *   4. timestamp 超出 ±5 分钟拒绝（防重放），nonce 在窗口期内不可重复（防重放）
 */
class ApiSignService
{
    use Instance;

    /** 时间戳容忍偏差（秒） */
    private const TOLERANCE = 300;

    /** 是否启用签名校验 */
    public function enabled(): bool
    {
        return trim((string) config('api.sign_secret', '')) !== '';
    }

    /** 校验签名通过返回 true；未启用时也返回 true */
    public function verify(array $params): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        $key = (string) ($params['app_key'] ?? '');
        $ts = (int) ($params['timestamp'] ?? 0);
        $nonce = (string) ($params['nonce'] ?? '');
        $sign = strtolower((string) ($params['sign'] ?? ''));

        if ($key === '' || $sign === '') {
            throw ApiException::unauthorized('缺少 app_key 或 sign');
        }
        if (abs(time() - $ts) > self::TOLERANCE) {
            throw ApiException::unauthorized('timestamp 已过期，请校准服务器时间');
        }
        if (!$this->consumeNonce($key, $nonce)) {
            throw ApiException::unauthorized('nonce 已被使用');
        }

        $expected = $this->sign($params);
        if (!hash_equals($expected, $sign)) {
            throw ApiException::unauthorized('签名校验失败');
        }
        return true;
    }

    /** 计算签名（供文档示例与测试使用） */
    public function sign(array $params): string
    {
        unset($params['sign']);
        ksort($params);
        $pieces = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $pieces[] = $k . '=' . (is_bool($v) ? ($v ? '1' : '0') : (string) $v);
        }
        $secret = (string) config('api.sign_secret', '');
        return strtolower(md5($secret . implode('&', $pieces) . $secret));
    }

    /**
     * nonce 一次性消费：窗口期内用过的拒绝复用
     * 存储：文件缓存（runtime/api-nonce/），不依赖 Cache —— 避免被 task:clean-cache 清掉后重放
     */
    private function consumeNonce(string $key, string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }
        $dir = runtime_path() . 'api-nonce';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . md5($key . $nonce);
        if (is_file($file)) {
            return false;
        }
        @file_put_contents($file, (string) time());
        // 顺带清理过期文件（概率触发，避免每次扫描目录）
        if (mt_rand(1, 50) === 1) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f) && time() - (int) file_get_contents($f) > self::TOLERANCE * 2) {
                    @unlink($f);
                }
            }
        }
        return true;
    }
}
