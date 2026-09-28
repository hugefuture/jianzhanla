<?php
declare(strict_types=1);

namespace plugin\hello;

use app\common\plugin\AbstractPlugin;

/**
 * Hello 示例插件主类
 *
 * 示范三件事：
 *  1) register() 里监听业务事件（这里是公告发布/下架）
 *  2) 通过 $this->config() 读取管理端保存的插件配置
 *  3) 通过 $this->log() 写插件私有日志（runtime/plugin/hello/plugin.log）
 */
class Plugin extends AbstractPlugin
{
    public function register(): void
    {
        $this->hook()->listen('notice.publish.after', function ($payload) {
            if (!is_array($payload)) {
                return null;
            }
            $action = (int) ($payload['status'] ?? 0) === 1 ? '已发布' : '已下架';
            $this->log('公告「' . (string) ($payload['title'] ?? '') . '」' . $action);
            return null;
        });
    }

    /** 安装时额外动作：写一条安装标记，便于演示私有数据目录用法 */
    protected function onInstall(): void
    {
        $this->log('插件安装完成 v' . ($this->manifest['version'] ?? '1.0.0'));
    }

    protected function onUninstall(): void
    {
        $this->log('插件已卸载');
    }
}
