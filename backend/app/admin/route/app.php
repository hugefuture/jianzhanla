<?php
// admin 应用路由
use app\common\middleware\OperLog;
use app\common\middleware\Token;
use think\facade\Route;

// 公开接口（无需登录）
Route::group(function () {
    Route::post('login', 'Login/login');
    Route::get('captcha', 'Login/captcha');
})->middleware([]);

// 需要登录的接口
Route::group(function () {
    // 账户
    Route::get('getInfo', 'Login/getInfo');
    Route::post('logout', 'Login/logout');
    Route::post('refresh', 'Login/refresh');

    // 个人中心
    Route::get('profile/info', 'Profile/info');
    Route::post('profile/update', 'Profile/update');
    Route::post('profile/password', 'Profile/password');

    // 上传
    Route::post('upload/image', 'Upload/image');
    Route::post('upload/file', 'Upload/file');

    // 仪表盘
    Route::get('index/stats', 'Index/stats');

    // ---------- 用户管理 ----------
    Route::get('system/user/list', 'system.User/index');
    Route::get('system/user/read/:id', 'system.User/read');
    Route::post('system/user/save', 'system.User/save');
    Route::post('system/user/delete', 'system.User/delete');
    Route::post('system/user/status', 'system.User/status');
    Route::post('system/user/resetPwd', 'system.User/resetPwd');
    Route::post('system/user/assignRoles', 'system.User/assignRoles');
    Route::get('system/user/roleOptions', 'system.User/roleOptions');
    Route::get('system/user/postOptions', 'system.User/postOptions');

    // ---------- 在线用户 ----------
    Route::get('system/online/list', 'system.Online/index');
    Route::post('system/online/kick', 'system.Online/kick');
    Route::post('system/online/kickUser', 'system.Online/kickUser');

    // ---------- 系统监控 ----------
    Route::get('system/monitor/index', 'system.Monitor/index');
    // ---------- 定时任务执行日志 ----------
    Route::get('system/monitor/tasklog', 'system.Monitor/tasklog');
    Route::get('system/monitor/taskOverview', 'system.Monitor/taskOverview');
    Route::post('system/monitor/clearTasklog', 'system.Monitor/clearTasklog');

    // ---------- 角色管理 ----------
    Route::get('system/role/list', 'system.Role/index');
    Route::get('system/role/read/:id', 'system.Role/read');
    Route::get('system/role/all', 'system.Role/all');
    Route::get('system/role/scopeOptions', 'system.Role/scopeOptions');
    Route::post('system/role/saveDepts', 'system.Role/saveDepts');
    Route::get('system/role/menuIds/:id', 'system.Role/menuIds');
    Route::post('system/role/save', 'system.Role/save');
    Route::post('system/role/delete', 'system.Role/delete');
    Route::post('system/role/status', 'system.Role/status');
    Route::post('system/role/assignMenus', 'system.Role/assignMenus');

    // ---------- 菜单管理 ----------
    Route::get('system/menu/list', 'system.Menu/index');
    Route::get('system/menu/read/:id', 'system.Menu/read');
    Route::get('system/menu/tree', 'system.Menu/tree');
    Route::post('system/menu/save', 'system.Menu/save');
    Route::post('system/menu/delete', 'system.Menu/delete');

    // ---------- 部门管理 ----------
    Route::get('system/dept/list', 'system.Dept/index');
    Route::get('system/dept/read/:id', 'system.Dept/read');
    Route::get('system/dept/tree', 'system.Dept/tree');
    Route::post('system/dept/save', 'system.Dept/save');
    Route::post('system/dept/delete', 'system.Dept/delete');

    // ---------- 岗位管理 ----------
    Route::get('system/post/list', 'system.Post/index');
    Route::get('system/post/read/:id', 'system.Post/read');
    Route::post('system/post/save', 'system.Post/save');
    Route::post('system/post/delete', 'system.Post/delete');
    Route::post('system/post/status', 'system.Post/status');

    // ---------- 字典管理 ----------
    Route::get('system/dict/list', 'system.Dict/index');
    Route::get('system/dict/read/:id', 'system.Dict/read');
    Route::post('system/dict/save', 'system.Dict/save');
    Route::post('system/dict/delete', 'system.Dict/delete');
    Route::get('system/dict/dataList', 'system.Dict/dataList');
    Route::get('system/dict/dataByType/:type', 'system.Dict/dataByType');
    Route::post('system/dict/saveData', 'system.Dict/saveData');
    Route::post('system/dict/deleteData', 'system.Dict/deleteData');

    // ---------- 参数配置 ----------
    Route::get('system/config/list', 'system.Config/index');
    Route::get('system/config/read/:id', 'system.Config/read');
    Route::get('system/config/getKey/:key', 'system.Config/getKey');
    Route::get('system/config/allValues', 'system.Config/allValues');
    Route::post('system/config/save', 'system.Config/save');
    Route::post('system/config/delete', 'system.Config/delete');
    Route::post('system/config/status', 'system.Config/status');

    // ---------- 文件管理 ----------
    Route::get('system/file/list', 'system.File/index');
    Route::get('system/file/read/:id', 'system.File/read');
    Route::post('system/file/delete', 'system.File/delete');
    Route::get('system/file/stats', 'system.File/stats');
    Route::post('system/file/clearOrphan', 'system.File/clearOrphan');
    Route::get('system/file/download', 'system.File/download');

    // ---------- 日志管理 ----------
    Route::get('system/log/oper', 'system.Log/oper');
    Route::post('system/log/clearOper', 'system.Log/clearOper');
    Route::get('system/log/login', 'system.Log/login');
    Route::post('system/log/clearLogin', 'system.Log/clearLogin');

    // ---------- 通知公告（业务模块样例 biz.Notice） ----------
    Route::get('biz/notice/list', 'biz.Notice/index');
    Route::get('biz/notice/read/:id', 'biz.Notice/read');
    Route::post('biz/notice/save', 'biz.Notice/save');
    Route::post('biz/notice/delete', 'biz.Notice/delete');
    Route::post('biz/notice/status', 'biz.Notice/status');
    Route::post('biz/notice/publish', 'biz.Notice/publish');
    Route::post('biz/notice/top', 'biz.Notice/top');
    Route::get('biz/notice/export', 'biz.Notice/export');
    Route::get('biz/notice/template', 'biz.Notice/template');
    Route::post('biz/notice/import', 'biz.Notice/import');

    // ---------- 代码生成（系统工具） ----------
    Route::get('tool/gen/tables', 'tool.Gen/tables');
    Route::get('tool/gen/columns', 'tool.Gen/columns');
    Route::get('tool/gen/preview', 'tool.Gen/preview');
    Route::post('tool/gen/generate', 'tool.Gen/generate');
    Route::post('tool/gen/zip', 'tool.Gen/zip');

    // ---------- 插件管理 ----------
    Route::get('system/plugin/list', 'system.Plugin/index');
    Route::post('system/plugin/install', 'system.Plugin/install');
    Route::post('system/plugin/uninstall', 'system.Plugin/uninstall');
    Route::post('system/plugin/enable', 'system.Plugin/enable');
    Route::post('system/plugin/disable', 'system.Plugin/disable');
    Route::get('system/plugin/config', 'system.Plugin/config');
    Route::post('system/plugin/saveConfig', 'system.Plugin/saveConfig');
    Route::get('system/plugin/hooks', 'system.Plugin/hooks');

    // ---------- 远程升级 ----------
    Route::get('system/upgrade/info', 'system.Upgrade/info');
    Route::post('system/upgrade/check', 'system.Upgrade/check');
    Route::get('system/upgrade/history', 'system.Upgrade/history');
    Route::post('system/upgrade/apply', 'system.Upgrade/apply');
    Route::post('system/upgrade/rollback', 'system.Upgrade/rollback');
    Route::post('system/upgrade/clearCache', 'system.Upgrade/clearCache');

    // ---------- 已启用插件的自定义路由 /admin/plugin/<name>/<rule> ----------
    // 注意：必须在 Route::miss 之前注册，且写在登录组内以继承 Token + OperLog
    \app\common\plugin\PluginManager::getInstance()->registerRoutes();
})->middleware([Token::class, OperLog::class]);

// 未匹配路由：走控制器默认渲染（会返回 404 JSON）
Route::miss(function () {
    return json(['code' => 404, 'msg' => '接口不存在', 'data' => (object) []]);
});


    


    
