<?php
declare(strict_types=1);

namespace app;

use app\common\exception\ApiException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use think\facade\Log;
use Throwable;

/**
 * 全局异常处理：统一输出 JSON 结构 {code, msg, data}
 */
class ExceptionHandle extends Handle
{
    public function render(Request $request, Throwable $e, Response $response = null): Response
    {
        // 业务异常：直接透出 message
        if ($e instanceof ApiException) {
            return json(['code' => $e->getCode() ?: 1, 'msg' => $e->getMessage(), 'data' => $e->getData() ?: (object) []]);
        }

        // 参数校验异常
        if ($e instanceof ValidateException) {
            return json(['code' => 422, 'msg' => $e->getError(), 'data' => (object) []]);
        }

        // 404
        if ($e instanceof HttpException && $e->getStatusCode() == 404) {
            return json(['code' => 404, 'msg' => '接口不存在', 'data' => (object) []]);
        }

        // 数据不存在
        if ($e instanceof DataNotFoundException || $e instanceof ModelNotFoundException) {
            return json(['code' => 404, 'msg' => '数据不存在', 'data' => (object) []]);
        }

        // 其他异常：记录日志
        Log::error('[Exception] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        $data = [
            'code' => 500,
            'msg' => $this->app->isDebug() ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : '系统繁忙，请稍后再试',
            'data' => (object) [],
        ];

        return json($data);
    }
}
