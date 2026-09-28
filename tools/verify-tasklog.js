/**
 * verify-tasklog.js — 定时任务执行日志（P1-D）
 *
 * 覆盖：task:run 写日志 → 分页接口 → 概况聚合 → 状态筛选 → 清空
 * 前置：php tools/dev-captcha.php 2 > tools/.captcha-pool.json
 */
const fs = require('fs')
const path = require('path')

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin'
const POOL = path.resolve(__dirname, '.captcha-pool.json')

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

async function login() {
  const c = nextCaptcha()
  const res = await fetch(BASE + '/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      username: 'admin',
      password: '123456',
      captcha_id: c.id,
      captcha_code: c.code ?? c.answer ?? c.text,
    }),
  })
  const json = await res.json()
  return json?.data?.token || ''
}

function get(token, path) {
  return fetch(BASE + path, { headers: { Authorization: 'Bearer ' + token } }).then((r) => r.json())
}
function post(token, path) {
  return fetch(BASE + path, {
    method: 'POST',
    headers: { Authorization: 'Bearer ' + token },
  }).then((r) => r.json())
}

;(async () => {
  console.log('\n=== 定时任务执行日志验证 ===\n')

  const token = await login()
  check('管理员登录成功', !!token)
  if (!token) {
    console.log('\n结果：无法登录，请先生成验证码池\n')
    process.exit(1)
  }

  // 1. 分页接口可用
  const page = await get(token, '/system/monitor/tasklog?page=1&limit=10')
  check('任务日志分页接口返回成功', page.code === 0, JSON.stringify(page).slice(0, 120))
  check('返回 list 数组', Array.isArray(page.data?.list), typeof page.data)
  check('返回 total 数值', typeof page.data?.total === 'number', String(page.data?.total))

  const rows = page.data?.list || []
  check('日志有记录（至少 1 条）', rows.length >= 1, `实际 ${rows.length} 条`)

  if (rows.length) {
    const r = rows[0]
    check('字段齐全：task_name', typeof r.task_name === 'string', JSON.stringify(r).slice(0, 120))
    check('字段齐全：task_desc', typeof r.task_desc === 'string')
    check('字段齐全：status', r.status === 0 || r.status === 1, String(r.status))
    check('字段齐全：cost_ms 非负', typeof r.cost_ms === 'number' && r.cost_ms >= 0, String(r.cost_ms))
    check('字段齐全：create_time', typeof r.create_time === 'string' && r.create_time.length > 0)
  }

  // 2. 概况聚合
  const ov = await get(token, '/system/monitor/taskOverview?days=7')
  check('概况接口返回成功', ov.code === 0, JSON.stringify(ov).slice(0, 120))
  check('概况含 list', Array.isArray(ov.data?.list))
  check('概况含 failCount', typeof ov.data?.failCount === 'number')
  const agg = ov.data?.list || []
  check('按任务聚合成记录', agg.length >= 1, `${agg.length} 个任务`)
  if (agg.length) {
    const t = agg[0]
    check('聚合含 runs/fails/avg_ms', Number.isInteger(t.runs) && Number.isInteger(t.fails) && Number.isInteger(t.avg_ms),
      JSON.stringify(t).slice(0, 140))
    check('聚合含 last_status', t.last_status === 0 || t.last_status === 1)
  }

  // 3. 按任务名筛选
  if (agg.length) {
    const name = agg[0].task_name
    const filtered = await get(token, `/system/monitor/tasklog?page=1&limit=50&task_name=${encodeURIComponent(name)}`)
    const hit = (filtered.data?.list || []).every((r) => r.task_name === name)
    check(`按任务名筛选（${name}）结果一致`, filtered.code === 0 && hit,
      `命中 ${filtered.data?.list?.length} 条`)
  }

  // 4. 按状态筛选
  const okFilter = await get(token, '/system/monitor/tasklog?page=1&limit=50&status=0')
  const okAll = (okFilter.data?.list || []).every((r) => r.status === 0)
  check('按状态筛选（成功）结果一致', okFilter.code === 0 && okAll, `命中 ${okFilter.data?.list?.length} 条`)

  // 5. 权限：未登录不可访问
  const anon = await fetch(BASE + '/system/monitor/tasklog').then((r) => r.json())
  check('未登录访问被拒', anon.code !== 0, JSON.stringify(anon).slice(0, 100))

  // 6. 清空（放在最后，避免影响前面断言）
  const cleared = await post(token, '/system/monitor/clearTasklog')
  check('清空任务日志成功', cleared.code === 0, JSON.stringify(cleared).slice(0, 120))
  const after = await get(token, '/system/monitor/tasklog?page=1&limit=10')
  check('清空后无剩余记录', (after.data?.list || []).length === 0, `剩余 ${after.data?.list?.length} 条`)

  console.log(`\n结果：${pass} 通过 / ${fail} 失败\n`)
  process.exit(fail === 0 ? 0 : 1)
})()
