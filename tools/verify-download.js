#!/usr/bin/env node
/**
 * verify-download.js — 静态文件下载链路验证（导出/模板/上传文件）
 * 用法：先 php tools/dev-captcha.php 4 > tools/.captcha-pool.json，再 node verify-download.js
 * 覆盖：中文文件名、ASCII 文件名、前端 dev 代理两条通路
 */
const fs = require('fs');
const path = require('path');

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin';
const WEB = process.env.WEB_BASE || 'http://localhost:5183';
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
async function head(url) {
  const res = await fetch(url);
  return { status: res.status, type: res.headers.get('content-type') || '', disp: res.headers.get('content-disposition') || '', size: (await res.arrayBuffer()).byteLength };
}

(async () => {
  console.log('== 静态文件下载链路 ==');

  const login = await api('POST', '/login', { username: 'admin', password: '123456', ...nextCaptcha() });
  const token = login.data?.token;
  check('admin 登录成功', login.code === 0);

  // 1. 导出接口
  const exp = await api('GET', '/biz/notice/export', null, token);
  check('导出接口返回 URL', exp.code === 0 && !!exp.data?.url, JSON.stringify(exp).slice(0, 120));
  const url = exp.data?.url || '';

  // 2. 后端直连下载
  const direct = await head('http://127.0.0.1:8000' + url);
  check('后端直连返回 200', direct.status === 200, `status=${direct.status}`);
  check('Content-Type 为 xlsx MIME', direct.type.includes('spreadsheetml'), direct.type);
  check('文件非空（>1KB）', direct.size > 1024, `${direct.size} bytes`);
  check('Content-Disposition 含中文 filename*', direct.disp.includes("filename*=UTF-8''"), direct.disp);

  // 3. 前端 dev 代理下载（Vite /uploads -> 8000）
  let proxy;
  try { proxy = await head(WEB + url); } catch (e) { proxy = { status: 0, type: '', disp: '', size: 0 }; }
  check('前端代理返回 200', proxy.status === 200, `status=${proxy.status}`);
  check('前端代理文件非空', proxy.size > 1024, `${proxy.size} bytes`);

  // 4. 导入模板（同为 xlsx 下载）
  const tpl = await api('GET', '/biz/notice/template', null, token);
  check('模板接口返回 URL', tpl.code === 0 && !!tpl.data?.url);
  const t = await head('http://127.0.0.1:8000' + (tpl.data?.url || ''));
  check('模板文件可下载', t.status === 200 && t.size > 1024, `status=${t.status} size=${t.size}`);

  // 5. 非静态路由未受影响（误伤检查）
  const missed = await api('GET', '/system/user/list?limit=1', null, token);
  check('API 路由未被静态处理误伤', missed.code === 0, JSON.stringify(missed).slice(0, 80));
  const notFound = await api('GET', '/uploads/excel/not-exist-file.xlsx', null, token);
  check('不存在的静态文件走框架 404（不 500）', notFound.code === 404 || notFound.code === 500 === false, JSON.stringify(notFound).slice(0, 80));

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('脚本异常:', e.message); process.exit(1); });
