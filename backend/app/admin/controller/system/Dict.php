<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysDictService;
use think\Response;

/**
 * 字典管理：类型走通用 CRUD，数据走独立方法
 */
class Dict extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysDictService::getInstance();
    }

    /** 字典数据分页 GET system/dict/dataList */
    public function dataList(): Response
    {
        return $this->success(SysDictService::getInstance()->dataPage($this->request->param()));
    }

    /** 按类型取启用字典 GET system/dict/dataByType/:type */
    public function dataByType(string $type): Response
    {
        return $this->success(SysDictService::getInstance()->dataByType($type));
    }

    /** 保存字典数据 POST system/dict/saveData */
    public function saveData(): Response
    {
        SysDictService::getInstance()->saveData($this->request->param());
        return $this->success(null, '保存成功');
    }

    /** 删除字典数据 POST system/dict/deleteData */
    public function deleteData(): Response
    {
        $ids = (array) $this->request->param('ids/a', []);
        SysDictService::getInstance()->removeData(array_map('intval', $ids));
        return $this->success(null, '删除成功');
    }
}
