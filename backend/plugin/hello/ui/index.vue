<template>
  <div class="page-card">
    <el-alert
      type="success"
      :closable="false"
      show-icon
      title="这是由 Hello 插件注册的业务页面"
      description="页面文件由插件安装时拷贝到 frontend/src/views/plugin/hello/，菜单与路由由插件清单自动注册。"
    />

    <el-descriptions :column="2" border class="mt-16">
      <el-descriptions-item label="插件标识">{{ info.name }}</el-descriptions-item>
      <el-descriptions-item label="插件名称">{{ info.title }}</el-descriptions-item>
      <el-descriptions-item label="版本">{{ info.version }}</el-descriptions-item>
      <el-descriptions-item label="作者">{{ info.author }}</el-descriptions-item>
      <el-descriptions-item label="服务器时间">{{ info.server_time }}</el-descriptions-item>
      <el-descriptions-item label="展示风格">{{ info.config?.theme }}</el-descriptions-item>
    </el-descriptions>

    <div class="search-bar mt-16">
      <el-input v-model="name" placeholder="你的名字（可选）" clearable @keyup.enter="loadGreet" />
      <el-button type="primary" :icon="Refresh" @click="loadGreet">调用插件接口</el-button>
    </div>

    <el-card shadow="never" class="mt-16">
      <div class="greet-text">{{ greet.text }}</div>
      <div v-if="greet.time" class="greet-time">{{ greet.time }}</div>
      <el-table :data="greet.items || []" size="small" class="mt-16">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="label" label="条目" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import request from '@/utils/request'

const info = ref({ name: '', title: '', version: '', author: '', server_time: '', config: {} })
const greet = reactive({ text: '', time: '', items: [] })
const name = ref('')

async function loadInfo() {
  const res = await request.get('/admin/plugin/hello/index')
  info.value = res || {}
}

async function loadGreet() {
  try {
    const res = await request.get('/admin/plugin/hello/greet', { params: { name: name.value } })
    greet.text = res?.text || ''
    greet.time = res?.time || ''
    greet.items = res?.items || []
  } catch (e) {
    ElMessage.error(e?.message || '插件接口调用失败')
  }
}

onMounted(async () => {
  await loadInfo()
  await loadGreet()
})
</script>

<style scoped>
.mt-16 {
  margin-top: 16px;
}
.greet-text {
  font-size: 18px;
  font-weight: 600;
  color: var(--el-color-primary);
}
.greet-time {
  margin-top: 6px;
  font-size: 12px;
  color: #909399;
}
</style>
