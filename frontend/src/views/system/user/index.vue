<template>
  <div class="page-card">
    <!-- 搜索栏 -->
    <div class="search-bar">
      <el-input v-model="query.username" placeholder="账号" clearable @keyup.enter="load" />
      <el-input v-model="query.nickname" placeholder="昵称" clearable @keyup.enter="load" />
      <el-select v-model="query.dept_id" placeholder="部门" clearable>
        <el-option v-for="d in deptOptions" :key="d.id" :label="d.name" :value="d.id" />
      </el-select>
      <el-select v-model="query.status" placeholder="状态" clearable>
        <el-option label="正常" :value="1" />
        <el-option label="停用" :value="0" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:user:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增用户</el-button>
    </div>

    <!-- 列表 -->
    <el-table :data="list" v-loading="loading" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="username" label="账号" width="120" />
      <el-table-column prop="nickname" label="昵称" width="120" />
      <el-table-column prop="dept_name" label="部门" width="120" />
      <el-table-column label="角色" min-width="140">
        <template #default="{ row }">
          <el-tag v-for="r in row.roles" :key="r.id" size="small" class="role-tag">{{ r.name }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="phone" label="手机号" width="130" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-switch
            v-if="hasPerm('system:user:status')"
            :model-value="row.status === 1"
            :disabled="row.id === 1"
            @change="(v) => toggleStatus(row, v)"
          />
          <el-tag v-else :type="row.status === 1 ? 'success' : 'danger'" size="small">
            {{ row.status === 1 ? '正常' : '停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="last_login_time" label="最后登录" width="160" />
      <el-table-column label="操作" width="240" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:user:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:user:resetPwd')" link type="warning" @click="openResetPwd(row)">重置密码</el-button>
          <el-button v-if="hasPerm('system:user:delete')" link type="danger" :disabled="row.id === 1" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.limit"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @change="load"
      />
    </div>

    <!-- 新增/编辑 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑用户' : '新增用户'" width="560px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="账号" prop="username">
          <el-input v-model="form.username" :disabled="!!form.id" placeholder="登录账号" />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="form.nickname" />
        </el-form-item>
        <el-form-item v-if="!form.id" label="初始密码" prop="password">
          <el-input v-model="form.password" type="password" show-password placeholder="不填默认 123456" />
        </el-form-item>
        <el-form-item label="部门">
          <el-select v-model="form.dept_id" clearable style="width: 100%">
            <el-option v-for="d in deptOptions" :key="d.id" :label="d.name" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="form.phone" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role_ids" multiple style="width: 100%">
            <el-option v-for="r in roleOptions" :key="r.id" :label="r.name" :value="r.id" />
          </el-select>
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

    <!-- 重置密码 -->
    <el-dialog v-model="resetPwdVisible" title="重置密码" width="420px" destroy-on-close>
      <el-form label-width="90px">
        <el-form-item label="账号">
          <el-input :model-value="resetPwdUser.username" disabled />
        </el-form-item>
        <el-form-item label="新密码">
          <el-input v-model="resetPwdValue" placeholder="不少于 6 位" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetPwdVisible = false">取消</el-button>
        <el-button type="primary" @click="handleResetPwd">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import {
  userList, userRead, userSave, userDelete, userStatus, userResetPwd,
  userRoleOptions, userPostOptions, deptTree,
} from '@/api'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()
const hasPerm = userStore.hasPerm

const query = reactive({ page: 1, limit: 10, username: '', nickname: '', dept_id: null, status: null })
const list = ref([])
const total = ref(0)
const loading = ref(false)

const deptOptions = ref([])
const roleOptions = ref([])

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, username: '', nickname: '', password: '', dept_id: null, phone: '', email: '', role_ids: [], status: 1 })
const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
}

const resetPwdVisible = ref(false)
const resetPwdUser = ref({})
const resetPwdValue = ref('')

async function load() {
  loading.value = true
  try {
    const data = await userList(query)
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

async function toggleStatus(row, v) {
  await userStatus({ id: row.id, status: v ? 1 : 0 })
  ElMessage.success('操作成功')
  load()
}

async function openDialog(row) {
  dialogVisible.value = true
  Object.keys(form).forEach((k) => {
    form[k] = k === 'role_ids' ? [] : k === 'status' ? 1 : null
  })
  if (row) {
    const detail = await userRead(row.id)
    Object.assign(form, detail, { role_ids: detail.role_ids || [] })
  }
}

async function handleSave() {
  await formRef.value.validate()
  await userSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

function openResetPwd(row) {
  resetPwdUser.value = row
  resetPwdValue.value = ''
  resetPwdVisible.value = true
}

async function handleResetPwd() {
  if (resetPwdValue.value.length < 6) {
    ElMessage.warning('密码不能少于 6 位')
    return
  }
  await userResetPwd({ id: resetPwdUser.value.id, password: resetPwdValue.value })
  ElMessage.success('密码已重置')
  resetPwdVisible.value = false
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除用户「${row.username}」吗？`, '提示', { type: 'warning' })
  await userDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(async () => {
  load()
  deptTree().then((data) => {
    // 树拍平为下拉
    const flat = []
    const walk = (nodes, prefix) => {
      nodes.forEach((n) => {
        flat.push({ id: n.id, name: prefix ? `${prefix} / ${n.name}` : n.name })
        if (n.children) walk(n.children, flat.at(-1).name)
      })
    }
    walk(data, '')
    deptOptions.value = flat
  })
  userRoleOptions().then((data) => (roleOptions.value = data))
})
</script>

<style scoped>
.role-tag {
  margin-right: 4px;
}
</style>
