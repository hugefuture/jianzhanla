import { createRouter, createWebHistory } from 'vue-router'
import Layout from '@/layout/index.vue'

// 固定路由
export const constantRoutes = [
  { path: '/login', component: () => import('@/views/login.vue'), meta: { title: '登录' } },
  {
    path: '/',
    name: 'Layout',
    component: Layout,
    redirect: '/dashboard',
    children: [
      { path: 'dashboard', component: () => import('@/views/dashboard.vue'), meta: { title: '首页', icon: 'HomeFilled' } },
      { path: 'profile', component: () => import('@/views/profile.vue'), meta: { title: '个人中心' } },
    ],
  },
  { path: '/:pathMatch(.*)*', component: () => import('@/views/404.vue'), meta: { title: '404' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes: constantRoutes,
})

export default router
