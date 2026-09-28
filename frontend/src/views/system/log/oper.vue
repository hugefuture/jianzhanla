<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.username" placeholder="账号" clearable @keyup.enter="load" />
      <el-select v-model="query.status" placeholder="状态" clearable>
        <el-option label="成功" :value="1" />
        <el-option label="失败" :value="0" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:log:clearOper')" type="danger" plain :icon="Delete" @click="handleClear">清空日志</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="username" label="账号" width="110" />
      <el-table-column prop="module" label="模块" width="90" />
      <el-table-column prop="action" label="操作" min-width="180" show-overflow-tooltip />
      <el-table-column prop="method" label="方式" width="80" />
      <el-table-column prop="url" label="地址" min-width="180" show-overflow-tooltip />
      <el-table-column prop="ip" label="IP" width="130" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '成功' : '失败' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="cost_time" label="耗时(ms)" width="90" />
      <el-table-column prop="create_time" label="时间" width="170" />
      <el-table-column label="操作" width="80" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="detail = row">详情</el-button>
        </template>
      </el-table-column>
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

    <el-dialog v-model="detailVisible" title="操作详情" width="640px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="账号">{{ detail.username }}</el-descriptions-item>
        <el-descriptions-item label="IP">{{ detail.ip }}</el-descriptions-item>
        <el-descriptions-item label="操作" :span="2">{{ detail.action }}</el-descriptions-item>
        <el-descriptions-item label="地址" :span="2">{{ detail.url }}</el-descriptions-item>
        <el-descriptions-item label="耗时">{{ detail.cost_time }} ms</el-descriptions-item>
        <el-descriptions-item label="时间">{{ detail.create_time }}</el-descriptions-item>
        <el-descriptions-item label="错误信息" :span="2">{{ detail.error_msg || '-' }}</el-descriptions-item>
        <el-descriptions-item label="请求参数" :span="2">
          <pre class="param-pre">{{ prettyParams }}</pre>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete } from '@element-plus/icons-vue'
import { operLogList, operLogClear } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, username: '', status: null })
const list = ref([])
const total = ref(0)
const loading = ref(false)
const detail = ref({})

const detailVisible = computed({
  get: () => !!detail.value?.id,
  set: (v) => {
    if (!v) detail.value = {}
  },
})

const prettyParams = computed(() => {
  try {
    return JSON.stringify(JSON.parse(detail.value.params || '{}'), null, 2)
  } catch {
    return detail.value.params || '-'
  }
})

async function load() {
  loading.value = true
  try {
    const data = await operLogList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

async function handleClear() {
  await ElMessageBox.confirm('确认清空全部操作日志吗？此操作不可恢复！', '警告', { type: 'warning' })
  await operLogClear()
  ElMessage.success('已清空')
  load()
}

onMounted(load)
</script>

<style scoped>
.param-pre {
  max-height: 300px;
  overflow: auto;
  margin: 0;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
