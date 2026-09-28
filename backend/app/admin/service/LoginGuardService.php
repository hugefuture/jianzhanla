<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\traits\Instance;
use think\facade\Cache;

/**
 * 登录防护服务：图形验证码 + 失败次数锁定
 *
 * 验证码答案存缓存（默认文件缓存），不依赖 session：
 *  - 键 login:captcha:{id}  值 验证码文本（大写）
 *  - 键 login:fail:{username}  值 连续失败次数
 *  - 键 login:lock:{username}  值 锁定到期时间戳
 */
class LoginGuardService
{
    use Instance;

    /** 验证码有效期（秒） */
    private const CAPTCHA_TTL = 300;

    /** 参数开关 */
    private function cfg(string $key, string $default): string
    {
        return SysConfigService::getInstance()->getValue($key, $default);
    }

    public function captchaEnabled(): bool
    {
        return $this->cfg('sys.login.captcha', '1') === '1';
    }

    public function maxRetry(): int
    {
        return max(3, (int) $this->cfg('sys.login.maxRetry', '5'));
    }

    public function lockMinutes(): int
    {
        return max(1, (int) $this->cfg('sys.login.lockMinutes', '10'));
    }

    public function passwordMinLength(): int
    {
        return max(6, (int) $this->cfg('sys.password.minLength', '6'));
    }

    public function passwordComplex(): bool
    {
        return $this->cfg('sys.password.complex', '0') === '1';
    }

    /**
     * 生成验证码
     * @return array{id:string, image:string} image 为 data URI，可直接给 <img src>
     */
    public function createCaptcha(): array
    {
        $id = bin2hex(random_bytes(16));
        $code = $this->randomCode();
        Cache::set('login:captcha:' . $id, $code, self::CAPTCHA_TTL);

        return [
            'id' => $id,
            'image' => $this->renderImage($code),
            'ttl' => self::CAPTCHA_TTL,
        ];
    }

    /** 校验验证码（忽略大小写） */
    public function verifyCaptcha(string $id, string $code): bool
    {
        if ($id === '' || $code === '') {
            return false;
        }
        $key = 'login:captcha:' . $id;
        $answer = Cache::get($key);
        if (!$answer) {
            return false;
        }
        // 一次性：校验后立即失效
        Cache::delete($key);
        return strtoupper(trim($code)) === strtoupper((string) $answer);
    }

    /** 账号是否被锁定，返回剩余秒数（0 表示未锁定） */
    public function lockRemain(string $username): int
    {
        $until = (int) Cache::get('login:lock:' . $username);
        if ($until <= time()) {
            return 0;
        }
        return $until - time();
    }

    /** 记录一次登录失败，达到上限则锁定 */
    public function recordFail(string $username): int
    {
        $key = 'login:fail:' . $username;
        $times = (int) Cache::get($key, 0) + 1;
        Cache::set($key, $times, $this->lockMinutes() * 60);
        if ($times >= $this->maxRetry()) {
            Cache::set('login:lock:' . $username, time() + $this->lockMinutes() * 60, $this->lockMinutes() * 60);
            Cache::delete($key);
        }
        return $this->maxRetry() - $times;
    }

    /** 登录成功后清空失败计数 */
    public function clearFail(string $username): void
    {
        Cache::delete('login:fail:' . $username);
        Cache::delete('login:lock:' . $username);
    }

    /** 密码策略校验，不通过抛业务异常 */
    public function assertPassword(string $password): void
    {
        $min = $this->passwordMinLength();
        if (mb_strlen($password) < $min) {
            throw \app\common\exception\ApiException::badRequest("密码长度不能少于 {$min} 位");
        }
        if ($this->passwordComplex() && !(preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password))) {
            throw \app\common\exception\ApiException::badRequest('密码必须同时包含字母和数字');
        }
    }

    // ---------- 内部 ----------

    private function randomCode(int $length = 4): string
    {
        // 去掉易混淆字符 0/O/1/I
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }
        return $code;
    }

    /** 用 GD 绘制验证码图片，返回 data URI */
    private function renderImage(string $code): string
    {
        $width = 120;
        $height = 40;
        $im = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($im, 245, 248, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($im, random_int(180, 220), random_int(180, 220), random_int(200, 240));
            imageline($im, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }
        // 噪点
        for ($i = 0; $i < 60; $i++) {
            $dotColor = imagecolorallocate($im, random_int(150, 220), random_int(150, 220), random_int(150, 220));
            imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
        }
        // 文字
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $textColor = imagecolorallocate($im, random_int(20, 90), random_int(40, 110), random_int(120, 200));
            $x = 12 + $i * 25 + random_int(-3, 3);
            $y = 8 + random_int(-2, 6);
            imagestring($im, 5, $x, $y, $code[$i], $textColor);
        }

        ob_start();
        imagepng($im);
        $binary = ob_get_clean();
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode((string) $binary);
    }
}
