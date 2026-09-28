<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;

/**
 * 服务层基类：封装通用 CRUD
 * 子类约定：
 *  - $dao           对应 Dao 实例
 *  - $allowFields   允许写入/更新的字段白名单
 *  - $uniques       唯一性校验字段 => [字段, 名称]
 *  - $required      新增时必填字段
 */
abstract class AbstractService
{
    use Instance;

    protected \app\common\dao\BaseDao $dao;

    /** 字段白名单（null 表示不过滤，由控制器/Dao 控制） */
    protected array $allowFields = [];

    /** 唯一性字段：'username' => '账号' */
    protected array $uniques = [];

    /** 新增必填字段 */
    protected array $required = [];

    /**
     * 附加字段：不属于主表的字段（如 role_ids / dept_ids / post_ids）
     * 它们不参与写入，但会原样传给 before/after 钩子供关联表同步使用
     */
    protected array $extraFields = [];

    /**
     * 分页查询（通用）
     * 过滤规则由 Dao 的 $search 属性定义
     */
    public function page(array $params = []): array
    {
        [$page, $limit] = [max(1, (int) ($params['page'] ?? 1)), (int) ($params['limit'] ?? 10)];
        unset($params['page'], $params['limit']);
        return $this->dao->getPage($params, $page, $limit);
    }

    /** 全量列表 */
    public function list(array $params = []): array
    {
        unset($params['page'], $params['limit']);
        return $this->dao->getList($params);
    }

    /**
     * 游标迭代：逐条产出，不把全量载入内存（大数据导出专用）
     * @return \Generator<int, array>
     */
    public function cursor(array $params = []): \Generator
    {
        unset($params['page'], $params['limit']);
        yield from $this->dao->cursor($params);
    }

    /** 详情 */
    public function read(int $id): array
    {
        $row = $this->dao->findByPk($id);
        if (!$row) {
            throw ApiException::notFound();
        }
        return $row;
    }

    /**
     * 新增 / 更新（id > 0 时更新）
     */
    public function save(array $data, int $id = 0): mixed
    {
        // 剥离附加字段，避免被写进主表
        $extras = [];
        foreach ($this->extraFields as $field) {
            if (array_key_exists($field, $data)) {
                $extras[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        // 字段白名单过滤
        if ($this->allowFields) {
            $data = array_intersect_key($data, array_flip($this->allowFields));
        }

        if ($id > 0) {
            $this->checkUnique($data, $id);
            $this->beforeUpdate($data, $id);
            if ($data) {
                $this->dao->updateByPk($id, $data);
            }
            $this->afterUpdate($data + $extras, $id);
            return $id;
        }

        foreach ($this->required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw ApiException::badRequest("参数 {$field} 不能为空");
            }
        }
        $this->checkUnique($data, 0);
        $this->beforeCreate($data);
        $row = $this->dao->create($data);
        $this->afterCreate($data + $extras, $row);
        return $row;
    }

    /** 删除（支持批量） */
    public function remove(array $ids): void
    {
        if (empty($ids)) {
            throw ApiException::badRequest('请选择要删除的数据');
        }
        $this->beforeRemove($ids);
        $this->dao->delete($ids);
        $this->afterRemove($ids);
    }

    /** 修改状态字段（status=1 正常 / 0 停用） */
    public function changeStatus(int $id, int $status): void
    {
        if (!in_array($status, [0, 1])) {
            throw ApiException::badRequest('状态值非法');
        }
        $this->dao->updateByPk($id, ['status' => $status]);
    }

    /** 唯一性校验 */
    protected function checkUnique(array $data, int $excludeId): void
    {
        foreach ($this->uniques as $field => $name) {
            if (!isset($data[$field]) || $data[$field] === '') {
                continue;
            }
            if ($this->dao->isUnique($field, $data[$field], $excludeId)) {
                continue;
            }
            throw ApiException::badRequest("{$name}已存在");
        }
    }

    // ---- 钩子，子类按需覆写 ----
    protected function beforeCreate(array &$data): void {}
    protected function afterCreate(array $data, $row): void {}
    protected function beforeUpdate(array &$data, int $id): void {}
    protected function afterUpdate(array $data, int $id): void {}
    protected function beforeRemove(array $ids): void {}
    protected function afterRemove(array $ids): void {}
}
