<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-title">
        <span class="logo-mark">建</span>
        <h2>建站啦后台管理系统</h2>
      </div>
      <el-form ref="formRef" :model="form" :rules="rules" size="large" @keyup.enter="handleLogin">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="账号" :prefix-icon="User" clearable />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="密码" :prefix-icon="Lock" show-password />
        </el-form-item>
        <el-form-item v-if="captcha.enabled" prop="captcha_code">
          <div class="captcha-row">
            <el-input v-model="form.captcha_code" placeholder="验证码" clearable />
            <img class="captcha-img" :src="captcha.image" alt="验证码" title="点击刷新" @click="loadCaptcha" />
          </div>
        </el-form-item>
        <el-button type="primary" class="login-btn" size="large" :loading="loading" @click="handleLogin">
          登 录
        </el-button>
      </el-form>
      <div class="login-tips">默认账号：admin / 123456</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'
import { useUserStore } from '@/store/user'
import { getCaptcha } from '@/api'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const formRef = ref()
const loading = ref(false)
const form = reactive({ username: 'admin', password: '', captcha_id: '', captcha_code: '' })
const captcha = reactive({ enabled: false, image: '' })
const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
  captcha_code: [{ required: true, message: '请输入验证码', trigger: 'blur' }],
}

async function loadCaptcha() {
  try {
    const data = await getCaptcha()
    captcha.enabled = !!data.enabled
    if (data.captcha) {
      captcha.image = data.captcha.image
      form.captcha_id = data.captcha.id
      form.captcha_code = ''
    }
  } catch {
    captcha.enabled = false
  }
}

async function handleLogin() {
  await formRef.value.validate()
  loading.value = true
  try {
    await userStore.login(form)
    ElMessage.success('登录成功')
    router.push(route.query.redirect || '/')
  } catch {
    // 登录失败刷新验证码，避免复用失效的验证码
    if (captcha.enabled) loadCaptcha()
  } finally {
    loading.value = false
  }
}

onMounted(loadCaptcha)
</script>

<style scoped lang="scss">
.login-page {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 60%, #1e3a8a 100%);
}

.login-card {
  width: 380px;
  background: #fff;
  border-radius: 10px;
  padding: 40px 36px 28px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
}

.login-title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-bottom: 28px;

  .logo-mark {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--sd-primary);
    color: #fff;
    font-weight: 700;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  h2 {
    margin: 0;
    font-size: 20px;
    color: var(--sd-text);
  }
}

.login-btn {
  width: 100%;
  margin-top: 4px;
}

.captcha-row {
  display: flex;
  gap: 10px;
  width: 100%;

  .captcha-img {
    height: 40px;
    border: 1px solid var(--sd-border);
    border-radius: 4px;
    cursor: pointer;
    flex-shrink: 0;
  }
}

.login-tips {
  margin-top: 16px;
  text-align: center;
  font-size: 12px;
  color: var(--sd-text-secondary);
}
</style>
