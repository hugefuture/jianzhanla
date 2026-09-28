<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysPost;
use app\admin\dao\SysPostDao;
use app\common\exception\ApiException;

/**
 * 岗位服务
 */
class SysPostService extends AbstractService
{
    protected array $allowFields = ['name', 'code', 'order_num', 'status', 'remark'];

    protected array $uniques = ['code' => '岗位编码'];
    protected array $required = ['name', 'code'];

    public function __construct()
    {
        $this->dao = SysPostDao::getInstance();
    }

    protected function beforeRemove(array $ids): void
    {
        if (\app\admin\model\SysUserPost::whereIn('post_id', $ids)->count() > 0) {
            throw ApiException::badRequest('岗位已分配给用户，请先解除分配');
        }
        \app\admin\model\SysUserPost::whereIn('post_id', $ids)->delete();
    }
}
