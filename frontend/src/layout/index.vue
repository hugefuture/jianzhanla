<template>
  <div class="layout">
    <Sidebar />
    <div class="layout-main">
      <Navbar />
      <main class="layout-content">
        <router-view v-slot="{ Component }">
          <component :is="Component" />
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import Sidebar from './Sidebar.vue'
import Navbar from './Navbar.vue'
</script>

<style scoped lang="scss">
.layout {
  display: flex;
  height: 100%;
}

.layout-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.layout-content {
  flex: 1;
  padding: 16px;
  overflow: auto;
  background: var(--sd-bg);
}
</style>
