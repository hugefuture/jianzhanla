<?php
declare(strict_types=1);
/**
 * make-upgrade-package.php — 生成本地演示升级源（tools/upgrade-repo）
 *
 * 用途：给「远程升级」功能造一套可复现的测试包，并生成 manifest.json。
 *
 * 用法：
 *   php tools/make-upgrade-package.php --reindex                  # 扫描包重建 manifest.json
 *   php tools/make-upgrade-package.php --version=1.1.0 --sql=1    # 生成一个正常包并重建清单
 *   php tools/make-upgrade-package.php --scenario=traversal --version=9.8.0
 *   php tools/make-upgrade-package.php --clean                    # 清空整个演示源
 *
 * scenario:
 *   ok          正常包（新增 docs/UPGRADE_TEST.md，可选执行 sql/probe.sql）
 *   traversal   路径穿越包（payload/../../evil.php）→ 应被拒绝
 *   outside     越界包（payload/evil.txt 落在允许根之外）→ 应被拒绝
 *   denypath    黑名单包（payload/backend/runtime/evil.php）→ 应被拒绝
 *   badsql      含 DROP DATABASE 的迁移脚本 → 应被拒绝
 *   nomanifest  缺 upgrade.json → 应被拒绝
 *
 * 起本地升级源：
 *   cd tools/upgrade-repo && php -S 127.0.0.1:8899 -t .
 */

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $a, $m)) {
        $opt[$m[1]] = $m[2] ?? true;
    }
}

$repo = __DIR__ . '/upgrade-repo';

if (isset($opt['clean'])) {
    foreach (glob($repo . '/*') ?: [] as $f) {
        is_file($f) ? @unlink($f) : rmrf($f);
    }
    echo "已清空 {$repo}\n";
    exit(0);
}

if (!is_dir($repo) && !@mkdir($repo, 0755, true)) {
    fwrite(STDERR, "无法创建目录 {$repo}\n");
    exit(1);
}

if (isset($opt['reindex']) || isset($opt['version'])) {
    if (isset($opt['version'])) {
        $version = trim((string) $opt['version']);
        $scenario = (string) ($opt['scenario'] ?? 'ok');
        $withSql = isset($opt['sql']) && $opt['sql'] !== '0';
        $zip = $repo . '/jianzhanla-admin-' . $version . '.zip';
        buildPackage($zip, $version, $scenario, $withSql);
        printf("✓ 生成包 %s（scenario=%s）\n", basename($zip), $scenario);
    }
    $badsha = isset($opt['badsha']) ? (string) $opt['badsha'] : '';
    reindex($repo, $badsha);
    exit(0);
}

fwrite(STDERR, "用法见文件头部注释\n");
exit(1);

/** 构建单个升级包 */
function buildPackage(string $zip, string $version, string $scenario, bool $withSql): void
{
    @unlink($zip);
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "无法创建 zip：{$zip}\n");
        exit(1);
    }

    $files = [];
    switch ($scenario) {
        case 'ok':
            $files['payload/docs/UPGRADE_TEST.md'] = "# 升级测试文件\n\n本文件由演示升级包写入，用于验证「文件落盘」链路。\n\n- 版本：{$version}\n- 生成时间：" . date('Y-m-d H:i:s') . "\n";
            if ($withSql) {
                $files['sql/probe.sql'] = "-- 升级探针：验证 SQL 迁移脚本被执行\n"
                    . "INSERT INTO `sdccc_sys_config` (`name`, `key`, `value`, `type`, `status`, `remark`)\n"
                    . "VALUES ('升级探针', 'upgrade.probe', '{$version}', 'text', 1, '由演示升级包写入')\n"
                    . "ON DUPLICATE KEY UPDATE `value` = '{$version}';\n";
                $files['payload/docs/UPGRADE_TEST.md'] .= "- SQL：写入 sys_config.upgrade.probe\n";
            }
            break;
        case 'traversal':
            $files['payload/../../../../evil.php'] = "<?php // Zip Slip 探针\n";
            break;
        case 'outside':
            $files['payload/evil.txt'] = "不在允许根内的文件\n";
            break;
        case 'denypath':
            $files['payload/backend/runtime/evil.php'] = "<?php // 黑名单路径探针\n";
            break;
        case 'badsql':
            $files['payload/docs/UPGRADE_TEST.md'] = "badsql 探针\n";
            $files['sql/bad.sql'] = "-- 危险 SQL 探针\nDROP DATABASE sdccc_admin;\n";
            break;
        case 'nomanifest':
            $files['payload/docs/UPGRADE_TEST.md'] = "无清单探针\n";
            break;
        default:
            fwrite(STDERR, "未知 scenario：{$scenario}\n");
            exit(1);
    }

    foreach ($files as $name => $content) {
        $za->addFromString($name, $content);
    }
    if ($scenario !== 'nomanifest') {
        $manifest = [
            'version'     => $version,
            'build'       => date('Ymd') . '.01',
            'release'     => date('Y-m-d'),
            'description' => "演示升级包 {$version}（{$scenario}）",
        ];
        if (in_array($scenario, ['ok'], true) && isset($files['sql/probe.sql'])) {
            $manifest['sql'] = ['sql/probe.sql'];
        }
        if ($scenario === 'badsql') {
            $manifest['sql'] = ['sql/bad.sql'];
        }
        $za->addFromString('upgrade.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $za->close();
}

/** 扫描目录下所有 zip，重建 manifest.json */
function reindex(string $repo, string $badshaVersion = ''): void
{
    $packages = [];
    foreach (glob($repo . '/jianzhanla-admin-*.zip') ?: [] as $file) {
        $v = pkgVersion($file);
        if ($v === '') {
            continue;
        }
        // --badsha=<version>：故意写错该包的 sha256，用于验证校验拦截
        $sha = ($badshaVersion !== '' && $v === $badshaVersion)
            ? str_repeat('a', 64)
            : (string) hash_file('sha256', $file);
        $packages[$v] = [
            'version'     => $v,
            'channel'     => 'stable',
            'requires'    => '>=1.0.0',
            'size'        => filesize($file),
            'sha256'      => $sha,
            'url'         => 'http://127.0.0.1:8899/' . basename($file),
            'released_at' => date('Y-m-d'),
            'changelog'   => ['演示升级包 ' . $v, '用于验证远程升级链路'],
        ];
    }
    ksort($packages, SORT_NATURAL);
    $manifest = [
        'appid'    => 'jianzhanla-admin',
        'latest'   => $packages === [] ? '' : array_key_last($packages),
        'packages' => array_values($packages),
    ];
    file_put_contents(
        $repo . '/manifest.json',
        json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    printf("✓ manifest.json 已重建，共 %d 个包\n", count($packages));
}

/** 从包内 upgrade.json 读版本号 */
function pkgVersion(string $zip): string
{
    $za = new ZipArchive();
    if ($za->open($zip) !== true) {
        return '';
    }
    $json = $za->getFromName('upgrade.json');
    $za->close();
    if (!$json) {
        return (string) preg_replace('/^jianzhanla-admin-|\.zip$/', '', basename($zip));
    }
    $data = json_decode($json, true);
    if (is_array($data) && !empty($data['version'])) {
        return (string) $data['version'];
    }
    return preg_replace('/^jianzhanla-admin-|\.zip$/', '', basename($zip)) ?: '';
}

function rmrf(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $f) {
        is_file($f) ? @unlink($f) : rmrf($f);
    }
    @rmdir($dir);
}
