<?php
declare(strict_types=1);

namespace app\common\middleware;

use think\Response;

/**
 * 跨域中间件
 */
class Cors
{
    public function handle($request, \Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        $response->header([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Token, X-Requested-With',
            'Access-Control-Max-Age' => '1800',
        ]);

        // OPTIONS 预检直接返回
        if (strtoupper($request->method()) === 'OPTIONS') {
            return response('', 200);
        }

        return $response;
    }
}
