<?php
declare(strict_types=1);

namespace app\common\plugin;

use app\admin\model\SysMenu;
use app\admin\model\SysRoleMenu;
use think\facade\Log;

/**
 * 插件基类：把「建菜单 / 拷前端页面 / 读配置 / 写私有数据」这些重复劳动做好，
 * 具体插件只关心自己的业务（接口、钩子、页面）。
 */
abstract class AbstractPlugin implements PluginInterface
{
    /** 插件标识（目录名） */
    protected string $name = '';

    /** manifest 内容 */
    protected array $manifest = [];

    /** 插件目录物理路径 */
    protected string $dir = '';

    public function __construct(array $manifest)
    {
        $this->manifest = $manifest;
        $this->name = (string) ($manifest['name'] ?? '');
        $this->dir = PluginManager::getInstance()->dir($this->name);
    }

    // ---------- 基本信息 ----------

    public function name(): string
    {
        return $this->name;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /** 插件内相对路径 */
    public function path(string $sub = ''): string
    {
        return rtrim($this->dir . '/' . ltrim($sub, '/'), '/');
    }

    // ---------- 生命周期（通用流程 + 子类钩子） ----------

    public function install(): void
    {
        $this->syncMenus();
        $this->copyUi();
        $this->onInstall();
    }

    public function uninstall(): void
    {
        $this->onUninstall();
        $this->removeMenus();
        $this->removeUi();
    }

    public function enable(): void
    {
        $this->setMenuVisible(1);
        $this->onEnable();
    }

    public function disable(): void
    {
        $this->setMenuVisible(0);
        $this->onDisable();
    }

    public function register(): void
    {
        // 默认不监听任何事件，子类覆写
    }

    protected function onInstall(): void {}
    protected function onUninstall(): void {}
    protected function onEnable(): void {}
    protected function onDisable(): void {}

    // ---------- 配置 ----------

    /**
     * 读取插件配置：DB 中保存的值覆盖 schema 默认值
     * config() 返回全部；config('key') 返回单项
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        $values = $this->rawConfig();
        if ($key === null) {
            return $values;
        }
        return $values[$key] ?? $default;
    }

    /** DB 中存储的配置（已合并默认值） */
    protected function rawConfig(): array
    {
        $values = [];
        foreach ($this->manifest['config'] ?? [] as $item) {
            if (!empty($item['key'])) {
                $values[$item['key']] = $item['default'] ?? '';
            }
        }
        $saved = \app\admin\model\SysPlugin::where('name', $this->name)->value('config');
        if (is_string($saved) && $saved !== '') {
            $decoded = json_decode($saved, true);
            if (is_array($decoded)) {
                $values = array_merge($values, $decoded);
            }
        } elseif (is_array($saved)) {
            $values = array_merge($values, $saved);
        }
        return $values;
    }

    // ---------- 私有数据与日志 ----------

    /** 插件私有数据目录 runtime/plugin/<name>/ */
    public function dataPath(string $file = ''): string
    {
        $dir = app()->getRuntimePath() . 'plugin/' . $this->name;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $file === '' ? rtrim($dir, '/') : $dir . '/' . ltrim($file, '/');
    }

    protected function log(string $message, string $level = 'info'): void
    {
        $line = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
        @file_put_contents($this->dataPath('plugin.log'), $line, FILE_APPEND);
        Log::{$level}('[plugin:' . $this->name . '] ' . $message);
    }

    protected function hook(): Hook
    {
        return Hook::getInstance();
    }

    // ---------- 菜单 ----------

    /** 按 manifest.menus 建菜单，并授权给超管 */
    protected function syncMenus(): int
    {
        $parentId = $this->parentMenuId();
        $created = 0;
        foreach ($this->manifest['menus'] ?? [] as $menu) {
            $perms = (string) ($menu['perms'] ?? '');
            if ($perms === '') {
                continue;
            }
            $exists = SysMenu::where('perms', $perms)->find();
            if ($exists) {
                $exists->save([
                    'parent_id' => $parentId,
                    'name' => $menu['name'] ?? $exists->name,
                    'component' => $menu['component'] ?? '',
                    'status' => 1,
                    'visible' => 1,
                ]);
                continue;
            }
            $row = SysMenu::create([
                'parent_id' => $parentId,
                'name' => (string) ($menu['name'] ?? $this->manifest['title'] ?? $this->name),
                'type' => SysMenu::TYPE_MENU,
                'perms' => $perms,
                'path' => (string) ($menu['path'] ?? $this->name),
                'component' => (string) ($menu['component'] ?? ''),
                'icon' => (string) ($menu['icon'] ?? 'MagicStick'),
                'order_num' => (int) ($menu['order_num'] ?? 99),
                'visible' => 1,
                'status' => 1,
                'remark' => (string) ($menu['remark'] ?? ('插件 ' . $this->name . ' 自动注册')),
            ]);
            $this->grantToSuperAdmin((int) $row->id);
            $created++;
        }
        return $created;
    }

    /** 删除本插件注册的菜单（按 perms 与 component 双条件匹配，避免误删） */
    protected function removeMenus(): int
    {
        $perms = [];
        $components = [];
        foreach ($this->manifest['menus'] ?? [] as $menu) {
            if (!empty($menu['perms'])) {
                $perms[] = (string) $menu['perms'];
            }
            if (!empty($menu['component'])) {
                $components[] = (string) $menu['component'];
            }
        }
        $components[] = 'plugin/' . $this->name . '/index';

        $ids = SysMenu::where(function ($q) use ($perms) {
                if ($perms) {
                    $q->whereIn('perms', $perms);
                }
            })
            ->whereOr(function ($q) use ($components) {
                $q->whereIn('component', $components);
            })
            ->column('id');

        $ids = array_map('intval', $ids);
        if (!$ids) {
            return 0;
        }
        SysRoleMenu::whereIn('menu_id', $ids)->delete();
        return SysMenu::whereIn('id', $ids)->delete();
    }

    /** 启停：同步菜单可见性 */
    protected function setMenuVisible(int $visible): void
    {
        $perms = [];
        foreach ($this->manifest['menus'] ?? [] as $menu) {
            if (!empty($menu['perms'])) {
                $perms[] = (string) $menu['perms'];
            }
        }
        if (!$perms) {
            return;
        }
        SysMenu::whereIn('perms', $perms)->update([
            'visible' => $visible,
            'status' => $visible,
        ]);
    }

    /** 插件菜单的统一父级：插件管理目录 */
    protected function parentMenuId(): int
    {
        $id = (int) (SysMenu::where('perms', PluginManager::PARENT_PERMS)->value('id') ?: 0);
        return $id;
    }

    protected function grantToSuperAdmin(int $menuId): void
    {
        if ($menuId <= 0) {
            return;
        }
        $exists = SysRoleMenu::where('role_id', 1)->where('menu_id', $menuId)->find();
        if (!$exists) {
            SysRoleMenu::create(['role_id' => 1, 'menu_id' => $menuId]);
        }
    }

    // ---------- 前端页面 ----------

    /** 插件 ui/ 目录 → frontend/src/views/plugin/<name>/ */
    public function copyUi(): int
    {
        $src = $this->path('ui');
        if (!is_dir($src)) {
            return 0;
        }
        $dest = PluginManager::getInstance()->uiDest($this->name);
        return $this->copyDir($src, $dest);
    }

    public function removeUi(): bool
    {
        $dest = PluginManager::getInstance()->uiDest($this->name);
        if (!is_dir($dest)) {
            return false;
        }
        return $this->delDir($dest);
    }

    protected function copyDir(string $src, string $dest): int
    {
        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }
        $count = 0;
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dest . '/' . $item;
            if (is_dir($from)) {
                $count += $this->copyDir($from, $to);
                continue;
            }
            if (@copy($from, $to)) {
                $count++;
            }
        }
        return $count;
    }

    protected function delDir(string $dir): bool
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delDir($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }
}
