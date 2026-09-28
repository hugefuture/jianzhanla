<template>
  <div class="notfound">
    <h1>404</h1>
    <p>页面不存在</p>
    <el-button type="primary" @click="$router.push('/')">返回首页</el-button>
  </div>
</template>

<style scoped>
.notfound {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
}
.notfound h1 {
  font-size: 72px;
  margin: 0;
  color: var(--sd-primary);
}
</style>
