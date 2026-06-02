<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header>
                <h1 class="text-xl font-semibold">Storefront customers</h1>
                <p class="text-xs text-(--text-muted) mt-1">Registered shoppers and guest checkouts.</p>
            </header>

            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-80">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search email or name..." class="form-control pl-9"
                        @input="onSearch" />
                </div>
                <label class="flex items-center gap-2 text-xs text-(--text-muted)">
                    <input v-model="excludeGuests" type="checkbox" @change="load" /> Exclude guests
                </label>
            </section>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="customers.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-users-off text-4xl text-(--text-muted)" />
                <p class="text-sm text-(--text-muted) mt-3">No shoppers yet.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="c in customers" :key="c.id" :to="`/ecommerce/customers/${c.id}`"
                    class="glass-card rounded-2xl p-5 flex items-center gap-4 hover:border-(--color-primary)/40 transition-colors">
                    <div class="w-12 h-12 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center font-semibold">
                        {{ initials(c) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold text-(--text-heading) truncate">
                            {{ c.firstName || c.lastName ? `${c.firstName || ''} ${c.lastName || ''}`.trim() : c.email }}
                        </h3>
                        <p class="text-xxs text-(--text-muted) truncate">{{ c.email }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span v-if="c.isGuest" class="badge-soft-secondary text-xxs">Guest</span>
                            <span v-else class="badge-soft-primary text-xxs">Registered</span>
                            <span class="text-xxs text-(--text-muted)">{{ c.orderCount ?? 0 }} orders</span>
                        </div>
                    </div>
                </NuxtLink>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useEcommerce, type EcomCustomerAdmin } from '~/composables/useEcommerce'

const ec = useEcommerce()

const search = ref('')
const excludeGuests = ref(false)
const loading = ref(true)
const customers = ref<EcomCustomerAdmin[]>([])

const initials = (c: EcomCustomerAdmin) => {
    const f = (c.firstName || c.email || '?')[0] || '?'
    const l = (c.lastName || '')[0] || ''
    return (f + l).toUpperCase()
}

let timer: ReturnType<typeof setTimeout> | null = null
function onSearch() {
    if (timer) clearTimeout(timer)
    timer = setTimeout(load, 250)
}

async function load() {
    loading.value = true
    try {
        const res = await ec.customers.list({
            search: search.value || undefined,
            exclude_guests: excludeGuests.value || undefined,
            limit: 30,
        })
        customers.value = res.data ?? []
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
