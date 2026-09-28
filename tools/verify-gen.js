/**
 * CRUD 代码生成器验证脚本
 * 流程：建演示表 -> 预览 -> 生成写入 -> 校验产物 -> 清理还原
 * 前置：php tools/dev-captcha.php 1 > tools/.captcha.json
 */
const BASE = process.env.BASE || 'http://127.0.0.1:8000'
const ROOT = 'C:/Users/Administrator/WorkBuddy/2026-09-27-08-42-40/admin-framework'
let pass = 0, fail = 0
const ok = (n, c, e = '') => { c ? (pass++, console.log('  ✓ ' + n)) : (fail++, console.log('  ✗ ' + n + (e ? ' -> ' + e : ''))) }

async function req(path, { method = 'GET', token, body } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  })
  return res.json()
}

const TABLE = 'sdccc_demo_book'
const DDL = `CREATE TABLE IF NOT EXISTS \`${TABLE}\` (
  \`id\` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  \`title\` VARCHAR(100) NOT NULL COMMENT '书名',
  \`author\` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '作者',
  \`price\` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '价格',
  \`stock\` INT NOT NULL DEFAULT 0 COMMENT '库存',
  \`is_top\` TINYINT NOT NULL DEFAULT 0 COMMENT '是否推荐',
  \`status\` TINYINT NOT NULL DEFAULT 1 COMMENT '状态',
  \`dept_id\` INT NOT NULL DEFAULT 0 COMMENT '归属部门',
  \`remark\` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  \`create_by\` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  \`create_time\` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  \`update_time\` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (\`id\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图书表'`

;(async () => {
  const fs = await import('node:fs')
  const p = await import('node:path')
  const cp = await import('node:child_process')
  const MYSQL = 'D:/phpstudy_pro/Extensions/MySQL5.7.26/bin/mysql.exe'
  const PHP = 'D:/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe'
  const exec = (cmd) => cp.execSync(cmd, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] })
  const sql = (s) => exec(`"${MYSQL}" -h127.0.0.1 -P3306 -uroot -proot sdccc_admin -e "${s.replace(/"/g, '\\"')}" 2>&1`)

  const cap = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha.json'), 'utf8'))
  let r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap.id, captcha_code: cap.answer },
  })
  const token = r.data?.token
  ok('管理员登录', !!token, JSON.stringify(r).slice(0, 120))

  console.log('\n== 1. 表列表与字段解析 ==')
  // 建演示表
  fs.writeFileSync(p.join(process.cwd(), '.demo.sql'), DDL + ';\n')
  exec(`"${MYSQL}" -h127.0.0.1 -P3306 -uroot -proot sdccc_admin < "${p.join(process.cwd(), '.demo.sql').replace(/\//g, '/')}" 2>&1`)

  r = await req('/admin/tool/gen/tables?keyword=demo', { token })
  ok('表列表可按关键字过滤', (r.data || []).some(t => t.name === TABLE), JSON.stringify(r.data || []).slice(0, 160))

  r = await req(`/admin/tool/gen/columns?table=${TABLE}`, { token })
  const fields = r.data || []
  const fTitle = fields.find(f => f.name === 'title')
  const fStatus = fields.find(f => f.name === 'status')
  const fCreate = fields.find(f => f.name === 'create_time')
  ok('字段解析：必填识别', fTitle?.required === true, JSON.stringify(fTitle))
  ok('字段解析：开关识别', fStatus?.is_switch === true && fStatus?.search_op === '=', JSON.stringify(fStatus))
  ok('字段解析：时间范围搜索', fCreate?.search_op === 'time', JSON.stringify(fCreate))
  ok('字段解析：系统字段排除', fields.find(f => f.name === 'create_by')?.system === true)

  console.log('\n== 2. 代码预览 ==')
  r = await req(`/admin/tool/gen/preview?table=${TABLE}&pkg=biz&module=business&perm=biz&parentMenu=${encodeURIComponent('业务管理')}`, { token })
  const files = r.data?.files || []
  const meta = r.data?.meta || {}
  ok('预览返回文件列表', files.length >= 8, String(files.length))
  const paths = files.map(f => f.path)
  ok('Model 路径正确', paths.some(x => x.endsWith('backend/app/admin/model/DemoBook.php')), paths.join(' | ').slice(0, 200))
  ok('Dao 路径正确', paths.some(x => x.endsWith('backend/app/admin/dao/DemoBookDao.php')))
  ok('Service 路径正确', paths.some(x => x.endsWith('backend/app/admin/service/DemoBookService.php')))
  ok('Controller 路径正确', paths.some(x => x.endsWith('backend/app/admin/controller/biz/DemoBook.php')))
  ok('前端页面路径正确', paths.some(x => x.endsWith('frontend/src/views/business/book/index.vue')))
  ok('菜单 SQL 路径正确', paths.some(x => x.endsWith('sql/gen_menu_demo_book.sql')))
  ok('元信息命名正确', meta.model === 'DemoBook' && meta.route === 'biz/book' && meta.perms === 'biz:book', JSON.stringify(meta).slice(0, 200))

  const vue = files.find(f => f.path.endsWith('.vue'))?.content || ''
  ok('Vue 含列表列', vue.includes('el-table-column prop="title"'), vue.slice(0, 120))
  ok('Vue 含搜索项', vue.includes('query.title'))
  ok('Vue 含权限控制', vue.includes("hasPerm('biz:book:save')"))
  const dao = files.find(f => f.path.endsWith('Dao.php'))?.content || ''
  ok('Dao 含搜索映射', dao.includes("'title' => 'like'") && dao.includes("'status' => '='"), dao.slice(0, 160))
  ok('Dao 开启数据权限', dao.includes('protected bool $dataScope = true'))
  const menu = files.find(f => f.path.endsWith('.sql'))?.content || ''
  ok('菜单 SQL 含三个权限点', menu.includes('biz:book:list') && menu.includes('biz:book:save') && menu.includes('biz:book:delete'))

  console.log('\n== 3. 生成写入 ==')
  r = await req('/admin/tool/gen/generate', {
    method: 'POST', token,
    body: { table: TABLE, pkg: 'biz', module: 'business', perm: 'biz', parentMenu: '业务管理' },
  })
  const written = r.data?.files || []
  ok('生成接口返回成功', r.code === 0, JSON.stringify(r).slice(0, 160))
  ok('新文件全部写入', written.filter(f => f.status === 'written').length >= 6, JSON.stringify(written).slice(0, 300))

  const expect = [
    'backend/app/admin/model/DemoBook.php',
    'backend/app/admin/dao/DemoBookDao.php',
    'backend/app/admin/service/DemoBookService.php',
    'backend/app/admin/controller/biz/DemoBook.php',
    'frontend/src/views/business/book/index.vue',
    'sql/gen_menu_demo_book.sql',
  ]
  for (const rel of expect) {
    ok('文件已落盘：' + rel, fs.existsSync(p.join(ROOT, rel)))
  }

  // PHP 语法检查（生成物必须能过 php -l）
  for (const rel of expect.filter(x => x.endsWith('.php'))) {
    let out = ''
    try { out = exec(`"${PHP}" -l "${p.join(ROOT, rel)}" 2>&1`) } catch (e) { out = String(e.stdout || e.message) }
    ok('语法检查通过：' + p.basename(rel), /No syntax errors/.test(out), out.slice(0, 160))
  }

  const routeFile = fs.readFileSync(p.join(ROOT, 'backend/app/admin/route/app.php'), 'utf8')
  ok('路由已追加', routeFile.includes("Route::get('biz/book/list'"), routeFile.slice(-200))
  const apiFile = fs.readFileSync(p.join(ROOT, 'frontend/src/api/index.js'), 'utf8')
  ok('前端 API 已追加', apiFile.includes("export const demoBookList"), apiFile.slice(-200))

  console.log('\n== 4. 幂等与 ZIP ==')
  r = await req('/admin/tool/gen/generate', {
    method: 'POST', token,
    body: { table: TABLE, pkg: 'biz', module: 'business', perm: 'biz', parentMenu: '业务管理' },
  })
  const second = r.data?.files || []
  ok('重复生成跳过已存在文件', second.filter(f => f.status === 'skipped').length >= 6, JSON.stringify(second).slice(0, 200))

  r = await req('/admin/tool/gen/zip', {
    method: 'POST', token,
    body: { table: TABLE, pkg: 'biz', module: 'business', perm: 'biz', parentMenu: '业务管理' },
  })
  const zipUrl = r.data?.url || ''
  ok('ZIP 打包返回地址', zipUrl.startsWith('/uploads/gen/'), JSON.stringify(r.data).slice(0, 160))
  ok('ZIP 文件已生成', fs.existsSync(p.join(ROOT, 'backend/public', zipUrl)), zipUrl)

  console.log('\n== 5. 清理还原 ==')
  // 删除生成的文件
  for (const rel of [...expect, 'backend/app/admin/route/app.php', 'frontend/src/api/index.js']) {
    if (rel.endsWith('app.php') || rel.endsWith('index.js')) continue
    try { fs.unlinkSync(p.join(ROOT, rel)) } catch {}
  }
  try { fs.rmSync(p.join(ROOT, 'frontend/src/views/business/book'), { recursive: true }) } catch {}
  try { fs.unlinkSync(p.join(ROOT, 'backend/public', zipUrl)) } catch {}
  // 还原追加型文件
  for (const f of files) {
    if (!f.append) continue
    const full = p.join(ROOT, f.path)
    let txt = fs.readFileSync(full, 'utf8')
    if (txt.includes(f.content.trim())) {
      txt = txt.replace(f.content.trim(), '')
      fs.writeFileSync(full, txt)
    }
  }
  sql(`DROP TABLE IF EXISTS \`${TABLE}\``)
  ok('演示文件与表已清理', !fs.existsSync(p.join(ROOT, 'backend/app/admin/model/DemoBook.php')))
  ok('路由文件已还原', !fs.readFileSync(p.join(ROOT, 'backend/app/admin/route/app.php'), 'utf8').includes("biz/book/list"))

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
