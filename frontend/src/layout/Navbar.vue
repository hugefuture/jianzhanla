<template>
  <header class="navbar">
    <div class="navbar-left">
      <el-icon class="collapse-btn" @click="appStore.toggleSidebar">
        <Expand v-if="appStore.sidebarCollapsed" />
        <Fold v-else />
      </el-icon>
      <el-breadcrumb separator="/">
        <el-breadcrumb-item v-if="route.meta.title">{{ route.meta.title }}</el-breadcrumb-item>
      </el-breadcrumb>
    </div>
    <div class="navbar-right">
      <el-dropdown @command="handleCommand">
        <span class="user-info">
          <el-avatar :size="30" class="user-avatar">{{ (userStore.user.nickname || 'U').charAt(0) }}</el-avatar>
          <span class="user-name">{{ userStore.user.nickname || userStore.user.username }}</span>
          <el-icon><ArrowDown /></el-icon>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">个人中心</el-dropdown-item>
            <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useAppStore } from '@/store/app'
import { useUserStore } from '@/store/user'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const userStore = useUserStore()

async function handleCommand(command) {
  if (command === 'profile') {
    router.push('/profile')
  } else if (command === 'logout') {
    await userStore.logout()
    ElMessage.success('已退出登录')
    router.push('/login')
  }
}
</script>

<style scoped lang="scss">
.navbar {
  height: 56px;
  background: #fff;
  border-bottom: 1px solid var(--sd-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  flex-shrink: 0;
}

.navbar-left {
  display: flex;
  align-items: center;
  gap: 12px;

  .collapse-btn {
    font-size: 20px;
    cursor: pointer;
    color: var(--sd-text-secondary);

    &:hover {
      color: var(--sd-primary);
    }
  }
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  outline: none;

  .user-avatar {
    background: var(--sd-primary);
    font-size: 14px;
  }

  .user-name {
    font-size: 14px;
  }
}
</style>
