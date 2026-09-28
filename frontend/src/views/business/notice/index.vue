<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.title" placeholder="公告标题" clearable @keyup.enter="load" />
      <el-select v-model="query.type" placeholder="类型" clearable style="width: 120px">
        <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="Number(o.value)" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable style="width: 120px">
        <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="Number(o.value)" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
      <el-button v-if="hasPerm('biz:notice:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增公告</el-button>
      <el-button :icon="Download" @click="handleExport">导出</el-button>
      <el-button :icon="Document" @click="handleTemplate">导入模板</el-button>
      <el-upload
        v-if="hasPerm('biz:notice:save')"
        :action="importAction"
        :headers="uploadHeaders"
        name="file"
        :show-file-list="false"
        accept=".xlsx,.xls,.csv"
        :on-success="onImportDone"
        :on-error="onImportFail"
        style="display: inline-flex; margin-left: 12px"
      >
        <el-button :icon="Upload">导入</el-button>
      </el-upload>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag :type="row.type === 2 ? 'warning' : 'info'" size="small">{{ row.type_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">{{ row.status_text }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="置顶" width="70" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_top === 1" type="danger" size="small">置顶</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="260" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('biz:notice:publish')" link type="primary" @click="handlePublish(row)">
            {{ row.status === 1 ? '下架' : '发布' }}
          </el-button>
          <el-button v-if="hasPerm('biz:notice:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('biz:notice:save')" link type="primary" @click="handleTop(row)">
            {{ row.is_top === 1 ? '取消置顶' : '置顶' }}
          </el-button>
          <el-button v-if="hasPerm('biz:notice:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑公告' : '新增公告'" width="640px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入公告标题" />
        </el-form-item>
        <el-form-item label="类型">
          <el-radio-group v-model="form.type">
            <el-radio :value="1">通知</el-radio>
            <el-radio :value="2">公告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="form.content" type="textarea" :rows="5" placeholder="请输入公告内容" />
        </el-form-item>
        <el-form-item label="置顶">
          <el-switch v-model="form.is_top" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus, Refresh, Download, Upload, Document } from '@element-plus/icons-vue'
import { noticeList, noticeSave, noticeDelete, noticePublish, noticeTop, noticeExport, noticeTemplate, dictDataByType } from '@/api'
import { useUserStore } from '@/store/user'
import { getToken } from '@/utils/auth'

const uploadHeaders = { Authorization: 'Bearer ' + (getToken() || '') }
const importAction = (import.meta.env.VITE_API_BASE || '') + '/admin/biz/notice/import'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, title: '', type: '', status: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)
const typeOptions = ref([])
const statusOptions = ref([])

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, title: '', type: 1, content: '', is_top: 0, remark: '' })
const rules = {
  title: [{ required: true, message: '请输入公告标题', trigger: 'blur' }],
}

async function load() {
  loading.value = true
  try {
    const data = await noticeList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function resetQuery() {
  Object.assign(query, { title: '', type: '', status: '', page: 1 })
  load()
}

function openDialog(row) {
  dialogVisible.value = true
  Object.assign(form, { id: null, title: '', type: 1, content: '', is_top: 0, remark: '' })
  if (row) Object.assign(form, row)
}

async function handleSave() {
  await formRef.value.validate()
  await noticeSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handlePublish(row) {
  const next = row.status === 1 ? 0 : 1
  await noticePublish(row.id, next)
  ElMessage.success(next === 1 ? '已发布' : '已下架')
  load()
}

async function handleTop(row) {
  await noticeTop(row.id, row.is_top === 1 ? 0 : 1)
  ElMessage.success('操作成功')
  load()
}

async function handleExport() {
  const data = await noticeExport(query)
  if (!data?.url) return
  ElMessage.success(`导出成功：${data.rows} 行${data.sheets > 1 ? ` / ${data.sheets} 个 sheet` : ''}`)
  window.open(data.url, '_blank')
}

async function handleTemplate() {
  const data = await noticeTemplate()
  if (data?.url) window.open(data.url, '_blank')
}

function onImportDone(res) {
  if (res?.code === 0) {
    ElMessage.success(res.msg || '导入完成')
    load()
  } else {
    ElMessage.error(res?.msg || '导入失败')
  }
}

function onImportFail() {
  ElMessage.error('导入失败，请检查文件格式')
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除公告「${row.title}」吗？`, '提示', { type: 'warning' })
  await noticeDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(async () => {
  // 下拉选项取自数据字典，字典维护在「系统管理 → 字典管理」
  try {
    typeOptions.value = await dictDataByType('biz_notice_type')
    statusOptions.value = await dictDataByType('biz_notice_status')
  } catch (e) { /* 字典缺失时不影响主流程 */ }
  load()
})
</script>
