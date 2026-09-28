<?php
declare(strict_types=1);

namespace app\common\traits;

/**
 * 单例 trait：Service / Dao / Model 共用
 */
trait Instance
{
    protected static ?array $instances = null;

    /** 按类名缓存实例（支持多态静态调用） */
    public static function getInstance(): static
    {
        $class = static::class;
        if (static::$instances === null) {
            static::$instances = [];
        }
        if (!isset(static::$instances[$class])) {
            static::$instances[$class] = new static();
        }
        return static::$instances[$class];
    }
}
