<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="customer" class="space-y-6">
            <header>
                <NuxtLink to="/ecommerce/customers" class="text-xs text-(--text-muted) hover:text-(--text-heading)">
                    <i class="ti ti-arrow-left" /> All customers
                </NuxtLink>
                <h1 class="text-xl font-semibold mt-2">
                    {{ customer.firstName || customer.lastName ? `${customer.firstName || ''} ${customer.lastName || ''}`.trim() : customer.email }}
                </h1>
                <p class="text-xs text-(--text-muted) mt-1">{{ customer.email }}</p>
            </header>

            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass-card rounded-2xl p-4">
                    <p class="text-xxs uppercase tracking-widest text-(--text-muted)">Status</p>
                    <p class="text-base font-mono text-(--text-heading) mt-1">{{ customer.isGuest ? 'Guest' : 'Registered' }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4">
                    <p class="text-xxs uppercase tracking-widest text-(--text-muted)">Orders</p>
                    <p class="text-base font-mono text-(--text-heading) mt-1">{{ customer.orderCount ?? 0 }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4">
                    <p class="text-xxs uppercase tracking-widest text-(--text-muted)">Phone</p>
                    <p class="text-base font-mono text-(--text-heading) mt-1 truncate">{{ customer.phone || '-' }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4">
                    <p class="text-xxs uppercase tracking-widest text-(--text-muted)">Last login</p>
                    <p class="text-base font-mono text-(--text-heading) mt-1">{{ formatDate(customer.lastLoginAt) }}</p>
                </div>
            </section>

            <div class="glass-card rounded-2xl p-5 space-y-3">
                <h3 class="text-sm font-semibold text-(--text-heading)">Address book</h3>
                <div v-if="!customer.addresses || customer.addresses.length === 0" class="text-xs text-(--text-muted) py-4 text-center">
                    No saved addresses.
                </div>
                <div v-else v-for="a in customer.addresses" :key="a.id" class="text-xs py-2 border-b border-(--border-color) last:border-0">
                    <div class="text-(--text-heading)">{{ a.label || a.recipientName }}</div>
                    <div class="text-(--text-muted)">{{ a.line1 }}, {{ a.city }} {{ a.postalCode }} - {{ a.country }}</div>
                    <div class="flex gap-1 mt-1">
                        <span v-if="a.isDefaultShipping" class="badge-soft-primary text-xxs">Default shipping</span>
                        <span v-if="a.isDefaultBilling" class="badge-soft-secondary text-xxs">Default billing</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass-card rounded-2xl py-20 text-center">
            <p class="text-sm text-(--text-muted)">Customer not found.</p>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useEcommerce, type EcomCustomerAdmin } from '~/composables/useEcommerce'
import { useRoute } from 'vue-router'

const ec = useEcommerce()
const route = useRoute()

const customer = ref<EcomCustomerAdmin | null>(null)
const loading = ref(true)

const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

onMounted(async () => {
    try {
        const res = await ec.customers.show(String(route.params.id))
        customer.value = res.data
    } finally {
        loading.value = false
    }
})
</script>
