<template>
  <el-row :gutter="16">
    <el-col :span="14">
      <div class="page-card">
        <h3 class="card-title">基本资料</h3>
        <el-form :model="form" label-width="80px" style="max-width: 420px">
          <el-form-item label="账号">
            <el-input v-model="form.username" disabled />
          </el-form-item>
          <el-form-item label="昵称">
            <el-input v-model="form.nickname" />
          </el-form-item>
          <el-form-item label="手机号">
            <el-input v-model="form.phone" />
          </el-form-item>
          <el-form-item label="邮箱">
            <el-input v-model="form.email" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSaveProfile">保存</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-col>
    <el-col :span="10">
      <div class="page-card">
        <h3 class="card-title">修改密码</h3>
        <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="80px" style="max-width: 420px">
          <el-form-item label="原密码" prop="oldPassword">
            <el-input v-model="pwdForm.oldPassword" type="password" show-password />
          </el-form-item>
          <el-form-item label="新密码" prop="newPassword">
            <el-input v-model="pwdForm.newPassword" type="password" show-password />
          </el-form-item>
          <el-form-item label="确认密码" prop="confirmPassword">
            <el-input v-model="pwdForm.confirmPassword" type="password" show-password />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleChangePwd">修改密码</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-col>
  </el-row>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getProfile, updateProfile, changePassword } from '@/api'
import { useUserStore } from '@/store/user'

const router = useRouter()
const userStore = useUserStore()
const pwdFormRef = ref()

const form = reactive({ username: '', nickname: '', phone: '', email: '' })
const pwdForm = reactive({ oldPassword: '', newPassword: '', confirmPassword: '' })

const pwdRules = {
  oldPassword: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码不能少于 6 位', trigger: 'blur' },
  ],
  confirmPassword: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_, value, cb) => (value === pwdForm.newPassword ? cb() : cb(new Error('两次输入不一致'))),
      trigger: 'blur',
    },
  ],
}

onMounted(async () => {
  const data = await getProfile()
  Object.assign(form, data.user)
})

async function handleSaveProfile() {
  await updateProfile(form)
  ElMessage.success('资料已更新')
  await userStore.fetchInfo()
}

async function handleChangePwd() {
  await pwdFormRef.value.validate()
  await changePassword(pwdForm)
  ElMessage.success('密码已修改，请重新登录')
  await userStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.card-title {
  margin: 0 0 16px;
  font-size: 15px;
}
</style>
