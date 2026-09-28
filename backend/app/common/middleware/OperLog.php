<?php
declare(strict_types=1);

namespace app\common\middleware;

use app\admin\model\SysOperLog;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 操作日志中间件：记录写操作（POST/PUT/DELETE）
 */
class OperLog
{
    public function handle($request, \Closure $next)
    {
        $start = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $cost = round((microtime(true) - $start) * 1000, 2);

        try {
            $method = strtoupper($request->method());
            if (in_array($method, ['GET', 'OPTIONS'])) {
                return $response;
            }

            $body = [];
            if (is_array($response->getData())) {
                $body = $response->getData();
            }
            $status = (isset($body['code']) && $body['code'] === 0) ? 1 : 0;
            $errorMsg = $status === 0 ? (string) ($body['msg'] ?? '') : '';

            SysOperLog::create([
                'user_id' => $request->user['id'] ?? 0,
                'username' => $request->user['username'] ?? '',
                'module' => $request->app ?? '',
                'action' => ($request->controller() ?? '') . '@' . ($request->action() ?? ''),
                'method' => $method,
                'url' => mb_substr((string) $request->url(), 0, 255),
                'params' => mb_substr(json_encode(mask_password($request->param()), JSON_UNESCAPED_UNICODE), 0, 2000),
                'ip' => $request->ip(),
                'status' => $status,
                'error_msg' => mb_substr($errorMsg, 0, 500),
                'cost_time' => $cost,
            ]);
        } catch (\Throwable $e) {
            // 日志记录失败不影响主流程
        }

        return $response;
    }
}
