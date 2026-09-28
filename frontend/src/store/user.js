import { defineStore } from 'pinia'
import { ref } from 'vue'
import { login as loginApi, getInfo as getInfoApi, logout as logoutApi } from '@/api'
import { getToken, setToken, removeToken } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  const token = ref(getToken())
  const user = ref({})
  const roles = ref([])
  const perms = ref([])
  const menus = ref([])

  async function login(form) {
    const data = await loginApi(form)
    token.value = data.token
    setToken(data.token)
  }

  async function fetchInfo() {
    const data = await getInfoApi()
    user.value = data.user
    roles.value = data.roles
    perms.value = data.perms
    menus.value = data.menus
    return data
  }

  async function logout() {
    try {
      await logoutApi()
    } catch (e) {
      // ignore
    }
    reset()
  }

  function reset() {
    token.value = ''
    user.value = {}
    roles.value = []
    perms.value = []
    menus.value = []
    removeToken()
  }

  /** 是否有某权限点 */
  function hasPerm(perm) {
    if (!perm) return true
    return perms.value.includes('*') || perms.value.includes(perm)
  }

  return { token, user, roles, perms, menus, login, fetchInfo, logout, reset, hasPerm }
})
