<template>
  <div class="page-card">
    <el-alert
      type="warning"
      :closable="false"
      show-icon
      title="代码生成会直接写入项目目录（backend/app、frontend/src、sql）。生产环境请慎用，建议先预览确认后再写入。"
      style="margin-bottom: 12px"
    />

    <div class="search-bar">
      <el-input v-model="keyword" placeholder="表名 / 表注释" clearable style="width: 240px" @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <span style="margin-left: auto; color: #909399; font-size: 13px">共 {{ tables.length }} 张表</span>
    </div>

    <el-table :data="tables" v-loading="loading" border stripe height="calc(100vh - 320px)">
      <el-table-column prop="name" label="表名" min-width="200" />
      <el-table-column prop="comment" label="注释" min-width="180" show-overflow-tooltip />
      <el-table-column prop="rows" label="行数" width="90" />
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="120" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openDrawer(row)">生成代码</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-drawer v-model="drawerVisible" :title="`代码生成 - ${current.name}`" size="70%" destroy-on-close>
      <el-form :model="opt" label-width="110px" inline class="gen-opt">
        <el-form-item label="模块名称">
          <el-input v-model="opt.title" style="width: 160px" />
        </el-form-item>
        <el-form-item label="后端包名">
          <el-input v-model="opt.pkg" placeholder="biz" style="width: 120px" />
        </el-form-item>
        <el-form-item label="前端目录">
          <el-input v-model="opt.module" placeholder="business" style="width: 140px" />
        </el-form-item>
        <el-form-item label="权限前缀">
          <el-input v-model="opt.perm" placeholder="biz" style="width: 120px" />
        </el-form-item>
        <el-form-item label="父级菜单">
          <el-input v-model="opt.parentMenu" style="width: 160px" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="opt.icon" style="width: 140px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="View" @click="doPreview">刷新预览</el-button>
        </el-form-item>
      </el-form>

      <el-descriptions :column="3" size="small" border style="margin-bottom: 12px" v-if="meta.table">
        <el-descriptions-item label="实体类">{{ meta.model }}</el-descriptions-item>
        <el-descriptions-item label="路由前缀">{{ meta.route }}</el-descriptions-item>
        <el-descriptions-item label="权限标识">{{ meta.perms }}:list</el-descriptions-item>
        <el-descriptions-item label="前端页面">{{ meta.module }}/{{ meta.page }}/index</el-descriptions-item>
        <el-descriptions-item label="后端控制器">{{ meta.pkg }}/{{ meta.model }}</el-descriptions-item>
        <el-descriptions-item label="数据表">{{ meta.table }}</el-descriptions-item>
      </el-descriptions>

      <el-tabs v-model="activeFile" type="card" v-if="files.length">
        <el-tab-pane v-for="(f, i) in files" :key="f.path" :label="fileName(f.path)" :name="String(i)">
          <div class="code-head">
            <code>{{ f.path }}</code>
            <div>
              <el-tag size="small" :type="f.append ? 'warning' : 'success'">
                {{ f.append ? '追加写入' : '新建文件' }}
              </el-tag>
              <el-button link type="primary" style="margin-left: 8px" @click="copy(f.content)">复制</el-button>
            </div>
          </div>
          <pre class="code-block">{{ f.content }}</pre>
        </el-tab-pane>
      </el-tabs>

      <template #footer>
        <div style="text-align: right">
          <el-button @click="drawerVisible = false">关闭</el-button>
          <el-button :icon="Download" @click="doZip">下载 ZIP</el-button>
          <el-button v-if="hasPerm('tool:gen:generate')" type="primary" :icon="MagicStick" @click="doGenerate">
            写入项目
          </el-button>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, View, Download, MagicStick } from '@element-plus/icons-vue'
import { genTables, genPreview, genGenerate, genZip } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const keyword = ref('')
const tables = ref([])
const loading = ref(false)

const drawerVisible = ref(false)
const current = ref({})
const files = ref([])
const meta = ref({})
const activeFile = ref('0')
const opt = reactive({ title: '', pkg: 'biz', module: 'business', perm: 'biz', parentMenu: '业务管理', icon: 'Document' })

async function load() {
  loading.value = true
  try {
    tables.value = await genTables(keyword.value)
  } finally {
    loading.value = false
  }
}

function fileName(p) {
  return p.split('/').pop()
}

async function openDrawer(row) {
  current.value = row
  opt.title = (row.comment || row.name).replace(/(表|信息)$/, '')
  drawerVisible.value = true
  await doPreview()
}

async function doPreview() {
  const data = await genPreview(current.value.name, { ...opt })
  files.value = data.files || []
  meta.value = data.meta || {}
  activeFile.value = '0'
}

async function doGenerate() {
  await ElMessageBox.confirm(
    `确认把代码写入项目目录？已存在的文件会跳过（路由与 API 片段自动追加）。`,
    '写入确认',
    { type: 'warning' }
  )
  const data = await genGenerate({ table: current.value.name, ...opt })
  const written = (data.files || []).filter(f => f.status === 'written').length
  const skipped = (data.files || []).filter(f => f.status === 'skipped').length
  ElMessage.success(`生成完成：写入 ${written} 个，跳过 ${skipped} 个（菜单 SQL 需手工执行）`)
}

async function doZip() {
  const data = await genZip({ table: current.value.name, ...opt })
  if (data?.url) window.open(data.url, '_blank')
}

function copy(text) {
  navigator.clipboard?.writeText(text).then(
    () => ElMessage.success('已复制'),
    () => ElMessage.warning('复制失败，请手动选择')
  )
}

onMounted(load)
</script>

<style scoped>
.gen-opt {
  margin-bottom: 12px;
}
.code-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.code-head code {
  color: #606266;
  font-size: 12px;
}
.code-block {
  background: #f6f8fa;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  padding: 12px;
  max-height: 46vh;
  overflow: auto;
  font-size: 12px;
  line-height: 1.6;
  margin: 0;
}
</style>
