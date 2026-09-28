<?php
declare(strict_types=1);

namespace plugin\login_notify;

use app\common\plugin\AbstractPlugin;

/**
 * 登录动态插件主类
 *
 * 纯钩子型插件：不改动任何既有业务代码，只是在系统埋点处挂一个监听器。
 * 数据落在插件私有目录 runtime/plugin/login-notify/events.json。
 */
class Plugin extends AbstractPlugin
{
    public function register(): void
    {
        $this->hook()->listen('user.login.success', function ($payload) {
            $this->record('success', is_array($payload) ? $payload : []);
            return null;
        });

        $this->hook()->listen('user.login.fail', function ($payload) {
            if ((int) $this->config('alert_on_fail', 1) !== 1) {
                return null;
            }
            $this->record('fail', is_array($payload) ? $payload : []);
            return null;
        });
    }

    /** 记录一条登录事件 */
    public function record(string $type, array $payload): void
    {
        $items = $this->events();
        array_unshift($items, [
            'type' => $type,
            'username' => (string) ($payload['username'] ?? ''),
            'ip' => (string) ($payload['ip'] ?? ''),
            'reason' => (string) ($payload['reason'] ?? ''),
            'time' => (string) ($payload['time'] ?? date('Y-m-d H:i:s')),
        ]);
        $keep = max(10, min(500, (int) $this->config('keep_count', 100)));
        $items = array_slice($items, 0, $keep);
        @file_put_contents(
            $this->dataPath('events.json'),
            json_encode(['items' => $items], JSON_UNESCAPED_UNICODE)
        );
    }

    /** 全部事件（新 → 旧） */
    public function events(): array
    {
        $file = $this->dataPath('events.json');
        if (!is_file($file)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
    }

    /** 统计 */
    public function stats(): array
    {
        $items = $this->events();
        $success = 0;
        $fail = 0;
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'fail') {
                $fail++;
            } else {
                $success++;
            }
        }
        return [
            'total' => count($items),
            'success' => $success,
            'fail' => $fail,
            'last_time' => $items[0]['time'] ?? '',
            'keep_count' => (int) $this->config('keep_count', 100),
        ];
    }

    public function clear(): void
    {
        @file_put_contents($this->dataPath('events.json'), json_encode(['items' => []], JSON_UNESCAPED_UNICODE));
    }
}
