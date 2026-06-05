<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Bills</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Vendor invoices booked into Accounts Payable. Approval posts DR Expense / CR AP. Cancellation posts an offsetting reversal.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Bill
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Bills</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-file-invoice text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ draftCount }} draft · {{ openCount }} open</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Outstanding AP</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-cash text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiOutstandingAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all approved bills</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Due This Week</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-danger flex items-center justify-center"><i class="ti ti-clock-exclamation text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiDueWeekAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ dueThisWeekCount }} bill(s)</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Overdue</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-danger flex items-center justify-center"><i class="ti ti-alert-triangle text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiOverdueAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ overdueCount }} past due</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'draft' }" @click="setStatusFilter('draft')">
                    <i class="ti ti-pencil" /> Draft
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'approved' }" @click="setStatusFilter('approved')">
                    <i class="ti ti-check" /> Approved
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'partially_paid' }" @click="setStatusFilter('partially_paid')">
                    <i class="ti ti-progress" /> Partially Paid
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'paid' }" @click="setStatusFilter('paid')">
                    <i class="ti ti-circle-check" /> Paid
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'cancelled' }" @click="setStatusFilter('cancelled')">
                    <i class="ti ti-x" /> Cancelled
                </button>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading bills...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="bills.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-file-invoice text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No bills found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Record your first vendor bill to start the AP cycle.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bill #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Vendor</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Issued</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Due</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Total</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Outstanding</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-40">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="b in bills" :key="b.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': b.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(b.id)">
                                        <i class="ti" :class="expanded.has(b.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(b.id)">
                                        <div class="font-mono font-semibold"
                                            :class="b.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ b.billNumber }}
                                        </div>
                                        <div v-if="b.supplierInvoiceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">
                                            Supplier ref: {{ b.supplierInvoiceNumber }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(b.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ b.supplier?.name || '—' }}</p>
                                        <p v-if="b.supplier?.code" class="text-xxs text-(--text-muted) font-mono">{{ b.supplier.code }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(b.id)">{{ formatDate(b.issueDate) }}</td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(b.id)" :class="dueClass(b)">{{ b.dueDate || '—' }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(b.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(b.status)">{{ b.status.replace('_', ' ') }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono cursor-pointer" @click="toggle(b.id)">{{ b.currency }} {{ b.total.toFixed(2) }}</td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold cursor-pointer" @click="toggle(b.id)"
                                        :class="b.outstandingAmount > 0 ? 'text-(--text-heading)' : 'text-(--text-muted)'">
                                        {{ b.outstandingAmount.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button v-if="canWrite && b.isEditable" type="button" class="action-btn"
                                                title="Edit" @click.stop="openEditModal(b)">
                                                <i class="ti ti-pencil" />
                                            </button>
                                            <button v-if="canWrite && b.isPostable" type="button" class="action-btn action-btn-success"
                                                title="Approve (post to GL)" @click.stop="confirmApprove(b)">
                                                <i class="ti ti-check" />
                                            </button>
                                            <button v-if="canWrite && b.status !== 'cancelled'" type="button" class="action-btn action-btn-danger"
                                                title="Cancel" @click.stop="confirmCancel(b)">
                                                <i class="ti ti-x" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(b.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="9" class="px-4 py-3">
                                        <div v-if="b.payableAccount" class="text-xxs text-(--text-muted) mb-2 font-mono">
                                            <i class="ti ti-tree" />
                                            AP: {{ b.payableAccount.code }} · {{ b.payableAccount.name }}
                                        </div>
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Account</th>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Description</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-20">Qty</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-28">Unit Price</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-28">Line Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="l in b.lines" :key="l.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span v-if="l.account">
                                                            <span class="text-(--text-heading)">{{ l.account.code }}</span>
                                                            <span class="text-(--text-muted) ml-2">{{ l.account.name }}</span>
                                                        </span>
                                                        <span v-else class="text-(--text-muted)">—</span>
                                                    </td>
                                                    <td class="py-1.5 text-(--text-body)">{{ l.description || '—' }}</td>
                                                    <td class="text-right py-1.5">{{ l.quantity }}</td>
                                                    <td class="text-right py-1.5">{{ l.unitPrice.toFixed(2) }}</td>
                                                    <td class="text-right py-1.5 font-semibold text-(--text-heading)">{{ l.lineTotal.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="4">Subtotal</td>
                                                    <td class="text-right py-1.5">{{ b.subtotal.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="text-(--text-body)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="4">Tax</td>
                                                    <td class="text-right py-1.5">{{ b.taxAmount.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="4">Total</td>
                                                    <td class="text-right py-1.5">{{ b.currency }} {{ b.total.toFixed(2) }}</td>
                                                </tr>
                                                <tr v-if="b.paidAmount > 0" class="text-(--color-success)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="4">Paid</td>
                                                    <td class="text-right py-1.5">({{ b.paidAmount.toFixed(2) }})</td>
                                                </tr>
                                                <tr v-if="b.outstandingAmount > 0" class="font-bold text-(--color-warning)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="4">Outstanding</td>
                                                    <td class="text-right py-1.5">{{ b.outstandingAmount.toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="b.notes" class="text-xxs text-(--text-muted) mt-3"><strong>Notes:</strong> {{ b.notes }}</p>
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

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-4xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Bill' : 'New Bill' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="save">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header row 1 -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Bill # *</label>
                                <input v-model="form.bill_number" type="text" required maxlength="64"
                                    placeholder="e.g. BILL-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Supplier Invoice #</label>
                                <input v-model="form.supplier_invoice_number" type="text" maxlength="64"
                                    placeholder="Their reference" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Currency</label>
                                <input v-model="form.currency" type="text" maxlength="3"
                                    class="form-control text-xs font-mono uppercase"
                                    @input="form.currency = (form.currency || '').toUpperCase()" />
                            </div>
                        </div>

                        <!-- Header row 2: vendor + dates -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Vendor *</label>
                                <select v-model="form.supplier_id" required class="form-control text-xs" :disabled="vendorsLoading"
                                    @change="onVendorChange">
                                    <option value="">— Pick vendor —</option>
                                    <option v-for="v in vendors" :key="v.id" :value="v.id">
                                        {{ v.code ? `${v.code} · ` : '' }}{{ v.name }}
                                    </option>
                                </select>
                                <p v-if="vendors.length === 0 && !vendorsLoading" class="text-xxs text-(--color-warning) flex items-center gap-1.5">
                                    <i class="ti ti-info-circle" />
                                    No vendors yet. Enable "Is Vendor" on a
                                    <NuxtLink to="/inventory/suppliers" class="underline">supplier</NuxtLink>.
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Issue Date *</label>
                                <input v-model="form.issue_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Due Date</label>
                                <input v-model="form.due_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <!-- Header row 3: AP account -->
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">AP Account (Liability) *</label>
                            <select v-model="form.payable_account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                <option :value="null">— Pick AP account —</option>
                                <option v-for="a in payableAccounts" :key="a.id" :value="a.id">
                                    {{ a.code }} · {{ a.name }} ({{ a.type }})
                                </option>
                            </select>
                            <p class="text-xxs text-(--text-muted)">Default pulled from vendor when available. Approval will post the bill total here as CR.</p>
                        </div>

                        <!-- Lines -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Lines *</label>
                                <button type="button" class="btn btn-ghost text-xxs" @click="addLine">
                                    <i class="ti ti-plus" />Add Line
                                </button>
                            </div>
                            <table class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Account (Expense/Asset)</th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Description</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-24">Qty</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-28">Unit Price</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-28">Line Total</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(l, idx) in form.lines" :key="idx" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5 pr-2">
                                            <select v-model="l.account_id" required class="form-control text-xs">
                                                <option value="">— pick account —</option>
                                                <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">
                                                    {{ a.code }} · {{ a.name }} ({{ a.type }})
                                                </option>
                                            </select>
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model="l.description" type="text" maxlength="500" class="form-control text-xs" />
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="l.quantity" type="number" step="0.01" min="0.01"
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="l.unit_price" type="number" step="0.01" min="0.01"
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5 pr-2 text-right font-mono text-xs font-semibold">
                                            {{ ((Number(l.quantity) || 0) * (Number(l.unit_price) || 0)).toFixed(2) }}
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

                        <!-- Totals -->
                        <div class="flex justify-end">
                            <div class="w-full sm:w-80 space-y-1.5 text-xs font-mono">
                                <div class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Subtotal</span>
                                    <span class="font-semibold">{{ formSubtotal.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Tax</span>
                                    <input v-model.number="form.tax_amount" type="number" step="0.01" min="0"
                                        class="form-control text-xs font-mono text-right w-28" />
                                </div>
                                <div class="flex items-center justify-between border-t border-(--border-color) pt-1.5 text-(--text-heading) text-sm">
                                    <span class="uppercase tracking-widest text-xxs">Total</span>
                                    <span class="font-bold">{{ form.currency }} {{ formTotal.toFixed(2) }}</span>
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
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Save Draft' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Approve Modal -->
        <div v-if="approveTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Approve Bill</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="approveTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-success text-xs flex items-start gap-2">
                        <i class="ti ti-info-circle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts to the General Ledger</p>
                            <p class="text-xxs mt-0.5">Approving <span class="font-mono">{{ approveTarget.billNumber }}</span> will post a balanced journal entry of <span class="font-mono font-bold">{{ approveTarget.currency }} {{ approveTarget.total.toFixed(2) }}</span> (DR each line account, CR AP). Once posted, the bill becomes immutable.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="approveTarget = null">Cancel</button>
                    <button type="button" class="btn btn-success text-xs" :disabled="approving" @click="onConfirmApprove">
                        <i v-if="approving" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-check" />
                        Approve & Post
                    </button>
                </footer>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel Bill</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Cancel <span class="font-mono font-semibold text-(--text-heading)">{{ cancelTarget.billNumber }}</span>?
                    </p>
                    <p v-if="cancelTarget.isReversible" class="text-xxs text-(--color-warning) mt-2 flex items-start gap-1.5">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <span>This will post a reversing journal entry. The original posting stays in the audit log.</span>
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Bill
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useFinance } from '~/composables/useFinance'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    Bill,
    BillStatus,
    CreateBillPayload,
    CreateBillLinePayload,
} from '~/types/finance'
import type { Supplier } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Bills' })

const finance = useFinance()
const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('fms.bills.read'))
const canWrite  = computed(() => authStore.hasPermission('fms.bills.write'))

const loading    = ref(false)
const submitting = ref(false)
const approving  = ref(false)
const cancelling = ref(false)

const bills = ref<Bill[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | BillStatus>('')
const expanded = ref<Set<string>>(new Set())

const toggle = (id: string) => {
    if (expanded.value.has(id)) expanded.value.delete(id)
    else expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

const today = new Date().toISOString().slice(0, 10)

const formatDate = (s: string) => {
    if (!s) return ''
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}

const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}

const statusBadge = (s: BillStatus) => ({
    draft:          'badge-soft-info',
    approved:       'badge-soft-primary',
    partially_paid: 'badge-soft-warning',
    paid:           'badge-soft-success',
    cancelled:      'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

const dueClass = (b: Bill) => {
    if (b.status !== 'approved' && b.status !== 'partially_paid') return 'text-(--text-body)'
    if (!b.dueDate) return 'text-(--text-body)'
    if (b.dueDate < today) return 'text-(--color-danger)'
    const due = new Date(b.dueDate).getTime()
    const sevenDays = Date.now() + 7 * 24 * 60 * 60 * 1000
    if (due <= sevenDays) return 'text-(--color-warning)'
    return 'text-(--text-body)'
}

// ---- KPIs --------------------------------------------------------------------

const draftCount = computed(() => bills.value.filter(b => b.status === 'draft').length)
const openCount  = computed(() => bills.value.filter(b => b.status === 'approved' || b.status === 'partially_paid').length)

const totalOutstanding = computed(() => bills.value
    .filter(b => b.status === 'approved' || b.status === 'partially_paid')
    .reduce((s, b) => s + b.outstandingAmount, 0))

const dueThisWeek = computed(() => bills.value.filter(b => {
    if (b.status !== 'approved' && b.status !== 'partially_paid') return false
    if (!b.dueDate) return false
    const due = new Date(b.dueDate).getTime()
    return due <= Date.now() + 7 * 24 * 60 * 60 * 1000 && due >= Date.now()
}))
const dueThisWeekCount  = computed(() => dueThisWeek.value.length)
const dueThisWeekAmount = computed(() => dueThisWeek.value.reduce((s, b) => s + b.outstandingAmount, 0))

const overdue = computed(() => bills.value.filter(b => {
    if (b.status !== 'approved' && b.status !== 'partially_paid') return false
    return b.dueDate ? b.dueDate < today : false
}))
const overdueCount  = computed(() => overdue.value.length)
const overdueAmount = computed(() => overdue.value.reduce((s, b) => s + b.outstandingAmount, 0))

const kpiCountAnim       = useCountUp(() => bills.value.length)
const kpiOutstandingAnim = useCountUp(() => totalOutstanding.value)
const kpiDueWeekAnim     = useCountUp(() => dueThisWeekAmount.value)
const kpiOverdueAnim     = useCountUp(() => overdueAmount.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.bills.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        bills.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load bills', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | BillStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Vendors + Accounts (modal pickers, lazy-loaded) -------------------------

const vendors = ref<Supplier[]>([])
const vendorsLoading = ref(false)

const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const payableAccounts = computed(() => accountsList.value.filter(a => a.type === 'liability' || a.type === 'equity'))
const expenseAccounts = computed(() => accountsList.value.filter(a => a.type === 'expense' || a.type === 'asset'))

const ensureVendorsLoaded = async () => {
    if (vendors.value.length || vendorsLoading.value) return
    vendorsLoading.value = true
    try {
        const res = await inventory.suppliers.list({ limit: 200, vendor_only: true })
        vendors.value = res.data
    } catch (err: any) {
        toast.error('Failed to load vendors', err?.data?.message)
    } finally {
        vendorsLoading.value = false
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
const isEdit = ref(false)
const editId = ref<string | null>(null)

const makeLine = (): CreateBillLinePayload => ({
    account_id: '',
    description: null,
    quantity: 1,
    unit_price: 0,
})

const blankForm = (): CreateBillPayload => ({
    bill_number: '',
    supplier_invoice_number: null,
    supplier_id: '',
    po_id: null,
    issue_date: today,
    due_date: null,
    currency: 'USD',
    tax_amount: 0,
    payable_account_id: null,
    notes: null,
    lines: [makeLine()],
})

const form = reactive<CreateBillPayload>(blankForm())

const resetForm = () => {
    Object.assign(form, blankForm())
    form.lines = [makeLine()]
}

const onVendorChange = () => {
    // Pre-fill the AP account from the chosen vendor's default if present.
    const v = vendors.value.find(x => x.id === form.supplier_id)
    if (v?.defaultPayableAccountId) {
        form.payable_account_id = v.defaultPayableAccountId
    }
}

const addLine = () => form.lines.push(makeLine())
const removeLine = (idx: number) => {
    if (form.lines.length <= 1) return
    form.lines.splice(idx, 1)
}

const formSubtotal = computed(() => form.lines.reduce(
    (s, l) => s + ((Number(l.quantity) || 0) * (Number(l.unit_price) || 0)), 0))
const formTotal = computed(() => formSubtotal.value + (Number(form.tax_amount) || 0))

const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    resetForm()
    showFormModal.value = true
    ensureVendorsLoaded()
    ensureAccountsLoaded()
}

const openEditModal = (b: Bill) => {
    isEdit.value = true
    editId.value = b.id
    form.bill_number              = b.billNumber
    form.supplier_invoice_number  = b.supplierInvoiceNumber
    form.supplier_id              = b.supplierId
    form.po_id                    = b.poId
    form.issue_date               = b.issueDate
    form.due_date                 = b.dueDate
    form.currency                 = b.currency
    form.tax_amount               = b.taxAmount
    form.payable_account_id       = b.payableAccountId
    form.notes                    = b.notes
    form.lines                    = b.lines.length > 0
        ? b.lines.map(l => ({
            account_id: l.accountId,
            description: l.description,
            quantity: l.quantity,
            unit_price: l.unitPrice,
        }))
        : [makeLine()]
    showFormModal.value = true
    ensureVendorsLoaded()
    ensureAccountsLoaded()
}

const save = async () => {
    submitting.value = true
    try {
        const payload: CreateBillPayload = {
            ...form,
            lines: form.lines
                .filter(l => l.account_id && (Number(l.quantity) > 0) && (Number(l.unit_price) > 0))
                .map(l => ({
                    account_id: l.account_id,
                    description: l.description?.trim() || null,
                    quantity: Number(l.quantity),
                    unit_price: Number(l.unit_price),
                })),
        }
        if (isEdit.value && editId.value) {
            await finance.bills.update(editId.value, payload)
            toast.success('Bill saved', payload.bill_number)
        } else {
            await finance.bills.create(payload)
            toast.success('Bill created', payload.bill_number)
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// ---- Approve / Cancel --------------------------------------------------------

const approveTarget = ref<Bill | null>(null)
const cancelTarget  = ref<Bill | null>(null)

const confirmApprove = (b: Bill) => { approveTarget.value = b }
const confirmCancel  = (b: Bill) => { cancelTarget.value = b }

const onConfirmApprove = async () => {
    if (!approveTarget.value) return
    approving.value = true
    try {
        const res = await finance.bills.approve(approveTarget.value.id)
        toast.success('Bill approved & posted', res.data.billNumber)
        approveTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Approve failed', err?.data?.message)
    } finally {
        approving.value = false
    }
}

const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.bills.cancel(cancelTarget.value.id)
        toast.success('Bill cancelled', res.data.billNumber)
        cancelTarget.value = null
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

.action-btn-success:hover {
    color: var(--color-success);
    border-color: rgb(var(--color-success-rgb) / 0.4);
}

.action-btn[disabled] {
    opacity: 0.3;
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
