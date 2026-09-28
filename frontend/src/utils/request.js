import axios from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, setToken, removeToken } from './auth'
import router from '@/router'

const service = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '',
  timeout: 15000,
})

// 请求拦截：附加 token
service.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`
  }
  return config
})

// 续期：并发请求只触发一次
let refreshing = null

function doRefresh() {
  if (!refreshing) {
    refreshing = axios
      .post((import.meta.env.VITE_API_BASE || '') + '/admin/refresh', {}, {
        headers: { Authorization: `Bearer ${getToken()}` },
      })
      .then((res) => {
        const data = res.data
        if (data.code === 0 && data.data?.token) {
          setToken(data.data.token)
          return data.data.token
        }
        throw new Error(data.msg || '续期失败')
      })
      .finally(() => {
        setTimeout(() => { refreshing = null }, 100)
      })
  }
  return refreshing
}

function toLogin(msg) {
  removeToken()
  ElMessage.error(msg || '登录已失效，请重新登录')
  if (router.currentRoute.value.path !== '/login') {
    router.push('/login')
  }
}

// 响应拦截：统一处理 {code, msg, data}
service.interceptors.response.use(
  (response) => {
    const res = response.data
    if (res.code !== 0) {
      // token 过期：尝试静默续期后重放一次请求
      if (res.code === 401 && /过期|expire/i.test(res.msg || '')) {
        return doRefresh()
          .then((token) => {
            response.config.headers['Authorization'] = `Bearer ${token}`
            return service.request(response.config)
          })
          .catch(() => {
            toLogin('登录已过期，请重新登录')
            return Promise.reject(new Error(res.msg || '登录已过期'))
          })
      }
      if (res.code === 401) {
        toLogin(res.msg)
      } else {
        ElMessage.error(res.msg || '请求失败')
      }
      return Promise.reject(new Error(res.msg || 'Error'))
    }
    return res.data
  },
  (error) => {
    ElMessage.error(error.message || '网络异常')
    return Promise.reject(error)
  }
)

export default service
