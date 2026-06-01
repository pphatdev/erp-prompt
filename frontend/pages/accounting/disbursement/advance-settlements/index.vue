<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Advance Settlements</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Settle actuals against an open cash advance. Posts DR Expense (per line) + DR Cash on unused return / CR Employee Advances Receivable. Rolls the advance status forward (open → partially settled → closed).</p>
                </div>
                <button v-if="canSettle" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Settlement
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-receipt-refund text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ postedCount }} posted · {{ cancelledCount }} cancelled</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Settled This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-arrow-down-right text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiMonthAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Applied to receivable</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Settlements today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Unused Returned</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-coin text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiUnusedAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Returned to bank</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'posted' }" @click="setStatusFilter('posted')">
                    <i class="ti ti-check" /> Posted
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'cancelled' }" @click="setStatusFilter('cancelled')">
                    <i class="ti ti-x" /> Cancelled
                </button>
            </section>

            <!-- Loading / Empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading settlements...</span>
            </div>
            <div v-else-if="settlements.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No settlements yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Settle an open cash advance with the employee's actual expenses.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Settlement #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Advance</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Employee</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Settled On</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Actual</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Returned</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Applied</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="s in settlements" :key="s.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': s.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(s.id)">
                                        <i class="ti" :class="expanded.has(s.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(s.id)">
                                        <div class="font-mono font-semibold"
                                            :class="s.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ s.settlementNumber }}
                                        </div>
                                        <div v-if="s.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ s.referenceNumber }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(s.id)">
                                        <div class="font-mono font-semibold text-(--text-heading)">{{ s.cashAdvance?.advanceNumber || '—' }}</div>
                                        <div v-if="s.cashAdvance?.amount != null" class="text-xxs text-(--text-muted) font-mono">{{ s.cashAdvance?.currency }} {{ s.cashAdvance.amount.toFixed(2) }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(s.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ s.cashAdvance?.employee?.fullName || '—' }}</p>
                                        <p v-if="s.cashAdvance?.employee?.employeeId" class="text-xxs text-(--text-muted) font-mono">{{ s.cashAdvance.employee.employeeId }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(s.id)">{{ formatDate(s.settledOn) }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(s.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(s.status)">{{ s.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono cursor-pointer" @click="toggle(s.id)">{{ s.actualAmount.toFixed(2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono cursor-pointer"
                                        :class="s.unusedReturned > 0 ? 'text-(--color-warning)' : 'text-(--text-muted)'"
                                        @click="toggle(s.id)">{{ s.unusedReturned.toFixed(2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(s.id)">{{ s.appliedToAdvance.toFixed(2) }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <button v-if="canSettle && s.isCancellable" type="button" class="action-btn action-btn-danger"
                                            title="Cancel settlement" @click.stop="confirmCancel(s)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(s.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="10" class="px-4 py-3">
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Expense Account</th>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Description</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="l in s.lines" :key="l.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span v-if="l.account">
                                                            <span class="text-(--text-heading)">{{ l.account.code }}</span>
                                                            <span class="text-(--text-muted) ml-2">{{ l.account.name }}</span>
                                                        </span>
                                                        <span v-else class="text-(--text-muted)">—</span>
                                                    </td>
                                                    <td class="py-1.5 text-(--text-body)">{{ l.description || '—' }}</td>
                                                    <td class="text-right py-1.5 font-semibold text-(--text-heading)">{{ l.amount.toFixed(2) }}</td>
                                                </tr>
                                                <tr v-if="s.unusedReturned > 0" class="border-t border-(--border-color)/40 text-(--color-warning)">
                                                    <td class="py-1.5 italic" colspan="2">Unused cash returned to bank ({{ s.bankAccount?.name || '—' }})</td>
                                                    <td class="text-right py-1.5 font-semibold">{{ s.unusedReturned.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="2">Actual Spend</td>
                                                    <td class="text-right py-1.5">{{ s.actualAmount.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="text-(--text-muted)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="2">Cleared off receivable</td>
                                                    <td class="text-right py-1.5 font-semibold">{{ s.appliedToAdvance.toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="s.notes" class="text-xxs text-(--text-muted) mt-3"><strong>Notes:</strong> {{ s.notes }}</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>
        </div>

        <!-- Create Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-4xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Record Advance Settlement</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header -->
                        <div class="grid grid-cols-4 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Settlement # *</label>
                                <input v-model="form.settlement_number" type="text" required maxlength="64"
                                    placeholder="e.g. SETT-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Settled On *</label>
                                <input v-model="form.settled_on" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Method</label>
                                <select v-model="form.payment_method" class="form-control text-xs">
                                    <option :value="null">— None —</option>
                                    <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reference #</label>
                                <input v-model="form.reference_number" type="text" maxlength="64"
                                    placeholder="Receipt batch ref" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <!-- Cash Advance Picker -->
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Cash Advance *</label>
                            <select v-model="form.cash_advance_id" required class="form-control text-xs" :disabled="advancesLoading">
                                <option value="">— Pick open advance —</option>
                                <option v-for="a in openAdvances" :key="a.id" :value="a.id">
                                    {{ a.advanceNumber }} — {{ a.employee?.fullName || 'employee' }} · {{ a.currency }} {{ a.outstandingAmount.toFixed(2) }} outstanding
                                </option>
                            </select>
                            <p v-if="openAdvances.length === 0 && !advancesLoading" class="text-xxs text-(--color-warning) flex items-center gap-1.5">
                                <i class="ti ti-info-circle" />
                                No open advances.
                                <NuxtLink to="/accounting/disbursement/cash-advances" class="underline">Issue one first</NuxtLink>.
                            </p>
                            <p v-if="selectedAdvance" class="text-xxs text-(--text-muted)">
                                Outstanding receivable: <span class="font-mono font-semibold text-(--text-heading)">{{ selectedAdvance.currency }} {{ selectedAdvance.outstandingAmount.toFixed(2) }}</span> ·
                                Issued: <span class="font-mono">{{ formatDate(selectedAdvance.issuedOn) }}</span> ·
                                Status: <span class="font-mono">{{ selectedAdvance.status.replace('_', ' ') }}</span>
                            </p>
                        </div>

                        <!-- Lines -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expense Lines *</label>
                                <button type="button" class="btn btn-ghost text-xxs" @click="addLine">
                                    <i class="ti ti-plus" />Add Line
                                </button>
                            </div>
                            <table class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Expense Account</th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Description</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Amount</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(l, idx) in form.lines" :key="idx" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5 pr-2">
                                            <select v-model="l.account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                                <option value="">— pick expense account —</option>
                                                <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">
                                                    {{ a.code }} · {{ a.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model="l.description" type="text" maxlength="500"
                                                placeholder="What was spent on" class="form-control text-xs" />
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="l.amount" type="number" step="0.01" min="0.01" required
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5">
                                            <button type="button" class="action-btn action-btn-danger"
                                                :disabled="form.lines.length <= 1" @click="removeLine(idx)">
                                                <i class="ti ti-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals / Actual / Unused returned -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Unused Returned</label>
                                <input v-model.number="form.unused_returned" type="number" step="0.01" min="0"
                                    class="form-control text-xs font-mono text-right" />
                                <p class="text-xxs text-(--text-muted)">Leftover cash the employee returned. Adds a DR Cash leg to the journal — pick a bank below when &gt; 0.</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Return Bank</label>
                                <select v-model="form.bank_account_id" class="form-control text-xs"
                                    :disabled="banksLoading || !needsBank" :required="needsBank">
                                    <option :value="null">— None —</option>
                                    <option v-for="b in banks" :key="b.id" :value="b.id">
                                        {{ b.name }} ({{ b.currency }}){{ b.isDefault ? ' · default' : '' }}
                                    </option>
                                </select>
                                <p v-if="needsBank && !form.bank_account_id" class="text-xxs text-(--color-warning) flex items-center gap-1.5">
                                    <i class="ti ti-alert-triangle" />
                                    Required when unused returned &gt; 0.
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <div class="w-full sm:w-80 space-y-1.5 text-xs font-mono">
                                <div class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Sum of Lines</span>
                                    <span class="font-semibold text-(--text-heading)">{{ formSumLines.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 border-t border-(--border-color) pt-1.5 text-sm">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Actual *</span>
                                    <input v-model.number="form.actual_amount" type="number" step="0.01" min="0.01" required
                                        class="form-control text-xs font-mono text-right w-32 font-bold" />
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                                    :class="actualMatches ? 'badge-soft-success' : 'badge-soft-warning'">
                                    <i class="ti" :class="actualMatches ? 'ti-check' : 'ti-alert-triangle'" />
                                    <span v-if="actualMatches">Lines balanced</span>
                                    <span v-else>Diff {{ (formSumLines - (Number(form.actual_amount) || 0)).toFixed(2) }}</span>
                                    <button type="button" class="ml-auto text-xxs underline" @click="form.actual_amount = Number(formSumLines.toFixed(2))">Use sum</button>
                                </div>
                                <div class="flex items-center justify-between border-t border-(--border-color) pt-1.5">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Applied to Advance</span>
                                    <span class="font-semibold" :class="appliedFits ? 'text-(--color-success)' : 'text-(--color-danger)'">{{ formApplied.toFixed(2) }}</span>
                                </div>
                                <div v-if="selectedAdvance" class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Outstanding</span>
                                    <span class="text-(--text-muted)">{{ selectedAdvance.outstandingAmount.toFixed(2) }}</span>
                                </div>
                                <div v-if="selectedAdvance && !appliedFits" class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs badge-soft-danger">
                                    <i class="ti ti-x" />
                                    Applied exceeds outstanding by {{ (formApplied - selectedAdvance.outstandingAmount).toFixed(2) }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Record & Post
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel Settlement</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Settlement <span class="font-mono">{{ cancelTarget.settlementNumber }}</span> will be marked cancelled. The original posting stays in the audit log. The linked advance's settled balance will roll back by <span class="font-mono">{{ cancelTarget.appliedToAdvance.toFixed(2) }}</span> and its status will downgrade if needed.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Settlement
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    BankAccount,
    CashAdvance,
    CashAdvanceSettlement,
    CashAdvanceSettlementStatus,
    CreateCashAdvanceSettlementPayload,
    CreateCashAdvanceSettlementLinePayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Advance Settlements' })

const PAYMENT_METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque',        label: 'Cheque' },
    { value: 'cash',          label: 'Cash' },
    { value: 'wire',          label: 'Wire' },
]

const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('fms.cash_advances.read'))
const canSettle = computed(() => authStore.hasPermission('fms.cash_advances.settle'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const settlements = ref<CashAdvanceSettlement[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | CashAdvanceSettlementStatus>('')
const expanded = ref<Set<string>>(new Set())

const toggle = (id: string) => {
    if (expanded.value.has(id)) expanded.value.delete(id)
    else expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

const today = new Date().toISOString().slice(0, 10)
const formatDate = (s: string | null) => {
    if (!s) return '—'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}
const statusBadge = (s: CashAdvanceSettlementStatus) => ({
    posted:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const postedCount    = computed(() => settlements.value.filter(s => s.status === 'posted').length)
const cancelledCount = computed(() => settlements.value.filter(s => s.status === 'cancelled').length)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const settledThisMonth = computed(() => settlements.value
    .filter(s => s.status === 'posted' && s.settledOn >= monthStart)
    .reduce((sum, s) => sum + s.appliedToAdvance, 0))
const settledTodayCount = computed(() => settlements.value
    .filter(s => s.status === 'posted' && s.settledOn === today).length)
const totalUnusedReturned = computed(() => settlements.value
    .filter(s => s.status === 'posted')
    .reduce((sum, s) => sum + s.unusedReturned, 0))

const kpiCountAnim  = useCountUp(() => settlements.value.length)
const kpiMonthAnim  = useCountUp(() => settledThisMonth.value)
const kpiTodayAnim  = useCountUp(() => settledTodayCount.value)
const kpiUnusedAnim = useCountUp(() => totalUnusedReturned.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.cashAdvanceSettlements.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        settlements.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load settlements', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | CashAdvanceSettlementStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const openAdvances = ref<CashAdvance[]>([])
const advancesLoading = ref(false)
const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const expenseAccounts = computed(() => accountsList.value.filter(a => a.type === 'expense'))

const ensureAdvancesLoaded = async () => {
    if (openAdvances.value.length || advancesLoading.value) return
    advancesLoading.value = true
    try {
        const res = await finance.cashAdvances.list({ limit: 200, open_only: true })
        openAdvances.value = res.data
    } catch (err: any) {
        toast.error('Failed to load open advances', err?.data?.message)
    } finally {
        advancesLoading.value = false
    }
}

const ensureBanksLoaded = async () => {
    if (banks.value.length || banksLoading.value) return
    banksLoading.value = true
    try {
        const res = await finance.bankAccounts.list({ limit: 100, is_active: true })
        banks.value = res.data
    } catch (err: any) {
        toast.error('Failed to load bank accounts', err?.data?.message)
    } finally {
        banksLoading.value = false
    }
}

const ensureAccountsLoaded = async () => {
    if (accountsList.value.length || accountsLoading.value) return
    accountsLoading.value = true
    try {
        const res = await finance.accounts.tree()
        const flat: Account[] = []
        const walk = (nodes: Account[]) => nodes.forEach(n => { flat.push(n); if (n.children?.length) walk(n.children) })
        walk(res.data)
        flat.sort((a, b) => a.code.localeCompare(b.code))
        accountsList.value = flat
    } catch (err: any) {
        toast.error('Failed to load accounts', err?.data?.message)
    } finally {
        accountsLoading.value = false
    }
}

// ---- Form --------------------------------------------------------------------

const showFormModal = ref(false)

const blankLine = (): CreateCashAdvanceSettlementLinePayload => ({
    account_id: '',
    description: null,
    amount: 0,
})

const blankForm = (): CreateCashAdvanceSettlementPayload => ({
    settlement_number: '',
    cash_advance_id: '',
    bank_account_id: null,
    settled_on: today,
    actual_amount: 0,
    unused_returned: 0,
    payment_method: null,
    reference_number: null,
    notes: null,
    lines: [blankLine()],
})

const form = reactive<CreateCashAdvanceSettlementPayload>(blankForm())

const resetForm = () => Object.assign(form, blankForm())

const openCreateModal = () => {
    resetForm()
    form.lines = [blankLine()]
    showFormModal.value = true
    ensureAdvancesLoaded()
    ensureBanksLoaded()
    ensureAccountsLoaded()
}

const addLine = () => { form.lines.push(blankLine()) }
const removeLine = (idx: number) => { if (form.lines.length > 1) form.lines.splice(idx, 1) }

const selectedAdvance = computed(() =>
    openAdvances.value.find(a => a.id === form.cash_advance_id) || null
)

watch(() => form.cash_advance_id, () => {
    if (selectedAdvance.value) {
        // Default actual_amount to outstanding to make the common-case "spent it all" fast.
        if (!form.actual_amount || form.actual_amount <= 0) {
            form.actual_amount = selectedAdvance.value.outstandingAmount
        }
    }
})

const formSumLines = computed(() =>
    form.lines.reduce((s, l) => s + (Number(l.amount) || 0), 0)
)
const actualMatches = computed(() =>
    Math.abs(formSumLines.value - (Number(form.actual_amount) || 0)) < 0.005
)
const formApplied = computed(() =>
    Math.max(0, (Number(form.actual_amount) || 0) - (Number(form.unused_returned) || 0))
)
const appliedFits = computed(() => {
    if (!selectedAdvance.value) return true
    return formApplied.value <= selectedAdvance.value.outstandingAmount + 0.001
        && formApplied.value > 0
})
const needsBank = computed(() => (Number(form.unused_returned) || 0) > 0.001)

const canSubmit = computed(() => {
    if (!form.settlement_number.trim()) return false
    if (!form.cash_advance_id) return false
    if (!form.settled_on) return false
    if (!form.actual_amount || form.actual_amount <= 0) return false
    if (!actualMatches.value) return false
    if (!appliedFits.value) return false
    if (needsBank.value && !form.bank_account_id) return false
    if (!form.lines.length) return false
    for (const l of form.lines) {
        if (!l.account_id) return false
        if (!l.amount || l.amount <= 0) return false
    }
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateCashAdvanceSettlementPayload = {
            ...form,
            settlement_number: form.settlement_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            notes: form.notes?.trim() || null,
            unused_returned: Number(form.unused_returned) || 0,
            bank_account_id: needsBank.value ? form.bank_account_id : (form.bank_account_id || null),
            lines: form.lines.map(l => ({
                account_id: l.account_id,
                description: l.description?.toString().trim() || null,
                amount: Number(l.amount),
            })),
        }
        const res = await finance.cashAdvanceSettlements.create(payload)
        toast.success('Settlement posted', res.data.settlementNumber)
        showFormModal.value = false
        // Refresh open advances list since this advance may have rolled to closed.
        openAdvances.value = []
        await load()
    } catch (err: any) {
        toast.error('Settle failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<CashAdvanceSettlement | null>(null)
const confirmCancel = (s: CashAdvanceSettlement) => { cancelTarget.value = s }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.cashAdvanceSettlements.cancel(cancelTarget.value.id)
        toast.success('Settlement cancelled', res.data.settlementNumber)
        cancelTarget.value = null
        openAdvances.value = []
        await load()
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        cancelling.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}

.action-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
