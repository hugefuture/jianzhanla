<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\service\AuthService;
use app\admin\service\UpgradeService;
use app\common\controller\AbstractController;
use app\common\exception\ApiException;
use think\Response;

/**
 * 远程升级
 *
 * 注意：升级会改动磁盘文件与数据库，只有超管（role_id=1 或 is_admin）可执行变更类操作
 */
class Upgrade extends AbstractController
{
    /** 当前版本概况 GET system/upgrade/info */
    public function info(): Response
    {
        return $this->success(UpgradeService::getInstance()->info());
    }

    /** 检查更新 POST system/upgrade/check（body: {force: 1}） */
    public function check(): Response
    {
        $force = (bool) ((int) $this->request->param('force', 0));
        return $this->success(UpgradeService::getInstance()->check($force), '检查完成');
    }

    /** 升级历史 GET system/upgrade/history */
    public function history(): Response
    {
        return $this->success(UpgradeService::getInstance()->history($this->request->param()));
    }

    /** 执行升级 POST system/upgrade/apply（body: {version: '1.1.0'}） */
    public function apply(): Response
    {
        $this->guardAdmin();
        $version = trim((string) $this->request->param('version', ''));
        if ($version === '') {
            throw ApiException::badRequest('请指定要升级到的版本号');
        }
        $localZip = trim((string) $this->request->param('local_package', ''));
        $result = UpgradeService::getInstance()->apply($version, $this->userId(), $localZip);
        return $this->success($result, "已升级到 {$version}");
    }

    /** 回滚 POST system/upgrade/rollback（body: {id: 1, with_db: 0}） */
    public function rollback(): Response
    {
        $this->guardAdmin();
        $id = (int) $this->request->param('id', 0);
        if ($id <= 0) {
            throw ApiException::badRequest('请选择要回滚的记录');
        }
        $withDb = (bool) ((int) $this->request->param('with_db', 0));
        return $this->success(UpgradeService::getInstance()->rollback($id, $withDb, $this->userId()), '回滚完成');
    }

    /** 清理升级下载缓存 POST system/upgrade/clearCache */
    public function clearCache(): Response
    {
        $this->guardAdmin();
        $n = UpgradeService::getInstance()->clearCache(true);
        return $this->success(['cleared' => $n], "已清理 {$n} 个缓存文件");
    }

    /**
     * 变更类操作的超管鉴权：仅 role code = super 的账号可执行
     */
    private function guardAdmin(): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }
        throw ApiException::forbidden('仅超级管理员可执行升级/回滚操作');
    }

    private function isSuperAdmin(): bool
    {
        $uid = $this->userId();
        if ($uid <= 0) {
            return false;
        }
        $codes = AuthService::getInstance()->getUserRoleCodes($uid);
        return in_array(\app\admin\model\SysRole::SUPER_CODE, $codes, true);
    }

    private function userId(): int
    {
        $user = $this->request->user ?? [];
        return (int) ($user['id'] ?? $user['uid'] ?? 0);
    }
}
