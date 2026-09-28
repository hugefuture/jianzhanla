<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysDictData;
use app\admin\model\SysDictType;
use app\admin\dao\SysDictTypeDao;
use app\common\exception\ApiException;
use think\facade\Cache;

/**
 * 字典服务（类型 + 数据，带缓存）
 */
class SysDictService extends AbstractService
{
    protected array $allowFields = ['name', 'type', 'status', 'remark'];
    protected array $uniques = ['type' => '字典类型'];
    protected array $required = ['name', 'type'];

    public function __construct()
    {
        $this->dao = SysDictTypeDao::getInstance();
    }

    // ---------- 字典数据 ----------

    /** 按类型取字典数据（带缓存） */
    public function dataByType(string $type): array
    {
        $cacheKey = 'dict:' . $type;
        $data = Cache::get($cacheKey);
        if ($data === null || $data === false) {
            $data = SysDictData::where('dict_type', $type)
                ->where('status', 1)
                ->field('label, value, dict_sort as sort, remark')
                ->order('dict_sort asc, id asc')
                ->select()
                ->toArray();
            Cache::set($cacheKey, $data, 3600);
        }
        return $data;
    }

    /** 分页取字典数据（管理用，含停用） */
    public function dataPage(array $params): array
    {
        [$page, $limit] = [max(1, (int) ($params['page'] ?? 1)), (int) ($params['limit'] ?? 10)];
        unset($params['page'], $params['limit']);
        return SysDictDataDao::getInstance()->getPage($params, $page, $limit);
    }

    /** 保存字典数据 */
    public function saveData(array $data): mixed
    {
        $allow = ['dict_type', 'label', 'value', 'dict_sort', 'status', 'remark'];
        $data = array_intersect_key($data, array_flip($allow));
        if (empty($data['dict_type']) || empty($data['label']) || !isset($data['value'])) {
            throw ApiException::badRequest('参数不完整');
        }
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            unset($data['id']);
            SysDictData::where('id', $id)->update($data);
        } else {
            $row = (new SysDictData())->create($data);
            $id = (int) $row->id;
        }
        $this->clearCache($data['dict_type']);
        return $id;
    }

    /** 删除字典数据 */
    public function removeData(array $ids): void
    {
        $types = SysDictData::whereIn('id', $ids)->column('dict_type');
        SysDictData::whereIn('id', $ids)->delete();
        foreach ($types as $type) {
            $this->clearCache($type);
        }
    }

    protected function clearCache(string $type): void
    {
        Cache::delete('dict:' . $type);
    }

    // ---------- 类型删除 ----------

    protected function beforeRemove(array $ids): void
    {
        $types = SysDictType::whereIn('id', $ids)->column('type');
        if ($types) {
            if (SysDictData::whereIn('dict_type', $types)->count() > 0) {
                throw ApiException::badRequest('字典类型下存在数据，不允许删除');
            }
            foreach ($types as $type) {
                $this->clearCache($type);
            }
        }
    }
}
