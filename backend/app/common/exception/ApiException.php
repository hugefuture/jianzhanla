<?php
declare(strict_types=1);

namespace app\common\exception;

use RuntimeException;

/**
 * 业务异常：抛出后由全局异常处理器统一渲染为 JSON
 */
class ApiException extends RuntimeException
{
    protected array $data = [];

    public function __construct(string $message = '操作失败', int $code = 1, array $data = [])
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /** 未登录 */
    public static function unauthorized(string $msg = '请先登录'): static
    {
        return new static($msg, 401);
    }

    /** 无权限 */
    public static function forbidden(string $msg = '没有操作权限'): static
    {
        return new static($msg, 403);
    }

    /** 参数错误 */
    public static function badRequest(string $msg = '参数错误'): static
    {
        return new static($msg, 400);
    }

    /** 资源不存在 */
    public static function notFound(string $msg = '数据不存在'): static
    {
        return new static($msg, 404);
    }
}
