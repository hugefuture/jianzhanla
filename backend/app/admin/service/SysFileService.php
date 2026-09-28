<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\dao\SysFileDao;
use app\admin\model\SysFile;
use app\common\service\AbstractService;

/**
 * 文件管理服务
 *
 * 职责：
 *  - 上传后登记到 sdccc_sys_file（由 UploadService 调用）
 *  - 删除记录时一并删除磁盘文件
 *  - 清理孤儿文件（磁盘有、库里没有）
 */
class SysFileService extends AbstractService
{
    protected array $allowFields = ['name'];
    protected array $extraFields = [];

    public function __construct()
    {
        $this->dao = SysFileDao::getInstance();
    }

    /** 上传根目录（public/uploads） */
    public function uploadRoot(): string
    {
        return dirname(__DIR__, 3) . '/public/uploads';
    }

    /** 登记一条上传记录 */
    public function record(array $file, int $userId = 0, int $deptId = 0, string $module = 'common'): int
    {
        $data = [
            'name' => (string) ($file['name'] ?? ''),
            'path' => (string) ($file['path'] ?? ''),
            'url' => (string) ($file['url'] ?? ''),
            'ext' => strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)),
            'size' => (int) ($file['size'] ?? 0),
            'mime' => (string) ($file['mime'] ?? ''),
            'module' => $module,
            'dept_id' => $deptId,
            'create_by' => $userId,
        ];
        $row = SysFile::create($data);
        return (int) $row->id;
    }

    /** 删除记录 + 磁盘文件 */
    public function remove(array $ids): void
    {
        $rows = SysFile::whereIn('id', $ids)->select()->toArray();
        parent::remove($ids);
        foreach ($rows as $row) {
            $this->deletePhysical((string) $row['path']);
        }
    }

    /**
     * 清理孤儿文件：磁盘上存在但数据库无记录
     * @return array{scanned:int, removed:int, size:int}
     */
    public function clearOrphans(): array
    {
        $root = $this->uploadRoot();
        if (!is_dir($root)) {
            return ['scanned' => 0, 'removed' => 0, 'size' => 0];
        }
        $known = [];
        foreach (SysFile::column('path') as $path) {
            $known[str_replace('\\', '/', (string) $path)] = true;
        }

        $scanned = 0;
        $removed = 0;
        $size = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $scanned++;
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (isset($known[$rel])) {
                continue;
            }
            // 只回收 uploads 下按日期目录归档的文件，避免误删
            if (!preg_match('#^(images|files)/\d{8}/#', $rel)) {
                continue;
            }
            $size += $file->getSize();
            if (@unlink($file->getPathname())) {
                $removed++;
            }
        }
        return ['scanned' => $scanned, 'removed' => $removed, 'size' => $size];
    }

    /** 统计 */
    public function stats(): array
    {
        return [
            'total' => (int) SysFile::count(),
            'size' => (int) SysFile::sum('size'),
        ];
    }

    protected function deletePhysical(string $path): void
    {
        if ($path === '') {
            return;
        }
        $full = $this->uploadRoot() . '/' . ltrim(str_replace('\\', '/', $path), '/');
        // 安全限制：只允许删除 uploads 目录内的文件
        $real = realpath($full);
        $rootReal = realpath($this->uploadRoot());
        if ($real && $rootReal && str_starts_with($real, $rootReal) && is_file($real)) {
            @unlink($real);
        }
    }
}
