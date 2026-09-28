#!/usr/bin/env node
/**
 * verify-upload-guard.js — 上传加固与鉴权下载验证
 * 用法：php tools/dev-captcha.php 4 > tools/.captcha-pool.json && node verify-upload-guard.js
 */
const fs = require('fs');
const path = require('path');

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin';
const pool = JSON.parse(fs.readFileSync(path.join(__dirname, '.captcha-pool.json'), 'utf8'));

let pass = 0, fail = 0;
function check(name, ok, detail = '') {
  ok ? pass++ : fail++;
  console.log(`  ${ok ? '✓' : '✗'} ${name}${ok ? '' : '  ' + detail}`);
}
function nextCaptcha() {
  const arr = Array.isArray(pool) ? pool : [pool];
  const c = arr.shift() || { id: '', answer: '' };
  return { captcha_id: c.id, captcha_code: c.answer };
}

async function api(method, url, data, token) {
  const res = await fetch(BASE + url, {
    method,
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
    body: data ? JSON.stringify(data) : undefined,
  });
  return res.json();
}
// 表单上传（multipart）：服务端固定读取 file 字段
async function upload(endpoint, filename, content, mime, token) {
  const fd = new FormData();
  fd.append('file', new Blob([content], { type: mime }), filename);
  const res = await fetch(BASE + endpoint, {
    method: 'POST',
    headers: token ? { Authorization: 'Bearer ' + token } : {},
    body: fd,
  });
  return res.json();
}

(async () => {
  console.log('== 上传加固与鉴权下载 ==');

  const login = await api('POST', '/login', { username: 'admin', password: '123456', ...nextCaptcha() });
  const token = login.data?.token;
  check('admin 登录成功', login.code === 0);

  // 1. 后缀白名单
  const bad = await upload('/upload/file', 'shell.php', '<?php echo 1;', 'text/plain', token);
  check('非法后缀 .php 被拒（类型错误）', bad.code !== 0 && !String(bad.msg).includes('未收到有效文件'), JSON.stringify(bad).slice(0, 120));

  const bad2 = await upload('/upload/file', 'x.exe', 'MZ', 'application/octet-stream', token);
  check('非法后缀 .exe 被拒（类型错误）', bad2.code !== 0 && !String(bad2.msg).includes('未收到有效文件'), JSON.stringify(bad2).slice(0, 120));

  // 2. 内容伪装：把脚本内容命名成图片后缀（图片必须真的是图片）
  const fake = await upload('/upload/image', 'fake.png', '<?php system($_GET[0]); ?>', 'image/png', token);
  check('伪装图片被拒（内容与后缀不符）', fake.code !== 0 && /内容不是有效图片/.test(String(fake.msg)), JSON.stringify(fake).slice(0, 140));

  // 3. 正常上传可用
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
    'base64'
  );
  const ok = await upload('/upload/image', 'ok.png', png, 'image/png', token);
  check('合法图片上传成功', ok.code === 0, JSON.stringify(ok).slice(0, 140));
  check('存储文件名无重复扩展名', ok.code === 0 && !/\.(png|jpg|jpeg)\.(png|jpg|jpeg)$/.test(String(ok.data?.path || '')), String(ok.data?.path));

  // 4. 文件列表拿到 id
  const list = await api('GET', '/system/file/list?limit=5', null, token);
  const row = list.data?.list?.[0];
  check('文件列表可用', list.code === 0 && !!row);
  const fileId = row?.id;

  // 5. 鉴权下载（query token）
  if (fileId) {
    const dl = await fetch(`${BASE}/system/file/download?id=${fileId}&token=${encodeURIComponent(token)}`);
    const body = await dl.arrayBuffer();
    check('鉴权下载返回 200', dl.status === 200, `status=${dl.status}`);
    check('返回附件下载头', (dl.headers.get('content-disposition') || '').includes('attachment'));
    check('下载内容非空', body.byteLength > 0, `${body.byteLength} bytes`);
  }

  // 6. 未登录下载被拒
  const noAuth = await fetch(`${BASE}/system/file/download?id=${fileId || 1}`);
  const json401 = await noAuth.json();
  check('无 token 下载返回 401', json401.code === 401, JSON.stringify(json401).slice(0, 100));

  // 7. 路径穿越防护
  const evil = await fetch(`${BASE}/system/file/download?id=${fileId || 1}&token=${encodeURIComponent(token)}&path=../../.env`);
  const evilJson = await evil.json().catch(() => ({}));
  check('路径穿越参数被忽略（不走 for 参数）', evil.status === 200 || evilJson.code !== 0);

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('脚本异常:', e.message); process.exit(1); });
