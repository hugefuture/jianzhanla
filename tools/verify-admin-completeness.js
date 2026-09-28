/**
 * 后台功能完整性自检
 *
 * 用途：校验「菜单是否齐全 + 各模块接口是否可用」，用于排查
 *       “数据库被回滚/覆盖导致菜单与功能缺失” 这类问题。
 *
 * 前置：php tools/dev-captcha.php 5 > tools/.captcha-pool.json
 * 运行：node tools/verify-admin-completeness.js
 *
 * 若菜单缺失，按依赖顺序重放迁移即可恢复：
 *   php tools/db-import.php sql/migrate_p0.sql sql/migrate_notice.sql sql/migrate_file.sql \
 *       sql/seed_gen_menu.sql sql/seed_online_menu.sql sql/seed_monitor_menu.sql \
 *       sql/migrate_task_log.sql sql/migrate_plugin.sql sql/migrate_upgrade.sql
 */
const fs = require('fs')
const path = require('path')

const BASE = 'http://127.0.0.1:8000/admin'
const poolFile = path.resolve(__dirname, '.captcha-pool.json')
let pass = 0, fail = 0

const ok = (name, cond, extra = '') => {
  if (cond) { pass++; console.log(`  ok  ${name}`) }
  else { fail++; console.log(`  ✗   ${name} ${extra}`) }
}

// 期望存在的菜单（后台功能全量口径）
const WANT_MENU = [
  '用户管理', '角色管理', '菜单管理', '部门管理', '岗位管理', '字典管理',
  '参数设置', '文件管理', '日志管理', '系统升级',
  '在线用户', '服务器监控', '任务日志',
  '通知公告', '代码生成', '插件管理', '插件列表',
]

// 期望可用的接口（GET 为主，均为列表类，不会写脏数据）
const WANT_API = [
  ['仪表盘', '/index/stats'],
  ['用户列表', '/system/user/list'],
  ['角色列表', '/system/role/list'],
  ['菜单列表', '/system/menu/list'],
  ['部门列表', '/system/dept/list'],
  ['岗位列表', '/system/post/list'],
  ['字典列表', '/system/dict/list'],
  ['参数列表', '/system/config/list'],
  ['文件列表', '/system/file/list'],
  ['操作日志', '/system/log/oper'],
  ['登录日志', '/system/log/login'],
  ['在线用户', '/system/online/list'],
  ['系统监控', '/system/monitor/index'],
  ['任务日志', '/system/monitor/tasklog'],
  ['公告列表', '/biz/notice/list'],
  ['代码生成-表列表', '/tool/gen/tables'],
  ['插件列表', '/system/plugin/list'],
  ['升级信息', '/system/upgrade/info'],
  ['升级历史', '/system/upgrade/history'],
]

async function login(username = 'admin', password = '123456') {
  const caps = JSON.parse(fs.readFileSync(poolFile, 'utf8'))
  const c = caps.pop()
  const r = await fetch(BASE + '/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password, captcha_id: c.id, captcha_code: c.answer }),
  })
  return (await r.json())?.data?.token
}

;(async () => {
  const token = await login()
  if (!token) {
    console.log('登录失败：请确认后端在运行且验证码池已刷新（见文件头注释）')
    process.exit(1)
  }
  ok('管理员登录', true)
  const h = { Authorization: 'Bearer ' + token }

  console.log('\n[1/2] 菜单完整性')
  const gi = await (await fetch(BASE + '/getInfo', { headers: h })).json()
  const flat = []
  const walk = (arr) => arr.forEach((m) => { flat.push(m); if (m.children) walk(m.children) })
  walk(gi?.data?.menus || [])
  const names = flat.map((m) => m.name)
  WANT_MENU.forEach((n) => ok('菜单:' + n, names.includes(n)))
  console.log(`  菜单节点总数：${flat.length}`)

  console.log('\n[2/2] 模块接口可用性')
  const get = async (url) =>
    (await fetch(BASE + url, { headers: { ...h, 'Content-Type': 'application/json' } })).json()
  for (const [label, url] of WANT_API) {
    const j = await get(url)
    ok(label, j.code === 0, j.msg ? `(${j.msg})` : '')
  }

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
