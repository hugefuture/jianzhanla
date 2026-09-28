<template>
  <div class="page-card">
    <div class="search-bar">
      <el-input v-model="query.name" placeholder="菜单名称" clearable @keyup.enter="load" />
      <el-button type="primary" :icon="Search" @click="load">查询</el-button>
      <el-button v-if="hasPerm('system:menu:save')" type="primary" plain :icon="Plus" @click="openDialog()">新增菜单</el-button>
    </div>

    <el-table :data="list" v-loading="loading" border row-key="id" :tree-props="{ children: 'children' }" default-expand-all>
      <el-table-column prop="name" label="菜单名称" min-width="160" />
      <el-table-column label="类型" width="80">
        <template #default="{ row }">
          <el-tag :type="typeTag(row.type)" size="small">{{ typeName(row.type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="perms" label="权限标识" min-width="180" />
      <el-table-column prop="path" label="路由地址" min-width="120" />
      <el-table-column prop="component" label="组件路径" min-width="160" />
      <el-table-column prop="order_num" label="排序" width="70" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-if="hasPerm('system:menu:save')" link type="primary" @click="openDialog(row)">编辑</el-button>
          <el-button v-if="hasPerm('system:menu:save') && row.type !== 'F'" link type="primary" @click="openDialog(null, row.id, row.type)">新增下级</el-button>
          <el-button v-if="hasPerm('system:menu:delete')" link type="danger" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑菜单' : '新增菜单'" width="560px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="上级菜单">
          <el-tree-select
            v-model="form.parent_id"
            :data="parentOptions"
            :props="{ label: 'label', children: 'children' }"
            check-strictly
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="菜单类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio value="M">目录</el-radio>
            <el-radio value="C">菜单</el-radio>
            <el-radio value="F">按钮</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="菜单名称" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item v-if="form.type !== 'F'" label="路由地址">
          <el-input v-model="form.path" placeholder="如 system/user" />
        </el-form-item>
        <el-form-item v-if="form.type === 'C'" label="组件路径">
          <el-input v-model="form.component" placeholder="如 system/user/index" />
        </el-form-item>
        <el-form-item v-if="form.type !== 'M'" label="权限标识">
          <el-input v-model="form.perms" placeholder="如 system:user:list" />
        </el-form-item>
        <el-form-item v-if="form.type !== 'F'" label="图标">
          <el-input v-model="form.icon" placeholder="Element Plus 图标名，如 Setting" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.order_num" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item v-if="form.type !== 'F'" label="是否显示">
          <el-switch v-model="form.visible" :active-value="1" :inactive-value="0" />
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
import { menuList, menuTree, menuSave, menuDelete } from '@/api'
import { useUserStore } from '@/store/user'

const hasPerm = useUserStore().hasPerm

const query = reactive({ name: '' })
const list = ref([])
const loading = ref(false)
const allList = ref([])

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive({ id: null, parent_id: 0, name: '', type: 'C', perms: '', path: '', component: '', icon: '', order_num: 0, visible: 1, status: 1 })
const rules = {
  name: [{ required: true, message: '请输入菜单名称', trigger: 'blur' }],
}

/** 上级菜单选项（仅目录/菜单） */
const parentOptions = computed(() => {
  const walk = (nodes) =>
    nodes
      .filter((n) => n.type !== 'F')
      .map((n) => ({ id: n.id, label: n.name, children: n.children ? walk(n.children) : undefined }))
  return [{ id: 0, label: '根目录', children: walk(allList.value) }]
})

function typeName(t) {
  return { M: '目录', C: '菜单', F: '按钮' }[t] || t
}
function typeTag(t) {
  return { M: 'warning', C: 'primary', F: 'info' }[t] || 'info'
}

async function load() {
  loading.value = true
  try {
    const data = await menuList(query)
    list.value = data.list
    allList.value = await menuList({}).then((d) => d.list)
  } finally {
    loading.value = false
  }
}

function openDialog(row, parentId = 0, parentType = 'M') {
  dialogVisible.value = true
  Object.assign(form, {
    id: null, parent_id: parentId, name: '', type: parentType === 'M' ? 'C' : 'F',
    perms: '', path: '', component: '', icon: '', order_num: 0, visible: 1, status: 1,
  })
  if (row) {
    Object.assign(form, row)
  }
}

async function handleSave() {
  await formRef.value.validate()
  await menuSave(form)
  ElMessage.success('保存成功')
  dialogVisible.value = false
  load()
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确认删除菜单「${row.name}」吗？`, '提示', { type: 'warning' })
  await menuDelete([row.id])
  ElMessage.success('删除成功')
  load()
}

onMounted(load)
</script>
