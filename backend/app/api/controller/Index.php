<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\controller\AbstractController;
use think\Response;

/**
 * API 应用默认控制器（示例）
 * api 应用默认不挂 Token 中间件，可按需自行接入
 */
class Index extends AbstractController
{
    public function index(): Response
    {
        return $this->success([
            'name' => '建站啦后台管理系统 API',
            'time' => date('Y-m-d H:i:s'),
        ]);
    }
}
