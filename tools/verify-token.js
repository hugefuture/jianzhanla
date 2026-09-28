/**
 * Token 续期与强制下线验证脚本
 */
const BASE = process.env.BASE || 'http://127.0.0.1:8000'
const PHP = process.env.PHP_BIN || 'D:/phpstudy_pro/Extensions/php/php8.2.9nts/php.exe'
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

  console.log('== 1. 登录与在线会话记录 ==')
  let r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap.id, captcha_code: cap.answer },
  })
  const token = r.data?.token
  ok('登录成功', !!token, JSON.stringify(r).slice(0, 140))

  r = await req('/admin/system/online/list', { token })
  const online = r.data?.list || []
  ok('在线列表包含当前会话', online.some(o => o.username === 'admin'), JSON.stringify(online).slice(0, 200))
  const myJti = online.find(o => o.username === 'admin')?.jti

  console.log('\n== 2. Token 续期 ==')
  r = await req('/admin/refresh', { method: 'POST', token })
  const newToken = r.data?.token
  ok('续期返回新 token', !!newToken && newToken !== token, JSON.stringify(r).slice(0, 120))
  r = await req('/admin/getInfo', { token: newToken })
  ok('新 token 可正常使用', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req('/admin/getInfo', { token })
  ok('旧 token 续期后失效', r.code !== 0, JSON.stringify(r).slice(0, 120))

  console.log('\n== 3. 按会话强制下线 ==')
  const cap2 = JSON.parse(fs.readFileSync(p.join(process.cwd(), '.captcha-lock.json'), 'utf8'))
  r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap2[0].id, captcha_code: cap2[0].answer },
  })
  const t2 = r.data?.token
  r = await req('/admin/system/online/list', { token: t2 })
  const jti2 = (r.data?.list || []).find(o => o.username === 'admin')?.jti
  ok('取到第二个会话 jti', !!jti2, JSON.stringify(r.data?.list || []).slice(0, 160))

  // 用第一个（已续期的）token 踢掉第二个会话
  r = await req('/admin/system/online/kick', { method: 'POST', token: newToken, body: { jti: jti2 } })
  ok('踢出指定会话成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req('/admin/getInfo', { token: t2 })
  ok('被踢会话立即失效', r.code !== 0, JSON.stringify(r).slice(0, 140))

  console.log('\n== 4. 按用户强制下线（踢掉全部会话） ==')
  r = await req('/admin/system/online/kickUser', { method: 'POST', token: newToken, body: { user_id: 1 } })
  ok('踢用户全部会话成功', r.code === 0, JSON.stringify(r).slice(0, 120))
  r = await req('/admin/getInfo', { token: newToken })
  ok('该用户所有 token 均失效', r.code !== 0, JSON.stringify(r).slice(0, 140))

  console.log('\n== 5. 重新登录恢复 ==')
  const cap3 = cap2[1]
  r = await req('/admin/login', {
    method: 'POST',
    body: { username: 'admin', password: '123456', captcha_id: cap3.id, captcha_code: cap3.answer },
  })
  ok('被踢后可重新登录', r.code === 0, JSON.stringify(r).slice(0, 140))

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
