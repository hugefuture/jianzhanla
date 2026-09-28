<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="keyword" placeholder="账号/IP 模糊搜索" clearable style="width: 240px" />
      <el-button type="primary" :icon="Refresh" @click="load">刷新</el-button>
    </div>

    <el-table :data="filtered" v-loading="loading" border stripe>
      <el-table-column prop="username" label="账号" width="140" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="ip" label="登录IP" width="140" />
      <el-table-column prop="browser" label="客户端" min-width="260" show-overflow-tooltip />
      <el-table-column prop="login_time" label="登录时间" width="180" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button
            v-if="hasPerm('system:online:kick')"
            link type="danger"
            @click="handleKick(row)"
          >强制下线</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="empty-tip">共 {{ filtered.length }} 个在线会话</div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { onlineList, onlineKick } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const list = ref([])
const loading = ref(false)
const keyword = ref('')

const filtered = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  if (!kw) return list.value
  return list.value.filter(
    (i) => String(i.username).toLowerCase().includes(kw) || String(i.ip).includes(kw)
  )
})

async function load() {
  loading.value = true
  try {
    const data = await onlineList()
    list.value = data.list || []
  } finally {
    loading.value = false
  }
}

async function handleKick(row) {
  await ElMessageBox.confirm(`确认将「${row.username}」的该会话强制下线吗？`, '提示', { type: 'warning' })
  await onlineKick(row.jti)
  ElMessage.success('已强制下线')
  load()
}

onMounted(load)
</script>

<style scoped>
.empty-tip {
  margin-top: 12px;
  font-size: 13px;
  color: var(--sd-text-secondary);
}
</style>
