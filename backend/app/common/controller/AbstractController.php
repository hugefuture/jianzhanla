<?php
declare(strict_types=1);

namespace app\common\controller;

use app\common\exception\ApiException;
use think\App;
use think\Request;
use think\Response;

/**
 * 控制器基类：统一入参解析与响应结构 {code, msg, data}
 */
abstract class AbstractController
{
    protected App $app;
    protected Request $request;

    /** 当前登录用户（由 TokenMiddleware 注入） */
    protected $user = null;

    /** 当前登录用户权限集（由 TokenMiddleware 注入） */
    protected array $perms = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
    }

    /** 成功响应 */
    protected function success(mixed $data = null, string $msg = 'ok'): Response
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data ?? (object) []]);
    }

    /** 失败响应 */
    protected function fail(string $msg = '操作失败', int $code = 1): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => (object) []]);
    }

    /** 获取分页参数 */
    protected function getPage(): array
    {
        $page = max(1, (int) $this->request->param('page/d', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit/d', 10)));
        return [$page, $limit];
    }

    /** 获取 JSON 或 form 请求体 */
    protected function input(array $default = []): array
    {
        $body = $this->request->param();
        foreach ($default as $key => $value) {
            $body[$key] = $body[$key] ?? $value;
        }
        return $body;
    }

    /** 校验必填 */
    protected function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw ApiException::badRequest("参数 {$field} 不能为空");
            }
        }
    }
}
