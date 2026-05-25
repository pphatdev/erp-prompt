<template>
    <div class="min-h-screen flex items-center justify-center bg-(--bg-layout)">
        <span
            class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
    router.push(authStore.isAuthenticated ? '/dashboard' : '/login')
})
</script>
