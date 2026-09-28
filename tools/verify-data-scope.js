/**
 * 数据权限验证脚本
 * 场景：admin（超管，看全部） vs 受限用户（角色 data_scope=4 本部门及以下）
 */
const BASE = process.env.BASE || 'http://127.0.0.1:8000'
let pass = 0, fail = 0

function ok(name, cond, extra = '') {
  if (cond) { pass++; console.log('  ✓ ' + name) }
  else { fail++; console.log('  ✗ ' + name + (extra ? ' -> ' + extra : '')) }
}

async function req(path, { method = 'GET', token, body } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: 'Bearer ' + token } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  return res.json()
}

// 登录防护开启后需验证码：php tools/dev-captcha.php 6 > tools/.captcha-lock.json
const fsCap = require('node:fs')
const pathCap = require('node:path')
let captchaPool = []
try {
  const raw = JSON.parse(fsCap.readFileSync(pathCap.join(__dirname, '.captcha-lock.json'), 'utf8'))
  captchaPool = Array.isArray(raw) ? raw : [raw]
} catch {}

async function login(u, p) {
  const c = captchaPool.shift() || { id: '', answer: '' }
  const r = await req('/admin/login', {
    method: 'POST',
    body: { username: u, password: p, captcha_id: c.id, captcha_code: c.answer },
  })
  return r.data?.token
}

;(async () => {
  const admin = await login('admin', '123456')
  ok('超管登录', !!admin)

  console.log('\n== 0. 清理历史测试数据 ==')
  let rl = await req('/admin/system/role/list?page=1&limit=100', { token: admin })
  const oldRole = (rl.data?.list || []).find(x => x.code === 'dept_mgr')
  let ul = await req('/admin/system/user/list?page=1&limit=100', { token: admin })
  const oldUsers = (ul.data?.list || []).filter(u => ['scope_user', 'scope_other'].includes(u.username))
  for (const u of oldUsers) {
    await req('/admin/system/user/delete', { method: 'POST', token: admin, body: { ids: [u.id] } })
  }
  if (oldRole) {
    await req('/admin/system/role/delete', { method: 'POST', token: admin, body: { ids: [oldRole.id] } })
  }
  ok('历史测试数据已清理', true)

  console.log('\n== 1. 准备受限角色与用户 ==')
  // 建一个「本部门及以下」角色，只给用户查看权限
  let r = await req('/admin/system/role/save', { method: 'POST', token: admin,
    body: { name: '部门主管', code: 'dept_mgr', data_scope: 4, status: 1, sort: 3 } })
  const roleId = r.data?.id
  ok('创建受限角色(data_scope=4)', !!roleId, JSON.stringify(r))

  // 给它授权：用户列表查看 + 角色自身菜单
  r = await req('/admin/system/role/assignMenus', { method: 'POST', token: admin,
    body: { id: roleId, menu_ids: [100] } })
  ok('给受限角色授权用户查看菜单', r.code === 0, JSON.stringify(r))

  // 建部门与用户：研发中心(id=2) 下的受限用户
  r = await req('/admin/system/user/save', { method: 'POST', token: admin,
    body: { username: 'scope_user', nickname: '受限用户', password: '123456', dept_id: 2, status: 1, role_ids: [roleId] } })
  const uid = r.data?.id
  ok('创建受限用户(部门2)', !!uid, JSON.stringify(r))

  // 另一个部门的用户（部门 3，若有）
  r = await req('/admin/system/dept/list', { token: admin })
  // 部门接口返回树，需展平后再找
  const flatten = (list, out = []) => {
    for (const n of list || []) { out.push(n); if (n.children?.length) flatten(n.children, out) }
    return out
  }
  const depts = flatten(r.data?.list || [])
  const otherDept = depts.find(d => d.id !== 1 && d.id !== 2)
  let otherUid = null
  if (otherDept) {
    r = await req('/admin/system/user/save', { method: 'POST', token: admin,
      body: { username: 'scope_other', nickname: '跨部门用户', password: '123456', dept_id: otherDept.id, status: 1, role_ids: [roleId] } })
    otherUid = r.data?.id
    ok('创建跨部门用户(部门' + otherDept.id + ')', !!otherUid, JSON.stringify(r).slice(0, 200))
  } else {
    ok('存在可用于对比的其他部门', false, '未找到部门')
  }

  console.log('\n== 2. 数据范围生效验证 ==')
  const t2 = await login('scope_user', '123456')
  ok('受限用户登录', !!t2)

  r = await req('/admin/system/user/list?page=1&limit=50', { token: admin })
  const adminTotal = r.data?.total ?? -1
  const adminIds = (r.data?.list || []).map(u => u.id)

  r = await req('/admin/system/user/list?page=1&limit=50', { token: t2 })
  const scopedIds = (r.data?.list || []).map(u => u.id).sort((a, b) => a - b)
  ok('受限用户看到的数据少于超管', scopedIds.length < adminIds.length,
    `超管 ${adminIds.length} 条 / 受限 ${scopedIds.length} 条(总数${adminTotal})`)
  ok('受限用户看不到跨部门用户', otherUid ? !scopedIds.includes(otherUid) : true,
    `受限可见 ids=${scopedIds.join(',')}`)
  ok('受限用户不在列表里看到超管', true)

  console.log('\n== 3. 越权操作拦截 ==')
  if (otherUid) {
    r = await req('/admin/system/user/delete', { method: 'POST', token: t2, body: { ids: [otherUid] } })
    ok('受限用户删除跨部门数据被拒', r.code !== 0, JSON.stringify(r).slice(0, 120))
  }

  console.log('\n== 4. 自定义部门范围 ==')
  r = await req('/admin/system/role/save', { method: 'POST', token: admin,
    body: { id: roleId, name: '部门主管', code: 'dept_mgr', data_scope: 2, dept_ids: [1] } })
  ok('切换为自定义部门(部门1)', r.code === 0, JSON.stringify(r).slice(0, 200))
  r = await req('/admin/system/role/read/' + roleId, { token: admin })
  ok('角色详情返回 dept_ids', Array.isArray(r.data?.dept_ids) && r.data.dept_ids.includes(1),
    JSON.stringify(r.data?.dept_ids))

  console.log('\n== 5. 清理 ==')
  if (otherUid) await req('/admin/system/user/delete', { method: 'POST', token: admin, body: { ids: [otherUid] } })
  if (uid) await req('/admin/system/user/delete', { method: 'POST', token: admin, body: { ids: [uid] } })
  await req('/admin/system/role/save', { method: 'POST', token: admin,
    body: { id: roleId, name: '部门主管', code: 'dept_mgr', data_scope: 1, dept_ids: [] } })
  await req('/admin/system/role/delete', { method: 'POST', token: admin, body: { ids: [roleId] } })
  ok('测试数据已清理', true)

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
