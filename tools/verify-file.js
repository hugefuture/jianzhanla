/**
 * 文件管理验证脚本
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

;(async () => {
  const fs = await import('node:fs')
  const p = await import('node:path')
  const cap = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha.json'), 'utf8'))
  let r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap.id, captcha_code: cap.answer },
  })
  const token = r.data?.token
  ok('管理员登录', !!token, JSON.stringify(r).slice(0, 120))

  console.log('\n== 1. 上传并记录 ==')
  // 构造一个最小 PNG
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64'
  )
  fs.writeFileSync(p.join(process.cwd(), '.upload-test.png'), png)
  const fd = new FormData()
  fd.append('file', new Blob([png], { type: 'image/png' }), 'verify-upload.png')
  const res = await fetch(BASE + '/admin/upload/image', {
    method: 'POST', headers: { Authorization: 'Bearer ' + token }, body: fd,
  })
  const up = await res.json()
  ok('上传成功', up.code === 0 && !!up.data?.url, JSON.stringify(up).slice(0, 160))
  ok('上传返回文件记录ID', !!up.data?.id, JSON.stringify(up.data?.id))

  r = await req('/admin/system/file/list?limit=20', { token })
  const list = r.data?.list || []
  const mine = list.find(f => f.name === 'verify-upload.png')
  ok('文件记录已入表', !!mine, JSON.stringify(list.slice(0, 2)).slice(0, 200))
  ok('记录含大小与后缀', mine?.ext === 'png' && Number(mine?.size) > 0, JSON.stringify(mine || {}).slice(0, 200))
  ok('记录含可读大小(size_text)', typeof mine?.size_text === 'string' && mine.size_text.length > 0, String(mine?.size_text))
  ok('记录创建人为当前用户', Number(mine?.create_by) === 1, String(mine?.create_by))

  console.log('\n== 2. 查询与统计 ==')
  r = await req('/admin/system/file/list?name=' + encodeURIComponent('verify-upload') + '&limit=10', { token })
  ok('按文件名模糊查询', (r.data?.list || []).length >= 1)
  r = await req('/admin/system/file/list?ext=png&limit=10', { token })
  ok('按后缀过滤', (r.data?.list || []).every(i => i.ext === 'png'))
  r = await req('/admin/system/file/stats', { token })
  ok('统计接口返回总数', Number(r.data?.total) >= 1, JSON.stringify(r.data))

  console.log('\n== 3. 清理孤儿文件 ==')
  // 手工放一个孤儿文件到 images 日期目录
  const orphanDir = p.join(ROOT, 'backend/public/uploads/images/20260101')
  fs.mkdirSync(orphanDir, { recursive: true })
  const orphan = p.join(orphanDir, 'orphan-test.png')
  fs.writeFileSync(orphan, png)
  r = await req('/admin/system/file/clearOrphan', { method: 'POST', token })
  ok('清理接口返回成功', r.code === 0, JSON.stringify(r).slice(0, 160))
  ok('孤儿文件被删除', !fs.existsSync(orphan), JSON.stringify(r.data))
  try { fs.rmSync(p.join(ROOT, 'backend/public/uploads/images/20260101'), { recursive: true, force: true }) } catch {}

  console.log('\n== 4. 删除记录（含磁盘文件） ==')
  const before = fs.existsSync(p.join(ROOT, 'backend/public', mine.url))
  ok('上传文件存在于磁盘', before, mine.url)
  r = await req('/admin/system/file/delete', { method: 'POST', token, body: { ids: [mine.id] } })
  ok('删除记录成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  ok('磁盘文件一并删除', !fs.existsSync(p.join(ROOT, 'backend/public', mine.url)))
  r = await req(`/admin/system/file/read/${mine.id}`, { token })
  ok('记录已不存在', r.code !== 0, JSON.stringify(r).slice(0, 120))

  try { fs.unlinkSync(p.join(process.cwd(), '.upload-test.png')) } catch {}

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
