#!/usr/bin/env node
/**
 * verify-monitor-render.js — 模拟前端取值链路（request 拦截器已解包 res.data）
 * 校验监控页模板用到的每个字段都存在，避免页面空白
 */
const fs = require('fs');
const path = require('path');
const BASE = process.env.APP_BASE || 'http://127.0.0.1:8000/admin';
const pool = JSON.parse(fs.readFileSync(path.join(__dirname, '.captcha-pool.json'), 'utf8'));
const c = (Array.isArray(pool) ? pool : [pool]).shift();

(async () => {
  const login = await (await fetch(BASE + '/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'admin', password: '123456', captcha_id: c.id, captcha_code: c.answer }),
  })).json();
  const token = login.data?.token;

  const res = await (await fetch(BASE + '/system/monitor/index', {
    headers: { Authorization: 'Bearer ' + token },
  })).json();

  // 拦截器行为：return res.data
  const info = res.data || {};
  console.log('== 模拟前端取值 ==');
  const fields = [
    ['server.os', info.server?.os],
    ['server.php', info.server?.php],
    ['server.thinkphp', info.server?.thinkphp],
    ['server.time', info.server?.time],
    ['server.timezone', info.server?.timezone],
    ['server.extensions', info.server?.extensions],
    ['server.ini.memory_limit', info.server?.ini?.memory_limit],
    ['server.ini.upload_max_filesize', info.server?.ini?.upload_max_filesize],
    ['server.ini.post_max_size', info.server?.ini?.post_max_size],
    ['server.uploads.files', info.server?.uploads?.files],
    ['server.uploads.size_text', info.server?.uploads?.size_text],
    ['server.runtime.files', info.server?.runtime?.files],
    ['server.runtime.size_text', info.server?.runtime?.size_text],
    ['server.disk.total', info.server?.disk?.total],
    ['server.disk.used', info.server?.disk?.used],
    ['server.disk.free', info.server?.disk?.free],
    ['server.disk.percent', info.server?.disk?.percent],
    ['mysql.version', info.mysql?.version],
    ['mysql.database', info.mysql?.database],
    ['mysql.table_count', info.mysql?.table_count],
    ['mysql.size_text', info.mysql?.size_text],
    ['mysql.uptime_text', info.mysql?.uptime_text],
    ['mysql.connections', info.mysql?.connections],
    ['mysql.max_connections', info.mysql?.max_connections],
    ['mysql.threads_running', info.mysql?.threads_running],
    ['mysql.total_queries', info.mysql?.total_queries],
    ['mysql.qps', info.mysql?.qps],
    ['mysql.slow_queries', info.mysql?.slow_queries],
  ];
  let missing = 0;
  for (const [k, v] of fields) {
    const ok = v !== undefined && v !== null;
    if (!ok) missing++;
    console.log(`  ${ok ? '✓' : '✗'} ${k} = ${JSON.stringify(v)}`);
  }
  const connPercent = Math.min(100, Math.round((info.mysql?.connections / info.mysql?.max_connections) * 1000) / 10);
  console.log(`\n磁盘进度条: ${info.server?.disk?.percent}%  连接占用: ${connPercent}%`);
  console.log(`结果：${fields.length - missing} 字段齐全 / ${missing} 缺失`);
  process.exit(missing ? 1 : 0);
})().catch(e => { console.error('异常:', e.message); process.exit(1); });
