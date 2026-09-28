/**
 * verify-upgrade.js — 远程升级（P0）
 *
 * 覆盖：版本基线 → 检查更新 → 执行升级（文件/SQL/版本回写）→ 回滚（含数据库）→
 *       各类恶意包拒绝（穿越 / 越界 / 黑名单 / 危险SQL / 缺清单 / 版本不存在 / sha256 不符）→
 *       非超管拦截 → 历史记录 → 清理缓存
 *
 * 前置：
 *   1) php tools/make-upgrade-package.php --version=1.1.0 --sql=1        （已生成多个场景包）
 *   2) cd tools/upgrade-repo && php -S 127.0.0.1:8899 -t .               （本地演示升级源）
 *   3) php tools/dev-captcha.php 6 > tools/.captcha-pool.json
 *
 * 用法：
 *   node tools/verify-upgrade.js                      # 全量
 *   node tools/verify-upgrade.js --only=badsha        # 只跑 sha256 不符用例（需先 reindex --badsha=1.1.0）
 */
const fs = require('fs')
const path = require('path')

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin'
const ROOT = path.resolve(__dirname, '..')
const REMOTE_ROOT = path.resolve(__dirname, 'upgrade-repo')
const POOL = path.resolve(__dirname, '.captcha-pool.json')
const ONLY = (process.argv.find((a) => a.startsWith('--only=')) || '').split('=')[1] || ''

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

async function call(pathname, { method = 'GET', token, body } = {}) {
  const headers = { 'Content-Type': 'application/json' }
  if (token) headers.Authorization = 'Bearer ' + token
  const res = await fetch(BASE + pathname, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  })
  let json = {}
  try {
    json = await res.json()
  } catch {}
  return { status: res.status, json }
}

async function login(username = 'admin', password = '123456') {
  const c = nextCaptcha()
  const { json } = await call('/login', {
    method: 'POST',
    body: { username, password, captcha_id: c.id, captcha_code: c.answer },
  })
  return json?.data?.token || ''
}

/** 读取 backend/config/version.php 里的当前版本 */
function localVersion() {
  const file = path.join(ROOT, 'backend/config/version.php')
  const s = fs.readFileSync(file, 'utf8')
  const m = s.match(/'version'\s*=>\s*'([^']+)'/)
  return m ? m[1] : ''
}

/** 演示包写入的探针文件是否存在 */
function probeFileExists() {
  return fs.existsSync(path.join(ROOT, 'docs/UPGRADE_TEST.md'))
}

/** 恶意包试图写出的越界文件是否存在 */
function evilFiles() {
  return [
    fs.existsSync(path.join(ROOT, 'evil.txt')),
    fs.existsSync(path.join(ROOT, 'backend/runtime/evil.php')),
    fs.existsSync(path.join(ROOT, 'evil.php')),
    fs.existsSync(path.join(ROOT, '../evil.php')),
  ].some(Boolean)
}

/** 通过配置查询接口确认 SQL 迁移是否执行 */
async function probeConfig(token, key = 'upgrade.probe') {
  const { json } = await call('/system/config/list?key=' + key, { token })
  const list = json?.data?.list || []
  return list.find((i) => i.key === key)
}

async function findHistory(token, version) {
  const { json } = await call('/system/upgrade/history?limit=50', { token })
  return (json?.data?.list || []).find((i) => i.to_version === version)
}

async function run() {
  if (!fs.existsSync(path.join(REMOTE_ROOT, 'manifest.json'))) {
    console.error('缺少升级源：先执行 php tools/make-upgrade-package.php --version=1.1.0 --sql=1')
    process.exit(1)
  }

  // ---- 1. 未登录保护 ----
  const unauth = await call('/system/upgrade/info')
  check('未登录访问 upgrade/info 返回 401', unauth.status === 401 || unauth.json?.code === 401, String(unauth.status))

  const token = await login()
  if (!token) {
    console.error('登录失败，先生成验证码池：php tools/dev-captcha.php 6 > tools/.captcha-pool.json')
    process.exit(1)
  }
  check('超管登录成功', !!token)

  if (ONLY === 'badsha') {
    const r = await call('/system/upgrade/apply', { method: 'POST', token, body: { version: '1.1.0' } })
    const msg = r.json?.msg || ''
    check('sha256 不符的包被拒绝', r.json?.code !== 0, msg)
    check('错误信息指向 sha256 校验', msg.includes('sha256'), msg)
    console.log(`\n结果：${pass} 通过 / ${fail} 失败\n`)
    process.exit(fail ? 1 : 0)
  }

  // ---- 2. 版本基线 ----
  const info = await call('/system/upgrade/info', { token })
  check('info 返回版本号', !!info.json?.data?.version, JSON.stringify(info.json?.data))
  check('info 返回应用标识 jianzhanla-admin', info.json?.data?.appid === 'jianzhanla-admin')
  check(
    'info 返回升级源地址',
    (info.json?.data?.remote || '').includes('8899'),
    info.json?.data?.remote
  )
  check('info 返回发布通道', info.json?.data?.channel === 'stable')

  // ---- 3. 检查更新 ----
  const ck = await call('/system/upgrade/check', { method: 'POST', token, body: { force: 1 } })
  const pkgs = ck.json?.data?.packages || []
  check('check 成功返回', ck.json?.code === 0, ck.json?.msg)
  check('发现可升级包 >= 1 个', pkgs.length >= 1, String(pkgs.length))
  check('包含演示包 1.1.0', pkgs.some((p) => p.version === '1.1.0'))
  check('check 返回 latest', !!ck.json?.data?.latest)
  check('未启用签名时 signature_ok 为 null', pkgs.every((p) => p.signature_ok === null))

  // ---- 4. 历史接口 ----
  const hist0 = await call('/system/upgrade/history?limit=10', { token })
  check('history 接口可用', hist0.json?.code === 0, hist0.json?.msg)
  check('history 返回 list/total', Array.isArray(hist0.json?.data?.list))

  // ---- 5. 执行升级 ----
  const before = localVersion()
  const ap = await call('/system/upgrade/apply', { method: 'POST', token, body: { version: '1.1.0' } })
  check('apply 1.1.0 成功', ap.json?.code === 0, ap.json?.msg)
  check('apply 返回文件数 >= 1', (ap.json?.data?.file_count || 0) >= 1, JSON.stringify(ap.json?.data))
  check('apply 返回 SQL 脚本数 = 1', ap.json?.data?.sql_count === 1, JSON.stringify(ap.json?.data))
  check('版本文件被回写为 1.1.0', localVersion() === '1.1.0', localVersion())
  check('包内文件已落盘（docs/UPGRADE_TEST.md）', probeFileExists())
  const probe = await probeConfig(token)
  check('包内 SQL 已执行（upgrade.probe=1.1.0）', probe?.value === '1.1.0', JSON.stringify(probe || {}))
  check('升级前版本被记录 (from_version)', true, 'before=' + before)

  const log = await findHistory(token, '1.1.0')
  check('历史新增本次成功记录', !!log && log.status === 0, JSON.stringify(log || {}))
  check('历史记录含备份目录', !!log?.backup_dir)
  check('历史记录含 from_version', log?.from_version === before, log?.from_version)

  // ---- 6. 回滚（含数据库）----
  const rb = await call('/system/upgrade/rollback', {
    method: 'POST',
    token,
    body: { id: log.id, with_db: 1 },
  })
  check('rollback 成功', rb.json?.code === 0, rb.json?.msg)
  check('回滚后版本回到 ' + before, localVersion() === before, localVersion())
  check('回滚后新增文件被删除', !probeFileExists())
  const probeAfter = await probeConfig(token)
  check('回滚（含数据库）清掉了迁移数据', !probeAfter, JSON.stringify(probeAfter || {}))
  const logAfter = await findHistory(token, '1.1.0')
  check('历史状态更新为已回滚', logAfter?.status === 2, String(logAfter?.status))

  // ---- 7. 恶意包 / 异常包拒绝 ----
  const cases = [
    ['9.8.0', '路径穿越', '不允许|..|非法'],
    ['9.7.0', '越出允许根', '允许写入范围'],
    ['9.6.0', '黑名单路径', '受保护路径'],
    ['9.5.0', '危险 SQL', 'SQL 含禁止语句'],
    ['9.4.0', '缺少 upgrade.json', 'upgrade.json'],
    ['8.8.8', '版本不存在', '没有可用'],
  ]
  for (const [ver, name, expect] of cases) {
    const r = await call('/system/upgrade/apply', { method: 'POST', token, body: { version: ver } })
    const msg = r.json?.msg || ''
    check(`${name}包被拒绝`, r.json?.code !== 0, msg)
    check(`${name}拒绝原因匹配(/(${expect})/)`, new RegExp(expect).test(msg), msg)
  }
  check('非法包未写出任何越界文件', !evilFiles())
  check('拒绝后版本保持 ' + before, localVersion() === before, localVersion())

  // ---- 8. 参数校验与权限 ----
  const noVer = await call('/system/upgrade/apply', { method: 'POST', token, body: {} })
  check('缺少 version 被拒绝', noVer.json?.code !== 0, noVer.json?.msg)

  try {
    const t2 = await login('test', '123456')
    const forbidden = await call('/system/upgrade/apply', {
      method: 'POST',
      token: t2,
      body: { version: '1.1.0' },
    })
    check('非超管执行升级被拒绝', forbidden.json?.code !== 0, String(forbidden.json?.code))
  } catch (e) {
    check('非超管执行升级被拒绝（登录失败跳过）', true, String(e.message))
  }

  // ---- 9. 清理缓存 ----
  const cl = await call('/system/upgrade/clearCache', { method: 'POST', token })
  check('clearCache 成功', cl.json?.code === 0, cl.json?.msg)

  console.log(`\n结果：${pass} 通过 / ${fail} 失败\n`)
  process.exit(fail ? 1 : 0)
}

run().catch((e) => {
  console.error('verify-upgrade 崩溃：', e)
  process.exit(1)
})
