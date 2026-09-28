<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\service\SysPluginService;
use app\common\controller\AbstractController;
use app\common\exception\ApiException;
use think\Response;

/**
 * 插件管理：发现 / 安装 / 卸载 / 启停 / 配置
 */
class Plugin extends AbstractController
{
    private SysPluginService $svc;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->svc = SysPluginService::getInstance();
    }

    /** 插件列表 GET system/plugin/list */
    public function index(): Response
    {
        return $this->success($this->svc->page($this->request->param()));
    }

    /** 安装 POST system/plugin/install {name, force?} */
    public function install(): Response
    {
        $name = $this->name();
        $force = (int) $this->request->param('force', 0) === 1;
        return $this->success($this->svc->install($name, $force), '安装成功');
    }

    /** 卸载 POST system/plugin/uninstall {name} */
    public function uninstall(): Response
    {
        return $this->success($this->svc->uninstall($this->name()), '卸载成功');
    }

    /** 启用 POST system/plugin/enable {name} */
    public function enable(): Response
    {
        return $this->success($this->svc->enable($this->name()), '已启用');
    }

    /** 禁用 POST system/plugin/disable {name} */
    public function disable(): Response
    {
        return $this->success($this->svc->disable($this->name()), '已禁用');
    }

    /** 配置读取 GET system/plugin/config?name= */
    public function config(): Response
    {
        return $this->success($this->svc->config($this->name()));
    }

    /** 配置保存 POST system/plugin/saveConfig {name, config:{}} */
    public function saveConfig(): Response
    {
        $name = $this->name();
        $input = $this->request->param('config');
        if (!is_array($input)) {
            $input = $this->request->param();
            unset($input['name']);
        }
        return $this->success($this->svc->saveConfig($name, $input), '配置已保存');
    }

    /** 已注册钩子 GET system/plugin/hooks */
    public function hooks(): Response
    {
        return $this->success($this->svc->hooks());
    }

    private function name(): string
    {
        $name = (string) $this->request->param('name', '');
        if ($name === '') {
            throw ApiException::badRequest('缺少插件标识');
        }
        return $name;
    }
}
