import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5183,
    proxy: {
      // 所有 /admin /uploads /api 请求代理到后端
      '/admin': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/uploads': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
  build: {
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return
          // Element Plus 及其依赖
          if (id.includes('element-plus') || id.includes('@element-plus')) return 'element-plus'
          // Vue 全家桶
          if (id.includes('@vue/') || id.includes('vue-router') || id.includes('pinia')) return 'vue-vendor'
          // 图表库
          if (id.includes('echarts') || id.includes('zrender')) return 'echarts'
          // 图标库
          if (id.includes('@element-plus/icons-vue')) return 'icons'
          // 其余第三方归入 vendor
          return 'vendor'
        },
      },
    },
  },
})
