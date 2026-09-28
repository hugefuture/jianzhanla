import router from './router'
import { useUserStore } from '@/store/user'
import { getToken } from '@/utils/auth'
import { ElMessage } from 'element-plus'

// 动态路由是否已加载
let routesLoaded = false

// 后端菜单组件 -> 前端视图组件映射
const viewModules = import.meta.glob('@/views/**/*.vue')

function resolveComponent(componentPath) {
  // import.meta.glob(alias) 生成的键是根相对路径，如 /src/views/system/user/index.vue
  const key = `/src/views/${componentPath}.vue`
  if (viewModules[key]) {
    return viewModules[key]
  }
  console.warn('[router] 未找到组件：', componentPath)
  return viewModules['/src/views/404.vue']
}

/** 根据后端菜单树构建动态路由（扁平挂到 Layout 下） */
function buildRoutes(menus, parentPath = '') {
  const routes = []
  for (const menu of menus) {
    if (menu.type === 'F') continue
    let fullPath = menu.path.startsWith('/')
      ? menu.path
      : parentPath
        ? `${parentPath}/${menu.path}`
        : `/${menu.path}`
    if (menu.type === 'C') {
      routes.push({
        path: fullPath,
        component: resolveComponent(menu.component),
        meta: { title: menu.name, icon: menu.icon, perm: menu.perms },
      })
    } else if (menu.type === 'M' && menu.children?.length) {
      routes.push(...buildRoutes(menu.children, fullPath))
    }
  }
  return routes
}

router.beforeEach(async (to) => {
  const userStore = useUserStore()
  const token = getToken()

  if (to.path === '/login') {
    routesLoaded = false
    return true
  }

  if (!token) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (!routesLoaded) {
    try {
      await userStore.fetchInfo()
      const dynamicRoutes = buildRoutes(userStore.menus)
      // 挂到 Layout 的 children 下
      dynamicRoutes.forEach((route) => {
        router.addRoute('Layout', route)
      })
      routesLoaded = true
      // 重新导航以解析新路由
      return { ...to, replace: true }
    } catch (e) {
      userStore.reset()
      return { path: '/login' }
    }
  }

  return true
})
