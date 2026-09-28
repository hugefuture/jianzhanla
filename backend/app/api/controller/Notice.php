<?php
declare(strict_types=1);

namespace app\api\controller;

use app\admin\service\SysNoticeService;
use app\api\service\ApiSignService;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 公开公告接口（只读）
 *
 * 与后台 JWT 完全分离：默认不要求登录，可通过 config/api.php 的 sign_secret 打开轻量签名校验。
 * 只能读到 status=1（已发布）的公告，草稿不会外泄。
 */
class Notice extends AbstractController
{
    /** 公告列表 GET api/notice/list */
    public function list(): Response
    {
        ApiSignService::getInstance()->verify($this->request->param());
        $params = $this->request->only(['page', 'limit', 'type', 'title'], 'get');
        $result = SysNoticeService::getInstance()->publicPage($params);
        return $this->success($result);
    }

    /** 公告详情 GET api/notice/read?id= */
    public function read(): Response
    {
        ApiSignService::getInstance()->verify($this->request->param());
        $id = (int) $this->request->param('id');
        if ($id <= 0) {
            return $this->fail('缺少 id 参数');
        }
        return $this->success(SysNoticeService::getInstance()->publicRead($id));
    }
}
