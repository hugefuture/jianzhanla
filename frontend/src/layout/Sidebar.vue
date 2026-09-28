<template>
  <aside class="sidebar" :class="{ collapsed: appStore.sidebarCollapsed }">
    <div class="logo">
      <span class="logo-mark">建</span>
      <span v-show="!appStore.sidebarCollapsed" class="logo-text">建站啦后台管理系统</span>
    </div>
    <el-scrollbar class="menu-scroll">
      <el-menu
        :default-active="route.path"
        :collapse="appStore.sidebarCollapsed"
        :collapse-transition="false"
        router
        class="sidebar-menu"
      >
        <!-- 首页 -->
        <el-menu-item index="/dashboard">
          <el-icon><HomeFilled /></el-icon>
          <template #title>首页</template>
        </el-menu-item>
        <!-- 动态菜单（支持任意层级） -->
        <sidebar-item v-for="menu in menuTree" :key="menu.path" :item="menu" />
      </el-menu>
    </el-scrollbar>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/store/app'
import { useUserStore } from '@/store/user'
import SidebarItem from './SidebarItem.vue'

const route = useRoute()
const appStore = useAppStore()
const userStore = useUserStore()

/** 把后端菜单树转成带完整路径的树，供 el-menu 使用 */
const menuTree = computed(() => {
  function walk(nodes, parentPath) {
    const result = []
    for (const node of nodes) {
      if (node.type === 'F') continue
      const fullPath = node.path.startsWith('/') ? node.path : `${parentPath}/${node.path}`
      const item = { name: node.name, icon: node.icon, path: fullPath }
      if (node.children?.length && node.type !== 'C') {
        const children = walk(node.children, fullPath)
        if (children.length) item.children = children
      }
      result.push(item)
    }
    return result
  }
  return walk(userStore.menus, '')
})
</script>

<style scoped lang="scss">
.sidebar {
  width: 220px;
  height: 100%;
  background: #fff;
  border-right: 1px solid var(--sd-border);
  display: flex;
  flex-direction: column;
  transition: width 0.2s;

  &.collapsed {
    width: 64px;
  }
}

.logo {
  height: 56px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 16px;
  border-bottom: 1px solid var(--sd-border);
  overflow: hidden;
  flex-shrink: 0;

  .logo-mark {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--sd-primary);
    color: #fff;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .logo-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--sd-primary);
    white-space: nowrap;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.menu-scroll {
  flex: 1;
}

.sidebar-menu {
  border-right: none;
}
</style>
