<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.username" placeholder="账号" clearable @keyup.enter="load" />
      <el-input v-model="query.ip" placeholder="IP" clearable @keyup.enter="load" />
      <el-select v-model="query.status" placeholder="状态" clearable>
        <el-option label="成功" :value="1" />
        <el-option label="失败" :value="0" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:log:clearLogin')" type="danger" plain :icon="Delete" @click="handleClear">清空日志</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="username" label="账号" width="120" />
      <el-table-column prop="ip" label="IP" width="140" />
      <el-table-column prop="browser" label="浏览器" width="110" />
      <el-table-column prop="os" label="操作系统" width="110" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '成功' : '失败' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="msg" label="提示" min-width="140" />
      <el-table-column prop="login_time" label="登录时间" width="170" />
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
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete } from '@element-plus/icons-vue'
import { loginLogList, loginLogClear } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, username: '', ip: '', status: null })
const list = ref([])
const total = ref(0)
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const data = await loginLogList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

async function handleClear() {
  await ElMessageBox.confirm('确认清空全部登录日志吗？此操作不可恢复！', '警告', { type: 'warning' })
  await loginLogClear()
  ElMessage.success('已清空')
  load()
}

onMounted(load)
</script>
