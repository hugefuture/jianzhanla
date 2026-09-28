<template>
  <div class="page-card">
    <!-- 字典类型 -->
    <div class="search-bar">
      <el-input v-model="typeQuery.name" placeholder="字典名称" clearable @keyup.enter="loadTypes" />
      <el-input v-model="typeQuery.type" placeholder="类型标识" clearable @keyup.enter="loadTypes" />
      <el-button type="primary" :icon="Search" @click="loadTypes">查询</el-button>
      <el-button v-if="hasPerm('system:dict:save')" type="primary" plain :icon="Plus" @click="openTypeDialog()">新增类型</el-button>
    </div>

    <el-table :data="typeList" v-loading="typeLoading" border stripe highlight-current-row @current-change="(row) => (currentType = row)">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="字典名称" min-width="140" />
      <el-table-column prop="type" label="类型标识" min-width="160">
        <template #default="{ row }">
          <el-link type="primary" @click="openDataDrawer(row)">{{ row.type }}</el-link>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="120" />
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="140" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:dict:save')" link type="primary" @click="openTypeDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:dict:delete')" link type="danger" @click="handleTypeDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="typeQuery.page"
        v-model:page-size="typeQuery.limit"
        :total="typeTotal"
        layout="total, prev, pager, next"
        @change="loadTypes"
      />
    </div>

    <!-- 类型弹窗 -->
    <el-dialog v-model="typeDialogVisible" :title="typeForm.id ? '编辑类型' : '新增类型'" width="460px" destroy-on-close>
      <el-form ref="typeFormRef" :model="typeForm" :rules="typeRules" label-width="90px">
        <el-form-item label="字典名称" prop="name">
          <el-input v-model="typeForm.name" />
        </el-form-item>
        <el-form-item label="类型标识" prop="type">
          <el-input v-model="typeForm.type" :disabled="!!typeForm.id" placeholder="如 sys_user_sex" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="typeForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="typeForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="typeDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleTypeSave">确定</el-button>
      </template>
    </el-dialog>

    <!-- 字典数据抽屉 -->
    <el-drawer v-model="dataDrawerVisible" :title="`字典数据 - ${currentType?.name || ''}`" size="620px">
      <div class="search-bar">
        <el-button v-if="hasPerm('system:dict:saveData')" type="primary" :icon="Plus" @click="openDataDialog()">新增数据</el-button>
      </div>
      <el-table :data="dataList" v-loading="dataLoading" border>
        <el-table-column prop="label" label="标签" width="120" />
        <el-table-column prop="value" label="值" width="100" />
        <el-table-column prop="dict_sort" label="排序" width="70" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="100" />
        <el-table-column label="操作" width="140">
          <template #default="{ row }">
            <el-button v-if="hasPerm('system:dict:saveData')" link type="primary" @click="openDataDialog(row)">编辑</el-button>
            <el-button v-if="hasPerm('system:dict:deleteData')" link type="danger" @click="handleDataDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-drawer>

    <!-- 数据弹窗 -->
    <el-dialog v-model="dataDialogVisible" :title="dataForm.id ? '编辑数据' : '新增数据'" width="460px" destroy-on-close>
      <el-form ref="dataFormRef" :model="dataForm" :rules="dataRules" label-width="90px">
        <el-form-item label="标签" prop="label">
          <el-input v-model="dataForm.label" />
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model="dataForm.value" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dataForm.dict_sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="dataForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="dataForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dataDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleDataSave">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import { dictTypeList, dictTypeSave, dictTypeDelete, dictDataList, dictDataSave, dictDataDelete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

// 类型
const typeQuery = reactive({ page: 1, limit: 10, name: '', type: '' })
const typeList = ref([])
const typeTotal = ref(0)
const typeLoading = ref(false)
const currentType = ref(null)

const typeDialogVisible = ref(false)
const typeFormRef = ref()
const typeForm = reactive({ id: null, name: '', type: '', status: 1, remark: '' })
const typeRules = {
  name: [{ required: true, message: '请输入字典名称', trigger: 'blur' }],
  type: [{ required: true, message: '请输入类型标识', trigger: 'blur' }],
}

// 数据
const dataDrawerVisible = ref(false)
const dataList = ref([])
const dataLoading = ref(false)
const dataDialogVisible = ref(false)
const dataForm = reactive({ id: null, dict_type: '', label: '', value: '', dict_sort: 0, status: 1, remark: '' })
const dataRules = {
  label: [{ required: true, message: '请输入标签', trigger: 'blur' }],
  value: [{ required: true, message: '请输入值', trigger: 'blur' }],
}

async function loadTypes() {
  typeLoading.value = true
  try {
    const data = await dictTypeList(typeQuery)
    typeList.value = data.list
    typeTotal.value = data.total
  } finally {
    typeLoading.value = false
  }
}

function openTypeDialog(row) {
  typeDialogVisible.value = true
  Object.assign(typeForm, { id: null, name: '', type: '', status: 1, remark: '' })
  if (row) Object.assign(typeForm, row)
}

async function handleTypeSave() {
  await typeFormRef.value.validate()
  await dictTypeSave(typeForm)
  ElMessage.success('保存成功')
  typeDialogVisible.value = false
  loadTypes()
}

async function handleTypeDelete(row) {
  await ElMessageBox.confirm(`确认删除字典类型「${row.name}」吗？`, '提示', { type: 'warning' })
  await dictTypeDelete([row.id])
  ElMessage.success('删除成功')
  loadTypes()
}

async function openDataDrawer(row) {
  currentType.value = row
  dataDrawerVisible.value = true
  loadData()
}

async function loadData() {
  if (!currentType.value) return
  dataLoading.value = true
  try {
    const data = await dictDataList({ dict_type: currentType.value.type, page: 1, limit: 100 })
    dataList.value = data.list
  } finally {
    dataLoading.value = false
  }
}

function openDataDialog(row) {
  dataDialogVisible.value = true
  Object.assign(dataForm, { id: null, dict_type: currentType.value.type, label: '', value: '', dict_sort: 0, status: 1, remark: '' })
  if (row) Object.assign(dataForm, row)
}

async function handleDataSave() {
  await dataFormRef.value.validate()
  await dictDataSave(dataForm)
  ElMessage.success('保存成功')
  dataDialogVisible.value = false
  loadData()
}

async function handleDataDelete(row) {
  await ElMessageBox.confirm(`确认删除数据「${row.label}」吗？`, '提示', { type: 'warning' })
  await dictDataDelete([row.id])
  ElMessage.success('删除成功')
  loadData()
}

onMounted(loadTypes)
</script>
