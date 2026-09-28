<template>
  <div class="page-card" v-loading="loading">
    <div class="search-bar">
      <span class="monitor-title">服务器与数据库状态</span>
      <el-button type="primary" :icon="Refresh" @click="load">刷新</el-button>
    </div>

    <el-row :gutter="16">
      <!-- 服务器信息 -->
      <el-col :span="12">
        <el-card shadow="never" header="服务器">
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="操作系统">{{ info.server?.os }}</el-descriptions-item>
            <el-descriptions-item label="PHP 版本">{{ info.server?.php }}</el-descriptions-item>
            <el-descriptions-item label="ThinkPHP">{{ info.server?.thinkphp }}</el-descriptions-item>
            <el-descriptions-item label="服务器时间">{{ info.server?.time }}（{{ info.server?.timezone }}）</el-descriptions-item>
            <el-descriptions-item label="系统负载">
              {{ info.server?.load ? info.server.load.join(' / ') : '（Windows 不支持）' }}
            </el-descriptions-item>
            <el-descriptions-item label="已加载扩展">{{ info.server?.extensions }} 个</el-descriptions-item>
            <el-descriptions-item label="内存限制">{{ info.server?.ini?.memory_limit }}</el-descriptions-item>
            <el-descriptions-item label="上传限制">
              {{ info.server?.ini?.upload_max_filesize }} / POST {{ info.server?.ini?.post_max_size }}
            </el-descriptions-item>
            <el-descriptions-item label="uploads 目录">{{ info.server?.uploads?.files }} 个文件 / {{ info.server?.uploads?.size_text }}</el-descriptions-item>
            <el-descriptions-item label="runtime 目录">{{ info.server?.runtime?.files }} 个文件 / {{ info.server?.runtime?.size_text }}</el-descriptions-item>
          </el-descriptions>

          <div class="disk-block" v-if="info.server?.disk?.total">
            <div class="disk-label">
              磁盘使用
              <span class="disk-text">
                {{ fmtSize(info.server.disk.used) }} / {{ fmtSize(info.server.disk.total) }}（剩余 {{ fmtSize(info.server.disk.free) }}）
              </span>
            </div>
            <el-progress
              :percentage="info.server.disk.percent"
              :color="diskColor(info.server.disk.percent)"
              :stroke-width="14"
            />
          </div>
        </el-card>
      </el-col>

      <!-- MySQL 信息 -->
      <el-col :span="12">
        <el-card shadow="never" header="MySQL">
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="版本">{{ info.mysql?.version }}</el-descriptions-item>
            <el-descriptions-item label="数据库">{{ info.mysql?.database }}</el-descriptions-item>
            <el-descriptions-item label="数据表">{{ info.mysql?.table_count }} 张 / {{ info.mysql?.size_text }}</el-descriptions-item>
            <el-descriptions-item label="运行时长">{{ info.mysql?.uptime_text }}</el-descriptions-item>
            <el-descriptions-item label="连接数">
              {{ info.mysql?.connections }} / {{ info.mysql?.max_connections }}（活跃 {{ info.mysql?.threads_running }}）
            </el-descriptions-item>
            <el-descriptions-item label="总查询数">{{ fmtNum(info.mysql?.total_queries) }}</el-descriptions-item>
            <el-descriptions-item label="QPS">{{ info.mysql?.qps }}</el-descriptions-item>
            <el-descriptions-item label="慢查询">
              <span :class="{ 'slow-warn': (info.mysql?.slow_queries || 0) > 0 }">{{ info.mysql?.slow_queries }}</span>
            </el-descriptions-item>
          </el-descriptions>

          <div class="disk-block" v-if="info.mysql?.max_connections">
            <div class="disk-label"><span class="disk-text">连接占用</span></div>
            <el-progress
              :percentage="connPercent"
              :color="diskColor(connPercent)"
              :stroke-width="14"
            />
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { monitorInfo } from '@/api'

const info = ref({})
const loading = ref(false)

const connPercent = computed(() => {
  const m = info.value.mysql
  if (!m || !m.max_connections) return 0
  return Math.min(100, Math.round((m.connections / m.max_connections) * 1000) / 10)
})

function diskColor(p) {
  if (p >= 90) return '#F56C6C'
  if (p >= 75) return '#E6A23C'
  return '#409EFF'
}

function fmtSize(bytes) {
  if (bytes == null) return '-'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let v = Number(bytes), i = 0
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++ }
  return `${v.toFixed(2)} ${units[i]}`
}

function fmtNum(n) {
  if (n == null) return '-'
  return Number(n).toLocaleString()
}

async function load() {
  loading.value = true
  try {
    // request 拦截器已解包 res.data，此处直接拿到 { server, mysql }
    const data = await monitorInfo()
    info.value = data || {}
  } catch (e) {
    ElMessage.error(e.message || '加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.monitor-title {
  font-size: 15px;
  font-weight: 600;
}
.disk-block {
  margin-top: 16px;
}
.disk-label {
  font-size: 13px;
  color: #606266;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
}
.disk-text {
  color: #909399;
  font-size: 12px;
}
.slow-warn {
  color: #F56C6C;
  font-weight: 600;
}
</style>
