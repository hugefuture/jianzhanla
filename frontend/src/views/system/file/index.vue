<template>
  <div class="page-card">
    <div class="stat-row">
      <el-card shadow="never" class="stat-card">
        <div class="stat-label">文件总数</div>
        <div class="stat-value">{{ stats.total }}</div>
      </el-card>
      <el-card shadow="never" class="stat-card">
        <div class="stat-label">占用空间</div>
        <div class="stat-value">{{ sizeText(stats.size) }}</div>
      </el-card>
    </div>

    <div class="search-bar">
      <el-input v-model="query.name" placeholder="文件名" clearable style="width: 200px" @keyup.enter="load" />
      <el-input v-model="query.module" placeholder="来源模块" clearable style="width: 140px" @keyup.enter="load" />
      <el-input v-model="query.ext" placeholder="后缀" clearable style="width: 100px" @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
      <el-button v-if="hasPerm('system:file:clear')" type="warning" plain :icon="Delete" @click="handleClearOrphan">
        清理孤儿文件
      </el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="预览" width="70" align="center">
        <template #default="{ row }">
          <el-image
            v-if="isImage(row)"
            :src="row.url"
            :preview-src-list="[row.url]"
            fit="cover"
            style="width: 40px; height: 40px; border-radius: 4px"
          />
          <el-icon v-else :size="24" color="#909399"><Document /></el-icon>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="文件名" min-width="200" show-overflow-tooltip />
      <el-table-column prop="ext" label="类型" width="80" />
      <el-table-column label="大小" width="110">
        <template #default="{ row }">{{ row.size_text }}</template>
      </el-table-column>
      <el-table-column prop="module" label="来源" width="110" />
      <el-table-column prop="create_time" label="上传时间" width="170" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openFile(row)">查看</el-button>
          <el-button v-if="hasPerm('system:file:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @change="load"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Delete, Document } from '@element-plus/icons-vue'
import { fileList, fileDelete, fileStats, fileClearOrphan } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, name: '', module: '', ext: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)
const stats = ref({ total: 0, size: 0 })

const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
const isImage = (row) => IMAGE_EXT.includes((row.ext || '').toLowerCase())

function sizeText(size) {
  const s = Number(size || 0)
  if (s < 1024) return s + ' B'
  if (s < 1024 * 1024) return (s / 1024).toFixed(2) + ' KB'
  if (s < 1024 * 1024 * 1024) return (s / 1024 / 1024).toFixed(2) + ' MB'
  return (s / 1024 / 1024 / 1024).toFixed(2) + ' GB'
}

async function loadStats() {
  try {
    stats.value = await fileStats()
  } catch { /* 忽略 */ }
}

async function load() {
  loading.value = true
  try {
    const data = await fileList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function resetQuery() {
  Object.assign(query, { name: '', module: '', ext: '', page: 1 })
  load()
}

function openFile(row) {
  // 图片直接预览（静态资源），其余走鉴权下载接口 system/file/download
  const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(String(row.ext || '').toLowerCase())
  if (isImage) {
    window.open(row.url, '_blank')
    return
  }
  window.open(fileDownloadUrl(row.id), '_blank')
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除文件「${row.name}」？磁盘文件将一并删除。`, '提示', { type: 'warning' })
  await fileDelete([row.id])
  ElMessage.success('删除成功')
  load()
  loadStats()
}

async function handleClearOrphan() {
  await ElMessageBox.confirm('将扫描 uploads 目录，删除未被数据库记录引用的历史文件（仅 images/files 下的日期目录）。', '提示', { type: 'warning' })
  const res = await fileClearOrphan()
  ElMessage.success(res || '清理完成')
  load()
  loadStats()
}

onMounted(() => {
  load()
  loadStats()
})
</script>

<style scoped>
.stat-row {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}
.stat-card {
  flex: 0 0 180px;
}
.stat-label {
  color: #909399;
  font-size: 13px;
}
.stat-value {
  font-size: 22px;
  font-weight: 600;
  margin-top: 4px;
}
</style>
