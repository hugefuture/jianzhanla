<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 文件记录表 sdccc_sys_file
 */
class SysFile extends Model
{
    protected $name = 'SysFile';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'size' => 'integer',
        'create_by' => 'integer',
        'dept_id' => 'integer',
    ];

    protected $append = ['size_text'];

    /** 人类可读的文件大小 */
    public function getSizeTextAttr($value, array $data = []): string
    {
        $size = (int) ($data['size'] ?? 0);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return ($i === 0 ? $size : round($size, 2)) . ' ' . $units[$i];
    }
}
