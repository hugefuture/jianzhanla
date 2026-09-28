<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\UploadService;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 上传
 */
class Upload extends AbstractController
{
    public function image(): Response
    {
        $result = UploadService::getInstance()->image($this->request->file('file'));
        return $this->success($result, '上传成功');
    }

    public function file(): Response
    {
        $result = UploadService::getInstance()->file($this->request->file('file'));
        return $this->success($result, '上传成功');
    }
}
