<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\dao\SysUpgradeLogDao;
use app\admin\model\SysUpgradeLog;
use app\common\exception\ApiException;
use app\common\service\AbstractService;
use think\facade\Cache;
use think\facade\Log;

/**
 * 远程升级服务
 *
 * 能力：
 *  - check()    拉取远端 manifest.json，算出可升级版本列表（通道过滤 + HMAC 验签 + 版本比较）
 *  - apply()    下载升级包 → sha256 校验 → 备份（文件 + 库）→ 落盘 → 执行 SQL → 回写版本 → 清缓存
 *  - rollback() 按备份清单还原被覆盖/新增的文件，可选连同数据库一起还原
 *
 * 安全边界：
 *  1. 包内文件必须落在 config/upgrade.php 的 allowed_roots 白名单内，且不在 deny_paths 黑名单内
 *  2. 路径一律做 .. / 绝对盘符 / 反斜杠 归一化，写盘前二次确认仍在根范围内（防 Zip Slip）
 *  3. SQL 脚本逐条拆分 + 危险语句黑名单；单文件有大小上限
 *  4. 全程独占锁，失败自动回滚到备份点；每次操作写 sdccc_sys_upgrade_log
 */
class UpgradeService extends AbstractService
{
    /** 清单缓存（秒） */
    protected const MANIFEST_TTL = 300;
    /** 锁过期兜底（秒） */
    protected const LOCK_TTL = 900;

    /** 项目根 admin-framework/ */
    private string $root;
    /** 后端根 backend/ */
    private string $backendRoot;
    /** 工作目录 runtime/upgrade */
    private string $workDir;

    public function __construct()
    {
        $this->dao = SysUpgradeLogDao::getInstance();
        // __DIR__ = backend/app/admin/service → 上溯 3 级 = backend/
        $this->backendRoot = rtrim(dirname(__DIR__, 3), '/\\');
        $this->root = dirname($this->backendRoot);
        $this->workDir = (string) (config('upgrade.work_dir', '') ?: app()->getRuntimePath() . 'upgrade');
    }

    // ---------------------------------------------------------------- 对外接口

    /** 当前发布通道：显式配置优先，否则跟随 config/version.php */
    public function channel(): string
    {
        $c = trim((string) config('upgrade.channel', ''));
        return $c !== '' ? $c : $this->versionInfo()['channel'];
    }

    /**
     * 当前版本概况
     */
    public function info(): array
    {
        $v = $this->versionInfo();
        return [
            'version'     => $v['version'],
            'build'       => $v['build'],
            'release'     => $v['release'],
            'channel'     => $this->channel(),
            'appid'       => (string) config('upgrade.appid', ''),
            'remote'      => (string) config('upgrade.remote', ''),
            'enabled'     => (bool) config('upgrade.enabled', true),
            'signed'      => trim((string) config('upgrade.secret', '')) !== '',
            'php_version' => PHP_VERSION,
            'time'        => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 检查更新（$force=true 跳过本地缓存直连远端）
     */
    public function check(bool $force = false): array
    {
        if (!(bool) config('upgrade.enabled', true)) {
            throw ApiException::badRequest('远程升级未开启');
        }
        $remote = rtrim((string) config('upgrade.remote', ''), '/');
        if ($remote === '') {
            throw ApiException::badRequest('未配置升级源地址（UPGRADE.REMOTE）');
        }
        $key = 'upgrade:manifest:' . md5($remote);
        $manifest = $force ? null : Cache::get($key);
        if (!is_array($manifest)) {
            $raw = $this->httpGet($remote . '/manifest.json');
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || !isset($manifest['packages']) || !is_array($manifest['packages'])) {
                throw ApiException::badRequest('升级源返回格式非法：缺少 packages');
            }
            Cache::set($key, $manifest, static::MANIFEST_TTL);
        }

        $appid = (string) config('upgrade.appid', '');
        $remoteAppid = (string) ($manifest['appid'] ?? '');
        if ($appid !== '' && $remoteAppid !== '' && $remoteAppid !== $appid) {
            throw ApiException::badRequest("升级源 appid 不匹配：远端 {$remoteAppid}");
        }

        $current = $this->versionInfo()['version'];
        $channel = $this->channel();
        $secret = trim((string) config('upgrade.secret', ''));

        $packages = [];
        foreach ($manifest['packages'] as $pkg) {
            if (!is_array($pkg) || !isset($pkg['version'], $pkg['url'])) {
                continue;
            }
            $pkgChannel = (string) ($pkg['channel'] ?? 'stable');
            if ($pkgChannel !== '' && $pkgChannel !== $channel) {
                continue;
            }
            // 已装/更旧的包跳过
            if (static::compareVersion((string) $pkg['version'], $current) <= 0) {
                continue;
            }
            // requires：如 ">=1.0.0"，不满足则跳过（不给出该版本）
            $requires = trim((string) ($pkg['requires'] ?? ''));
            if ($requires !== '' && !$this->matchRequires($requires, $current)) {
                continue;
            }
            $signature = (string) ($pkg['signature'] ?? '');
            $pkg['signature_ok'] = $secret === ''
                ? null                                        // 未启用签名
                : $this->verifySignature($appid, (string) $pkg['version'], (string) ($pkg['sha256'] ?? ''), $signature, $secret);
            $packages[] = $pkg;
        }
        usort($packages, fn ($a, $b) => static::compareVersion((string) $a['version'], (string) $b['version']));

        $latest = $packages === [] ? '' : (string) end($packages)['version'];

        return [
            'current'    => $current,
            'latest'     => $latest,
            'upgradable' => $latest !== '',
            'channel'    => $channel,
            'packages'   => array_values($packages),
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 执行升级
     *
     * @param string $version  目标版本（必须为 check() 返回的包之一）
     * @param int    $userId   操作人
     * @param string $localZip 可选：本地已存在的升级包绝对路径（离线升级）
     */
    public function apply(string $version, int $userId = 0, string $localZip = ''): array
    {
        if (!(bool) config('upgrade.enabled', true)) {
            throw ApiException::badRequest('远程升级未开启');
        }
        // 升级可能持续数十秒到数分钟（下载 / 备份 / SQL），不受常规执行时限约束
        @set_time_limit(0);
        $start = microtime(true);
        $currentVersion = $this->versionInfo()['version'];
        $zip = '';
        $bak = '';
        $logId = 0;

        $this->acquireLock();
        try {
            $meta = [];
            if ($localZip !== '') {
                if (!is_file($localZip)) {
                    throw ApiException::badRequest('本地升级包不存在：' . $localZip);
                }
                $zip = $localZip;
            } else {
                $meta = $this->findPackage($version);
                if (!$meta) {
                    throw ApiException::badRequest("升级源中没有可用的 {$version} 包");
                }
                $zip = $this->download($meta);
            }

            // 1. 解压 + 解析 + 安全校验（先校验再动任何文件）
            $plan = $this->inspect($zip, $meta);
            if (static::compareVersion($plan['version'], $currentVersion) <= 0) {
                throw ApiException::badRequest("目标版本 {$plan['version']} 不高于当前版本 {$currentVersion}");
            }

            // 2. 备份（文件 + 数据库）
            $bak = $this->backupFiles($plan['files']);
            $bakDb = $this->backupDatabase($bak);

            // 3. 落盘：先写临时清单再落地，失败自动回滚
            try {
                $this->deployFiles($plan['files'], $plan['extract']);
                $sqlCount = $this->runSql($plan['sqls']);
                $this->writeVersionFile($plan['version'], $plan['build'], $plan['release']);
                $this->clearCache();
            } catch (\Throwable $e) {
                // 任何一步失败都回退到备份点
                $this->restoreFiles($bak . '/files/backup.json');
                throw $e;
            }

            $cost = (int) round((microtime(true) - $start) * 1000);
            $logId = $this->writeLog([
                'from_version' => $currentVersion,
                'to_version'   => $plan['version'],
                'channel'      => $this->channel(),
                'package'      => $plan['package'],
                'status'       => SysUpgradeLog::ST_OK,
                'file_count'   => count($plan['files']),
                'sql_count'    => $sqlCount,
                'backup_dir'   => $bak,
                'backup_db'    => $bakDb,
                'error_msg'    => '',
                'cost_ms'      => $cost,
                'create_by'    => $userId,
            ]);

            return [
                'log_id'    => $logId,
                'version'   => $plan['version'],
                'file_count'=> count($plan['files']),
                'sql_count' => $sqlCount,
                'backup'    => $bak,
                'cost_ms'   => $cost,
            ];
        } catch (ApiException $e) {
            $this->writeLog([
                'from_version' => $currentVersion,
                'to_version'   => $version,
                'channel'      => $this->channel(),
                'package'      => $zip,
                'status'       => SysUpgradeLog::ST_FAIL,
                'backup_dir'   => $bak,
                'error_msg'    => $e->getMessage(),
                'cost_ms'      => (int) round((microtime(true) - $start) * 1000),
                'create_by'    => $userId,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[upgrade] 升级失败：' . $e->getMessage());
            $this->writeLog([
                'from_version' => $currentVersion,
                'to_version'   => $version,
                'channel'      => $this->channel(),
                'package'      => $zip,
                'status'       => SysUpgradeLog::ST_FAIL,
                'backup_dir'   => $bak,
                'error_msg'    => $e->getMessage(),
                'cost_ms'      => (int) round((microtime(true) - $start) * 1000),
                'create_by'    => $userId,
            ]);
            throw ApiException::badRequest('升级失败：' . $e->getMessage());
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 回滚某次升级
     *
     * @param int  $id      升级日志 ID
     * @param bool $withDb  是否连同数据库一起还原（默认关闭，避免误灌库）
     */
    public function rollback(int $id, bool $withDb = false, int $userId = 0): array
    {
        $row = $this->dao->findByPk($id);
        if (!$row) {
            throw ApiException::notFound('升级记录不存在');
        }
        if ((int) $row['status'] === SysUpgradeLog::ST_ROLLBACK) {
            throw ApiException::badRequest('该记录已回滚过');
        }
        $bak = (string) $row['backup_dir'];
        if ($bak === '' || !is_dir($bak)) {
            throw ApiException::badRequest('备份目录不存在，无法回滚');
        }
        // 回滚同样不受常规执行时限约束
        @set_time_limit(0);

        $this->acquireLock();
        $start = microtime(true);
        try {
            $restored = $this->restoreFiles($bak . '/files/backup.json');
            if ($withDb && is_file($bak . '/db.sql')) {
                $this->importSqlFile($bak . '/db.sql');
            }
            $this->writeVersionFile(
                (string) $row['from_version'],
                (string) ($this->versionInfo()['build'] ?? ''),
                date('Y-m-d')
            );
            $this->clearCache();

            $this->dao->updateByPk($id, [
                'status'    => SysUpgradeLog::ST_ROLLBACK,
                'error_msg' => '',
                'cost_ms'   => (int) round((microtime(true) - $start) * 1000),
            ]);

            return [
                'log_id'   => $id,
                'restored' => $restored,
                'with_db'  => $withDb,
                'version'  => (string) $row['from_version'],
            ];
        } catch (\Throwable $e) {
            Log::error('[upgrade] 回滚失败：' . $e->getMessage());
            $this->dao->updateByPk($id, [
                'status'    => SysUpgradeLog::ST_ROLLBACK_FAIL,
                'error_msg' => mb_substr('回滚失败：' . $e->getMessage(), 0, 900),
            ]);
            throw ApiException::badRequest('回滚失败：' . $e->getMessage());
        } finally {
            $this->releaseLock();
        }
    }

    /** 升级历史（分页） */
    public function history(array $params = []): array
    {
        return $this->page($params);
    }

    /** 清理指定版本的下载缓存（保留备份） */
    public function clearCache(bool $includePackages = false): int
    {
        // 某些环境（含 Windows + 沙箱）删文件很慢，给一个时间预算，尽力而为
        $budget = microtime(true) + 10;
        $n = 0;
        $cacheDir = $this->backendRoot . '/runtime/cache';
        foreach (glob($cacheDir . '/*') ?: [] as $f) {
            if (microtime(true) > $budget) {
                break;
            }
            if (is_file($f)) {
                @unlink($f) && $n++;
            } elseif (is_dir($f)) {
                $n += $this->rmdirRecursive($f, $budget);
            }
        }
        if ($includePackages) {
            foreach (glob($this->workDir . '/dl/*') ?: [] as $f) {
                is_file($f) && @unlink($f) && $n++;
            }
        }
        return $n;
    }

    // ---------------------------------------------------------------- 版本与比较

    /** 读取 config/version.php */
    public function versionInfo(): array
    {
        $file = $this->backendRoot . '/config/version.php';
        $v = is_file($file) ? (array) require $file : [];
        return [
            'version' => (string) ($v['version'] ?? '0.0.0'),
            'build'   => (string) ($v['build'] ?? ''),
            'release' => (string) ($v['release'] ?? ''),
            'channel' => (string) ($v['channel'] ?? 'stable'),
        ];
    }

    /** 语义化版本比较：a>b 返回 1，相等 0，小于 -1 */
    public static function compareVersion(string $a, string $b): int
    {
        return version_compare(trim($a), trim($b));
    }

    /**
     * requires 表达式匹配（如 ">=1.0.0" 或 ">=1.0.0,<2.0.0"）
     */
    public function matchRequires(string $expr, string $version): bool
    {
        foreach (array_filter(array_map('trim', explode(',', $expr))) as $part) {
            if (!preg_match('/^(>=|<=|>|<|==|=)?\s*([0-9][0-9a-zA-Z.\-+]*)$/', $part, $m)) {
                return false;
            }
            $op = $m[1] ?: '>=';
            $cmp = static::compareVersion($version, $m[2]);
            $ok = match ($op) {
                '>'     => $cmp > 0,
                '>='    => $cmp >= 0,
                '<'     => $cmp < 0,
                '<='    => $cmp <= 0,
                default => $cmp === 0,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    // ---------------------------------------------------------------- 网络

    /** GET 下载文本 */
    private function httpGet(string $url): string
    {
        return $this->curl($url);
    }

    /** HTTP 请求：$saveTo 非空时下载到文件 */
    private function curl(string $url, string $saveTo = ''): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw ApiException::badRequest("不支持的协议：{$scheme}");
        }
        $saveTo = trim((string) $saveTo);
        $fp = null;
        if ($saveTo !== '') {
            @mkdir(dirname($saveTo), 0755, true);
            $fp = @fopen($saveTo, 'wb');
            if (!$fp) {
                throw ApiException::badRequest('无法创建本地文件：' . $saveTo);
            }
        }
        $ch = curl_init($url);
        $common = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => max(10, (int) config('upgrade.timeout', 20)),
            CURLOPT_USERAGENT      => 'jianzhanla-upgrader/1.0',
        ];
        if ($fp) {
            // 下载到文件：注意 CURLOPT_FILE 不可为 null，否则 libcurl 会回落到 STDOUT
            curl_setopt_array($ch, $common + [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FILE           => $fp,
            ]);
        } else {
            curl_setopt_array($ch, $common + [CURLOPT_RETURNTRANSFER => true]);
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($fp) {
            fclose($fp);
        }
        if ($body === false || $err !== '') {
            throw ApiException::badRequest('请求升级源失败：' . $err);
        }
        if ($code >= 400) {
            throw ApiException::badRequest("升级源返回 HTTP {$code}");
        }
        return is_string($body) ? $body : '';
    }

    /** 从清单中取得指定版本的包元信息 */
    private function findPackage(string $version): array
    {
        foreach ($this->check(true)['packages'] as $pkg) {
            if ((string) $pkg['version'] === $version) {
                return $pkg;
            }
        }
        return [];
    }

    /** 下载包并校验 sha256 / 体积 */
    private function download(array $meta): string
    {
        $url = (string) $meta['url'];
        $file = $this->workDir . '/dl/' . basename((string) parse_url($url, PHP_URL_PATH) ?: 'package.zip');
        @mkdir(dirname($file), 0755, true);
        $this->curl($url, $file);
        if (!is_file($file) || filesize($file) === 0) {
            throw ApiException::badRequest('升级包下载失败（空文件）');
        }
        $sha = (string) ($meta['sha256'] ?? '');
        if ($sha !== '' && !hash_equals(strtolower($sha), strtolower((string) hash_file('sha256', $file)))) {
            @unlink($file);
            throw ApiException::badRequest('升级包校验失败（sha256 不一致）');
        }
        $size = (int) ($meta['size'] ?? 0);
        if ($size > 0 && abs(filesize($file) - $size) > 2) {
            @unlink($file);
            throw ApiException::badRequest('升级包体积与清单不符');
        }
        return $file;
    }

    /** HMAC 验签：hmac_sha256(appid|version|sha256, secret) */
    private function verifySignature(string $appid, string $version, string $sha256, string $signature, string $secret): bool
    {
        if ($signature === '') {
            return false;
        }
        $expect = hash_hmac('sha256', "{$appid}|{$version}|{$sha256}", $secret);
        return hash_equals($expect, strtolower($signature));
    }

    // ---------------------------------------------------------------- 包解析与安全校验

    /**
     * 打开升级包，产出部署计划
     *
     * 包结构：
     *   upgrade.json          {"version":"1.1.0","build":"...","release":"...","description":"...","sql":["sql/a.sql"]}
     *   payload/**            相对项目根目录的文件（白名单控制）
     *   sql/*.sql             迁移脚本（默认按文件名字序执行）
     */
    private function inspect(string $zip, array $meta): array
    {
        $tmp = $this->workDir . '/tmp/' . md5($zip . microtime(true));
        $this->rmdirRecursive($tmp);
        @mkdir($tmp, 0755, true);

        $za = new \ZipArchive();
        if ($za->open($zip) !== true) {
            throw ApiException::badRequest('升级包无法打开（不是有效 zip）');
        }
        if (!$za->extractTo($tmp)) {
            $za->close();
            throw ApiException::badRequest('升级包解压失败');
        }
        $za->close();

        $manifestFile = $tmp . '/upgrade.json';
        if (!is_file($manifestFile)) {
            throw ApiException::badRequest('升级包缺少 upgrade.json');
        }
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($manifest) || empty($manifest['version'])) {
            throw ApiException::badRequest('upgrade.json 格式非法：缺少 version');
        }

        // payload 枚举
        $payloadDir = $tmp . '/payload';
        $files = [];
        if (is_dir($payloadDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($payloadDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($payloadDir) + 1));
                $target = $this->assertTargetAllowed($rel);
                $files[] = ['rel' => $target, 'src' => $f->getPathname()];
            }
        }
        usort($files, fn ($a, $b) => strcmp($a['rel'], $b['rel']));
        if ($files === [] && empty($manifest['sql'])) {
            throw ApiException::badRequest('升级包为空：既无文件也无 SQL');
        }

        // SQL 脚本
        $sqls = [];
        $declared = (array) ($manifest['sql'] ?? []);
        if ($declared !== []) {
            foreach ($declared as $rel) {
                $rel = str_replace('\\', '/', (string) $rel);
                $file = $tmp . '/' . ltrim($rel, '/');
                if (!str_starts_with($rel, 'sql/') || str_contains($rel, '..')) {
                    throw ApiException::badRequest("SQL 路径非法：{$rel}");
                }
                if (!is_file($file)) {
                    throw ApiException::badRequest("包内 SQL 不存在：{$rel}");
                }
                $sqls[] = $file;
            }
        }

        return [
            'version'  => (string) $manifest['version'],
            'build'    => (string) ($manifest['build'] ?? date('Ymd') . '.01'),
            'release'  => (string) ($manifest['release'] ?? date('Y-m-d')),
            'files'    => $files,
            'sqls'     => $sqls,
            'extract'  => $tmp,
            'package'  => (string) ($meta['url'] ?? $zip),
        ];
    }

    /**
     * 目标路径合法性校验（白名单 + 黑名单 + 防穿越），返回归一化后的相对路径
     */
    public function assertTargetAllowed(string $rel): string
    {
        if (trim($rel) === '') {
            throw ApiException::badRequest('包内含空路径条目');
        }
        $rel = str_replace('\\', '/', $rel);
        if (preg_match('/^[a-zA-Z]:/', $rel) || str_starts_with($rel, '/')) {
            throw ApiException::badRequest("绝对路径条目不允许：{$rel}");
        }
        $parts = [];
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                throw ApiException::badRequest("路径包含 ..，拒绝写入：{$rel}");
            }
            $parts[] = $seg;
        }
        $clean = implode('/', $parts);
        $lower = strtolower($clean);

        foreach ((array) config('upgrade.deny_paths', []) as $deny) {
            $deny = strtolower(str_replace('\\', '/', (string) $deny));
            if ($deny !== '' && ($lower === $deny || str_starts_with($lower, $deny . '/'))) {
                throw ApiException::badRequest("受保护路径不允许覆盖：{$clean}");
            }
        }
        $allowed = false;
        foreach ((array) config('upgrade.allowed_roots', []) as $root) {
            $root = strtolower(str_replace('\\', '/', (string) $root));
            if ($root !== '' && ($lower === $root || str_starts_with($lower, $root . '/'))) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw ApiException::badRequest("路径不在允许写入范围内：{$clean}");
        }
        // 写盘前再做一次真实路径确认（存在的文件用 realpath 兜住符号链接）
        $target = $this->root . '/' . $parts[0];
        $real = str_replace('\\', '/', (string) (is_dir($target) ? realpath($target) : $target));
        if (!str_starts_with($real, str_replace('\\', '/', $this->root) . '/')) {
            throw ApiException::badRequest("路径越界：{$clean}");
        }
        return $clean;
    }

    /** SQL 安全扫描：返回 null 表示通过，否则返回错误原因 */
    public function sqlSafetyError(string $sql): ?string
    {
        $lower = strtolower($sql);
        foreach ((array) config('upgrade.deny_sql', []) as $bad) {
            $bad = strtolower((string) $bad);
            if ($bad !== '' && str_contains($lower, $bad)) {
                return "SQL 含禁止语句：{$bad}";
            }
        }
        if (strlen($sql) > max(1, (int) config('upgrade.sql_max_kb', 512)) * 1024) {
            return 'SQL 文件超过大小上限';
        }
        return null;
    }

    // ---------------------------------------------------------------- 备份 / 落盘 / 回滚

    /** 备份即将被覆盖的文件，返回备份目录 */
    private function backupFiles(array $files): string
    {
        $bak = $this->workDir . '/backup/' . date('Ymd_His') . '_' . substr(md5((string) microtime(true)), 0, 6);
        $fileDir = $bak . '/files';
        @mkdir($fileDir, 0755, true);

        $entries = [];
        // 版本文件由我们自己改写，无论如何都要备份
        foreach (array_merge([['rel' => 'backend/config/version.php']], $files) as $item) {
            $rel = $item['rel'];
            $abs = $this->root . '/' . $rel;
            $key = str_replace(['/', '\\'], '__', $rel);
            if (is_file($abs)) {
                @mkdir(dirname($fileDir . '/' . $key), 0755, true);
                @copy($abs, $fileDir . '/' . $key);
                $entries[] = ['rel' => $rel, 'existed' => true, 'sha256' => (string) hash_file('sha256', $abs), 'bak' => $key];
            } else {
                $entries[] = ['rel' => $rel, 'existed' => false, 'sha256' => '', 'bak' => ''];
            }
        }
        // 去重（version.php 可能重复）
        $uniq = [];
        foreach ($entries as $e) {
            $uniq[$e['rel']] = $e;
        }
        file_put_contents($fileDir . '/backup.json', json_encode(array_values($uniq), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $bak;
    }

    /** 整库备份（依赖 mysqldump） */
    private function backupDatabase(string $bakDir): string
    {
        if (!(bool) config('upgrade.backup_db', true)) {
            return '';
        }
        $bin = $this->resolveMysqldump();
        if ($bin === '') {
            return '';
        }
        $cfg = $this->dbConfig();
        $file = $bakDir . '/db.sql';
        $cnf = sys_get_temp_dir() . '/jianzhanla_upgrade_' . getmypid() . '.cnf';
        file_put_contents($cnf, "[client]\nuser=\"{$cfg['username']}\"\npassword=\"{$cfg['password']}\"\n");
        @chmod($cnf, 0600);

        // 升级日志表不入备份：回滚时保留审计记录，否则会把「这次升级本身」一并抹掉
        $ignore = '';
        foreach ($this->auditGuardTables() as $table) {
            $ignore .= ' --ignore-table=' . escapeshellarg($cfg['database'] . '.' . $table);
        }

        $cmd = escapeshellarg($bin)
            . " --defaults-extra-file=" . escapeshellarg($cnf)
            . " -h" . escapeshellarg($cfg['host'])
            . " -P" . (int) $cfg['port']
            . " --default-character-set=utf8mb4 --single-transaction --routines=false"
            . $ignore
            . " " . escapeshellarg($cfg['database']) . " > " . escapeshellarg($file) . " 2>&1";
        exec($cmd, $out, $code);
        @unlink($cnf);
        if ($code !== 0 || !is_file($file) || filesize($file) === 0) {
            return '';
        }
        return $file;
    }

    /** 落盘 */
    private function deployFiles(array $files, string $extractDir): int
    {
        $n = 0;
        foreach ($files as $item) {
            $abs = $this->root . '/' . $item['rel'];
            @mkdir(dirname($abs), 0755, true);
            if (!@copy($item['src'], $abs)) {
                throw new \RuntimeException("写入失败：{$item['rel']}");
            }
            $n++;
        }
        return $n;
    }

    /** 执行 SQL 迁移 */
    private function runSql(array $files): int
    {
        if ($files === []) {
            return 0;
        }
        $pdo = $this->pdo();
        $n = 0;
        foreach ($files as $file) {
            $raw = (string) file_get_contents($file);
            if ($err = $this->sqlSafetyError($raw)) {
                throw new \RuntimeException($err . '（' . basename($file) . '）');
            }
            $sql = preg_replace('/^\s*--.*$/m', '', $raw);
            $sql = (string) preg_replace('#/\*.*?\*/#s', '', (string) $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') {
                    continue;
                }
                if ($err = $this->sqlSafetyError($stmt)) {
                    throw new \RuntimeException($err . '（' . basename($file) . '）');
                }
                try {
                    $pdo->exec($stmt);
                } catch (\PDOException $e) {
                    throw new \RuntimeException('SQL 执行失败 [' . basename($file) . ']：' . $e->getMessage());
                }
            }
            $n++;
        }
        return $n;
    }

    /** 按备份清单还原文件，返回还原/删除的文件数 */
    private function restoreFiles(string $manifestPath): int
    {
        if (!is_file($manifestPath)) {
            return 0;
        }
        $entries = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($entries)) {
            return 0;
        }
        $dir = dirname($manifestPath);
        $n = 0;
        foreach ($entries as $e) {
            $abs = $this->root . '/' . $e['rel'];
            if (!empty($e['existed']) && !empty($e['bak']) && is_file($dir . '/' . $e['bak'])) {
                @mkdir(dirname($abs), 0755, true);
                @copy($dir . '/' . $e['bak'], $abs);
                $n++;
            } elseif (empty($e['existed'])) {
                @unlink($abs);
                $n++;
            }
        }
        return $n;
    }

    /** 导入 SQL 文件（回滚数据库用） */
    private function importSqlFile(string $file): void
    {
        $raw = (string) file_get_contents($file);
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $raw);
        $sql = (string) preg_replace('#/\*.*?\*/#s', '', $sql);
        $pdo = $this->pdo();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            $pdo->exec($stmt);
        }
    }

    /** 回写 config/version.php */
    private function writeVersionFile(string $version, string $build = '', string $release = ''): void
    {
        $v = addcslashes($version, "'");
        $b = addcslashes($build !== '' ? $build : date('Ymd') . '.01', "'");
        $r = addcslashes($release !== '' ? $release : date('Y-m-d'), "'");
        $content = <<<PHP
<?php
/**
 * 版本基线（远程升级的最小真值）
 *
 * 说明：
 *  - 系统当前版本以此文件为准，升级成功后由 UpgradeService 回写 version / build / release
 *  - 该文件本身允许被升级包覆盖（包内若为 backend/config/version.php 则以包内容为准）
 *  - 不要在此文件写任何动态逻辑，保持纯数组常量，便于被程序安全地重写
 */

return [
    // 语义化版本号（升级比较用此字段）
    'version' => '{$v}',
    // 构建号：建议 日期.序号，用于区分同版本的不同补丁
    'build'   => '{$b}',
    // 发布日期
    'release' => '{$r}',
    // 当前发布通道：stable | beta | dev —— 只会拉取同通道的包
    'channel' => 'stable',
];

PHP;
        $file = $this->backendRoot . '/config/version.php';
        if (@file_put_contents($file, $content) === false) {
            throw new \RuntimeException('版本文件写入失败：' . $file);
        }
    }

    // ---------------------------------------------------------------- 其它基础设施

    private function writeLog(array $data): int
    {
        $row = $this->dao->create($data);
        return (int) ($row['id'] ?? 0);
    }

    private function pdo(): \PDO
    {
        static $pdo = null;
        if ($pdo instanceof \PDO) {
            return $pdo;
        }
        $cfg = $this->dbConfig();
        $pdo = new \PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4",
            $cfg['username'],
            $cfg['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    }

    private function dbConfig(): array
    {
        $default = (string) config('database.default', 'mysql');
        $conn = (array) config('database.connections.' . $default, []);
        return [
            'host'     => (string) ($conn['hostname'] ?: '127.0.0.1'),
            'port'     => (int) ($conn['hostport'] ?: 3306),
            'database' => (string) ($conn['database'] ?: ''),
            'username' => (string) ($conn['username'] ?: ''),
            'password' => (string) ($conn['password'] ?: ''),
        ];
    }

    private function resolveMysqldump(): string
    {
        $candidates = array_filter([(string) config('upgrade.mysqldump', ''), (string) config('database.backup.mysqldump', '')]);
        foreach (glob('D:/phpstudy_pro/Extensions/MySQL*/bin/mysqldump.exe') ?: [] as $p) {
            $candidates[] = $p;
        }
        $candidates[] = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/MySQL/*/bin/mysqldump.exe' : '/usr/bin/mysqldump';
        foreach ($candidates as $p) {
            foreach (glob((string) $p) ?: [(string) $p] as $real) {
                if (is_file($real)) {
                    return $real;
                }
            }
        }
        $w = [];
        exec(PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>nul' : 'which mysqldump 2>/dev/null', $w, $c);
        if ($c === 0 && !empty($w[0]) && is_file(trim($w[0]))) {
            return trim($w[0]);
        }
        return '';
    }

    /** 回滚时需要保留的审计表（不入数据库备份） */
    private function auditGuardTables(): array
    {
        $prefix = (string) config('database.connections.mysql.prefix', '');
        return array_values(array_filter([
            $prefix . 'sys_upgrade_log',
        ]));
    }

    /** 并发锁：文件 + TTL 兜底 */
    private function acquireLock(): void
    {
        $lock = $this->workDir . '/upgrade.lock';
        @mkdir(dirname($lock), 0755, true);
        if (is_file($lock) && time() - (int) @filemtime($lock) < static::LOCK_TTL) {
            throw ApiException::badRequest('已有升级/回滚任务在执行中，请稍后再试');
        }
        file_put_contents($lock, (string) time());
    }

    private function releaseLock(): void
    {
        @unlink($this->workDir . '/upgrade.lock');
    }

    private function rmdirRecursive(string $dir, float $deadline = 0): int
    {
        if (!is_dir($dir) || ($deadline > 0 && microtime(true) > $deadline)) {
            return 0;
        }
        $n = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            if ($deadline > 0 && microtime(true) > $deadline) {
                break;
            }
            if (is_file($f)) {
                @unlink($f) && $n++;
            } else {
                $n += $this->rmdirRecursive($f, $deadline);
            }
        }
        @rmdir($dir);
        return $n;
    }
}
