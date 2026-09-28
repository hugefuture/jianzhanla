<?php
declare(strict_types=1);

namespace app\command;

use app\admin\model\SysRole;
use app\admin\model\SysUser;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * 管理员密码重置 / 创建
 *
 * 用法：
 *   php think admin:password                # 重置 admin 密码为 123456
 *   php think admin:password admin 654321   # 重置指定账号密码
 *   php think admin:password newadmin 123456 --create   # 不存在则创建
 */
class AdminPassword extends Command
{
    protected function configure()
    {
        $this->setName('admin:password')
            ->addArgument('username', Argument::OPTIONAL, '账号', 'admin')
            ->addArgument('password', Argument::OPTIONAL, '新密码', '123456')
            ->addOption('create', null, Option::VALUE_NONE, '账号不存在时创建')
            ->setDescription('重置（或创建）管理员账号密码');
    }

    protected function execute(Input $input, Output $output)
    {
        $username = (string) $input->getArgument('username');
        $password = (string) $input->getArgument('password');

        if (mb_strlen($password) < 6) {
            $output->error('密码长度不能少于 6 位');
            return 1;
        }

        $user = SysUser::where('username', $username)->find();
        if ($user) {
            $user->password = $password;
            $user->status = 1;
            $user->save();
            $output->writeln("<info>已重置账号 [{$username}] 的密码为 {$password}</info>");
            return 0;
        }

        if (!$input->getOption('create')) {
            $output->writeln("<comment>账号 [{$username}] 不存在。可加 --create 参数创建。</comment>");
            return 1;
        }

        SysUser::create([
            'username' => $username,
            'nickname' => $username,
            'password' => $password,
            'status' => 1,
        ]);
        // 关联超级管理员角色
        $roleId = SysRole::where('code', SysRole::SUPER_CODE)->value('id');
        if ($roleId) {
            \app\admin\model\SysUserRole::create(['user_id' => (int) \app\admin\model\SysUser::where('username', $username)->value('id'), 'role_id' => (int) $roleId]);
        }
        $output->writeln("<info>已创建超级管理员账号 [{$username}]，密码 {$password}</info>");
        return 0;
    }
}
