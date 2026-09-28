<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.name" placeholder="岗位名称" clearable @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:post:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增岗位</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="岗位名称" width="150" />
      <el-table-column prop="code" label="岗位编码" width="120" />
      <el-table-column prop="order_num" label="排序" width="80" />
      <el-table-column prop="remark" label="备注" min-width="150" />
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:post:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:post:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑岗位' : '新增岗位'" width="460px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="岗位名称" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="岗位编码" prop="code">
          <el-input v-model="form.code" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.order_num" :min="0" />
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
import { postList, postSave, postDelete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, name: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, name: '', code: '', order_num: 0, status: 1, remark: '' })
const rules = {
  name: [{ required: true, message: '请输入岗位名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入岗位编码', trigger: 'blur' }],
}

async function load() {
  loading.value = true
  try {
    const data = await postList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function openDialog(row) {
  dialogVisible.value = true
  Object.assign(form, { id: null, name: '', code: '', order_num: 0, status: 1, remark: '' })
  if (row) Object.assign(form, row)
}

async function handleSave() {
  await formRef.value.validate()
  await postSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除岗位「${row.name}」吗？`, '提示', { type: 'warning' })
  await postDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(load)
</script>
