/**
 * verify-api-open.js — api 应用公开接口（P1-F）
 *
 * 覆盖：匿名可读已发布、草稿/未发布不外泄、分页与筛选、详情、签名开关
 * 前置：无需验证码（公开接口），但要有一条形如「草稿」的未发布公告做负例
 */
const BASE = (process.env.APP_BASE || 'http://127.0.0.1:8000').replace(/\/admin$/, '')

let pass = 0
let fail = 0
function check(name, ok, detail = '') {
  if (ok) {
    pass++
    console.log(`  ✓ ${name}${detail ? '  ' + detail : ''}`)
  } else {
    fail++
    console.log(`  ✗ ${name}${detail ? '  → ' + detail : ''}`)
  }
}

async function api(path, token) {
  const res = await fetch(BASE + '/api' + path, token ? { headers: { Authorization: 'Bearer ' + token } } : {})
  return res.json()
}

async function adminLogin() {
  // 公开接口的负例需要后台配合：先用 admin 造一条草稿
  const fs = require('fs')
  const path = require('path')
  const POOL = path.resolve(__dirname, '.captcha-pool.json')
  let captchas = []
  try {
    const p = JSON.parse(fs.readFileSync(POOL, 'utf8'))
    captchas = Array.isArray(p) ? p : [p]
  } catch {}
  const c = captchas.pop() || { id: '', answer: '' }
  const res = await fetch(BASE + '/admin/login', {
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

;(async () => {
  console.log('\n=== api 应用公开接口验证 ===\n')

  // 1. 匿名可访问（与 admin 的 JWT 分离）
  const list = await api('/notice/list?page=1&limit=10')
  check('匿名可访问公告列表', list.code === 0, JSON.stringify(list).slice(0, 140))
  check('返回 list + total', Array.isArray(list.data?.list) && typeof list.data?.total === 'number')

  const rows = list.data?.list || []
  check('返回的都是已发布（status=1）', rows.every((r) => r.status === 1), `${rows.length} 条`)

  // 2. 后台先建一条草稿，验证不会被公开接口读到
  const token = await adminLogin()
  check('后台登录成功（用于造负例数据）', !!token)

  let draftId = 0
  if (token) {
    const created = await fetch(BASE + '/admin/biz/notice/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
      body: JSON.stringify({ title: '【草稿】api负例 ' + Date.now(), type: 1, content: '不应被公开接口读到', status: 0 }),
    }).then((r) => r.json())
    draftId = created?.data?.id ?? created?.data ?? 0
    check('后台创建草稿公告成功', !!draftId, JSON.stringify(created).slice(0, 120))

    const after = await api('/notice/list?page=1&limit=100')
    const titles = (after.data?.list || []).map((r) => r.title)
    check('草稿不出现在公开列表中', !titles.some((t) => String(t).includes('api负例')), `共 ${titles.length} 条`)

    if (draftId) {
      const detail = await api('/notice/read?id=' + draftId)
      check('草稿详情不可读（404）', detail.code !== 0, JSON.stringify(detail).slice(0, 120))

      await fetch(BASE + '/admin/biz/notice/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
        body: JSON.stringify({ ids: [draftId] }),
      })
    }
  }

  // 3. 详情可正常读取已发布公告
  if (rows.length) {
    const detail = await api('/notice/read?id=' + rows[0].id)
    check('已发布公告详情可读', detail.code === 0 && detail.data?.id === rows[0].id, JSON.stringify(detail).slice(0, 140))
    check('详情不含内部字段 remark / create_by', detail.data && !('remark' in detail.data) && !('create_by' in detail.data),
      Object.keys(detail.data || {}).join(','))
  } else {
    const none = await api('/notice/read?id=1')
    check('无已发布公告时详情接口仍结构正常', none.code === 0 || none.code === 404, JSON.stringify(none).slice(0, 100))
  }

  // 4. 参数校验
  const noId = await api('/notice/read')
  check('缺少 id 被拒', noId.code !== 0, JSON.stringify(noId).slice(0, 100))

  const badId = await api('/notice/read?id=abc')
  check('非法 id 被拒', badId.code !== 0, JSON.stringify(badId).slice(0, 100))

  // 5. 分页限制
  const limited = await api('/notice/list?page=1&limit=2')
  check('limit 生效', (limited.data?.list || []).length <= 2, `返回 ${limited.data?.list?.length} 条`)

  const filtered = await api('/notice/list?page=1&limit=10&type=1')
  check('按类型筛选生效', filtered.code === 0 && (filtered.data?.list || []).every((r) => r.type === 1))

  // 6. 写入一律不允许（公开接口只读）
  for (const method of ['POST', 'PUT', 'DELETE']) {
    const res = await fetch(BASE + '/api/notice/list', { method })
    const json = await res.json().catch(() => ({}))
    check(`${method} 不被接受（只读）`, res.status !== 200 || json.code !== 0, `HTTP ${res.status} ${JSON.stringify(json).slice(0, 80)}`)
  }

  // 7. api 应用根路径
  const root = await api('')
  check('api 根路径可用', root.code === 0, JSON.stringify(root).slice(0, 120))

  console.log(`\n结果：${pass} 通过 / ${fail} 失败\n`)
  process.exit(fail === 0 ? 0 : 1)
})()
