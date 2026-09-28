/**
 * 业务模块样例验证：通知公告（Controller/Service/Dao/Model 四层）
 * 前置：tools 目录下需先生成 .captcha.json（php dev-captcha.php 1 > .captcha.json）
 */
const BASE = process.env.BASE || 'http://127.0.0.1:8000'
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

;(async () => {
  const fs = await import('node:fs')
  const p = await import('node:path')
  const cap = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha.json'), 'utf8'))

  console.log('== 1. 登录 ==')
  let r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap.id, captcha_code: cap.answer },
  })
  const token = r.data?.token
  ok('管理员登录', !!token, JSON.stringify(r).slice(0, 140))

  console.log('\n== 2. 列表与查询 ==')
  r = await req('/admin/biz/notice/list?page=1&limit=10', { token })
  const list = r.data?.list || []
  ok('列表返回数据', list.length > 0, JSON.stringify(r).slice(0, 160))
  ok('Model 获取器生效（type_text/status_text）',
    !!list[0]?.type_text && !!list[0]?.status_text,
    JSON.stringify(list[0] || {}).slice(0, 200))

  r = await req('/admin/biz/notice/list?title=放假&limit=10', { token })
  ok('标题模糊查询', (r.data?.list || []).length === 1, JSON.stringify(r.data?.list || []).slice(0, 140))

  r = await req('/admin/biz/notice/list?status=1&limit=10', { token })
  ok('状态精确过滤', (r.data?.list || []).every(i => i.status === 1), JSON.stringify(r.data?.list || []).slice(0, 140))

  console.log('\n== 3. 新增（Service 自动填充 create_by / dept_id） ==')
  r = await req('/admin/biz/notice/save', {
    method: 'POST', token,
    body: { title: '验证用公告', type: 2, content: '由验证脚本创建', remark: 'verify' },
  })
  const id = r.data?.id
  ok('新增成功', !!id, JSON.stringify(r).slice(0, 140))

  r = await req(`/admin/biz/notice/read/${id}`, { token })
  ok('创建人自动填充为当前用户', r.data?.create_by === 1, JSON.stringify(r.data || {}).slice(0, 200))
  ok('部门默认取创建人部门', Number(r.data?.dept_id) > 0, JSON.stringify(r.data?.dept_id))
  ok('新增默认草稿状态', Number(r.data?.status) === 0, String(r.data?.status))

  console.log('\n== 4. 发布 / 下架 / 置顶 ==')
  r = await req('/admin/biz/notice/publish', { method: 'POST', token, body: { id, status: 1 } })
  ok('发布成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req(`/admin/biz/notice/read/${id}`, { token })
  ok('状态变为已发布', Number(r.data?.status) === 1, String(r.data?.status))

  r = await req('/admin/biz/notice/top', { method: 'POST', token, body: { id, is_top: 1 } })
  ok('置顶成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req('/admin/biz/notice/list?limit=10', { token })
  ok('置顶项排首位', (r.data?.list || [])[0]?.is_top === 1, JSON.stringify((r.data?.list || [])[0] || {}).slice(0, 160))

  r = await req('/admin/biz/notice/publish', { method: 'POST', token, body: { id, status: 0 } })
  ok('下架成功', r.code === 0, JSON.stringify(r).slice(0, 120))

  console.log('\n== 5. 校验与异常 ==')
  r = await req('/admin/biz/notice/save', { method: 'POST', token, body: { content: '无标题' } })
  ok('缺标题被拒绝', r.code !== 0, JSON.stringify(r).slice(0, 120))

  r = await req('/admin/biz/notice/save', { method: 'POST', token, body: { id, title: '改类型', type: 9 } })
  ok('非法类型被拒绝', r.code !== 0, JSON.stringify(r).slice(0, 120))

  r = await req('/admin/biz/notice/publish', { method: 'POST', token, body: { id: 999999, status: 1 } })
  ok('不存在的公告返回错误', r.code !== 0, JSON.stringify(r).slice(0, 120))

  console.log('\n== 6. 权限点校验（无 biz 权限用户应被拒） ==')
  r = await req('/admin/system/user/save', {
    method: 'POST', token,
    body: { username: 'noperm', nickname: '无权限', password: '123456', dept_id: 1, role_ids: [2], status: 1 },
  })
  const uid = r.data?.id
  const cap2 = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha-lock.json'), 'utf8'))
  r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'noperm', password: '123456', captcha_id: cap2[0].id, captcha_code: cap2[0].answer },
  })
  const t2 = r.data?.token
  ok('受限用户登录成功', !!t2, JSON.stringify(r).slice(0, 120))
  if (t2) {
    r = await req('/admin/biz/notice/list', { token: t2 })
    ok('无 biz:notice:list 权限被拒', r.code !== 0, JSON.stringify(r).slice(0, 140))
  }

  console.log('\n== 7. 删除与清理 ==')
  r = await req('/admin/biz/notice/delete', { method: 'POST', token, body: { ids: [id] } })
  ok('删除成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req(`/admin/biz/notice/read/${id}`, { token })
  ok('删除后读取不到', r.code !== 0, JSON.stringify(r).slice(0, 120))
  if (uid) await req('/admin/system/user/delete', { method: 'POST', token, body: { ids: [uid] } })

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
