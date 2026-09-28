<?php
declare(strict_types=1);

namespace app\common\plugin;

/**
 * 插件主类契约
 *
 * 插件目录下可选提供 Plugin.php（类名 plugin\<name>\Plugin），
 * 继承 AbstractPlugin 后按需覆写 install/uninstall/enable/disable/register 即可。
 */
interface PluginInterface
{
    /** 安装：建菜单、拷贝前端页面、初始化数据 */
    public function install(): void;

    /** 卸载：删菜单、删前端页面、清理数据 */
    public function uninstall(): void;

    /** 启用 */
    public function enable(): void;

    /** 禁用 */
    public function disable(): void;

    /** 注册钩子（每次请求启动时对已启用插件调用一次） */
    public function register(): void;
}
