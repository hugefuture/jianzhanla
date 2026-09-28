<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\controller\AbstractController;
use app\common\exception\ApiException;
use think\Response;

/**
 * 通用 CRUD 控制器
 * 子类只需设置 $service 即可获得标准增删改查能力
 */
abstract class CrudController extends AbstractController
{
    protected \app\common\service\AbstractService $service;

    /** 分页列表 GET /list */
    public function index(): Response
    {
        return $this->success($this->service->page($this->request->param()));
    }

    /** 详情 GET /read/:id */
    public function read(int $id): Response
    {
        return $this->success($this->service->read($id));
    }

    /** 新增/更新 POST /save（body 带 id 则更新） */
    public function save(): Response
    {
        $data = $this->input();
        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);
        return $this->success($this->service->save($data, $id), $id > 0 ? '更新成功' : '新增成功');
    }

    /** 删除 POST /delete（body: {ids: []}） */
    public function delete(): Response
    {
        $ids = $this->request->param('ids');
        if (is_numeric($ids)) {
            $ids = [(int) $ids];
        }
        if (!is_array($ids)) {
            throw ApiException::badRequest('参数 ids 非法');
        }
        $this->service->remove(array_map('intval', $ids));
        return $this->success(null, '删除成功');
    }

    /** 修改状态 POST /status（body: {id, status}） */
    public function status(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->param('status');
        $this->service->changeStatus($id, $status);
        return $this->success(null, '操作成功');
    }
}
