<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\exception\ApiException;
use app\common\traits\Instance;
use think\facade\Db;

/**
 * CRUD 代码生成器
 *
 * 读取 information_schema 表结构，按项目四层规范生成：
 *   Model / Dao / Service / Controller + 前端页面 + 菜单SQL + 路由片段
 *
 * 生成结果可在线预览，也可直接写入项目目录（仅开发环境建议使用）。
 */
class GenService
{
    use Instance;

    /** 表前缀（生成类名时会剥离） */
    protected string $prefix = 'sdccc_';

    /** 不出现在表单/列表的系统字段 */
    protected array $skipFields = ['id', 'create_time', 'update_time', 'delete_time', 'create_by'];

    /** 后端根目录 */
    protected function backendPath(): string
    {
        return dirname(__DIR__, 3);
    }

    /** 项目根目录（backend 的上一级） */
    protected function projectPath(): string
    {
        return dirname(__DIR__, 4);
    }

    /** 当前数据库名 */
    protected function dbName(): string
    {
        return (string) config('database.connections.mysql.database');
    }

    /** 当前表前缀 */
    protected function prefix(): string
    {
        return (string) config('database.connections.mysql.prefix', 'sdccc_');
    }

    /**
     * 数据表列表
     * @return array
     */
    public function tables(string $keyword = ''): array
    {
        $db = $this->dbName();
        $query = Db::table('information_schema.tables')
            ->field('TABLE_NAME as name, TABLE_COMMENT as comment, CREATE_TIME as create_time, TABLE_ROWS as rows')
            ->where('TABLE_SCHEMA', $db);
        if ($keyword !== '') {
            $query = $query->whereLike('TABLE_NAME|TABLE_COMMENT', "%{$keyword}%");
        }
        $list = $query->order('TABLE_NAME')->select()->toArray();
        foreach ($list as &$item) {
            $item['comment'] = $item['comment'] ?: $item['name'];
        }
        return $list;
    }

    /**
     * 表字段元数据
     * @return array
     */
    public function columns(string $table): array
    {
        $this->assertTable($table);
        $rows = Db::table('information_schema.columns')
            ->field('COLUMN_NAME as name, COLUMN_TYPE as type, DATA_TYPE as data_type,
                     COLUMN_COMMENT as comment, COLUMN_KEY as key_name, IS_NULLABLE as nullable,
                     COLUMN_DEFAULT as default_value, ORDINAL_POSITION as sort')
            ->where('TABLE_SCHEMA', $this->dbName())
            ->where('TABLE_NAME', $table)
            ->order('ORDINAL_POSITION')
            ->select()
            ->toArray();

        $fields = [];
        foreach ($rows as $row) {
            $fields[] = $this->analyseField($row);
        }
        return $fields;
    }

    /** 字段属性推断：表单控件 / 搜索方式 / 是否必填 */
    protected function analyseField(array $row): array
    {
        $name = (string) $row['name'];
        $type = strtolower((string) $row['data_type']);
        $comment = (string) $row['comment'];
        $isPk = ($row['key_name'] ?? '') === 'PRI';
        $nullable = ($row['nullable'] ?? 'NO') === 'YES';

        $f = [
            'name' => $name,
            'type' => (string) $row['type'],
            'data_type' => $type,
            'comment' => $comment !== '' ? $comment : $name,
            'pk' => $isPk,
            'nullable' => $nullable,
            'default' => $row['default_value'],
            'is_time' => in_array($type, ['datetime', 'timestamp', 'date', 'time', 'year'], true),
            'is_text' => in_array($type, ['text', 'longtext', 'mediumtext', 'tinytext'], true),
            'is_number' => in_array($type, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'float', 'double'], true),
            'is_switch' => false,
            'search' => false,
            'search_op' => '',
            'form' => true,
            'control' => 'input',
            'required' => false,
            'system' => in_array($name, $this->skipFields, true),
        ];

        if ($name === 'status' || (str_starts_with($name, 'is_') && $type === 'tinyint')) {
            $f['is_switch'] = true;
            $f['control'] = 'switch';
            $f['search'] = true;
            $f['search_op'] = '=';
        } elseif ($f['is_time']) {
            $f['control'] = 'datetime';
            $f['search'] = ($name === 'create_time');
            $f['search_op'] = 'time';
        } elseif ($f['is_text']) {
            $f['control'] = 'textarea';
        } elseif ($f['is_number']) {
            $f['control'] = 'number';
        }

        // 名称类字段支持模糊搜索
        if (!$f['search'] && preg_match('/(name|title|code|remark|content)$/', $name) && !$f['is_text']) {
            $f['search'] = true;
            $f['search_op'] = 'like';
        }
        // like 精度太低的长文本不参与列表搜索
        if ($f['search_op'] === 'like' && $f['is_text']) {
            $f['search'] = false;
            $f['search_op'] = '';
        }

        // 必填：非空、无默认值、非系统字段、非开关/排序
        $f['required'] = !$nullable
            && $row['default_value'] === null
            && !$f['system']
            && !$f['is_switch']
            && !in_array($name, ['sort', 'order_num', 'remark', 'parent_id'], true);

        return $f;
    }

    /**
     * 生成代码文件（不落盘）
     * @return array{files: array, meta: array}
     */
    public function preview(string $table, array $opt = []): array
    {
        $meta = $this->buildMeta($table, $opt);
        $fields = $this->columns($table);
        $files = [
            $this->buildModel($meta, $fields),
            $this->buildDao($meta, $fields),
            $this->buildService($meta, $fields),
            $this->buildController($meta, $fields),
            $this->buildVue($meta, $fields),
            $this->buildApiSnippet($meta),
            $this->buildMenuSql($meta),
            $this->buildRouteSnippet($meta),
        ];
        return ['files' => $files, 'meta' => $meta, 'fields' => $fields];
    }

    /** 生成并写入项目目录 */
    public function generate(string $table, array $opt = []): array
    {
        $result = $this->preview($table, $opt);
        $written = [];
        foreach ($result['files'] as $file) {
            if (empty($file['write'])) {
                $written[] = ['path' => $file['path'], 'status' => 'skipped', 'msg' => '需手工追加'];
                continue;
            }
            $full = $this->projectPath() . '/' . ltrim($file['path'], '/');
            $dir = dirname($full);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw ApiException::badRequest("目录创建失败：{$dir}");
            }
            $content = $file['content'];
            // 长驻进程（php -S / php-fpm worker）会缓存 stat 结果，
            // 文件被外部删除后 is_file() 仍可能返回旧值，这里显式清缓存
            clearstatcache(true, $full);
            // 路由文件采用追加方式，避免覆盖
            if (!empty($file['append'])) {
                $old = is_file($full) ? (string) file_get_contents($full) : '';
                if (str_contains($old, $file['marker'])) {
                    $written[] = ['path' => $file['path'], 'status' => 'skipped', 'msg' => '路由已存在'];
                    continue;
                }
                $content = str_ends_with($full, '.js')
                    ? rtrim($old, "\n") . "\n" . $file['content']
                    : $this->injectRoute($old, $file['content']);
            }
            if (is_file($full) && empty($file['append']) && empty($opt['overwrite'])) {
                $written[] = ['path' => $file['path'], 'status' => 'skipped', 'msg' => '文件已存在'];
                continue;
            }
            @file_put_contents($full, $content);
            $written[] = ['path' => $file['path'], 'status' => 'written'];
        }
        return ['meta' => $result['meta'], 'files' => $written];
    }

    /** 打包为 zip，返回可下载的相对 URL */
    public function zip(string $table, array $opt = []): string
    {
        $result = $this->preview($table, $opt);
        $meta = $result['meta'];
        $dir = $this->backendPath() . '/public/uploads/gen';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $zipFile = $dir . '/' . $meta['table'] . '.zip';
        @unlink($zipFile);

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            throw ApiException::badRequest('ZIP 创建失败');
        }
        foreach ($result['files'] as $file) {
            $zip->addFromString($file['path'], $file['content']);
        }
        $zip->close();
        return '/uploads/gen/' . $meta['table'] . '.zip';
    }

    // ---------------- 元信息 ----------------

    protected function buildMeta(string $table, array $opt): array
    {
        $this->assertTable($table);
        $prefix = $this->prefix();
        $short = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;

        $info = Db::table('information_schema.tables')
            ->field('TABLE_COMMENT as comment')
            ->where('TABLE_SCHEMA', $this->dbName())
            ->where('TABLE_NAME', $table)
            ->find();
        $comment = (string) ($info['comment'] ?? '');
        // 表注释形如「通知公告表」
        $title = $opt['title'] ?? (preg_replace('/(表|信息)$/u', '', $comment) ?: $short);

        $studly = $this->studly($short);          // SysNotice
        $kebab = $this->kebab($short);            // sys-notice -> 实际用最后一段
        $kebab = $this->kebab(substr($short, strrpos($short, '_') === false ? 0 : strrpos($short, '_') + 1));

        return [
            'table' => $table,
            'short' => $short,
            'title' => $title,
            'model' => $studly,
            'modelVar' => lcfirst($studly),
            'pkg' => $opt['pkg'] ?? 'biz',                     // 后端 controller 子目录
            'namespace' => 'app\\admin\\controller\\' . ($opt['pkg'] ?? 'biz'),
            'module' => $opt['module'] ?? 'business',          // 前端 views 子目录
            'page' => $kebab,                                  // 前端页面目录
            'perm' => $opt['perm'] ?? 'biz',                   // 权限前缀
            'route' => ($opt['perm'] ?? 'biz') . '/' . $kebab,  // 路由前缀
            'perms' => ($opt['perm'] ?? 'biz') . ':' . str_replace('-', '', $kebab),
            'parentMenu' => $opt['parentMenu'] ?? '业务管理',
            'icon' => $opt['icon'] ?? 'Document',
            'author' => $opt['author'] ?? 'jianzhanla-admin',
            'date' => date('Y-m-d'),
        ];
    }

    protected function assertTable(string $table): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw ApiException::badRequest('表名非法');
        }
        $exists = Db::table('information_schema.tables')
            ->where('TABLE_SCHEMA', $this->dbName())
            ->where('TABLE_NAME', $table)
            ->count();
        if (!$exists) {
            throw ApiException::notFound('数据表不存在');
        }
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    protected function kebab(string $value): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('_', '-', $value)) ?: $value);
    }

    /** 把路由片段插入到路由分组结束前 */
    protected function injectRoute(string $old, string $snippet): string
    {
        $marker = "])->middleware([Token::class, OperLog::class]);";
        $pos = strrpos($old, $marker);
        if ($pos === false) {
            return $old . "\n" . $snippet;
        }
        return substr($old, 0, $pos) . $snippet . substr($old, $pos);
    }

    // ---------------- 各层模板 ----------------

    protected function buildModel(array $m, array $fields): array
    {
        $append = [];
        foreach ($fields as $f) {
            if ($f['is_switch'] || ($f['name'] === 'status')) {
                $append[] = $f['name'];
            }
        }
        $phpType = [];
        foreach ($fields as $f) {
            if ($f['is_number']) {
                $phpType[] = "        '{$f['name']}' => 'integer',";
            }
        }
        $appendCode = '';
        if ($append) {
            $list = implode("', '", $append);
            $appendCode = "    /** 派生字段（列表直接展示） */\n    protected \$append = ['{$list}'];\n\n";
            foreach ($append as $name) {
                $getter = $this->studly($name);
                $appendCode .= "    public function get{$getter}TextAttr(\$value, array \$data = []): string\n    {\n"
                    . "        return (int) (\$data['{$name}'] ?? 0) === 1 ? '是' : '否';\n    }\n\n";
            }
        }
        $typeCode = $phpType ? "    protected \$type = [\n" . implode("\n", $phpType) . "\n    ];\n\n" : '';

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace app\\admin\\model;

use think\\Model;

/**
 * {$m['title']} {$m['table']}
 * 由代码生成器自动生成（{$m['date']}），可按需调整
 */
class {$m['model']} extends Model
{
    protected \$name = '{$m['model']}';
    protected \$pk = 'id';
    protected \$autoWriteTimestamp = 'datetime';

{$typeCode}{$appendCode}}

PHP;
        return [
            'path' => "backend/app/admin/model/{$m['model']}.php",
            'lang' => 'php',
            'write' => true,
            'content' => $content,
        ];
    }

    protected function buildDao(array $m, array $fields): array
    {
        $search = [];
        foreach ($fields as $f) {
            if ($f['search'] && !$f['system']) {
                $search[] = "        '{$f['name']}' => '{$f['search_op']}',";
            }
        }
        $searchCode = $search ? "\n" . implode("\n", $search) . "\n    " : '';

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace app\\admin\\dao;

use app\\common\\dao\\BaseDao;

/**
 * {$m['title']} Dao
 */
class {$m['model']}Dao extends BaseDao
{
    protected array \$search = [{$searchCode}];

    protected string \$order = 'id desc';

    /** 是否需要按部门/创建人做数据权限过滤 */
    protected bool \$dataScope = true;
    protected string \$scopeDeptField = 'dept_id';
    protected string \$scopeUserField = 'create_by';

    protected function setModel(): string
    {
        return \\app\\admin\\model\\{$m['model']}::class;
    }
}

PHP;
        return [
            'path' => "backend/app/admin/dao/{$m['model']}Dao.php",
            'lang' => 'php',
            'write' => true,
            'content' => $content,
        ];
    }

    protected function buildService(array $m, array $fields): array
    {
        $allow = [];
        $required = [];
        $hasDept = false;
        foreach ($fields as $f) {
            if ($f['system'] || $f['pk']) {
                if ($f['name'] === 'dept_id') {
                    $hasDept = true;
                }
                continue;
            }
            $allow[] = $f['name'];
            if ($f['required']) {
                $required[] = "'{$f['name']}'";
            }
        }
        if ($hasDept && !in_array('dept_id', $allow, true)) {
            $allow[] = 'dept_id';
        }
        $allowCode = "'" . implode("', '", $allow) . "'";
        $reqCode = $required ? '[' . implode(', ', $required) . ']' : '[]';

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace app\\admin\\service;

use app\\admin\\dao\\{$m['model']}Dao;
use app\\common\\service\\AbstractService;

/**
 * {$m['title']} 服务
 */
class {$m['model']}Service extends AbstractService
{
    protected array \$allowFields = [{$allowCode}];
    protected array \$required = {$reqCode};

    public function __construct()
    {
        \$this->dao = {$m['model']}Dao::getInstance();
    }

    protected function beforeCreate(array &\$data): void
    {
        \$user = request()->user ?? [];
        \$data['create_by'] = (int) (\$user['id'] ?? 0);
        if (empty(\$data['dept_id'])) {
            \$data['dept_id'] = (int) (\$user['dept_id'] ?? 0);
        }
    }
}

PHP;
        return [
            'path' => "backend/app/admin/service/{$m['model']}Service.php",
            'lang' => 'php',
            'write' => true,
            'content' => $content,
        ];
    }

    protected function buildController(array $m, array $fields): array
    {
        $ns = $m['namespace'];
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace {$ns};

use app\\admin\\controller\\CrudController;
use app\\admin\\service\\{$m['model']}Service;
use think\\App;

/**
 * {$m['title']}
 * 继承 CrudController 即具备 list / read / save / delete / status
 */
class {$m['model']} extends CrudController
{
    public function __construct(App \$app)
    {
        parent::__construct(\$app);
        \$this->service = {$m['model']}Service::getInstance();
    }
}

PHP;
        return [
            'path' => "backend/app/admin/controller/{$m['pkg']}/{$m['model']}.php",
            'lang' => 'php',
            'write' => true,
            'content' => $content,
        ];
    }

    protected function buildVue(array $m, array $fields): array
    {
        $listCols = [];
        $formItems = [];
        $searchItems = [];
        $formDefaults = [];
        $rules = [];
        $apiName = lcfirst($m['model']);

        foreach ($fields as $f) {
            $label = $f['comment'];
            if ($f['pk']) {
                $listCols[] = "      <el-table-column prop=\"id\" label=\"ID\" width=\"60\" />";
                continue;
            }
            if (in_array($f['name'], ['create_time', 'update_time'], true)) {
                $listCols[] = "      <el-table-column prop=\"create_time\" label=\"创建时间\" width=\"170\" />";
                continue;
            }
            if (in_array($f['name'], ['create_by', 'dept_id'], true)) {
                continue;
            }

            // 列表列
            if ($f['is_switch']) {
                $getter = $f['name'] . '_text';
                $listCols[] = "      <el-table-column label=\"{$label}\" width=\"90\">\n"
                    . "        <template #default=\"{ row }\">\n"
                    . "          <el-tag :type=\"row.{$f['name']} === 1 ? 'success' : 'info'\" size=\"small\">{{ row.{$getter} ?? row.{$f['name']} }}</el-tag>\n"
                    . "        </template>\n"
                    . "      </el-table-column>";
            } elseif ($f['is_text']) {
                $listCols[] = "      <el-table-column prop=\"{$f['name']}\" label=\"{$label}\" min-width=\"180\" show-overflow-tooltip />";
            } else {
                $listCols[] = "      <el-table-column prop=\"{$f['name']}\" label=\"{$label}\" min-width=\"120\" show-overflow-tooltip />";
            }

            // 搜索项
            if ($f['search']) {
                if ($f['search_op'] === 'like') {
                    $searchItems[] = "      <el-input v-model=\"query.{$f['name']}\" placeholder=\"{$label}\" clearable style=\"width: 160px\" @keyup.enter=\"load\" />";
                } elseif ($f['search_op'] === 'time') {
                    $searchItems[] = "      <el-date-picker v-model=\"query.{$f['name']}\" type=\"daterange\" value-format=\"YYYY-MM-DD\" start-placeholder=\"开始\" end-placeholder=\"结束\" style=\"width: 240px\" />";
                } elseif ($f['is_switch']) {
                    $searchItems[] = "      <el-select v-model=\"query.{$f['name']}\" placeholder=\"{$label}\" clearable style=\"width: 120px\">\n"
                        . "        <el-option label=\"是\" :value=\"1\" />\n"
                        . "        <el-option label=\"否\" :value=\"0\" />\n"
                        . "      </el-select>";
                } else {
                    $searchItems[] = "      <el-input v-model=\"query.{$f['name']}\" placeholder=\"{$label}\" clearable style=\"width: 160px\" @keyup.enter=\"load\" />";
                }
            }

            // 表单项
            $control = match (true) {
                $f['is_switch'] => "          <el-switch v-model=\"form.{$f['name']}\" :active-value=\"1\" :inactive-value=\"0\" />",
                $f['is_text'], $f['control'] === 'textarea' => "          <el-input v-model=\"form.{$f['name']}\" type=\"textarea\" :rows=\"4\" />",
                $f['is_number'] => "          <el-input-number v-model=\"form.{$f['name']}\" :min=\"0\" />",
                $f['is_time'] => "          <el-date-picker v-model=\"form.{$f['name']}\" type=\"datetime\" value-format=\"YYYY-MM-DD HH:mm:ss\" />",
                default => "          <el-input v-model=\"form.{$f['name']}\" />",
            };
            $prop = $f['required'] ? " prop=\"{$f['name']}\"" : '';
            $formItems[] = "        <el-form-item label=\"{$label}\"{$prop}>\n{$control}\n        </el-form-item>";

            // 默认值
            $default = match (true) {
                $f['is_switch'] => 0,
                $f['is_number'] => 0,
                default => '',
            };
            $formDefaults[] = $default === '' ? "{$f['name']}: ''" : "{$f['name']}: {$default}";

            if ($f['required']) {
                $rules[] = "  {$f['name']}: [{ required: true, message: '请输入{$label}', trigger: 'blur' }],";
            }
        }

        $listCode = implode("\n", $listCols);
        $searchCode = implode("\n", $searchItems);
        $formCode = implode("\n", $formItems);
        $defaults = implode(', ', array_merge(['id: null'], $formDefaults));
        $rulesCode = $rules ? "\n" . implode("\n", $rules) . "\n" : '';
        $perm = $m['perms'];

        $content = <<<'VUE'
<template>
  <div class="page-card">
    <div class="search-bar">
__SEARCH__
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
      <el-button v-if="hasPerm('__PERM__:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
__LIST__
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('__PERM__:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('__PERM__:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @change="load"
      />
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑' : '新增'" width="600px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
__FORM__
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus, Refresh } from '@element-plus/icons-vue'
import { __API__List, __API__Save, __API__Delete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10 })
const list = ref([])
const total = ref(0)
const loading = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ __DEFAULTS__ })
const rules = {__RULES__}

async function load() {
  loading.value = true
  try {
    const data = await __API__List(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function resetQuery() {
  Object.assign(query, { page: 1 })
  load()
}

function openDialog(row) {
  dialogVisible.value = true
  Object.assign(form, { __DEFAULTS__ })
  if (row) Object.assign(form, row)
}

async function handleSave() {
  await formRef.value.validate()
  await __API__Save(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handleDelete(row) {
  await ElMessageBox.confirm('确认删除这条数据吗？', '提示', { type: 'warning' })
  await __API__Delete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(load)
</script>
VUE;

        $content = str_replace(
            ['__SEARCH__', '__LIST__', '__FORM__', '__DEFAULTS__', '__RULES__', '__PERM__', '__API__'],
            [$searchCode, $listCode, $formCode, $defaults, $rulesCode, $perm, $apiName],
            $content
        );

        return [
            'path' => "frontend/src/views/{$m['module']}/{$m['page']}/index.vue",
            'lang' => 'vue',
            'write' => true,
            'content' => $content,
        ];
    }

    protected function buildMenuSql(array $m): array
    {
        $perm = $m['perms'];
        $sql = <<<SQL
-- {$m['title']} 菜单与按钮权限（可重复执行）
-- 父级菜单：{$m['parentMenu']}
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '{$m['title']}', 'C', '{$perm}:list', '{$m['page']}', '{$m['module']}/{$m['page']}/index', '{$m['icon']}', 10, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '{$m['parentMenu']}' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = '{$perm}:list');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`)
SELECT c.id, '{$m['title']}新增/编辑', 'F', '{$perm}:save', 1, 1, 1
FROM `sdccc_sys_menu` c
WHERE c.`perms` = '{$perm}:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = '{$perm}:save');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`)
SELECT c.id, '{$m['title']}删除', 'F', '{$perm}:delete', 2, 1, 1
FROM `sdccc_sys_menu` c
WHERE c.`perms` = '{$perm}:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = '{$perm}:delete');

-- 授权给超级管理员角色
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('{$perm}:list', '{$perm}:save', '{$perm}:delete')
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id);
SQL;

        return [
            'path' => "sql/gen_menu_{$m['short']}.sql",
            'lang' => 'sql',
            'write' => true,
            'content' => $sql . "\n",
        ];
    }

    protected function buildApiSnippet(array $m): array
    {
        $api = lcfirst($m['model']);
        $route = $m['route'];
        $snippet = <<<JS

// ---------- {$m['title']}（代码生成） ----------
export const {$api}List = (params) => request.get('/admin/{$route}/list', { params })
export const {$api}Read = (id) => request.get(`/admin/{$route}/read/\${id}`)
export const {$api}Save = (data) => request.post('/admin/{$route}/save', data)
export const {$api}Delete = (ids) => request.post('/admin/{$route}/delete', { ids })
JS;
        return [
            'path' => "frontend/src/api/index.js",
            'lang' => 'js',
            'write' => true,
            'append' => true,
            'marker' => "/admin/{$route}/list",
            'content' => $snippet . "\n",
        ];
    }

    protected function buildRouteSnippet(array $m): array
    {
        $route = $m['route'];
        $ctrl = "{$m['pkg']}.{$m['model']}";
        $snippet = <<<PHP

    // ---------- {$m['title']}（代码生成） ----------
    Route::get('{$route}/list', '{$ctrl}/index');
    Route::get('{$route}/read/:id', '{$ctrl}/read');
    Route::post('{$route}/save', '{$ctrl}/save');
    Route::post('{$route}/delete', '{$ctrl}/delete');
    Route::post('{$route}/status', '{$ctrl}/status');
PHP;
        return [
            'path' => "backend/app/admin/route/app.php",
            'lang' => 'php',
            'write' => true,
            'append' => true,
            'marker' => "{$route}/list",
            'content' => $snippet . "\n",
        ];
    }
}
