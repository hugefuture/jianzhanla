<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysPostService;

/**
 * 岗位管理
 */
class Post extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysPostService::getInstance();
    }
}
