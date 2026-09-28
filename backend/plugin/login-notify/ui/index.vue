<template>
  <div class="page-card">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="钩子型插件：不改动任何业务代码"
      description="本插件只监听 user.login.success / user.login.fail 两个系统事件，把登录动态沉淀到插件私有目录。"
    />

    <div class="stat-row">
      <el-card shadow="never" class="stat-card">
        <div class="stat-value">{{ stats.total }}</div>
        <div class="stat-label">总记录</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value success">{{ stats.success }}</div>
        <div class="stat-label">登录成功</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value danger">{{ stats.fail }}</div>
        <div class="stat-label">登录失败</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-value small">{{ stats.last_time || '-' }}</div>
        <div class="stat-label">最近一次</div>
      </el-card>
    </div>

    <div class="search-bar">
      <el-button type="primary" :icon="Refresh" @click="load">刷新</el-button>
      <el-button plain :icon="Delete" @click="handleClear">清空记录</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column label="类型" width="100">
        <template #default="{ row }">
          <el-tag :type="row.type === 'fail' ? 'danger' : 'success'" size="small">
            {{ row.type === 'fail' ? '失败' : '成功' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="username" label="账号" width="140" />
      <el-table-column prop="ip" label="IP" width="140" />
      <el-table-column prop="reason" label="说明" min-width="160" show-overflow-tooltip />
      <el-table-column prop="time" label="时间" width="180" />
    </el-table>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Delete } from '@element-plus/icons-vue'
import request from '@/utils/request'

const stats = ref({ total: 0, success: 0, fail: 0, last_time: '' })
const list = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const s = await request.get('/admin/plugin/login-notify/stats')
    stats.value = s || {}
    const r = await request.get('/admin/plugin/login-notify/recent', { params: { limit: 50 } })
    list.value = Array.isArray(r) ? r : []
  } finally {
    loading.value = false
  }
}

async function handleClear() {
  await ElMessageBox.confirm('确认清空插件记录的登录动态吗？', '提示', { type: 'warning' })
  await request.post('/admin/plugin/login-notify/clear')
  ElMessage.success('已清空')
  load()
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
.stat-value.danger {
  color: #f56c6c;
}
.stat-value.small {
  font-size: 15px;
}
.stat-label {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}
</style>
