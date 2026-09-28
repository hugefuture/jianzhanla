<template>
  <div class="page-card">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="远程升级"
      description="从升级源拉取 manifest.json 检查新版本，升级时会自动备份被覆盖的文件与整库 SQL，升级失败自动回滚。执行升级 / 回滚仅限超级管理员。"
    />

    <el-card v-loading="infoLoading" shadow="never" class="version-card">
      <template #header>
        <div class="card-head">
          <span>当前版本</span>
          <div>
            <el-button :icon="Refresh" :loading="checkLoading" @click="handleCheck(true)">检查更新</el-button>
            <el-button v-if="hasPerm('system:upgrade:clearCache')" plain :icon="Delete" @click="handleClearCache">
              清理升级缓存
            </el-button>
          </div>
        </div>
      </template>

      <el-descriptions :column="4" border size="small">
        <el-descriptions-item label="版本号">
          <span class="ver">v{{ info.version || '-' }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="构建号">{{ info.build || '-' }}</el-descriptions-item>
        <el-descriptions-item label="发布通道">
          <el-tag size="small" :type="info.channel === 'stable' ? 'success' : 'warning'">
            {{ info.channel || '-' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="发布日期">{{ info.release || '-' }}</el-descriptions-item>
        <el-descriptions-item label="应用标识">{{ info.appid || '-' }}</el-descriptions-item>
        <el-descriptions-item label="升级源">
          <span class="mono">{{ info.remote || '未配置' }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="包签名">
          <el-tag size="small" :type="info.signed ? 'success' : 'info'">
            {{ info.signed ? '已启用 HMAC 校验' : '仅 sha256 校验' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="最近检查">{{ checkedAt || '未检查' }}</el-descriptions-item>
      </el-descriptions>
    </el-card>

    <el-card v-if="packages.length" shadow="never" class="pkg-card">
      <template #header><span>可升级版本（{{ packages.length }}）</span></template>
      <el-table :data="packages" border stripe size="small">
        <el-table-column prop="version" label="版本" width="100">
          <template #default="{ row }"><span class="ver">v{{ row.version }}</span></template>
        </el-table-column>
        <el-table-column label="体积" width="100">
          <template #default="{ row }">{{ humanSize(row.size) }}</template>
        </el-table-column>
        <el-table-column prop="released_at" label="发布时间" width="120" />
        <el-table-column label="签名" width="110">
          <template #default="{ row }">
            <el-tag v-if="row.signature_ok === true" size="small" type="success">已验证</el-tag>
            <el-tag v-else-if="row.signature_ok === false" size="small" type="danger">不通过</el-tag>
            <el-tag v-else size="small" type="info">未启用</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="更新内容" min-width="260">
          <template #default="{ row }">
            <ul v-if="(row.changelog || []).length" class="changelog">
              <li v-for="(c, i) in row.changelog" :key="i">{{ c }}</li>
            </ul>
            <span v-else class="muted">未提供</span>
          </template>
        </el-table-column>
        <el-table-column v-if="hasPerm('system:upgrade:apply')" label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              :loading="applying === row.version"
              :disabled="row.signature_ok === false"
              @click="handleApply(row)"
            >
              升级到此版本
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-empty v-else-if="checked && !checkLoading" description="已是最新版本，或升级源中暂无可用包" :image-size="80" />

    <el-card shadow="never">
      <template #header><span>升级记录</span></template>
      <el-table :data="list" v-loading="historyLoading" border stripe size="small">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="版本" min-width="160">
          <template #default="{ row }">
            v{{ row.from_version || '-' }} <el-icon><Right /></el-icon> v{{ row.to_version }}
          </template>
        </el-table-column>
        <el-table-column prop="channel" label="通道" width="90" />
        <el-table-column label="文件 / SQL" width="120">
          <template #default="{ row }">{{ row.file_count }} / {{ row.sql_count }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="statusType(row.status)">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="耗时" width="90">
          <template #default="{ row }">{{ row.cost_ms }} ms</template>
        </el-table-column>
        <el-table-column label="错误信息" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ row.error_msg || '-' }}</template>
        </el-table-column>
        <el-table-column prop="create_time" label="时间" width="170" />
        <el-table-column v-if="hasPerm('system:upgrade:rollback')" label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 0">
              <el-button link type="warning" @click="handleRollback(row, false)">回滚文件</el-button>
              <el-button link type="danger" @click="handleRollback(row, true)">含数据库</el-button>
            </template>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="query.page"
          v-model:page-size="query.limit"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="loadHistory"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Delete, Right } from '@element-plus/icons-vue'
import {
  upgradeApply,
  upgradeCheck,
  upgradeClearCache,
  upgradeHistory,
  upgradeInfo,
  upgradeRollback,
} from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const info = ref({})
const packages = ref([])
const list = ref([])
const total = ref(0)
const query = reactive({ page: 1, limit: 10 })
const checked = ref(false)
const checkedAt = ref('')
const infoLoading = ref(false)
const checkLoading = ref(false)
const historyLoading = ref(false)
const applying = ref('')

const statusType = (s) => ({ 0: 'success', 1: 'danger', 2: 'warning', 3: 'danger' }[s] ?? 'info')

function humanSize(bytes) {
  const n = Number(bytes || 0)
  if (!n) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let v = n
  let i = 0
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return `${v.toFixed(1)} ${units[i]}`
}

async function loadInfo() {
  infoLoading.value = true
  try {
    info.value = await upgradeInfo()
  } finally {
    infoLoading.value = false
  }
}

async function handleCheck(force = false) {
  checkLoading.value = true
  try {
    const data = await upgradeCheck(force)
    packages.value = data.packages || []
    checkedAt.value = data.checked_at
    checked.value = true
    ElMessage.success(data.upgradable ? `发现新版本 v${data.latest}` : '已是最新版本')
  } finally {
    checkLoading.value = false
  }
}

async function handleApply(row) {
  if (row.signature_ok === false) {
    ElMessage.warning('包签名校验不通过，已禁止升级')
    return
  }
  await ElMessageBox.confirm(
    `即将升级到 v${row.version}。升级前会自动备份被覆盖的文件${row.sql_count ? '与数据库' : ''}，失败自动回滚。确认继续？`,
    '升级确认',
    { type: 'warning' }
  )
  applying.value = row.version
  try {
    const res = await upgradeApply(row.version)
    ElMessage.success(`已升级到 v${res.version}（文件 ${res.file_count} / SQL ${res.sql_count}）`)
    packages.value = []
    await Promise.all([loadInfo(), loadHistory()])
  } finally {
    applying.value = ''
  }
}

async function handleRollback(row, withDb) {
  await ElMessageBox.confirm(
    withDb
      ? `将把文件与数据库一并还原到 v${row.from_version}。此后对数据库的改动会丢失，确认继续？`
      : `将把文件还原到 v${row.from_version}（不动数据库），确认继续？`,
    '回滚确认',
    { type: 'warning' }
  )
  const res = await upgradeRollback(row.id, withDb)
  ElMessage.success(`已回滚 ${res.restored} 个文件到 v${res.version}`)
  await Promise.all([loadInfo(), loadHistory()])
}

async function handleClearCache() {
  await ElMessageBox.confirm('清理下载缓存与运行时缓存，不影响已安装的升级包升级结果。确认清理？', '提示', {
    type: 'info',
  })
  const res = await upgradeClearCache()
  ElMessage.success(`已清理 ${res.cleared} 个文件`)
}

async function loadHistory() {
  if (!hasPerm('system:upgrade:check')) return
  historyLoading.value = true
  try {
    const data = await upgradeHistory(query)
    list.value = data.list || []
    total.value = data.total || 0
  } finally {
    historyLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadInfo(), loadHistory()])
  await handleCheck()
})
</script>

<style scoped lang="scss">
.version-card {
  margin-top: 12px;
}
.pkg-card {
  margin-top: 12px;
}
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.ver {
  font-weight: 600;
  color: var(--sd-primary);
}
.mono {
  font-family: Consolas, Monaco, monospace;
  font-size: 12px;
}
.changelog {
  margin: 0;
  padding-left: 16px;
  li {
    line-height: 1.7;
  }
}
.muted {
  color: var(--sd-text-light, #9ca3af);
}
.pagination-wrap {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}
</style>
