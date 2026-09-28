<template>
  <div class="page-card">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="插件机制"
      description="插件以目录形式放在 backend/plugin/<标识>/，通过 plugin.json 声明菜单、接口、配置与监听的钩子；安装后自动注册菜单与路由。带页面的插件安装后需重新构建前端（或重启 dev 服务）才能看到新页面。"
    />

    <div class="stat-row">
      <el-card shadow="never" class="stat-card">
        <div class="stat-value">{{ overview.total }}</div>
        <div class="stat-label">插件总数</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value">{{ overview.installed }}</div>
        <div class="stat-label">已安装</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value success">{{ overview.enabled }}</div>
        <div class="stat-label">已启用</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value warn">{{ overview.upgradable }}</div>
        <div class="stat-label">版本待更新</div>
      </el-card>
    </div>

    <div class="search-bar">
      <el-input v-model="query.keyword" placeholder="插件名称 / 标识 / 作者" clearable @keyup.enter="load" />
      <el-select v-model="query.status" placeholder="全部状态" clearable style="width: 140px" @change="load">
        <el-option label="未安装" :value="0" />
        <el-option label="已启用" :value="1" />
        <el-option label="已禁用" :value="2" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button :icon="Refresh" @click="loadHooks">钩子一览</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column label="插件" min-width="200">
        <template #default="{ row }">
          <div class="plugin-title">
            {{ row.title }}
            <el-tag v-if="row.version_mismatch" type="warning" size="small">待更新</el-tag>
          </div>
          <div class="plugin-sub">{{ row.name }} · v{{ row.version }} · {{ row.author || '未知作者' }}</div>
        </template>
      </el-table-column>
      <el-table-column prop="description" label="说明" min-width="220" show-overflow-tooltip />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag v-if="!row.installed" type="info" size="small">未安装</el-tag>
          <el-tag v-else :type="row.status === 1 ? 'success' : 'warning'" size="small">
            {{ row.status === 1 ? '已启用' : '已禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="组成" width="160">
        <template #default="{ row }">
          <span>菜单 {{ row.menus.length }}</span>
          <el-divider direction="vertical" />
          <span>接口 {{ row.routes.length }}</span>
          <div class="plugin-sub">
            页面 {{ row.has_ui ? (row.ui_installed ? '已就位' : '待安装') : '无' }}
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="install_time" label="安装时间" width="170" />
      <el-table-column label="操作" width="230" fixed="right">
        <template #default="{ row }">
          <el-button v-if="!row.installed" link type="primary" @click="handleInstall(row)">安装</el-button>
          <template v-else>
            <el-button link type="primary" @click="openConfig(row)">配置</el-button>
            <el-button v-if="row.status === 1" link type="warning" @click="handleSwitch(row, false)">禁用</el-button>
            <el-button v-else link type="success" @click="handleSwitch(row, true)">启用</el-button>
            <el-button link type="danger" @click="handleUninstall(row)">卸载</el-button>
          </template>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        layout="total, prev, pager, next"
        @change="load"
      />
    </div>

    <!-- 插件配置 -->
    <el-dialog v-model="configVisible" :title="`插件配置 · ${current.title}`" width="520px" destroy-on-close>
      <el-form v-if="schema.length" ref="formRef" :model="form" label-width="120px">
        <el-form-item v-for="item in schema" :key="item.key" :label="item.label">
          <el-input
            v-if="item.type === 'text'"
            v-model="form[item.key]"
            :placeholder="item.tip"
            clearable
          />
          <el-input
            v-else-if="item.type === 'textarea'"
            v-model="form[item.key]"
            type="textarea"
            :rows="3"
            :placeholder="item.tip"
          />
          <el-input-number v-else-if="item.type === 'number'" v-model="form[item.key]" />
          <el-switch
            v-else-if="item.type === 'switch'"
            v-model="form[item.key]"
            :active-value="1"
            :inactive-value="0"
          />
          <el-select v-else-if="item.type === 'select'" v-model="form[item.key]">
            <el-option v-for="(label, value) in item.options" :key="value" :label="label" :value="value" />
          </el-select>
          <div v-if="item.tip && item.type !== 'text'" class="plugin-tip">{{ item.tip }}</div>
        </el-form-item>
      </el-form>
      <el-empty v-else description="该插件无可配置项" />
      <template #footer>
        <el-button @click="configVisible = false">取消</el-button>
        <el-button v-if="schema.length" type="primary" @click="handleSaveConfig">保存配置</el-button>
      </template>
    </el-dialog>

    <!-- 钩子一览 -->
    <el-dialog v-model="hooksVisible" title="已注册钩子" width="460px">
      <el-table :data="hooks" size="small" border>
        <el-table-column prop="event" label="事件" min-width="200" />
        <el-table-column prop="listeners" label="监听器" width="100" />
      </el-table>
      <el-empty v-if="!hooks.length" description="暂无插件监听任何事件" />
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh } from '@element-plus/icons-vue'
import {
  pluginList,
  pluginInstall,
  pluginUninstall,
  pluginEnable,
  pluginDisable,
  pluginConfig,
  pluginSaveConfig,
  pluginHooks,
} from '@/api'

const query = reactive({ page: 1, limit: 20, keyword: '', status: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)
const overview = ref({ total: 0, installed: 0, enabled: 0, upgradable: 0 })

const configVisible = ref(false)
const schema = ref([])
const form = reactive({})
const current = ref({})

const hooksVisible = ref(false)
const hooks = ref([])

async function load() {
  loading.value = true
  try {
    const params = { ...query }
    if (params.status === '') delete params.status
    const data = await pluginList(params)
    list.value = data.list
    total.value = data.total
    overview.value = data.overview || overview.value
  } finally {
    loading.value = false
  }
}

async function handleInstall(row) {
  await pluginInstall(row.name)
  ElMessage.success('安装成功')
  if (row.has_ui) {
    ElMessage.warning('插件含前端页面，请重新构建前端（npm run build）后访问')
  }
  load()
}

async function handleUninstall(row) {
  await ElMessageBox.confirm(`确认卸载插件「${row.title}」？将删除其菜单与页面。`, '提示', { type: 'warning' })
  await pluginUninstall(row.name)
  ElMessage.success('已卸载')
  load()
}

async function handleSwitch(row, enable) {
  if (enable) {
    await pluginEnable(row.name)
    ElMessage.success('已启用')
  } else {
    await pluginDisable(row.name)
    ElMessage.success('已禁用')
  }
  load()
}

async function openConfig(row) {
  current.value = row
  const data = await pluginConfig(row.name)
  schema.value = data.schema || []
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, data.values || {})
  // switch 需要 0/1
  schema.value.forEach((item) => {
    if (item.type === 'switch') form[item.key] = Number(form[item.key] ?? 0) ? 1 : 0
    if (item.type === 'number') form[item.key] = Number(form[item.key] ?? 0)
  })
  configVisible.value = true
}

async function handleSaveConfig() {
  await pluginSaveConfig(current.value.name, { ...form })
  ElMessage.success('配置已保存')
  configVisible.value = false
  load()
}

async function loadHooks() {
  hooks.value = await pluginHooks()
  hooksVisible.value = true
}

onMounted(load)
</script>

<style scoped>
.stat-row {
  display: flex;
  gap: 16px;
  margin: 16px 0;
}
.stat-card {
  flex: 1;
  text-align: center;
}
.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: var(--el-color-primary);
}
.stat-value.success {
  color: #67c23a;
}
.stat-value.warn {
  color: #e6a23c;
}
.stat-label {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}
.plugin-title {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}
.plugin-sub {
  margin-top: 2px;
  font-size: 12px;
  color: #909399;
}
.plugin-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}
</style>
