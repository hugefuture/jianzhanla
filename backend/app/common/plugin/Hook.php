<?php
declare(strict_types=1);

namespace app\common\plugin;

use app\common\traits\Instance;
use think\facade\Log;

/**
 * 钩子系统：插件用来扩展既有业务，业务侧用 Hook::trigger() 埋点
 *
 * 约定：
 *  - 事件名用小写点分，如 user.login.success
 *  - 监听器按 priority 降序执行，前一个的返回值作为后一个的入参（可链式改写 payload）
 *  - 单个监听器抛异常只记录日志，不中断主流程
 */
class Hook
{
    use Instance;

    /** event => [[priority, seq, callable], ...] */
    protected array $listeners = [];

    protected int $seq = 0;

    /** 注册监听 */
    public function listen(string $event, callable $callback, int $priority = 0): void
    {
        $this->listeners[$event][] = [$priority, $this->seq++, $callback];
        // 保持稳定排序：priority 降序，同优先级按注册顺序
        usort($this->listeners[$event], fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
    }

    /** 是否已注册 */
    public function has(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /** 已注册事件名 */
    public function events(): array
    {
        $events = array_keys($this->listeners);
        sort($events);
        return $events;
    }

    /** 某事件的监听器数量（调试/自检用） */
    public function count(string $event): int
    {
        return count($this->listeners[$event] ?? []);
    }

    /**
     * 触发事件，返回最终 payload
     */
    public function trigger(string $event, mixed $payload = null): mixed
    {
        foreach ($this->listeners[$event] ?? [] as [, , $callback]) {
            try {
                $result = $callback($payload);
                // 监听器返回 null 表示不改写 payload
                if ($result !== null) {
                    $payload = $result;
                }
            } catch (\Throwable $e) {
                Log::error('[hook] ' . $event . ' 监听器执行失败：' . $e->getMessage());
            }
        }
        return $payload;
    }

    /** 清空（测试用） */
    public function clear(): void
    {
        $this->listeners = [];
    }
}
