<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysConfig;
use app\admin\dao\SysConfigDao;
use app\common\exception\ApiException;
use think\facade\Cache;

/**
 * 参数配置服务（带缓存）
 */
class SysConfigService extends AbstractService
{
    protected array $allowFields = ['name', 'key', 'value', 'type', 'status', 'remark'];
    protected array $uniques = ['key' => '参数键名'];
    protected array $required = ['name', 'key'];

    public function __construct()
    {
        $this->dao = SysConfigDao::getInstance();
    }

    /** 按 key 取配置值 */
    public function getValue(string $key, string $default = ''): string
    {
        $cacheKey = 'config:' . $key;
        $value = Cache::get($cacheKey);
        if ($value === null || $value === false) {
            $value = (string) (SysConfig::where('key', $key)->value('value') ?? '');
            Cache::set($cacheKey, $value, 3600);
        }
        return $value !== '' ? $value : $default;
    }

    /** 全量配置（key => value） */
    public function allValues(): array
    {
        return SysConfig::where('status', 1)->column('value', 'key');
    }

    protected function afterCreate(array $data, $row): void
    {
        Cache::delete('config:' . $data['key']);
    }

    protected function afterUpdate(array $data, int $id): void
    {
        if (isset($data['key'])) {
            Cache::delete('config:' . $data['key']);
        }
    }

    protected function beforeRemove(array $ids): void
    {
        $keys = SysConfig::whereIn('id', $ids)->column('key');
        foreach ($keys as $key) {
            Cache::delete('config:' . $key);
        }
    }
}
