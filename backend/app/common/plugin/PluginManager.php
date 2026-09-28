<?php
declare(strict_types=1);

namespace app\common\plugin;

use app\admin\model\SysPlugin;
use app\common\exception\ApiException;
use app\common\traits\Instance;
use think\facade\Route;
use think\facade\Log;

/**
 * 插件管理器：发现 / 安装 / 卸载 / 启停 / 配置 / 路由注册 / 钩子注册
 *
 * 目录约定：
 *   backend/plugin/<name>/plugin.json    插件清单（必填）
 *   backend/plugin/<name>/Plugin.php     插件主类（可选，继承 AbstractPlugin）
 *   backend/plugin/<name>/controller/    插件控制器（可选，命名空间 plugin\<name>\controller）
 *   backend/plugin/<name>/ui/            前端页面（可选，安装时拷贝到 frontend/src/views/plugin/<name>/）
 *
 * 路由约定：已启用插件的 route 注册为 /admin/plugin/<name>/<rule>
 */
class PluginManager
{
    use Instance;

    /** 插件菜单挂载的父目录 perms */
    public const PARENT_PERMS = 'plugin:manage';

    /** 插件标识命名规则（防目录穿越） */
    private const NAME_PATTERN = '/^[a-z][a-z0-9_-]{0,49}$/';

    protected string $root = '';

    protected string $frontRoot = '';

    /** 已实例化的插件主类 */
    protected array $loaded = [];

    /** 钩子是否已注册 */
    protected bool $booted = false;

    /** manifest 缓存 */
    protected array $manifests = [];

    public function __construct()
    {
        $root = app()->getRootPath();
        $this->root = rtrim($root, '/\\') . '/plugin';
        $this->frontRoot = rtrim(dirname($root), '/\\') . '/frontend';
        $this->registerAutoload();
    }

    /** 插件根目录 */
    public function root(): string
    {
        return $this->root;
    }

    /** 单个插件目录 */
    public function dir(string $name): string
    {
        return $this->root . '/' . $name;
    }

    /** 插件前端页面目标目录 */
    public function uiDest(string $name): string
    {
        return $this->frontRoot . '/src/views/plugin/' . $name;
    }

    // ---------- 发现 ----------

    /**
     * 扫描磁盘 + 合并数据库状态
     * @return array<int, array>
     */
    public function discover(): array
    {
        $statusMap = $this->statusMap();
        $configMap = $this->configMap();
        $list = [];
        foreach ($this->scan() as $name => $manifest) {
            $installed = isset($statusMap[$name]);
            $status = $installed ? (int) $statusMap[$name] : 0;
            $dbVersion = $installed ? (string) ($statusMap[$name . '@version'] ?? '') : '';
            $list[] = [
                'name' => $name,
                'title' => $manifest['title'],
                'version' => $manifest['version'],
                'author' => $manifest['author'],
                'description' => $manifest['description'],
                'installed' => $installed,
                'status' => $status,
                'installed_version' => $dbVersion,
                'version_mismatch' => $installed && $dbVersion !== '' && $dbVersion !== $manifest['version'],
                'has_ui' => is_dir($this->dir($name) . '/ui'),
                'ui_installed' => is_dir($this->uiDest($name)),
                'menus' => $manifest['menus'],
                'routes' => $manifest['routes'],
                'config' => $manifest['config'],
                'config_values' => $configMap[$name] ?? $this->configDefaults($manifest),
                'hooks' => $manifest['hooks'],
                'install_time' => $installed ? (string) ($statusMap[$name . '@time'] ?? '') : '',
            ];
        }
        usort($list, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $list;
    }

    /** 磁盘上的插件清单 */
    public function scan(): array
    {
        $result = [];
        if (!is_dir($this->root)) {
            return $result;
        }
        foreach (glob($this->root . '/*/plugin.json') ?: [] as $file) {
            $name = basename(dirname($file));
            $manifest = $this->manifest($name);
            if ($manifest) {
                $result[$name] = $manifest;
            }
        }
        ksort($result);
        return $result;
    }

    /** 读取并规范化插件清单，非法返回 null */
    public function manifest(string $name): ?array
    {
        if (isset($this->manifests[$name])) {
            return $this->manifests[$name];
        }
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $file = $this->dir($name) . '/plugin.json';
        if (!is_file($file)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            Log::warning('[plugin] 清单解析失败：' . $name);
            return null;
        }
        $manifest = [
            'name' => $name,
            'title' => (string) ($json['title'] ?? $name),
            'version' => (string) ($json['version'] ?? '1.0.0'),
            'author' => (string) ($json['author'] ?? ''),
            'description' => (string) ($json['description'] ?? ''),
            'class' => (string) ($json['class'] ?? 'Plugin'),
            'routes' => $this->normalizeRoutes($json['routes'] ?? []),
            'menus' => $this->normalizeMenus($json['menus'] ?? []),
            'config' => $this->normalizeConfig($json['config'] ?? []),
            'hooks' => is_array($json['hooks'] ?? null) ? array_values(array_filter(array_map('strval', $json['hooks']))) : [],
        ];
        return $this->manifests[$name] = $manifest;
    }

    protected function normalizeRoutes(mixed $routes): array
    {
        if (!is_array($routes)) {
            return [];
        }
        $out = [];
        foreach ($routes as $route) {
            if (!is_array($route) || empty($route['rule']) || empty($route['action'])) {
                continue;
            }
            $out[] = [
                'method' => strtolower((string) ($route['method'] ?? 'get')),
                'rule' => trim((string) $route['rule'], '/'),
                'action' => trim((string) $route['action'], '/'),
                'desc' => (string) ($route['desc'] ?? ''),
            ];
        }
        return $out;
    }

    protected function normalizeMenus(mixed $menus): array
    {
        if (!is_array($menus)) {
            return [];
        }
        $out = [];
        foreach ($menus as $menu) {
            if (!is_array($menu) || empty($menu['name'])) {
                continue;
            }
            $out[] = [
                'name' => (string) $menu['name'],
                'path' => (string) ($menu['path'] ?? ''),
                'component' => (string) ($menu['component'] ?? ''),
                'perms' => (string) ($menu['perms'] ?? ''),
                'icon' => (string) ($menu['icon'] ?? 'MagicStick'),
                'order_num' => (int) ($menu['order_num'] ?? 99),
                'remark' => (string) ($menu['remark'] ?? ''),
            ];
        }
        return $out;
    }

    protected function normalizeConfig(mixed $config): array
    {
        if (!is_array($config)) {
            return [];
        }
        $out = [];
        foreach ($config as $item) {
            if (!is_array($item) || empty($item['key'])) {
                continue;
            }
            $out[] = [
                'key' => (string) $item['key'],
                'label' => (string) ($item['label'] ?? $item['key']),
                'type' => in_array((string) ($item['type'] ?? 'text'), ['text', 'textarea', 'number', 'switch', 'select'], true)
                    ? (string) ($item['type'] ?? 'text') : 'text',
                'default' => $item['default'] ?? '',
                'options' => is_array($item['options'] ?? null) ? $item['options'] : [],
                'required' => !empty($item['required']),
                'tip' => (string) ($item['tip'] ?? ''),
            ];
        }
        return $out;
    }

    /** schema 默认配置 */
    public function configDefaults(array $manifest): array
    {
        $defaults = [];
        foreach ($manifest['config'] ?? [] as $item) {
            $defaults[$item['key']] = $item['default'] ?? '';
        }
        return $defaults;
    }

    // ---------- 状态 ----------

    /** name => status，附带 name@version / name@time */
    protected function statusMap(): array
    {
        try {
            $rows = SysPlugin::field('name,version,status,install_time')->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $map[$row['name']] = $row['status'];
            $map[$row['name'] . '@version'] = $row['version'];
            $map[$row['name'] . '@time'] = $row['install_time'] ?? '';
        }
        return $map;
    }

    protected function configMap(): array
    {
        try {
            $rows = SysPlugin::field('name,config')->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $values = [];
            if (!empty($row['config'])) {
                $decoded = json_decode((string) $row['config'], true);
                if (is_array($decoded)) {
                    $values = $decoded;
                }
            }
            $manifest = $this->manifest((string) $row['name']);
            $map[$row['name']] = $manifest
                ? array_merge($this->configDefaults($manifest), $values)
                : $values;
        }
        return $map;
    }

    public function row(string $name): ?array
    {
        $row = SysPlugin::where('name', $name)->find();
        return $row ? $row->toArray() : null;
    }

    public function isInstalled(string $name): bool
    {
        return $this->row($name) !== null;
    }

    public function isEnabled(string $name): bool
    {
        $row = $this->row($name);
        return $row !== null && (int) $row['status'] === 1;
    }

    /** 已启用插件名列表 */
    public function enabled(): array
    {
        try {
            return SysPlugin::where('status', 1)->column('name');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 读取某插件生效配置（含默认值） */
    public function configOf(string $name): array
    {
        $manifest = $this->manifest($name);
        if (!$manifest) {
            throw ApiException::notFound('插件不存在');
        }
        $values = $this->configDefaults($manifest);
        $row = $this->row($name);
        if ($row && !empty($row['config'])) {
            $decoded = json_decode((string) $row['config'], true);
            if (is_array($decoded)) {
                $values = array_merge($values, $decoded);
            }
        }
        return $values;
    }

    // ---------- 生命周期 ----------

    /** 安装：写库 + 建菜单 + 拷前端页面 + 执行主类 install() */
    public function install(string $name, bool $force = false): array
    {
        $manifest = $this->requireManifest($name);
        if ($this->isInstalled($name)) {
            if (!$force) {
                throw ApiException::badRequest('插件已安装，请勿重复安装');
            }
            $this->uninstall($name);
        }

        $now = date('Y-m-d H:i:s');
        $row = SysPlugin::create([
            'name' => $name,
            'title' => $manifest['title'],
            'version' => $manifest['version'],
            'author' => $manifest['author'],
            'description' => $manifest['description'],
            'status' => 1,
            'config' => json_encode($this->configDefaults($manifest), JSON_UNESCAPED_UNICODE),
            'install_time' => $now,
            'create_time' => $now,
        ]);

        $plugin = $this->load($name);
        if ($plugin) {
            try {
                $plugin->install();
            } catch (\Throwable $e) {
                // 安装失败回滚，避免留下半截状态
                SysPlugin::where('name', $name)->delete();
                throw ApiException::badRequest('插件安装失败：' . $e->getMessage());
            }
        }
        $this->boot(true);
        Log::info('[plugin] 安装 ' . $name . ' v' . $manifest['version']);
        return $row->toArray();
    }

    /** 卸载：执行主类 uninstall() + 删菜单/前端页面 + 删库记录 */
    public function uninstall(string $name): array
    {
        $manifest = $this->requireManifest($name);
        $row = SysPlugin::where('name', $name)->find();
        if (!$row) {
            throw ApiException::notFound('插件未安装');
        }
        $plugin = $this->load($name);
        if ($plugin) {
            try {
                $plugin->uninstall();
            } catch (\Throwable $e) {
                Log::error('[plugin] 卸载清理失败 ' . $name . '：' . $e->getMessage());
            }
        }
        $title = (string) $row->title;
        $row->delete();
        $this->boot(true);
        Log::info('[plugin] 卸载 ' . $name);
        return ['name' => $name, 'title' => $title ?: $manifest['title']];
    }

    public function enable(string $name): array
    {
        $row = SysPlugin::where('name', $name)->find();
        if (!$row) {
            throw ApiException::notFound('插件未安装');
        }
        $row->save(['status' => 1]);
        $plugin = $this->load($name);
        if ($plugin) {
            $plugin->enable();
        }
        $this->boot(true);
        return ['name' => $name, 'status' => 1];
    }

    public function disable(string $name): array
    {
        $row = SysPlugin::where('name', $name)->find();
        if (!$row) {
            throw ApiException::notFound('插件未安装');
        }
        $row->save(['status' => 2]);
        $plugin = $this->load($name);
        if ($plugin) {
            $plugin->disable();
        }
        $this->boot(true);
        return ['name' => $name, 'status' => 2];
    }

    /** 保存配置（按 schema 校验与类型转换） */
    public function saveConfig(string $name, array $input): array
    {
        $manifest = $this->requireManifest($name);
        $row = SysPlugin::where('name', $name)->find();
        if (!$row) {
            throw ApiException::notFound('插件未安装，请先安装');
        }
        $data = [];
        foreach ($manifest['config'] as $item) {
            $key = $item['key'];
            $value = $input[$key] ?? $item['default'] ?? '';
            switch ($item['type']) {
                case 'number':
                    $value = is_numeric($value) ? $value + 0 : 0;
                    break;
                case 'switch':
                    $value = (int) !in_array($value, [0, '0', false, null, ''], true);
                    break;
                case 'select':
                    $allowed = array_map('strval', array_keys($item['options']));
                    if ($item['options'] && !in_array((string) $value, $allowed, true)) {
                        throw ApiException::badRequest($item['label'] . '取值非法');
                    }
                    $value = (string) $value;
                    break;
                default:
                    $value = is_scalar($value) ? (string) $value : '';
            }
            if ($item['required'] && ($value === '' || $value === null)) {
                throw ApiException::badRequest($item['label'] . '不能为空');
            }
            $data[$key] = $value;
        }
        $row->save(['config' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return $data;
    }

    // ---------- 运行时 ----------

    /** 实例化插件主类（不存在返回 null） */
    public function load(string $name): ?AbstractPlugin
    {
        if (array_key_exists($name, $this->loaded)) {
            return $this->loaded[$name];
        }
        $manifest = $this->manifest($name);
        if (!$manifest) {
            return $this->loaded[$name] = null;
        }
        $class = $this->namespace($name) . '\\' . str_replace('/', '\\', $manifest['class']);
        if (!class_exists($class)) {
            return $this->loaded[$name] = null;
        }
        $instance = new $class($manifest);
        if (!$instance instanceof AbstractPlugin) {
            Log::warning('[plugin] 主类未继承 AbstractPlugin：' . $class);
            return $this->loaded[$name] = null;
        }
        return $this->loaded[$name] = $instance;
    }

    /** 注册已启用插件的钩子（每请求一次） */
    public function boot(bool $fresh = false): void
    {
        if ($this->booted && !$fresh) {
            return;
        }
        $this->booted = true;
        if ($fresh) {
            Hook::getInstance()->clear();
        }
        foreach ($this->enabled() as $name) {
            $plugin = $this->load((string) $name);
            if (!$plugin) {
                continue;
            }
            try {
                $plugin->register();
            } catch (\Throwable $e) {
                Log::error('[plugin] ' . $name . ' 钩子注册失败：' . $e->getMessage());
            }
        }
    }

    /** 在 admin 路由文件里调用：把已启用插件的路由挂到 /admin/plugin/<name>/... */
    public function registerRoutes(): int
    {
        $this->boot();
        $count = 0;
        foreach ($this->enabled() as $name) {
            $manifest = $this->manifest((string) $name);
            if (!$manifest) {
                continue;
            }
            foreach ($manifest['routes'] as $route) {
                $method = in_array($route['method'], ['get', 'post', 'put', 'delete', 'any'], true) ? $route['method'] : 'get';
                $rule = 'plugin/' . $name . '/' . $route['rule'];
                $action = '\\' . $this->namespace((string) $name) . '\\controller\\' . str_replace('/', '@', $route['action']);
                Route::{$method}($rule, $action);
                $count++;
            }
        }
        return $count;
    }

    /** 触发事件（业务侧埋点入口） */
    public function trigger(string $event, mixed $payload = null): mixed
    {
        $this->boot();
        return Hook::getInstance()->trigger($event, $payload);
    }

    /** 已注册钩子一览（调试用） */
    public function hooks(): array
    {
        $this->boot();
        $events = Hook::getInstance()->events();
        $out = [];
        foreach ($events as $event) {
            $out[] = ['event' => $event, 'listeners' => Hook::getInstance()->count($event)];
        }
        return $out;
    }

    /**
     * 插件命名空间：目录名允许连字符，PHP 命名空间不允许，统一转成下划线
     * login-notify => plugin\login_notify
     */
    public function namespace(string $name): string
    {
        return 'plugin\\' . str_replace('-', '_', $name);
    }

    protected function requireManifest(string $name): array
    {
        $manifest = $this->manifest($name);
        if (!$manifest) {
            throw ApiException::notFound('插件不存在或清单非法：' . $name);
        }
        return $manifest;
    }

    /** 让 plugin\ 命名空间无需 composer dump 也能加载 */
    protected function registerAutoload(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        $root = $this->root;
        spl_autoload_register(function (string $class) use ($root) {
            if (!str_starts_with($class, 'plugin\\')) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, 7));
            if (str_contains($relative, '..')) {
                return;
            }
            // 先按原样找，再尝试下划线 → 连字符（目录 login-notify / 命名空间 login_notify）
            $candidates = [
                $root . '/' . $relative . '.php',
                $root . '/' . str_replace('_', '-', $relative) . '.php',
            ];
            foreach ($candidates as $file) {
                if (is_file($file)) {
                    require $file;
                    return;
                }
            }
        });
    }
}
