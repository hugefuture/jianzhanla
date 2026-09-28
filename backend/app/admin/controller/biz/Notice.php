<?php
declare(strict_types=1);

namespace app\admin\controller\biz;

use app\admin\controller\CrudController;
use app\admin\service\ExcelService;
use app\admin\service\SysNoticeService;
use app\common\exception\ApiException;
use think\App;
use think\Response;

/**
 * 通知公告（业务模块样例）
 *
 * 继承 CrudController 即自动获得 list / read / save / delete / status
 * 只需在此追加业务独有的接口
 */
class Notice extends CrudController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = SysNoticeService::getInstance();
    }

    /** 发布 / 下架 POST biz/notice/publish */
    public function publish(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->param('status');
        SysNoticeService::getInstance()->publish($id, $status);
        return $this->success(null, $status === 1 ? '已发布' : '已下架');
    }

    /** 导出 Excel GET biz/notice/export */
    public function export(): Response
    {
        $params = $this->request->param();
        unset($params['page'], $params['limit']);
        $headers = [
            'title' => '公告标题',
            'type_text' => '类型',
            'status_text' => '状态',
            'is_top' => '置顶',
            'content' => '内容',
            'remark' => '备注',
            'create_time' => '创建时间',
        ];
        // 传闭包而非数组：闭包返回游标生成器，边查边写，不占内存
        $result = ExcelService::getInstance()->export(
            $headers,
            fn () => SysNoticeService::getInstance()->cursor($params),
            '通知公告'
        );
        return $this->success($result, "导出成功：{$result['rows']} 行 / {$result['sheets']} 个 sheet");
    }

    /** 导入模板 GET biz/notice/template */
    public function template(): Response
    {
        $url = ExcelService::getInstance()->template([
            '公告标题' => '示例标题',
            '类型' => 1,
            '内容' => '示例内容',
            '备注' => '',
        ], '通知公告导入');
        return $this->success(['url' => $url]);
    }

    /** 导入 Excel POST biz/notice/import（multipart: file） */
    public function import(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            throw ApiException::badRequest('未收到有效文件');
        }
        $ext = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw ApiException::badRequest('仅支持 xlsx / xls / csv 文件');
        }

        $dir = app()->getRuntimePath() . 'temp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $saved = $file->move($dir, $name);
        $path = $saved->getPathname();

        try {
            $parsed = ExcelService::getInstance()->import($path, [
                '公告标题' => 'title',
                '类型' => 'type',
                '内容' => 'content',
                '备注' => 'remark',
            ]);

            $success = 0;
            $failed = [];
            foreach ($parsed['rows'] as $row) {
                $lineNo = (int) ($row['_row'] ?? 0);
                unset($row['_row']);
                try {
                    SysNoticeService::getInstance()->save($row);
                    $success++;
                } catch (\Throwable $e) {
                    $failed[] = ['row' => $lineNo, 'msg' => $e->getMessage()];
                }
            }
        } finally {
            @unlink($path);
        }

        return $this->success([
            'total' => $parsed['total'] ?? 0,
            'success' => $success,
            'failed' => $failed,
        ], "导入完成：成功 {$success} 条" . ($failed ? ('，失败 ' . count($failed) . ' 条') : ''));
    }

    /** 置顶切换 POST biz/notice/top */
    public function top(): Response
    {
        $id = (int) $this->request->param('id');
        $isTop = (int) $this->request->param('is_top');
        SysNoticeService::getInstance()->toggleTop($id, $isTop);
        return $this->success(null, $isTop ? '已置顶' : '已取消置顶');
    }
}
