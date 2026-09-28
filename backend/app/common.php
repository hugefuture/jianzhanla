<?php
// 应用公共函数文件
use think\Response;

if (!function_exists('json_ok')) {
    /** 成功响应 */
    function json_ok(array $data = [], string $msg = 'ok', int $code = 0): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }
}

if (!function_exists('json_fail')) {
    /** 失败响应 */
    function json_fail(string $msg = 'fail', int $code = 1, array $data = []): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }
}

if (!function_exists('mask_password')) {
    /** 移除数组中的敏感字段 */
    function mask_password(array $data): array
    {
        unset($data['password'], $data['pwd'], $data['oldPassword'], $data['newPassword'], $data['confirmPassword']);
        return $data;
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        return request()->ip();
    }
}
