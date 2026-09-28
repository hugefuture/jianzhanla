<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.name" placeholder="参数名称" clearable @keyup.enter="load" />
      <el-input v-model="query.key" placeholder="参数键名" clearable @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:config:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增参数</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="参数名称" width="150" />
      <el-table-column prop="key" label="键名" min-width="140" />
      <el-table-column prop="value" label="值" min-width="140" show-overflow-tooltip />
      <el-table-column prop="remark" label="备注" min-width="140" />
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:config:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:config:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        layout="total, prev, pager, next"
        @change="load"
      />
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑参数' : '新增参数'" width="480px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="参数名称" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="键名" prop="key">
          <el-input v-model="form.key" placeholder="如 site_name" />
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model="form.value" type="textarea" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" />
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
import { Search, Plus } from '@element-plus/icons-vue'
import { configList, configSave, configDelete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, name: '', key: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, name: '', key: '', value: '', status: 1, remark: '' })
const rules = {
  name: [{ required: true, message: '请输入参数名称', trigger: 'blur' }],
  key: [{ required: true, message: '请输入键名', trigger: 'blur' }],
}

async function load() {
  loading.value = true
  try {
    const data = await configList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function openDialog(row) {
  dialogVisible.value = true
  Object.assign(form, { id: null, name: '', key: '', value: '', status: 1, remark: '' })
  if (row) Object.assign(form, row)
}

async function handleSave() {
  await formRef.value.validate()
  await configSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除参数「${row.name}」吗？`, '提示', { type: 'warning' })
  await configDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(load)
</script>
