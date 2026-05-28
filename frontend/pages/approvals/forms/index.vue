<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header>
                <h1 class="text-xl font-semibold">eApprovals Forms Portal</h1>
                <p class="text-xs text-(--text-muted) mt-1">Select a request type to initiate an approval workflow.</p>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="form in formTypes" :key="form.id" 
                    class="glass-card rounded-xl p-5 hover:-translate-y-1 transition-transform cursor-pointer group border-2 border-transparent hover:border-primary/50"
                    @click="openForm(form.route)">
                    
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center mb-4 transition-colors"
                        :class="`bg-${form.color}/10 text-${form.color} group-hover:bg-${form.color}/20`">
                        <i :class="['ti', form.icon, 'text-2xl']"></i>
                    </div>
                    
                    <h3 class="font-semibold text-lg mb-1">{{ form.title }}</h3>
                    <p class="text-xs text-(--text-muted)">{{ form.description }}</p>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

// Standard ERP request types that trigger workflows
const formTypes = [
    { id: 'leave', title: 'Leave Request', description: 'Request annual, sick, or unpaid leave.', icon: 'ti-calendar-event', color: 'primary', route: '/approvals/forms/leave' },
    { id: 'overtime', title: 'Overtime', description: 'Log extra hours for manager approval.', icon: 'ti-clock', color: 'warning', route: '/hrm/hrm/overtime/new' },
    { id: 'expense', title: 'Expense Claim', description: 'Submit receipts for reimbursement.', icon: 'ti-receipt', color: 'success', route: '/finance/expenses/new' },
    { id: 'purchase', title: 'Purchase Requisition', description: 'Request to buy items or services.', icon: 'ti-shopping-cart', color: 'info', route: '/inventory/purchase-orders/new' },
    { id: 'petty_cash', title: 'Petty Cash', description: 'Small cash advances for operations.', icon: 'ti-cash', color: 'danger', route: '/finance/petty-cash/new' },
    { id: 'appraisal', title: 'Self Appraisal', description: 'Submit performance review goals.', icon: 'ti-clipboard-list', color: 'secondary', route: '/hrm/appraisals/new' },
]

const openForm = (routePath: string) => {
    router.push(routePath)
}
</script>
