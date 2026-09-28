<?php
declare(strict_types=1);

namespace plugin\hello\controller;

use app\common\controller\AbstractController;
use app\common\plugin\PluginManager;
use think\Response;

/**
 * Hello 插件接口
 * 路由由 PluginManager 自动注册：/admin/plugin/hello/index、/admin/plugin/hello/greet
 */
class Index extends AbstractController
{
    public const PLUGIN_NAME = 'hello';

    private ?\plugin\hello\Plugin $plugin = null;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $loaded = PluginManager::getInstance()->load(self::PLUGIN_NAME);
        $this->plugin = $loaded instanceof \plugin\hello\Plugin ? $loaded : null;
    }

    /** 插件信息 GET /admin/plugin/hello/index */
    public function index(): Response
    {
        $manifest = PluginManager::getInstance()->manifest(self::PLUGIN_NAME) ?: [];
        return $this->success([
            'name' => self::PLUGIN_NAME,
            'title' => $manifest['title'] ?? self::PLUGIN_NAME,
            'version' => $manifest['version'] ?? '',
            'author' => $manifest['author'] ?? '',
            'description' => $manifest['description'] ?? '',
            'config' => $this->plugin ? $this->plugin->config() : [],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 问候语 GET /admin/plugin/hello/greet?name=xxx */
    public function greet(): Response
    {
        $conf = $this->plugin ? $this->plugin->config() : [];
        $name = trim((string) $this->request->param('name', ''));
        $text = (string) ($conf['greeting'] ?? '你好');
        if ($name !== '') {
            $text .= '，' . $name;
        }
        $maxItems = max(1, min(20, (int) ($conf['max_items'] ?? 5)));
        $items = [];
        for ($i = 1; $i <= $maxItems; $i++) {
            $items[] = ['id' => $i, 'label' => "示例条目 {$i}"];
        }
        return $this->success([
            'text' => $text,
            'time' => (int) ($conf['show_time'] ?? 1) === 1 ? date('Y-m-d H:i:s') : '',
            'theme' => (string) ($conf['theme'] ?? 'blue'),
            'items' => $items,
            'operator' => (string) (request()->user['username'] ?? ''),
        ]);
    }
}
