// 建站啦后台管理系统 接口冒烟测试
// 用法：
//   1) 生成验证码：php tools/dev-captcha.php 12 > tools/.captcha-pool.json
//   2) 运行：      node tools/smoke-test.js
// 需先启动后端 127.0.0.1:8000 与 MySQL
const BASE = process.env.BASE || 'http://127.0.0.1:8000';

// 登录防护开启后登录需携带验证码，测试脚本从验证码池依次取用
const fs = require('node:fs');
const path = require('node:path');
let captchaPool = [];
try {
  const raw = JSON.parse(fs.readFileSync(path.join(__dirname, '.captcha-pool.json'), 'utf8'));
  captchaPool = Array.isArray(raw) ? raw : [raw];
} catch {}
const takeCaptcha = () => {
  const c = captchaPool.shift() || { id: '', answer: '' };
  return { captcha_id: c.id, captcha_code: c.answer };
};
const withCaptcha = (body) => ({ ...body, ...takeCaptcha() });

let passed = 0, failed = 0;
function check(name, cond, detail = '') {
  if (cond) { passed++; console.log(`  ✓ ${name}`); }
  else { failed++; console.log(`  ✗ ${name}  ${detail}`); }
}

async function req(method, path, { token, body } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data = null;
  try { data = await res.json(); } catch {}
  return { status: res.status, data };
}

async function main() {
  console.log('== 1. 登录与鉴权 ==');
  const bad = await req('POST', '/admin/login', { body: withCaptcha({ username: 'admin', password: 'wrong' }) });
  check('错误密码被拒绝', bad.data?.code === 400, JSON.stringify(bad.data));

  const login = await req('POST', '/admin/login', { body: withCaptcha({ username: 'admin', password: '123456' }) });
  check('admin 登录成功', login.data?.code === 0 && !!login.data?.data?.token, JSON.stringify(login.data).slice(0, 120));
  const token = login.data?.data?.token;

  const noAuth = await req('GET', '/admin/system/user/list');
  check('无 token 返回 401', noAuth.data?.code === 401, JSON.stringify(noAuth.data));

  const info = await req('GET', '/admin/getInfo', { token });
  check('getInfo 返回用户+权限+菜单', info.data?.code === 0 && info.data?.data?.user?.username === 'admin'
    && info.data?.data?.perms?.includes('*') && info.data?.data?.menus?.length > 0, JSON.stringify(info.data).slice(0, 150));

  console.log('== 2. 用户 CRUD ==');
  const created = await req('POST', '/admin/system/user/save', { token, body: { username: 'wangwu', nickname: '王五', password: '123456', dept_id: 2, role_ids: [2], status: 1 } });
  const newId = created.data?.data?.id || created.data?.data;
  check('新增用户', created.data?.code === 0, JSON.stringify(created.data).slice(0, 120));
  check('用户 ID 有效', Number(newId) > 0, String(newId));

  const read = await req('GET', `/admin/system/user/read/${newId}`, { token });
  check('用户详情含 role_ids', read.data?.code === 0 && Array.isArray(read.data?.data?.role_ids));

  const upd = await req('POST', '/admin/system/user/save', { token, body: { id: newId, nickname: '王五改' } });
  check('更新用户', upd.data?.code === 0);
  const read2 = await req('GET', `/admin/system/user/read/${newId}`, { token });
  check('更新生效', read2.data?.data?.nickname === '王五改');

  const dup = await req('POST', '/admin/system/user/save', { token, body: { username: 'wangwu', nickname: '重复' } });
  check('账号唯一性校验', dup.data?.code === 400, JSON.stringify(dup.data));

  const reset = await req('POST', '/admin/system/user/resetPwd', { token, body: { id: newId, password: '654321' } });
  check('重置密码', reset.data?.code === 0);
  const rel = await req('POST', '/admin/login', { body: withCaptcha({ username: 'wangwu', password: '654321' }) });
  check('新密码可登录', rel.data?.code === 0);

  const list = await req('GET', '/admin/system/user/list?page=1&limit=10&nickname=王五', { token });
  check('用户分页+搜索', list.data?.code === 0 && list.data?.data?.total >= 1);

  const del = await req('POST', '/admin/system/user/delete', { token, body: { ids: [newId] } });
  check('删除用户', del.data?.code === 0);
  const delAdmin = await req('POST', '/admin/system/user/delete', { token, body: { ids: [1] } });
  check('超管账号不可删除', delAdmin.data?.code !== 0);

  console.log('== 3. 角色与授权 ==');
  const roleList = await req('GET', '/admin/system/role/list', { token });
  check('角色分页', roleList.data?.code === 0 && roleList.data?.data?.total >= 2);
  const menuTree = await req('GET', '/admin/system/menu/tree', { token });
  check('菜单授权树', menuTree.data?.code === 0 && menuTree.data?.data?.length > 0);
  const menuIds = await req('GET', '/admin/system/role/menuIds/2', { token });
  check('角色已授权菜单', menuIds.data?.code === 0 && menuIds.data?.data?.length > 0);

  console.log('== 4. 菜单 / 部门 / 岗位 / 字典 / 参数 ==');
  for (const [name, path, key] of [
    ['菜单列表(树)', '/admin/system/menu/list', 'list'],
    ['部门列表(树)', '/admin/system/dept/list', 'list'],
    ['部门树(下拉)', '/admin/system/dept/tree', ''],
    ['岗位列表', '/admin/system/post/list', 'list'],
    ['字典类型', '/admin/system/dict/list', 'list'],
    ['参数配置', '/admin/system/config/list', 'list'],
  ]) {
    const r = await req('GET', path, { token });
    check(name, r.data?.code === 0 && (key ? r.data?.data?.[key]?.length > 0 : true), JSON.stringify(r.data).slice(0, 100));
  }
  const dictData = await req('GET', '/admin/system/dict/dataByType/sys_normal_status', { token });
  check('字典数据(带缓存)', dictData.data?.code === 0 && dictData.data?.data?.length === 2);

  console.log('== 5. 权限隔离（test 用户） ==');
  const tLogin = await req('POST', '/admin/login', { body: withCaptcha({ username: 'test', password: '123456' }) });
  const tToken = tLogin.data?.data?.token;
  check('test 登录成功', !!tToken);
  const tList = await req('GET', '/admin/system/user/list', { token: tToken });
  check('test 可查看用户列表(list 权限)', tList.data?.code === 0, JSON.stringify(tList.data).slice(0, 120));
  const tSave = await req('POST', '/admin/system/user/save', { token: tToken, body: { username: 'hack', nickname: 'hack' } });
  check('test 无保存权限被拒 403', tSave.data?.code === 403, JSON.stringify(tSave.data));

  console.log('== 6. 日志与仪表盘 ==');
  const operLog = await req('GET', '/admin/system/log/oper?page=1&limit=10', { token });
  check('操作日志已自动记录', operLog.data?.code === 0 && operLog.data?.data?.total >= 1, JSON.stringify(operLog.data).slice(0, 100));
  const loginLog = await req('GET', '/admin/system/log/login?page=1&limit=10', { token });
  check('登录日志已记录', loginLog.data?.code === 0 && loginLog.data?.data?.total >= 3);
  const stats = await req('GET', '/admin/index/stats', { token });
  check('仪表盘统计', stats.data?.code === 0 && stats.data?.data?.user_total >= 2);

  console.log('== 7. 个人中心 ==');
  const profile = await req('GET', '/admin/profile/info', { token });
  check('个人资料', profile.data?.code === 0 && profile.data?.data?.user?.username === 'admin');
  const updProfile = await req('POST', '/admin/profile/update', { token, body: { nickname: '超级管理员', phone: '13800000000' } });
  check('更新资料', updProfile.data?.code === 0);

  console.log('== 8. API 应用 ==');
  const api = await req('GET', '/api/');
  check('多应用 /api 正常', api.data?.code === 0);
  const notFound = await req('GET', '/admin/not/exist/path');
  check('未匹配路由返回 404', notFound.data?.code === 404);

  console.log(`\n结果：${passed} 通过 / ${failed} 失败`);
  process.exit(failed ? 1 : 0);
}

main().catch((e) => { console.error('FATAL', e); process.exit(1); });
