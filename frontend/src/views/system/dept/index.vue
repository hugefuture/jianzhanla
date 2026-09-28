<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.name" placeholder="部门名称" clearable @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:dept:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增部门</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border row-key="id" :tree-props="{ children: 'children' }" default-expand-all>
      <el-table-column prop="name" label="部门名称" min-width="180" />
      <el-table-column prop="leader" label="负责人" width="120" />
      <el-table-column prop="phone" label="联系电话" width="140" />
      <el-table-column prop="order_num" label="排序" width="80" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:dept:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:dept:save')" link type="primary" @click="openDialog(null, row.id)">新增下级</el-button>
          <el-button v-if="hasPerm('system:dept:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑部门' : '新增部门'" width="480px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="上级部门">
          <el-tree-select
            v-model="form.parent_id"
            :data="parentOptions"
            :props="{ label: 'label', children: 'children' }"
            check-strictly
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="部门名称" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="负责人">
          <el-input v-model="form.leader" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.phone" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.order_num" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
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
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import { deptList, deptSave, deptDelete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ name: '' })
const list = ref([])
const allList = ref([])
const loading = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, parent_id: 0, name: '', leader: '', phone: '', email: '', order_num: 0, status: 1 })
const rules = { name: [{ required: true, message: '请输入部门名称', trigger: 'blur' }] }

const parentOptions = computed(() => {
  const walk = (nodes) => nodes.map((n) => ({ id: n.id, label: n.name, children: n.children ? walk(n.children) : undefined }))
  return [{ id: 0, label: '根目录', children: walk(allList.value) }]
})

async function load() {
  loading.value = true
  try {
    const data = await deptList(query)
    list.value = data.list
    allList.value = await deptList({}).then((d) => d.list)
  } finally {
    loading.value = false
  }
}

function openDialog(row, parentId = 0) {
  dialogVisible.value = true
  Object.assign(form, { id: null, parent_id: parentId, name: '', leader: '', phone: '', email: '', order_num: 0, status: 1 })
  if (row) Object.assign(form, row)
}

async function handleSave() {
  await formRef.value.validate()
  await deptSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除部门「${row.name}」吗？`, '提示', { type: 'warning' })
  await deptDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(load)
</script>
