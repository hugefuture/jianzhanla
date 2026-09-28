<?php
declare(strict_types=1);

namespace app\common\dao;

use app\common\exception\ApiException;
use app\common\traits\Instance;
use think\db\BaseQuery;
use think\db\Query;

/**
 * Dao 基类：封装数据访问
 * 子类约定：
 *  - setModel()  返回 Model 类名
 *  - $search     通用查询映射：字段 => 匹配类型（'=' / 'like' / 'in' / 'between' / '>=' / '<=' / 'time'）
 *  - $order      默认排序
 */
abstract class BaseDao
{
    use Instance;

    /** 通用搜索映射 */
    protected array $search = [];

    /** 默认排序 */
    protected string $order = 'id desc';

    /** 是否启用数据权限过滤 */
    protected bool $dataScope = false;

    /** 数据权限：部门字段名（表无部门维度请置空） */
    protected string $scopeDeptField = 'dept_id';

    /** 数据权限：创建人/归属人字段名 */
    protected string $scopeUserField = 'create_by';

    /** 返回实体 Model 类名 */
    abstract protected function setModel(): string;

    /** 获取 Model 实例（每请求复用） */
    protected function model(): \think\Model
    {
        $class = $this->setModel();
        return new $class();
    }

    /** 查询构造器 */
    public function search(array $params = []): Query
    {
        $query = $this->model()->newQuery();
        foreach ($this->search as $field => $op) {
            if (!array_key_exists($field, $params)) {
                continue;
            }
            $value = $params[$field];
            if ($value === '' || $value === null) {
                continue;
            }
            // 支持 ['in', [...]] / ['>=', v] 这类动态操作符格式
            if (is_array($value) && count($value) === 2 && is_string($value[0]) && in_array(strtolower($value[0]), ['=', '<>', '>', '<', '>=', '<=', 'in', 'like'], true)) {
                [$op2, $val2] = $value;
                $query = $this->applyWhere($query, $field, $op2, $val2);
                continue;
            }
            $query = $this->applyWhere($query, $field, $op, $value);
        }
        return $this->applyDataScope($query);
    }

    /** 附加数据权限过滤（子类开启 $dataScope 后生效） */
    protected function applyDataScope(Query $query): Query
    {
        if (!$this->dataScope) {
            return $query;
        }
        $user = request()->user ?? null;
        if (!is_array($user) || empty($user['id'])) {
            return $query;
        }
        $scope = \app\admin\service\DataScopeService::getInstance()->resolve($user);
        return \app\admin\service\DataScopeService::getInstance()
            ->apply($query, $scope, $this->scopeDeptField, $this->scopeUserField);
    }

    /** 应用单个查询条件 */
    protected function applyWhere(Query $query, string $field, string $op, mixed $value): Query
    {
        switch (strtolower($op)) {
            case 'like':
                return $query->whereLike($field, "%{$value}%");
            case 'in':
                return $query->whereIn($field, (array) $value);
            case 'between':
                // value: [min, max]
                if (is_array($value) && (isset($value[0]) || isset($value[1]))) {
                    if (isset($value[0]) && $value[0] !== '') {
                        $query = $query->where($field, '>=', $value[0]);
                    }
                    if (isset($value[1]) && $value[1] !== '') {
                        $query = $query->where($field, '<=', $value[1]);
                    }
                }
                return $query;
            case 'time':
                // 日期区间：[startDate, endDate] -> 字段 datetime 范围
                return $query->whereBetweenTime($field, (string) $value[0], (string) $value[1]);
            default:
                return $query->where($field, $op, $value);
        }
    }

    /** 分页查询：返回 ['list' => [], 'total' => int] */
    public function getPage(array $params, int $page, int $limit): array
    {
        $query = $this->search($params);
        $total = (clone $query)->count();
        $list = $query->order($this->order)
            ->page($page, $limit)
            ->select()
            ->toArray();
        return ['list' => $list, 'total' => $total];
    }

    /** 全量列表（数据量大时用 cursor()） */
    public function getList(array $params = []): array
    {
        return $this->search($params)->order($this->order)->select()->toArray();
    }

    /**
     * 游标迭代：逐条产出数组，不一次性载入内存（大数据导出专用）
     * @return \Generator<int, array>
     */
    public function cursor(array $params = []): \Generator
    {
        foreach ($this->search($params)->order($this->order)->cursor() as $row) {
            yield $row->toArray();
        }
    }

    /** 按主键查询 */
    public function findByPk(int $id): ?array
    {
        $row = $this->model()->find($id);
        return $row ? $row->toArray() : null;
    }

    /** 按字段查询一条 */
    public function findOne(array $where): ?array
    {
        $row = $this->model()->where($where)->find();
        return $row ? $row->toArray() : null;
    }

    /** 新增 */
    public function create(array $data): array
    {
        $row = $this->model()->create($data);
        return $row->toArray();
    }

    /** 按主键更新 */
    public function updateByPk(int $id, array $data): bool
    {
        return (bool) $this->model()->where($this->model()->getPk(), $id)->update($data);
    }

    /** 批量删除 */
    public function delete(array $ids): int
    {
        $pk = $this->model()->getPk();
        return $this->model()->whereIn($pk, $ids)->delete();
    }

    /** 唯一性检查（true = 可用） */
    public function isUnique(string $field, mixed $value, int $excludeId = 0): bool
    {
        $query = $this->model()->where($field, $value);
        if ($excludeId > 0) {
            $query = $query->where($this->model()->getPk(), '<>', $excludeId);
        }
        return !$query->find();
    }

    /** 字段计数 */
    public function count(array $where = []): int
    {
        return $this->model()->where($where)->count();
    }
}
