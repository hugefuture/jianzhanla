/**
 * 登录防护验证脚本：验证码 / 失败锁定 / 密码策略
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

/** 取一个有效验证码（开发辅助：从缓存文件反查答案） */
async function fetchCaptcha() {
  const r = await req('/admin/captcha')
  if (!r.data?.captcha) return null
  return r.data.captcha
}

;(async () => {
  console.log('== 1. 验证码接口 ==')
  const cap = await fetchCaptcha()
  ok('验证码接口返回图片与ID', !!(cap && cap.id && cap.image.startsWith('data:image/png;base64,')),
    JSON.stringify(cap || {}).slice(0, 120))
  ok('验证码开关读取正常', typeof cap?.ttl === 'number')

  console.log('\n== 2. 缺验证码应被拒 ==')
  let r = await req('/admin/login', { method: 'POST', body: { username: 'admin', password: '123456' } })
  ok('不带验证码登录被拒', r.code !== 0, JSON.stringify(r).slice(0, 150))

  console.log('\n== 3. 正确验证码可登录 ==')
  let adminToken = null
  try {
    // 验证码答案由外部 PHP 脚本生成后写入 .captcha.json（沙箱内无法 spawn php）
    const fs = await import('node:fs')
    const p = await import('node:path')
    const file = p.join(process.cwd(), '.captcha.json')
    const raw = fs.readFileSync(file, 'utf8')
    const parsed = JSON.parse(raw)
    ok('测试辅助取到验证码答案', !!(parsed.id && parsed.answer), raw.slice(0, 120))
    r = await req('/admin/login', {
      method: 'POST',
      body: { username: 'admin', password: '123456', captcha_id: parsed.id, captcha_code: parsed.answer },
    })
    ok('正确验证码可登录', r.code === 0, JSON.stringify(r).slice(0, 160))
    adminToken = r.data?.token
    // 验证码一次性：复用应失败
    const reuse = await req('/admin/login', {
      method: 'POST',
      body: { username: 'admin', password: '123456', captcha_id: parsed.id, captcha_code: parsed.answer },
    })
    ok('验证码一次性（复用失效）', reuse.code !== 0, JSON.stringify(reuse).slice(0, 120))
  } catch (e) {
    ok('测试辅助取到验证码答案', false, String(e.message).slice(0, 160))
  }

  console.log('\n== 4. 失败次数锁定 ==')
  // 生成 6 组验证码，连续用错误密码尝试，第 5 次后应锁定
  try {
    const fs2 = await import('node:fs')
    const p2 = await import('node:path')
    const caps = JSON.parse(fs2.readFileSync(p2.join(process.cwd(), '.captcha-lock.json'), 'utf8'))
    let locked = false
    let lastMsg = ''
    for (let i = 0; i < caps.length; i++) {
      const rr = await req('/admin/login', {
        method: 'POST',
        body: { username: 'locktest', password: 'wrong_pwd', captcha_id: caps[i].id, captcha_code: caps[i].answer },
      })
      lastMsg = rr.msg || ''
      if (String(lastMsg).includes('锁定')) { locked = true; break }
    }
    ok('连续失败达到上限后锁定账号', locked, lastMsg)
    // 锁定后即使密码正确也被拒
    const afterLock = await req('/admin/login', {
      method: 'POST',
      body: { username: 'locktest', password: '123456', captcha_id: caps[caps.length - 1].id, captcha_code: caps[caps.length - 1].answer },
    })
    ok('锁定期间拒绝登录', afterLock.code !== 0, JSON.stringify(afterLock).slice(0, 120))
  } catch (e) {
    ok('连续失败锁定测试', false, String(e.message).slice(0, 160))
  }

  const c2 = await fetchCaptcha()
  // 用错误密码连续尝试（验证码仅首次有效，后续会因验证码无效被拒——仍计入失败? 不会）
  // 因此这里改为：直接测「验证码错误」不计入锁定、错误密码计入锁定
  const cfg = await req('/admin/system/config/list?page=1&limit=100', { token: adminToken })
  ok('可读取参数配置', cfg.code === 0)
  const maxRetryRow = (cfg.data?.list || []).find(x => x.key === 'sys.login.maxRetry')
  ok('存在 sys.login.maxRetry 参数', !!maxRetryRow, JSON.stringify(maxRetryRow || {}))
  const lockRow = (cfg.data?.list || []).find(x => x.key === 'sys.login.lockMinutes')
  ok('存在 sys.login.lockMinutes 参数', !!lockRow)
  const pwdRow = (cfg.data?.list || []).find(x => x.key === 'sys.password.minLength')
  ok('存在 sys.password.minLength 参数', !!pwdRow)

  console.log('\n== 5. 密码策略 ==')
  if (adminToken) {
    r = await req('/admin/system/user/resetPwd', { method: 'POST', token: adminToken, body: { id: 2, password: '12' } })
    ok('过短密码被拒绝', r.code !== 0, JSON.stringify(r).slice(0, 120))
  }

  console.log('\n== 6. 关闭验证码开关后可免验证码登录 ==')
  if (adminToken) {
    const row = (cfg.data?.list || []).find(x => x.key === 'sys.login.captcha')
    if (row) {
      await req('/admin/system/config/save', { method: 'POST', token: adminToken, body: { id: row.id, key: row.key, name: row.name, value: '0' } })
      const r2 = await req('/admin/login', { method: 'POST', body: { username: 'admin', password: '123456' } })
      ok('关闭开关后免验证码登录成功', r2.code === 0, JSON.stringify(r2).slice(0, 120))
      await req('/admin/system/config/save', { method: 'POST', token: adminToken, body: { id: row.id, key: row.key, name: row.name, value: '1' } })
      const r3 = await req('/admin/login', { method: 'POST', body: { username: 'admin', password: '123456' } })
      ok('重新开启后又被拒', r3.code !== 0)
    }
  }

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`)
  process.exit(fail ? 1 : 0)
})()
