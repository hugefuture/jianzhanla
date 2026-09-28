<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\AuthService;
use app\admin\service\LoginGuardService;
use app\common\controller\AbstractController;
use app\common\middleware\OperLog;
use app\common\service\TokenService;
use think\Response;

/**
 * 登录 / 用户信息 / 退出
 */
class Login extends AbstractController
{
    /**
     * 登录 POST admin/login
     */
    public function login(): Response
    {
        $username = (string) $this->request->param('username', '');
        $password = (string) $this->request->param('password', '');
        $captchaId = (string) $this->request->param('captcha_id', '');
        $captchaCode = (string) $this->request->param('captcha_code', '');
        $data = AuthService::getInstance()->login($username, $password, $captchaId, $captchaCode);
        return $this->success($data, '登录成功');
    }

    /**
     * 获取登录验证码 GET admin/captcha
     */
    public function captcha(): Response
    {
        $enabled = LoginGuardService::getInstance()->captchaEnabled();
        return $this->success([
            'enabled' => $enabled,
            'captcha' => $enabled ? LoginGuardService::getInstance()->createCaptcha() : null,
        ]);
    }

    /**
     * 当前用户信息（含权限与前端动态路由）GET admin/getInfo
     */
    public function getInfo(): Response
    {
        $user = $this->request->user;
        return $this->success(AuthService::getInstance()->getInfo($user));
    }

    /**
     * 续期：用旧 token 换新 token POST admin/refresh
     */
    public function refresh(): Response
    {
        $token = TokenService::getInstance()->fromRequest($this->request);
        if (!$token) {
            return $this->fail('缺少登录凭证', 401);
        }
        $data = TokenService::getInstance()->refresh($token);
        return $this->success([
            'token' => $data['token'],
            'expire_at' => $data['expire_at'],
        ], '已续期');
    }

    /**
     * 退出 POST admin/logout：当前会话失效
     */
    public function logout(): Response
    {
        $token = TokenService::getInstance()->fromRequest($this->request);
        if ($token) {
            try {
                $parsed = TokenService::getInstance()->parse($token, true);
                if (!empty($parsed['jti'])) {
                    TokenService::getInstance()->kickToken($parsed['jti']);
                }
            } catch (\Throwable) {
                // token 已失效时忽略
            }
        }
        return $this->success(null, '已退出登录');
    }
}
