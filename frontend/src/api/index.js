import request from '@/utils/request'
import { getToken } from '@/utils/auth'

// ---------- 认证 ----------
export const login = (data) => request.post('/admin/login', data)
export const getCaptcha = () => request.get('/admin/captcha')
export const getInfo = () => request.get('/admin/getInfo')
export const logout = () => request.post('/admin/logout')
export const getStats = () => request.get('/admin/index/stats')

// ---------- 个人中心 ----------
export const getProfile = () => request.get('/admin/profile/info')
export const updateProfile = (data) => request.post('/admin/profile/update', data)
export const changePassword = (data) => request.post('/admin/profile/password', data)

// ---------- 用户管理 ----------
export const userList = (params) => request.get('/admin/system/user/list', { params })
export const userRead = (id) => request.get(`/admin/system/user/read/${id}`)
export const userSave = (data) => request.post('/admin/system/user/save', data)
export const userDelete = (ids) => request.post('/admin/system/user/delete', { ids })
export const userStatus = (data) => request.post('/admin/system/user/status', data)
export const userResetPwd = (data) => request.post('/admin/system/user/resetPwd', data)
export const userRoleOptions = () => request.get('/admin/system/user/roleOptions')
export const userPostOptions = () => request.get('/admin/system/user/postOptions')

// ---------- 角色管理 ----------
export const roleList = (params) => request.get('/admin/system/role/list', { params })
export const roleRead = (id) => request.get(`/admin/system/role/read/${id}`)
export const roleScopeOptions = () => request.get('/admin/system/role/scopeOptions')
export const onlineList = () => request.get('/admin/system/online/list')
export const onlineKick = (jti) => request.post('/admin/system/online/kick', { jti })
export const onlineKickUser = (userId) => request.post('/admin/system/online/kickUser', { user_id: userId })
export const monitorInfo = () => request.get('/admin/system/monitor/index')
export const taskLogList = (params) => request.get('/admin/system/monitor/tasklog', { params })
export const taskOverview = (days) => request.get('/admin/system/monitor/taskOverview', { params: { days } })
export const taskLogClear = () => request.post('/admin/system/monitor/clearTasklog')
export const refreshToken = () => request.post('/admin/refresh')
export const roleAll = () => request.get('/admin/system/role/all')
export const roleSave = (data) => request.post('/admin/system/role/save', data)
export const roleDelete = (ids) => request.post('/admin/system/role/delete', { ids })
export const roleStatus = (data) => request.post('/admin/system/role/status', data)
export const roleMenuIds = (id) => request.get(`/admin/system/role/menuIds/${id}`)
export const roleAssignMenus = (data) => request.post('/admin/system/role/assignMenus', data)

// ---------- 菜单管理 ----------
export const menuList = (params) => request.get('/admin/system/menu/list', { params })
export const menuRead = (id) => request.get(`/admin/system/menu/read/${id}`)
export const menuTree = () => request.get('/admin/system/menu/tree')
export const menuSave = (data) => request.post('/admin/system/menu/save', data)
export const menuDelete = (ids) => request.post('/admin/system/menu/delete', { ids })

// ---------- 部门管理 ----------
export const deptList = (params) => request.get('/admin/system/dept/list', { params })
export const deptRead = (id) => request.get(`/admin/system/dept/read/${id}`)
export const deptTree = () => request.get('/admin/system/dept/tree')
export const deptSave = (data) => request.post('/admin/system/dept/save', data)
export const deptDelete = (ids) => request.post('/admin/system/dept/delete', { ids })

// ---------- 岗位管理 ----------
export const postList = (params) => request.get('/admin/system/post/list', { params })
export const postSave = (data) => request.post('/admin/system/post/save', data)
export const postDelete = (ids) => request.post('/admin/system/post/delete', { ids })
export const postStatus = (data) => request.post('/admin/system/post/status', data)

// ---------- 字典管理 ----------
export const dictTypeList = (params) => request.get('/admin/system/dict/list', { params })
export const dictTypeSave = (data) => request.post('/admin/system/dict/save', data)
export const dictTypeDelete = (ids) => request.post('/admin/system/dict/delete', { ids })
export const dictDataList = (params) => request.get('/admin/system/dict/dataList', { params })
export const dictDataByType = (type) => request.get(`/admin/system/dict/dataByType/${type}`)
export const dictDataSave = (data) => request.post('/admin/system/dict/saveData', data)
export const dictDataDelete = (ids) => request.post('/admin/system/dict/deleteData', { ids })

// ---------- 参数配置 ----------
export const configList = (params) => request.get('/admin/system/config/list', { params })
export const configSave = (data) => request.post('/admin/system/config/save', data)
export const configDelete = (ids) => request.post('/admin/system/config/delete', { ids })
export const configStatus = (data) => request.post('/admin/system/config/status', data)

// ---------- 日志 ----------
export const operLogList = (params) => request.get('/admin/system/log/oper', { params })
export const operLogClear = () => request.post('/admin/system/log/clearOper')
export const loginLogList = (params) => request.get('/admin/system/log/login', { params })
export const loginLogClear = () => request.post('/admin/system/log/clearLogin')

// ---------- 通知公告（业务模块样例） ----------
export const noticeList = (params) => request.get('/admin/biz/notice/list', { params })
export const noticeRead = (id) => request.get(`/admin/biz/notice/read/${id}`)
export const noticeSave = (data) => request.post('/admin/biz/notice/save', data)
export const noticeDelete = (ids) => request.post('/admin/biz/notice/delete', { ids })
export const noticePublish = (id, status) => request.post('/admin/biz/notice/publish', { id, status })
export const noticeTop = (id, is_top) => request.post('/admin/biz/notice/top', { id, is_top })
export const noticeExport = (params) => request.get('/admin/biz/notice/export', { params })
export const noticeTemplate = () => request.get('/admin/biz/notice/template')

// ---------- 代码生成 ----------
export const genTables = (keyword) => request.get('/admin/tool/gen/tables', { params: { keyword } })
export const genColumns = (table) => request.get('/admin/tool/gen/columns', { params: { table } })
export const genPreview = (table, opt) => request.get('/admin/tool/gen/preview', { params: { table, ...opt } })
export const genGenerate = (data) => request.post('/admin/tool/gen/generate', data)
export const genZip = (data) => request.post('/admin/tool/gen/zip', data)

// ---------- 插件管理 ----------
export const pluginList = (params) => request.get('/admin/system/plugin/list', { params })
export const pluginInstall = (name, force = false) => request.post('/admin/system/plugin/install', { name, force: force ? 1 : 0 })
export const pluginUninstall = (name) => request.post('/admin/system/plugin/uninstall', { name })
export const pluginEnable = (name) => request.post('/admin/system/plugin/enable', { name })
export const pluginDisable = (name) => request.post('/admin/system/plugin/disable', { name })
export const pluginConfig = (name) => request.get('/admin/system/plugin/config', { params: { name } })
export const pluginSaveConfig = (name, config) => request.post('/admin/system/plugin/saveConfig', { name, config })
export const pluginHooks = () => request.get('/admin/system/plugin/hooks')

// ---------- 文件管理 ----------
export const fileList = (params) => request.get('/admin/system/file/list', { params })
export const fileRead = (id) => request.get(`/admin/system/file/read/${id}`)
export const fileDelete = (ids) => request.post('/admin/system/file/delete', { ids })
// 鉴权下载地址：需要带 token（window.open 无法自定义 header，用 query 传 token）
export const fileDownloadUrl = (id) => `${request.defaults.baseURL}/admin/system/file/download?id=${id}&token=${encodeURIComponent(getToken())}`
export const fileStats = () => request.get('/admin/system/file/stats')
export const fileClearOrphan = () => request.post('/admin/system/file/clearOrphan')

// ---------- 远程升级 ----------
export const upgradeInfo = () => request.get('/admin/system/upgrade/info')
export const upgradeCheck = (force = false) => request.post('/admin/system/upgrade/check', { force: force ? 1 : 0 })
export const upgradeHistory = (params) => request.get('/admin/system/upgrade/history', { params })
export const upgradeApply = (version, localPackage = '') => request.post('/admin/system/upgrade/apply', { version, local_package: localPackage })
export const upgradeRollback = (id, withDb = false) => request.post('/admin/system/upgrade/rollback', { id, with_db: withDb ? 1 : 0 })
export const upgradeClearCache = () => request.post('/admin/system/upgrade/clearCache')


