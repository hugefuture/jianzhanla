<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 菜单/权限表 sdccc_sys_menu
 * type: M=目录 C=菜单 F=按钮
 */
class SysMenu extends Model
{
    protected $name = 'SysMenu';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    public const TYPE_DIR = 'M';
    public const TYPE_MENU = 'C';
    public const TYPE_BUTTON = 'F';
}
