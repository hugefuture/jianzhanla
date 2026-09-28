<?php
declare(strict_types=1);

namespace app\admin\controller\tool;

use app\admin\service\GenService;
use app\common\controller\AbstractController;
use app\common\exception\ApiException;
use think\App;
use think\Response;

/**
 * CRUD 代码生成
 */
class Gen extends AbstractController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /** 数据表列表 GET tool/gen/tables */
    public function tables(): Response
    {
        $keyword = (string) $this->request->param('keyword', '');
        return $this->success(GenService::getInstance()->tables($keyword));
    }

    /** 表字段 GET tool/gen/columns */
    public function columns(): Response
    {
        $table = (string) $this->request->param('table', '');
        if ($table === '') {
            throw ApiException::badRequest('参数 table 不能为空');
        }
        return $this->success(GenService::getInstance()->columns($table));
    }

    /** 代码预览 GET tool/gen/preview */
    public function preview(): Response
    {
        $table = (string) $this->request->param('table', '');
        if ($table === '') {
            throw ApiException::badRequest('参数 table 不能为空');
        }
        return $this->success(GenService::getInstance()->preview($table, $this->options()));
    }

    /** 生成并写入项目 POST tool/gen/generate */
    public function generate(): Response
    {
        $data = $this->input();
        $table = (string) ($data['table'] ?? '');
        if ($table === '') {
            throw ApiException::badRequest('参数 table 不能为空');
        }
        $result = GenService::getInstance()->generate($table, $this->options());
        return $this->success($result, '生成完成');
    }

    /** 打包下载 POST tool/gen/zip */
    public function zip(): Response
    {
        $data = $this->input();
        $table = (string) ($data['table'] ?? '');
        if ($table === '') {
            throw ApiException::badRequest('参数 table 不能为空');
        }
        $url = GenService::getInstance()->zip($table, $this->options());
        return $this->success(['url' => $url]);
    }

    /** 生成选项（前端可覆盖） */
    protected function options(): array
    {
        $p = $this->request->param();
        $opt = [];
        foreach (['pkg', 'module', 'perm', 'parentMenu', 'icon', 'title', 'author'] as $key) {
            if (!empty($p[$key])) {
                $opt[$key] = (string) $p[$key];
            }
        }
        if (!empty($p['overwrite'])) {
            $opt['overwrite'] = true;
        }
        return $opt;
    }
}
