<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\model\SysLoginLog;
use app\admin\model\SysUser;
use app\admin\dao\SysUserDao;
use app\common\exception\ApiException;
use app\common\service\AbstractService;
use app\common\service\TokenService;
use app\common\traits\Instance;

/**
 * 认证服务：登录 / 用户信息 / 权限
 */
class AuthService extends AbstractService
{
    use Instance;

    protected array $allowFields = [];

    public function __construct()
    {
        $this->dao = SysUserDao::getInstance();
    }

    /** 钩子：登录失败（插件可做风控、告警） */
    protected function fireLoginFail(string $username, string $reason): void
    {
        \app\common\plugin\PluginManager::getInstance()->trigger('user.login.fail', [
            'username' => $username,
            'reason' => $reason,
            'ip' => request()->ip(),
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 登录：校验账号密码，签发 JWT
     */
    public function login(string $username, string $password, string $captchaId = '', string $captchaCode = ''): array
    {
        if ($username === '' || $password === '') {
            throw ApiException::badRequest('请输入账号和密码');
        }

        $guard = LoginGuardService::getInstance();

        // 1) 锁定检查
        $remain = $guard->lockRemain($username);
        if ($remain > 0) {
            throw ApiException::forbidden('登录失败次数过多，账号已锁定，请 ' . ceil($remain / 60) . ' 分钟后再试');
        }

        // 2) 验证码校验（开启时必填）
        if ($guard->captchaEnabled() && !$guard->verifyCaptcha($captchaId, $captchaCode)) {
            $this->logLogin($username, 0, '验证码错误');
            $this->fireLoginFail($username, '验证码错误');
            throw ApiException::badRequest('验证码错误或已失效');
        }

        $user = $this->dao->findWithPassword($username);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            // 账号不存在时不泄露是否存在，但同样计入失败次数防爆破
            $left = $guard->recordFail($username);
            $this->logLogin($username, 0, '账号或密码错误');
            $this->fireLoginFail($username, '账号或密码错误');
            throw ApiException::badRequest($left > 0
                ? "账号或密码错误，还可尝试 {$left} 次"
                : '账号或密码错误，账号已锁定');
        }
        if ((int) $user['status'] !== 1) {
            throw ApiException::forbidden('账号已被停用，请联系管理员');
        }
        $guard->clearFail($username);

        // 更新登录信息
        SysUser::where('id', $user['id'])->update([
            'last_login_time' => date('Y-m-d H:i:s'),
            'last_login_ip' => request()->ip(),
        ]);

        $this->logLogin($username, 1, '登录成功');

        // 钩子：登录成功后通知插件（登录提醒、风控、欢迎语等）
        \app\common\plugin\PluginManager::getInstance()->trigger('user.login.success', [
            'username' => $username,
            'user_id' => (int) $user['id'],
            'nickname' => (string) ($user['nickname'] ?? $user['username'] ?? ''),
            'ip' => request()->ip(),
            'time' => date('Y-m-d H:i:s'),
        ]);

        $token = TokenService::getInstance()->create((int) $user['id']);
        return [
            'token' => $token['token'],
            'expire_at' => $token['expire_at'],
            'user' => $this->getActiveUser((int) $user['id']),
        ];
    }

    /** 记录登录日志 */
    protected function logLogin(string $username, int $status, string $msg): void
    {
        try {
            SysLoginLog::create([
                'username' => $username,
                'ip' => request()->ip(),
                'browser' => mb_substr($this->parseBrowser(request()->header('user-agent', '')), 0, 50),
                'os' => mb_substr($this->parseOs(request()->header('user-agent', '')), 0, 50),
                'status' => $status,
                'msg' => $msg,
            ]);
        } catch (\Throwable) {
        }
    }

    /** 取可用的登录用户（Token 中间件调用） */
    public function getActiveUser(int $uid): array
    {
        $user = $this->dao->findByPk($uid);
        if (!$user || (int) $user['status'] !== 1) {
            throw ApiException::unauthorized('账号不存在或已停用');
        }
        $dept = $user['dept_id'] ? \app\admin\model\SysDept::where('id', $user['dept_id'])->value('name') : '';
        $user['dept_name'] = $dept ?: '';
        unset($user['password']);
        return $user;
    }

    /**
     * 用户信息接口返回：用户 + 角色 + 权限点 + 前端路由
     */
    public function getInfo(array $user): array
    {
        return [
            'user' => $user,
            'roles' => $this->getUserRoleCodes((int) $user['id']),
            'perms' => $this->getUserPerms($user),
            'menus' => SysMenuService::getInstance()->getUserMenus($user),
        ];
    }

    /** 用户角色编码集合 */
    public function getUserRoleCodes(int $userId): array
    {
        return \app\admin\model\SysRole::alias('r')
            ->join('SysUserRole ur', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->column('r.code');
    }

    /**
     * 权限点集合：超级管理员返回 ['*']
     */
    public function getUserPerms(array $user): array
    {
        $roleCodes = $this->getUserRoleCodes((int) $user['id']);
        if (in_array(\app\admin\model\SysRole::SUPER_CODE, $roleCodes, true)) {
            return ['*'];
        }
        return \app\admin\model\SysMenu::alias('m')
            ->join('SysRoleMenu rm', 'rm.menu_id = m.id')
            ->join('SysUserRole ur', 'ur.role_id = rm.role_id')
            ->where('ur.user_id', (int) $user['id'])
            ->where('m.perms', '<>', '')
            ->column('DISTINCT m.perms');
    }

    /** 简易 UA 解析 */
    protected function parseBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Unknown',
        };
    }

    protected function parseOs(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone|iPad') => 'iOS',
            default => 'Unknown',
        };
    }
}
