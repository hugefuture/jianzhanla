<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysConfigService;
use think\Response;

/**
 * 参数配置
 */
class Config extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysConfigService::getInstance();
    }

    /** 按 key 取值 GET system/config/getKey/:key */
    public function getKey(string $key): Response
    {
        return $this->success(['value' => SysConfigService::getInstance()->getValue($key)]);
    }

    /** 全量配置 GET system/config/allValues */
    public function allValues(): Response
    {
        return $this->success(SysConfigService::getInstance()->allValues());
    }
}
