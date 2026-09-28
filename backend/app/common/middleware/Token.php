<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\admin\service\AuthService;
use app\common\exception\ApiException;
use app\common\service\TokenService;

/**
 * 登录鉴权 + 接口权限校验
 *
 * 权限规则：
 *  1. 路径 system/user/list -> 权限标识 system:user:list（与 sys_menu.perms 对应）
 *  2. 超级管理员（角色 code=super）放行所有接口
 *  3. 白名单（个人中心、上传等）仅需登录
 */
class Token
{
    /** 仅需登录、无需权限点校验的路径前缀 */
    protected array $whitelist = [
        'getInfo',
        'logout',
        'profile',
        'upload',
        'index/stats',
    ];

    public function handle($request, \Closure $next)
    {
        $token = (new TokenService())->fromRequest($request);
        if (!$token) {
            throw ApiException::unauthorized();
        }

        $path0 = rtrim((string) $request->pathinfo(), '/');
        // 续期接口允许「刚过期但仍在宽限期内」的 token 通过
        $allowExpired = ($path0 === 'refresh');
        $parsed = (new TokenService())->parse($token, $allowExpired);
        $uid = (int) $parsed['uid'];
        $authService = AuthService::getInstance();
        $user = $authService->getActiveUser($uid);

        // 注入当前用户与权限集
        $request->user = $user;
        $request->perms = $authService->getUserPerms($user);

        // 权限校验
        $path = rtrim((string) $request->pathinfo(), '/');
        $path = preg_replace('/\.html$/', '', $path);

        if (!$this->inWhitelist($path)) {
            $perm = str_replace('/', ':', $path);
            // 去掉动态参数段（如 system/user/read/5 -> system:user:read）
            if (!$this->permGranted($request->perms, $perm)) {
                throw ApiException::forbidden();
            }
        }

        return $next($request);
    }

    protected function inWhitelist(string $path): bool
    {
        foreach ($this->whitelist as $item) {
            if ($path === $item || str_starts_with($path, $item . '/') || str_starts_with($path, $item)) {
                if ($path === $item || str_starts_with($path, $item . '/')) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function permGranted(array $perms, string $perm): bool
    {
        // 超级管理员通配
        if (in_array('*', $perms, true)) {
            return true;
        }
        // 截断动态参数段：去掉最后的数字段
        if (in_array($perm, $perms, true)) {
            return true;
        }
        // 允许读接口复用 list 权限（如 system:user:read 亦需 system:user:list）
        if (str_ends_with($perm, ':read') && in_array(str_replace(':read', ':list', $perm), $perms, true)) {
            return true;
        }
        return false;
    }
}
