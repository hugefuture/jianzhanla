<template>
  <el-sub-menu v-if="item.children?.length" :index="item.path">
    <template #title>
      <el-icon v-if="item.icon"><component :is="item.icon" /></el-icon>
      <span>{{ item.name }}</span>
    </template>
    <!-- 递归渲染任意层级菜单 -->
    <sidebar-item v-for="child in item.children" :key="child.path" :item="child" />
  </el-sub-menu>

  <el-menu-item v-else :index="item.path">
    <el-icon v-if="item.icon"><component :is="item.icon" /></el-icon>
    <template #title>{{ item.name }}</template>
  </el-menu-item>
</template>

<script setup>
// 在 <script setup> 中，SFC 可通过文件名递归引用自身
defineProps({
  item: { type: Object, required: true },
})
</script>
