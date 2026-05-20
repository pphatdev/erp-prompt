<template>
  <div class="antialiased min-h-screen font-sans text-(--text-body)">
    <NuxtPage />
    <ToastViewport />
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const applyTheme = (mode: string) => {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  if (mode === 'dark') {
    root.setAttribute('data-bs-theme', 'dark')
  } else {
    root.removeAttribute('data-bs-theme')
  }
}

onMounted(() => {
  tenantStore.initializeTenant()
  authStore.initializeAuth()

  const saved = localStorage.getItem('theme')
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
  applyTheme(saved || (prefersDark ? 'dark' : 'light'))

  setTimeout(() => {
    const path = router.currentRoute.value.path
    if (!authStore.isAuthenticated && path !== '/login') {
      router.push('/login')
    } else if (authStore.isAuthenticated && (path === '/login' || path === '/')) {
      router.push('/dashboard')
    }
  }, 100)
})
</script>
