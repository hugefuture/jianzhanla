<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\model\SysFile;
use app\admin\service\SysFileService;
use think\App;
use think\Response;

/**
 * 文件管理
 */
class File extends CrudController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = SysFileService::getInstance();
    }

    /** 统计 GET system/file/stats */
    public function stats(): Response
    {
        return $this->success(SysFileService::getInstance()->stats());
    }

    /** 清理孤儿文件 POST system/file/clearOrphan */
    public function clearOrphan(): Response
    {
        $result = SysFileService::getInstance()->clearOrphans();
        return $this->success($result, "扫描 {$result['scanned']} 个文件，清理 {$result['removed']} 个");
    }

    /**
     * 鉴权下载 GET system/file/download?id=
     * 需登录且具备 system:file:list 权限；按文件 ID 取流，不暴露物理路径
     */
    public function download(): Response
    {
        $id = (int) $this->request->param('id', 0);
        if ($id <= 0) {
            return $this->fail('缺少文件ID');
        }
        $row = SysFile::where('id', $id)->find();
        if (!$row) {
            return $this->fail('文件不存在');
        }

        $root = realpath(SysFileService::getInstance()->uploadRoot());
        $path = ltrim((string) $row['path'], '/');
        $file = realpath($root . DIRECTORY_SEPARATOR . $path);
        // 路径穿越防护：真实路径必须落在 uploadRoot 内
        if ($root === false || $file === false || $path === ''
            || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            return $this->fail('文件路径非法');
        }
        if (!is_file($file)) {
            return $this->fail('文件已被清理或不存在');
        }

        $name = (string) ($row['name'] ?: basename($file));
        $content = (string) file_get_contents($file);
        return response($content, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Length'      => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="'
                . preg_replace('/[^\x20-\x7E]/', '_', $name)
                . "\"; filename*=UTF-8''" . rawurlencode($name),
        ]);
    }
}
