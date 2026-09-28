<template>
  <div>
    <div class="welcome card">
      <h2>{{ greeting }}，{{ userStore.user.nickname || userStore.user.username }} 👋</h2>
      <p>欢迎使用 建站啦后台管理系统</p>
    </div>

    <el-row :gutter="16" class="stat-row">
      <el-col :span="6" v-for="item in statCards" :key="item.label">
        <div class="stat-card card">
          <div class="stat-icon" :style="{ background: item.color }">
            <el-icon :size="22"><component :is="item.icon" /></el-icon>
          </div>
          <div>
            <div class="stat-value">{{ item.value }}</div>
            <div class="stat-label">{{ item.label }}</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="14">
        <div class="card">
          <h3 class="card-title">最近登录</h3>
          <el-table :data="stats.recent_logins || []" size="default">
            <el-table-column prop="username" label="账号" width="120" />
            <el-table-column prop="ip" label="IP" width="140" />
            <el-table-column prop="browser" label="浏览器" width="100" />
            <el-table-column prop="os" label="系统" width="100" />
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                  {{ row.status === 1 ? '成功' : '失败' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="login_time" label="时间" />
          </el-table>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { getStats } from '@/api'
import { useUserStore } from '@/store/user'

const userStore = useUserStore()
const stats = ref({})

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 6) return '凌晨好'
  if (hour < 12) return '上午好'
  if (hour < 14) return '中午好'
  if (hour < 18) return '下午好'
  return '晚上好'
})

const statCards = computed(() => [
  { label: '用户总数', value: stats.value.user_total ?? '-', icon: 'User', color: '#2563eb' },
  { label: '启用用户', value: stats.value.user_active ?? '-', icon: 'UserFilled', color: '#10b981' },
  { label: '今日登录', value: stats.value.login_today ?? '-', icon: 'Position', color: '#f59e0b' },
  { label: '今日操作', value: stats.value.oper_today ?? '-', icon: 'Document', color: '#8b5cf6' },
])

onMounted(async () => {
  stats.value = await getStats()
})
</script>

<style scoped lang="scss">
.card {
  background: #fff;
  border-radius: 6px;
  padding: 16px;
}

.welcome {
  margin-bottom: 16px;

  h2 {
    margin: 0 0 4px;
    font-size: 20px;
  }

  p {
    margin: 0;
    color: var(--sd-text-secondary);
    font-size: 13px;
  }
}

.stat-row {
  margin-bottom: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;

  .stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .stat-value {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
  }

  .stat-label {
    font-size: 12px;
    color: var(--sd-text-secondary);
  }
}

.card-title {
  margin: 0 0 12px;
  font-size: 15px;
}
</style>
