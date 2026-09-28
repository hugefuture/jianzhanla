<?php
declare(strict_types=1);

namespace plugin\login_notify\controller;

use app\common\controller\AbstractController;
use app\common\plugin\PluginManager;
use think\Response;

/**
 * 登录动态插件接口
 * 路由：/admin/plugin/login-notify/stats、/recent、/clear
 */
class Index extends AbstractController
{
    public const PLUGIN_NAME = 'login-notify';

    private ?\plugin\login_notify\Plugin $plugin = null;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $loaded = PluginManager::getInstance()->load(self::PLUGIN_NAME);
        $this->plugin = $loaded instanceof \plugin\login_notify\Plugin ? $loaded : null;
    }

    /** 统计 GET /admin/plugin/login-notify/stats */
    public function stats(): Response
    {
        if (!$this->plugin) {
            return $this->fail('插件未加载', 500);
        }
        return $this->success($this->plugin->stats());
    }

    /** 最近动态 GET /admin/plugin/login-notify/recent?limit=20 */
    public function recent(): Response
    {
        if (!$this->plugin) {
            return $this->fail('插件未加载', 500);
        }
        $limit = max(1, min(200, (int) $this->request->param('limit', 20)));
        return $this->success(array_slice($this->plugin->events(), 0, $limit));
    }

    /** 清空 POST /admin/plugin/login-notify/clear */
    public function clear(): Response
    {
        if (!$this->plugin) {
            return $this->fail('插件未加载', 500);
        }
        $this->plugin->clear();
        return $this->success(null, '已清空');
    }
}
