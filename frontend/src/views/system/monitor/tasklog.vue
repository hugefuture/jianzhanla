<template>
  <div class="page-card">
    <!-- 任务执行概况 -->
    <el-row :gutter="12" class="stat-row">
      <el-col :span="6">
        <div class="stat-box">
          <div class="stat-label">任务总数</div>
          <div class="stat-value">{{ overview.list.length }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box">
          <div class="stat-label">最近 7 天执行</div>
          <div class="stat-value">{{ totalRuns }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box">
          <div class="stat-label">失败次数</div>
          <div class="stat-value" :class="{ danger: overview.failCount > 0 }">{{ overview.failCount }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-box">
          <div class="stat-label">最近执行时间</div>
          <div class="stat-value small">{{ overview.lastRunAt || '暂无' }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 每个任务一张卡片 -->
    <el-row v-if="overview.list.length" :gutter="12" class="task-row">
      <el-col v-for="t in overview.list" :key="t.task_name" :span="8">
        <div class="task-box">
          <div class="task-head">
            <span class="task-name">{{ t.task_desc }}</span>
            <el-tag :type="t.last_status === 0 ? 'success' : 'danger'" size="small">
              {{ t.last_status === 0 ? '正常' : '失败' }}
            </el-tag>
          </div>
          <div class="task-meta">
            <span>标识 <code>{{ t.task_name }}</code></span>
            <span>执行 {{ t.runs }} 次</span>
            <span v-if="t.fails" class="danger">失败 {{ t.fails }} 次</span>
            <span>平均 {{ t.avg_ms }} ms</span>
          </div>
          <div class="task-time">{{ t.last_time || '尚未执行' }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="search-bar">
      <el-select v-model="query.task_name" placeholder="任务" clearable>
        <el-option v-for="t in taskOptions" :key="t.value" :label="t.label" :value="t.value" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable>
        <el-option label="成功" :value="0" />
        <el-option label="失败" :value="1" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
      <el-button :icon="Refresh" @click="handleReset">重置</el-button>
      <el-button v-if="hasPerm('system:monitor:tasklog')" type="danger" plain :icon="Delete" @click="handleClear">
        清空日志
      </el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="task_desc" label="任务" width="140" />
      <el-table-column prop="task_name" label="标识" width="110">
        <template #default="{ row }"><code>{{ row.task_name }}</code></template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 0 ? 'success' : 'danger'" size="small">
            {{ row.status === 0 ? '成功' : '失败' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="cost_ms" label="耗时(ms)" width="100" />
      <el-table-column prop="error_msg" label="错误信息" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">{{ row.error_msg || '-' }}</template>
      </el-table-column>
      <el-table-column prop="create_time" label="执行时间" width="170" />
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @change="load"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Delete } from '@element-plus/icons-vue'
import { taskLogList, taskOverview, taskLogClear } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, task_name: '', status: null })
const list = ref([])
const total = ref(0)
const loading = ref(false)
const overview = ref({ list: [], lastRunAt: null, failCount: 0 })

const taskOptions = computed(() =>
  overview.value.list.map((t) => ({ value: t.task_name, label: t.task_desc }))
)
const totalRuns = computed(() => overview.value.list.reduce((s, t) => s + t.runs, 0))

async function loadOverview() {
  try {
    overview.value = await taskOverview(7)
  } catch {
    overview.value = { list: [], lastRunAt: null, failCount: 0 }
  }
}

async function load() {
  loading.value = true
  try {
    const data = await taskLogList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  query.page = 1
  load()
}

function handleReset() {
  query.task_name = ''
  query.status = null
  query.page = 1
  load()
}

async function handleClear() {
  await ElMessageBox.confirm('确认清空全部任务日志吗？此操作不可恢复！', '警告', { type: 'warning' })
  await taskLogClear()
  ElMessage.success('已清空')
  loadOverview()
  load()
}

onMounted(async () => {
  await loadOverview()
  load()
})
</script>

<style scoped>
.stat-row {
  margin-bottom: 12px;
}

.stat-box {
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
  padding: 12px 16px;
  background: var(--el-fill-color-lighter);
}

.stat-label {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.stat-value {
  font-size: 22px;
  font-weight: 600;
  margin-top: 4px;
}

.stat-value.small {
  font-size: 15px;
}

.task-row {
  margin-bottom: 16px;
}

.task-box {
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
  padding: 10px 14px;
  margin-bottom: 12px;
}

.task-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.task-name {
  font-weight: 600;
}

.task-meta {
  margin-top: 6px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.task-time {
  margin-top: 4px;
  font-size: 12px;
  color: var(--el-text-color-placeholder);
}

.danger {
  color: var(--el-color-danger);
}
</style>
