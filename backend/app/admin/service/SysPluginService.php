<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\plugin\PluginManager;
use app\common\service\AbstractService;
use app\admin\dao\SysPluginDao;

/**
 * 插件服务：以磁盘清单为准、DB 只保存安装状态与配置
 */
class SysPluginService extends AbstractService
{
    protected array $allowFields = ['name'];

    public function __construct()
    {
        $this->dao = SysPluginDao::getInstance();
    }

    /** 管理器实例 */
    public function manager(): PluginManager
    {
        return PluginManager::getInstance();
    }

    /**
     * 插件列表（内存分页：插件数量有限，且要合并磁盘与 DB 两侧信息）
     */
    public function page(array $params = []): array
    {
        $list = $this->manager()->discover();

        $keyword = mb_strtolower(trim((string) ($params['keyword'] ?? '')));
        if ($keyword !== '') {
            $list = array_values(array_filter($list, function ($item) use ($keyword) {
                return str_contains(mb_strtolower($item['title'] . ' ' . $item['name'] . ' ' . $item['author']), $keyword);
            }));
        }
        $status = $params['status'] ?? null;
        if ($status !== null && $status !== '') {
            $list = array_values(array_filter($list, fn ($item) => (int) $item['status'] === (int) $status));
        }

        $total = count($list);
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $slice = array_slice($list, ($page - 1) * $limit, $limit);

        return [
            'list' => $slice,
            'total' => $total,
            'overview' => $this->overview($list),
        ];
    }

    /** 统计概览 */
    public function overview(?array $list = null): array
    {
        $list ??= $this->manager()->discover();
        $installed = 0;
        $enabled = 0;
        $upgradable = 0;
        foreach ($list as $item) {
            if ($item['installed']) {
                $installed++;
            }
            if ((int) $item['status'] === 1) {
                $enabled++;
            }
            if (!empty($item['version_mismatch'])) {
                $upgradable++;
            }
        }
        return [
            'total' => count($list),
            'installed' => $installed,
            'enabled' => $enabled,
            'upgradable' => $upgradable,
        ];
    }

    public function install(string $name, bool $force = false): array
    {
        return $this->manager()->install($name, $force);
    }

    public function uninstall(string $name): array
    {
        return $this->manager()->uninstall($name);
    }

    public function enable(string $name): array
    {
        return $this->manager()->enable($name);
    }

    public function disable(string $name): array
    {
        return $this->manager()->disable($name);
    }

    /** 配置：schema + 当前值 */
    public function config(string $name): array
    {
        $manifest = $this->manager()->manifest($name);
        if (!$manifest) {
            return ['schema' => [], 'values' => []];
        }
        return [
            'schema' => $manifest['config'],
            'values' => $this->manager()->configOf($name),
        ];
    }

    public function saveConfig(string $name, array $input): array
    {
        return $this->manager()->saveConfig($name, $input);
    }

    /** 已注册钩子 */
    public function hooks(): array
    {
        return $this->manager()->hooks();
    }
}
