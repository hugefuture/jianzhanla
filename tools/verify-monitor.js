#!/usr/bin/env node
/**
 * verify-monitor.js — 系统监控模块验证
 * 用法：先 php tools/dev-captcha.php 4 > tools/.captcha-pool.json，再 node verify-monitor.js
 */
const fs = require('fs');
const path = require('path');

const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin';
const poolFile = path.join(__dirname, '.captcha-pool.json');

let pass = 0, fail = 0;
function check(name, ok, detail = '') {
  ok ? pass++ : fail++;
  console.log(`  ${ok ? '✓' : '✗'} ${name}${ok ? '' : '  ' + detail}`);
}
function captcha() {
  const pool = JSON.parse(fs.readFileSync(poolFile, 'utf8'));
  const c = (Array.isArray(pool) ? pool : [pool]).shift() || { id: '', answer: '' };
  fs.writeFileSync(poolFile, JSON.stringify(Array.isArray(pool) ? pool : []));
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

(async () => {
  console.log('== 系统监控模块 ==');

  // 1. 未登录拒绝
  const noAuth = await api('GET', '/system/monitor/index');
  check('未登录返回 401', noAuth.code === 401, JSON.stringify(noAuth).slice(0, 100));

  // 2. admin 登录
  const login = await api('POST', '/login', { username: 'admin', password: '123456', ...captcha() });
  check('admin 登录成功', login.code === 0, JSON.stringify(login).slice(0, 100));
  const token = login.data?.token;

  // 3. 监控接口返回
  const res = await api('GET', '/system/monitor/index', null, token);
  check('监控接口返回成功', res.code === 0, JSON.stringify(res).slice(0, 150));

  const s = res.data?.server || {};
  const m = res.data?.mysql || {};

  // 4. 服务器字段
  check('server.os 存在', !!s.os, JSON.stringify(s).slice(0, 100));
  check('server.php 含版本号', /^[\d.]+/.test(String(s.php || '')));
  check('server.disk.percent 合理', typeof s.disk?.percent === 'number' && s.disk.percent >= 0 && s.disk.percent <= 100);
  check('server.uploads 统计存在', typeof s.uploads?.files === 'number');
  check('server.runtime 统计存在', typeof s.runtime?.files === 'number');
  check('server.ini.memory_limit 存在', !!s.ini?.memory_limit);

  // 5. MySQL 字段
  check('mysql.version 存在', !!m.version);
  check('mysql.database 为 sdccc_admin', m.database === 'sdccc_admin');
  check('mysql.table_count > 10', m.table_count > 10, `实际 ${m.table_count}`);
  check('mysql.size_text 存在', !!m.size_text);
  check('mysql.uptime_text 存在', !!m.uptime_text);
  check('mysql.qps 为数字', typeof m.qps === 'number');
  check('mysql.connections <= max_connections', m.connections <= m.max_connections);

  // 6. 菜单权限（test 用户无权限应 403）
  const loginTest = await api('POST', '/login', { username: 'test', password: '123456', ...captcha() });
  check('test 登录成功', loginTest.code === 0);
  const testRes = await api('GET', '/system/monitor/index', null, loginTest.data?.token);
  check('test 无监控权限被拒 403', testRes.code === 403, JSON.stringify(testRes).slice(0, 100));

  console.log(`\n结果：${pass} 通过 / ${fail} 失败`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('脚本异常:', e.message); process.exit(1); });
