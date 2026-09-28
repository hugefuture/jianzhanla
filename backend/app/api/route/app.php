<?php
// api 应用路由
use think\facade\Route;

Route::get('/', 'Index/index');

// 注册已启用插件的钩子（api 应用无插件路由，但事件监听要生效）
\app\common\plugin\PluginManager::getInstance()->boot();

// ---------- 公开公告（只读，仅供已发布内容）----------
Route::get('notice/list', 'Notice/list');
Route::get('notice/read', 'Notice/read');

Route::miss(function () {
    return json(['code' => 404, 'msg' => '接口不存在', 'data' => (object) []]);
});
