/**
 * verify-plugin.js — 插件管理模块
 *
 * 覆盖：插件发现 → 安装（建菜单/拷前端页面）→ 插件接口 → 配置读写
 *       → 钩子生效（登录事件）→ 禁用拦截 → 启用 → 卸载（清菜单/删页面）
 * 前置：php tools/dev-captcha.php 4 > tools/.captcha-pool.json
 */
const fs = require('fs')
const path = require('path')

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin'
const POOL = path.resolve(__dirname, '.captcha-pool.json')
const FRONT_VIEWS = path.resolve(__dirname, '../frontend/src/views/plugin')

let pass = 0
let fail = 0
function check(name, ok, detail = '') {
  if (ok) {
    pass++
    console.log(`  ✓ ${name}`)
  } else {
    fail++
    console.log(`  ✗ ${name}${detail ? '  → ' + detail : ''}`)
  }
}

const captchas = (() => {
  try {
    const p = JSON.parse(fs.readFileSync(POOL, 'utf8'))
    return Array.isArray(p) ? p : [p]
  } catch {
    return []
  }
})()
function nextCaptcha() {
  return captchas.pop() || { id: '', answer: '' }
}

async function request(pathname, options = {}, token = '') {
  const res = await fetch(BASE + pathname, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: 'Bearer ' + token } : {}),
      ...(options.headers || {}),
    },
  })
  return res.json().catch(() => ({}))
}

async function login(username = 'admin', password = '123456') {
  const c = nextCaptcha()
  const json = await request('/login', {
    method: 'POST',
    body: JSON.stringify({
      username,
      password,
      captcha_id: c.id,
      captcha_code: c.answer ?? c.code ?? c.text,
    }),
  })
  return json?.data?.token || ''
}

const get = (token, p) => request(p, {}, token)
const post = (token, p, body = {}) => request(p, { method: 'POST', body: JSON.stringify(body) }, token)

function exists(p) {
  try {
    fs.accessSync(p)
    return true
  } catch {
    return false
  }
}

;(async () => {
  console.log('\n=== 插件管理模块验证 ===\n')

  const token = await login()
  check('管理员登录成功', !!token)
  if (!token) {
    console.log('\n结果：无法登录，请先生成验证码池\n')
    process.exit(1)
  }

  // ---------- 1. 发现 ----------
  const listRes = await get(token, '/system/plugin/list')
  const list = listRes?.data?.list || []
  const overview = listRes?.data?.overview || {}
  check('插件列表返回 2 个内置插件', list.length === 2, JSON.stringify(list.map((i) => i.name)))
  check('发现 hello 插件', list.some((i) => i.name === 'hello'))
  check('发现 login-notify 插件', list.some((i) => i.name === 'login-notify'))
  check('插件默认未安装', list.every((i) => i.installed === false), JSON.stringify(list.map((i) => [i.name, i.status])))
  check('清单含菜单声明', (list.find((i) => i.name === 'hello')?.menus || []).length === 1)
  check('清单含配置 schema', (list.find((i) => i.name === 'hello')?.config || []).length >= 3)
  check('概览统计正确', overview.total === 2 && overview.installed === 0, JSON.stringify(overview))

  // ---------- 2. 安装 hello ----------
  const installRes = await post(token, '/system/plugin/install', { name: 'hello' })
  check('安装 hello 成功', installRes?.code === 0, installRes?.msg)
  check('安装后状态为启用', installRes?.data?.status === 1, JSON.stringify(installRes?.data))

  const afterInstall = await get(token, '/system/plugin/list')
  const hello = (afterInstall?.data?.list || []).find((i) => i.name === 'hello') || {}
  check('列表中 hello 已安装', hello.installed === true && hello.status === 1)
  check('前端页面已拷贝到 views/plugin/hello', exists(path.join(FRONT_VIEWS, 'hello/index.vue')))

  // 菜单是否创建
  const menus = await get(token, '/system/menu/list')
  const flat = []
  const walk = (arr) => arr.forEach((m) => { flat.push(m); if (m.children) walk(m.children) })
  walk(menus?.data?.list || [])
  check('插件菜单「Hello 插件」已注册', flat.some((m) => m.perms === 'plugin:hello:index'), flat.map((m) => m.perms).join(','))
  const helloMenu = flat.find((m) => m.perms === 'plugin:hello:index') || {}
  const parent = flat.find((m) => m.id === helloMenu.parent_id) || {}
  check('插件菜单挂在「插件管理」目录下', parent.perms === 'plugin:manage', JSON.stringify(parent.perms))

  // ---------- 3. 插件接口 ----------
  const helloIndex = await get(token, '/plugin/hello/index')
  check('插件接口 plugin/hello/index 可访问', helloIndex?.code === 0, helloIndex?.msg)
  check('插件接口返回版本与配置', helloIndex?.data?.version === '1.0.0' && !!helloIndex?.data?.config, JSON.stringify(helloIndex?.data)?.slice(0, 120))

  const greet = await get(token, '/plugin/hello/greet?name=admin')
  check('greet 接口返回问候语', typeof greet?.data?.text === 'string' && greet.data.text.includes('admin'), JSON.stringify(greet?.data?.text))
  check('greet 默认展示时间', !!greet?.data?.time)

  // ---------- 4. 配置 ----------
  const confRes = await get(token, '/system/plugin/config?name=hello')
  const schema = confRes?.data?.schema || []
  check('配置 schema 可读', schema.length >= 3, JSON.stringify(schema.map((i) => i.key)))
  const saveRes = await post(token, '/system/plugin/saveConfig', {
    name: 'hello',
    config: { greeting: '插件配置已生效', show_time: 0, theme: 'green', max_items: 3 },
  })
  check('配置保存成功', saveRes?.code === 0, saveRes?.msg)
  check('number 类型被转换', saveRes?.data?.max_items === 3, JSON.stringify(saveRes?.data))
  const greet2 = await get(token, '/plugin/hello/greet?name=admin')
  check('配置生效到插件接口', greet2?.data?.text?.includes('插件配置已生效') && greet2?.data?.theme === 'green', JSON.stringify(greet2?.data))
  check('关闭开关后不返回时间', greet2?.data?.time === '')
  check('条目上限生效', (greet2?.data?.items || []).length === 3)

  // 必填校验
  const badConf = await post(token, '/system/plugin/saveConfig', { name: 'hello', config: { greeting: '' } })
  check('必填项为空被拒绝', badConf?.code !== 0, JSON.stringify(badConf?.msg))

  // ---------- 5. 钩子 ----------
  await post(token, '/system/plugin/install', { name: 'login-notify' })
  const lnInstalled = ((await get(token, '/system/plugin/list'))?.data?.list || []).find((i) => i.name === 'login-notify')
  check('安装 login-notify 成功', lnInstalled?.installed === true)

  const before = await get(token, '/plugin/login-notify/stats')
  const token2 = await login()
  check('再次登录成功（触发登录钩子）', !!token2)
  const after = await get(token, '/plugin/login-notify/stats')
  check('登录成功事件被插件记录', (after?.data?.total || 0) === (before?.data?.total || 0) + 1, `${before?.data?.total} → ${after?.data?.total}`)

  const failLogin = await request('/login', {
    method: 'POST',
    body: JSON.stringify({ username: 'admin', password: 'wrong-password', captcha_id: '', captcha_code: '' }),
  })
  check('错误密码登录被拒绝', failLogin?.code !== 0, JSON.stringify(failLogin?.msg))
  const afterFail = await get(token, '/plugin/login-notify/stats')
  check('登录失败事件被插件记录', (afterFail?.data?.fail || 0) === (after?.data?.fail || 0) + 1, `${after?.data?.fail} → ${afterFail?.data?.fail}`)

  const recent = await get(token, '/plugin/login-notify/recent?limit=5')
  check('最近动态可查询', (recent?.data || []).length >= 2, JSON.stringify(recent?.data?.length))

  const hooksRes = await get(token, '/system/plugin/hooks')
  const hooks = hooksRes?.data || []
  check('钩子注册可见', hooks.some((h) => h.event === 'user.login.success'), JSON.stringify(hooks))

  // ---------- 6. 禁用 / 启用 ----------
  const disableRes = await post(token, '/system/plugin/disable', { name: 'hello' })
  check('禁用 hello 成功', disableRes?.code === 0)
  const disabledCall = await get(token, '/plugin/hello/index')
  check('禁用后插件接口不可访问', disabledCall?.code === 404, JSON.stringify(disabledCall?.code))
  const enableRes = await post(token, '/system/plugin/enable', { name: 'hello' })
  check('启用 hello 成功', enableRes?.code === 0)
  const enabledCall = await get(token, '/plugin/hello/index')
  check('启用后插件接口恢复', enabledCall?.code === 0)

  // ---------- 7. 卸载 ----------
  const uninstallRes = await post(token, '/system/plugin/uninstall', { name: 'hello' })
  check('卸载 hello 成功', uninstallRes?.code === 0, uninstallRes?.msg)
  const afterUninstall = await get(token, '/system/plugin/list')
  const hello2 = (afterUninstall?.data?.list || []).find((i) => i.name === 'hello') || {}
  check('卸载后状态回到未安装', hello2.installed === false && hello2.status === 0)
  const menus2 = await get(token, '/system/menu/list')
  const flat2 = []
  const walk2 = (arr) => arr.forEach((m) => { flat2.push(m); if (m.children) walk2(m.children) })
  walk2(menus2?.data?.list || [])
  check('插件菜单已清理', !flat2.some((m) => m.perms === 'plugin:hello:index'))
  check('前端页面已清理', !exists(path.join(FRONT_VIEWS, 'hello/index.vue')))
  const uninstalledCall = await get(token, '/plugin/hello/index')
  check('卸载后插件接口不可访问', uninstalledCall?.code === 404)

  // 重复安装校验
  const dup = await post(token, '/system/plugin/install', { name: 'login-notify' })
  check('重复安装被拒绝', dup?.code !== 0, JSON.stringify(dup?.msg))
  const force = await post(token, '/system/plugin/install', { name: 'login-notify', force: 1 })
  check('强制重装可用', force?.code === 0, force?.msg)

  // 非法插件名
  const bad = await post(token, '/system/plugin/install', { name: '../evil' })
  check('非法插件名被拒绝', bad?.code !== 0, JSON.stringify(bad?.msg))

  // 清理：恢复初始状态
  await post(token, '/system/plugin/uninstall', { name: 'login-notify' })

  console.log(`\n结果：${pass} 通过 / ${fail} 失败\n`)
  process.exit(fail > 0 ? 1 : 0)
})()
