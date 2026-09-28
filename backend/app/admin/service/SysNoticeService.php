<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\dao\SysNoticeDao;
use app\admin\model\SysNotice;
use app\common\exception\ApiException;
use app\common\service\AbstractService;

/**
 * 通知公告服务（业务模块样例）
 *
 * 四层职责边界示例：
 *  - Controller 只做参数接收与响应
 *  - Service    业务规则（自动填充创建人、发布/下架校验）
 *  - Dao        数据访问（查询映射 + 数据权限）
 *  - Model      数据结构与派生字段
 */
class SysNoticeService extends AbstractService
{
    protected array $allowFields = ['title', 'type', 'content', 'status', 'is_top', 'dept_id', 'remark'];
    protected array $required = ['title'];

    public function __construct()
    {
        $this->dao = SysNoticeDao::getInstance();
    }

    /** 发布 / 下架 */
    public function publish(int $id, int $status): void
    {
        if (!in_array($status, [0, 1], true)) {
            throw ApiException::badRequest('状态值非法');
        }
        $row = $this->dao->findByPk($id);
        if (!$row) {
            throw ApiException::notFound('公告不存在');
        }
        $this->dao->updateByPk($id, ['status' => $status]);

        // 钩子：公告发布/下架后通知插件（如同步到外部渠道）
        \app\common\plugin\PluginManager::getInstance()->trigger('notice.publish.after', [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'status' => $status,
            'operator' => (int) (request()->user['id'] ?? 0),
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 置顶 / 取消置顶 */
    public function toggleTop(int $id, int $isTop): void
    {
        $this->dao->updateByPk($id, ['is_top' => $isTop ? 1 : 0]);
    }

    /**
     * 分页查询已发布公告（门户用，不套数据权限、强制 status=1）
     * @return array{list: array, total: int}
     */
    public function publicPage(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 10)));

        $query = SysNotice::where('status', 1);
        if (!empty($params['type'])) {
            $query->where('type', (int) $params['type']);
        }
        if (!empty($params['title'])) {
            $query->whereLike('title', '%' . trim((string) $params['title']) . '%');
        }

        $total = (clone $query)->count();
        $list = $query->order('is_top desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        // 门户不需要内部字段
        foreach ($list as &$row) {
            unset($row['remark'], $row['create_by']);
        }
        return ['list' => $list, 'total' => $total];
    }

    /** 读取单条已发布公告（未发布的不予返回） */
    public function publicRead(int $id): array
    {
        $row = SysNotice::where('status', 1)->find($id);
        if (!$row) {
            throw ApiException::notFound('公告不存在或未发布');
        }
        $data = $row->toArray();
        unset($data['remark'], $data['create_by']);
        return $data;
    }

    protected function beforeCreate(array &$data): void
    {
        $user = request()->user ?? [];
        $data['create_by'] = (int) ($user['id'] ?? 0);
        // 未指定部门时归入创建人所在部门，保证数据权限可命中
        if (empty($data['dept_id'])) {
            $data['dept_id'] = (int) ($user['dept_id'] ?? 0);
        }
        $data['type'] = in_array((int) ($data['type'] ?? 1), [1, 2], true) ? (int) $data['type'] : 1;
    }

    protected function beforeUpdate(array &$data, int $id): void
    {
        if (isset($data['type']) && !in_array((int) $data['type'], [1, 2], true)) {
            throw ApiException::badRequest('公告类型非法');
        }
    }
}
