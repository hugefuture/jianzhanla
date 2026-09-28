<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.name" placeholder="角色名称" clearable @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:role:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增角色</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="角色名称" width="150" />
      <el-table-column prop="code" label="角色编码" width="120" />
      <el-table-column prop="sort" label="排序" width="70" />
      <el-table-column label="数据范围" width="120">
        <template #default="{ row }">
          {{ scopeOptions[row.data_scope] || '全部数据' }}
        </template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="150" />
      <el-table-column prop="create_time" label="创建时间" width="170" />
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:role:save')" link type="primary" :disabled="row.id === 1" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:role:assignMenus')" link type="primary" @click="openAuth(row)">授权</el-button>
          <el-button v-if="hasPerm('system:role:delete')" link type="danger" :disabled="row.id === 1" @click="handleDelete(row)">删除</el-button>
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

    <!-- 新增/编辑 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑角色' : '新增角色'" width="480px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="角色编码" prop="code">
          <el-input v-model="form.code" :disabled="form.id === 1" placeholder="如 editor" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="数据范围">
          <el-select v-model="form.data_scope" placeholder="请选择数据范围">
            <el-option
              v-for="(label, value) in scopeOptions"
              :key="value"
              :label="label"
              :value="Number(value)"
            />
          </el-select>
          <div class="form-tip">控制该角色能看到哪些部门的数据</div>
        </el-form-item>
        <el-form-item v-if="form.data_scope === 2" label="自定义部门">
          <el-tree
            ref="deptTreeRef"
            :data="deptTree"
            :props="{ label: 'name', children: 'children' }"
            node-key="id"
            show-checkbox
            default-expand-all
            :default-checked-keys="form.dept_ids"
          />
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

    <!-- 授权 -->
    <el-dialog v-model="authVisible" title="分配菜单权限" width="480px">
      <el-tree
        ref="authTreeRef"
        :data="menuTreeData"
        :props="{ label: 'label', children: 'children' }"
        node-key="id"
        show-checkbox
        default-expand-all
        :default-checked-keys="checkedIds"
      />
      <template #footer>
        <el-button @click="authVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAuth">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import {
  roleList, roleRead, roleSave, roleDelete, roleMenuIds, roleAssignMenus,
  menuTree as menuTreeApi, deptList, roleScopeOptions,
} from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ page: 1, limit: 10, name: '' })
const list = ref([])
const total = ref(0)
const loading = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({
  id: null, name: '', code: '', sort: 0, status: 1, remark: '',
  data_scope: 1, dept_ids: [],
})
const scopeOptions = ref({})
const deptTree = ref([])
const deptTreeRef = ref()
const rules = {
  name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入角色编码', trigger: 'blur' }],
}

const authVisible = ref(false)
const authTreeRef = ref()
const authRoleId = ref(null)
const menuTreeData = ref([])
const checkedIds = ref([])

async function load() {
  loading.value = true
  try {
    const data = await roleList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

async function openDialog(row) {
  dialogVisible.value = true
  Object.assign(form, {
    id: null, name: '', code: '', sort: 0, status: 1, remark: '',
    data_scope: 1, dept_ids: [],
  })
  if (!Object.keys(scopeOptions.value).length) {
    scopeOptions.value = await roleScopeOptions()
  }
  if (!deptTree.value.length) {
    const res = await deptList()
    deptTree.value = res.list || res
  }
  if (row) {
    const detail = await roleRead(row.id)
    Object.assign(form, {
      ...detail,
      data_scope: detail.data_scope ?? 1,
      dept_ids: detail.dept_ids ?? [],
    })
  }
}

async function handleSave() {
  await formRef.value.validate()
  const payload = { ...form }
  if (payload.data_scope === 2 && deptTreeRef.value) {
    payload.dept_ids = [
      ...deptTreeRef.value.getCheckedKeys(),
      ...deptTreeRef.value.getHalfCheckedKeys(),
    ]
  }
  await roleSave(payload)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function openAuth(row) {
  authRoleId.value = row.id
  menuTreeData.value = await menuTreeApi()
  checkedIds.value = await roleMenuIds(row.id)
  authVisible.value = true
  await nextTick()
  // 半选的父节点不默认勾选：仅勾选叶子
  const leafIds = []
  const walk = (nodes) => nodes.forEach((n) => (n.children ? walk(n.children) : leafIds.push(n.id)))
  walk(menuTreeData.value)
  authTreeRef.value.setCheckedKeys(checkedIds.value.filter((id) => leafIds.includes(id)))
}

async function handleAuth() {
  // 全选 + 半选的父节点
  const checked = [...authTreeRef.value.getCheckedKeys(), ...authTreeRef.value.getHalfCheckedKeys()]
  await roleAssignMenus({ id: authRoleId.value, menu_ids: checked })
  ElMessage.success('授权成功')
  authVisible.value = false
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除角色「${row.name}」吗？`, '提示', { type: 'warning' })
  await roleDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(async () => {
  load()
  scopeOptions.value = await roleScopeOptions()
})
</script>
