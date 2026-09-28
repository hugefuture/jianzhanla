<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;
use think\facade\Filesystem;

/**
 * 上传服务
 */
class UploadService
{
    use Instance;

    /** 允许的图片后缀 */
    protected array $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    /** 允许的文件后缀 */
    protected array $fileExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'csv', 'mp4', 'mp3'];

    /**
     * 从配置读取限制，便于不同环境调整（.env 的 [UPLOAD] 段）
     * @return array{ext: array, maxSize: int}
     */
    protected function limits(string $kind): array
    {
        $envExt = trim((string) config('upload.allow_ext', ''));
        $envMax = (int) config('upload.max_size', 0);
        $base = $kind === 'image' ? $this->imageExt : $this->fileExt;
        $max  = $kind === 'image' ? 10 * 1024 * 1024 : 50 * 1024 * 1024;
        return [
            // 配置的白名单与代码内置白名单取交集，写错配置不会反而放开权限
            'ext' => $envExt !== '' ? array_values(array_intersect($base, explode(',', strtolower($envExt)))) : $base,
            'maxSize' => $envMax > 0 ? min($max, $envMax * 1024 * 1024) : $max,
        ];
    }

    /** 上传图片 */
    public function image($file, string $module = 'common'): array
    {
        $limit = $this->limits('image');
        return $this->doUpload($file, $limit['ext'], $limit['maxSize'], 'images', $module);
    }

    /** 上传文件 */
    public function file($file, string $module = 'common'): array
    {
        $limit = $this->limits('file');
        return $this->doUpload($file, $limit['ext'], $limit['maxSize'], 'files', $module);
    }

    protected function doUpload($file, array $allowExt, int $maxSize, string $dir, string $module = 'common'): array
    {
        if (!$file || !$file->isValid()) {
            throw ApiException::badRequest('未收到有效文件');
        }
        $ext = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowExt, true)) {
            throw ApiException::badRequest('不允许的文件类型：' . $ext);
        }
        if ($file->getSize() > $maxSize) {
            throw ApiException::badRequest(sprintf(
                '文件大小超出限制：%.2fMB / 上限 %dMB',
                $file->getSize() / 1048576,
                intdiv($maxSize, 1048576)
            ));
        }
        // 真实内容类型必须与后缀一致，避免改后缀绕过（如把脚本伪装成图片）
        $this->assertRealMime($file->getRealPath(), $ext);

        // 文件名不带扩展名，由 Filesystem 依据原扩展名补全（避免 hash.png.png 双后缀）
        $path = Filesystem::disk('public')->putFile($dir, $file, function () {
            return date('Ymd') . '/' . md5(uniqid((string) mt_rand(), true));
        });

        $result = [
            'name' => $file->getOriginalName(),
            'path' => $path,
            'url' => '/uploads/' . str_replace('\\', '/', $path),
            'size' => $file->getSize(),
            'mime' => method_exists($file, 'getMime') ? (string) $file->getMime() : '',
        ];

        // 登记到文件记录表，供「文件管理」页统一管理
        try {
            $user = request()->user ?? [];
            $result['id'] = SysFileService::getInstance()->record(
                $result,
                (int) ($user['id'] ?? 0),
                (int) ($user['dept_id'] ?? 0),
                $module
            );
        } catch (\Throwable $e) {
            // 记录失败不影响上传结果
        }

        return $result;
    }

    /**
     * 校验文件真实内容类型与后缀是否匹配
     * 说明：仅对能识别的类型做校验，识别不了的一律放行（避免误杀压缩包等复合格式）
     */
    protected function assertRealMime(string $realPath, string $ext): void
    {
        if (!is_file($realPath) || !function_exists('finfo_open')) {
            return;
        }
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        if ($detected === '' || $detected === 'application/octet-stream') {
            return;
        }
        // 后缀 -> 允许的 MIME（含浏览器常见别名）
        $map = [
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
            'pdf'  => ['application/pdf'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/plain', 'text/csv'],
            'mp3'  => ['audio/mpeg'],
            'mp4'  => ['video/mp4'],
        ];
        // 图片类统一要求 image/*，防止把网页脚本改名上传
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (in_array($ext, $imageExt, true)) {
            if (!str_starts_with($detected, 'image/')) {
                throw ApiException::badRequest('文件内容不是有效图片，请勿修改后缀上传');
            }
            return;
        }
        if (isset($map[$ext]) && !in_array($detected, $map[$ext], true)) {
            throw ApiException::badRequest('文件内容与扩展名不符，请检查文件是否损坏或被改名');
        }
    }
}
