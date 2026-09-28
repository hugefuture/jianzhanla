/**
 * Excel 导入导出验证脚本
 * 前置：
 *   php tools/dev-captcha.php 1 > tools/.captcha.json
 *   php tools/dev-excel-file.php tools/.import-test.xlsx
 */
const BASE = process.env.BASE || 'http://127.0.0.1:8000'
const ROOT = 'C:/Users/Administrator/WorkBuddy/2026-09-27-08-42-40/admin-framework'
let pass = 0, fail = 0
const ok = (n, c, e = '') => { c ? (pass++, console.log('  ✓ ' + n)) : (fail++, console.log('  ✗ ' + n + (e ? ' -> ' + e : ''))) }

async function req(path, { method = 'GET', token, body, raw = false } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  })
  return raw ? res : res.json()
}

;(async () => {
  const fs = await import('node:fs')
  const p = await import('node:path')
  // 验证码池：兼容数组（dev-captcha.php N）与单个对象（dev-captcha.php 1）两种形状
  const rawCaptcha = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha.json'), 'utf8'))
  const cap = Array.isArray(rawCaptcha) ? rawCaptcha[0] : rawCaptcha
  let r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap.id, captcha_code: cap.answer },
  })
  const token = r.data?.token
  ok('管理员登录', !!token, JSON.stringify(r).slice(0, 120))

  console.log('\n== 1. 导出 ==')
  r = await req('/admin/biz/notice/export', { token })
  const exportUrl = r.data?.url || ''
  ok('导出返回文件地址', exportUrl.startsWith('/uploads/excel/'), JSON.stringify(r).slice(0, 160))
  const exportFile = p.join(ROOT, 'backend/public', exportUrl)
  ok('导出文件已落盘', fs.existsSync(exportFile), exportUrl)
  if (exportFile && fs.existsSync(exportFile) && fs.statSync(exportFile).isFile()) {
    const buf = fs.readFileSync(exportFile)
    ok('文件为合法 xlsx（PK 头）', buf.slice(0, 2).toString() === 'PK', buf.slice(0, 4).toString())
    ok('导出文件非空', buf.length > 3000, String(buf.length))
  }

  console.log('\n== 2. 导入模板 ==')
  r = await req('/admin/biz/notice/template', { token })
  const tplUrl = r.data?.url || ''
  ok('模板返回文件地址', tplUrl.startsWith('/uploads/excel/'), JSON.stringify(r).slice(0, 160))
  ok('模板文件已落盘', fs.existsSync(p.join(ROOT, 'backend/public', tplUrl)), tplUrl)

  console.log('\n== 3. 导入 ==')
  const src = p.join(process.cwd(), '.import-test.xlsx')
  ok('测试文件存在', fs.existsSync(src), src)
  const buf = fs.readFileSync(src)
  const fd = new FormData()
  fd.append('file', new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), 'import-test.xlsx')
  const res = await fetch(BASE + '/admin/biz/notice/import', {
    method: 'POST',
    headers: { Authorization: 'Bearer ' + token },
    body: fd,
  })
  const imp = await res.json()
  ok('导入接口返回成功', imp.code === 0, JSON.stringify(imp).slice(0, 200))
  ok('解析到 3 行数据', imp.data?.total === 3, JSON.stringify(imp.data?.total))
  ok('成功 2 行', imp.data?.success === 2, JSON.stringify(imp.data?.success))
  ok('失败 1 行（缺标题）', (imp.data?.failed || []).length === 1, JSON.stringify(imp.data?.failed))
  ok('失败行带行号', (imp.data?.failed || [])[0]?.row >= 2, JSON.stringify(imp.data?.failed))

  r = await req('/admin/biz/notice/list?title=' + encodeURIComponent('导入测试') + '&limit=10', { token })
  const list = r.data?.list || []
  ok('导入数据已入库', list.length === 2, JSON.stringify(list.map(i => i.title)))
  ok('导入字段正确映射', list.some(i => i.content === '来自 Excel 导入' && i.remark === 'verify'),
    JSON.stringify(list[0] || {}).slice(0, 200))
  ok('导入数据默认草稿', list.every(i => Number(i.status) === 0))

  console.log('\n== 4. 非法文件与清理 ==')
  const fd2 = new FormData()
  fd2.append('file', new Blob([Buffer.from('not an excel')], { type: 'text/plain' }), 'bad.txt')
  const res2 = await fetch(BASE + '/admin/biz/notice/import', {
    method: 'POST', headers: { Authorization: 'Bearer ' + token }, body: fd2,
  })
  const bad = await res2.json()
  ok('非法文件被拒绝', bad.code !== 0, JSON.stringify(bad).slice(0, 160))

  for (const item of list) {
    await req('/admin/biz/notice/delete', { method: 'POST', token, body: { ids: [item.id] } })
  }
  r = await req('/admin/biz/notice/list?title=' + encodeURIComponent('导入测试') + '&limit=10', { token })
  ok('导入测试数据已清理', (r.data?.list || []).length === 0)
  for (const f of [exportFile, p.join(ROOT, 'backend/public', tplUrl)]) {
    try { fs.unlinkSync(f) } catch {}
  }

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
