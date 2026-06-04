<template>
    <NuxtLayout name="default">
        <!-- ===== Loading ===== -->
        <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted) font-medium">Loading employee profile...</span>
        </div>

        <!-- ===== Not found ===== -->
        <div v-else-if="!employee" class="py-24 flex flex-col items-center justify-center gap-3">
            <i class="ti ti-user-question text-4xl text-(--text-muted)" />
            <p class="text-sm text-(--text-heading) font-semibold">Employee not found</p>
            <p class="text-xs text-(--text-muted)">It may have been removed or you don't have access.</p>
            <NuxtLink to="/hrm/employees" class="btn btn-soft-primary text-xs mt-2">
                <i class="ti ti-arrow-left" /> Back to employees
            </NuxtLink>
        </div>

        <!-- ===== Profile ===== -->
        <div v-else class="space-y-6">
            <!-- ===== Hero header ===== -->
            <section class="glass-card rounded-2xl p-6 md:p-7">
                <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-xl md:text-2xl font-bold bg-(--color-primary-subtle) text-(--color-primary) overflow-hidden"
                            :title="employee.fullName">
                            <img v-if="employee.imageUrl" :src="employee.imageUrl" :alt="employee.fullName" class="w-full h-full object-cover" />
                            <span v-else>{{ initials(employee) }}</span>
                        </div>
                        <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-(--bg-card)"
                            :class="statusDotClass(employee.status)" :title="statusLabel(employee.status)" />
                    </div>

                    <!-- Identity -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-lg md:text-xl font-semibold text-(--text-heading) truncate">
                                {{ employee.fullName }}
                            </h1>
                            <span class="font-mono text-xxs text-(--text-muted) bg-(--bg-muted) px-1.5 py-0.5 rounded">
                                {{ employee.employeeId }}
                            </span>
                            <Badge :variant="statusVariant(employee.status)" :dot="true">{{ employee.status }}</Badge>
                        </div>
                        <p class="text-xs text-(--text-muted) mt-1 truncate">
                            <span class="text-(--text-body)">{{ employee.position?.title || 'Unassigned position'
                                }}</span>
                            <span v-if="employee.department"> · {{ employee.department.name }}</span>
                            <span v-if="employee.hiredAt"> · Joined {{ formatDate(employee.hiredAt) }}</span>
                        </p>
                    </div>

                    <!-- Quick contact -->
                    <div class="flex items-center gap-1.5 self-start md:self-center">
                        <a v-if="employee.email" :href="`mailto:${employee.email}`" class="icon-btn" title="Send email">
                            <i class="ti ti-mail" />
                        </a>
                        <a v-if="employee.phone" :href="`tel:${employee.phone}`" class="icon-btn" title="Call">
                            <i class="ti ti-phone" />
                        </a>
                        <button v-if="canWrite" class="btn btn-soft-primary text-xs" @click="openEditOnList">
                            <i class="ti ti-pencil" /> Edit
                        </button>
                    </div>
                </div>
            </section>

            <!-- ===== Tab nav ===== -->
            <nav class="glass-card rounded-xl px-2 py-1.5 flex items-center gap-1 overflow-x-auto">
                <button v-for="t in visibleTabs" :key="t.key" type="button" class="tab-trigger"
                    :class="{ 'tab-trigger-active': activeTab === t.key }" @click="setTab(t.key)">
                    <i :class="['ti', t.icon]" />
                    <span>{{ t.label }}</span>
                </button>
            </nav>

            <!-- ============================ Tab: Overview ========================== -->
            <section v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="glass-card rounded-2xl p-5 lg:col-span-2 space-y-4">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Personal information</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-xs">
                        <InfoRow label="First name" :value="employee.firstName" />
                        <InfoRow label="Last name" :value="employee.lastName" />
                        <InfoRow label="Email" :value="employee.email" mono />
                        <InfoRow label="Phone" :value="employee.phone" mono />
                        <InfoRow label="Gender" :value="genderLabel(employee.gender)" />
                    </dl>
                </div>

                <aside class="glass-card rounded-2xl p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Employment</h3>
                    <dl class="space-y-3 text-xs">
                        <InfoRow label="Status" :value="statusLabel(employee.status)" />
                        <InfoRow label="Department" :value="employee.department?.name" />
                        <InfoRow label="Position" :value="employee.position?.title" />
                        <InfoRow label="Employee ID" :value="employee.employeeId" mono />
                        <InfoRow label="Hired" :value="formatDate(employee.hiredAt)" mono />
                    </dl>
                </aside>
            </section>

            <!-- ============================ Tab: Compensation ========================== -->
            <section v-else-if="activeTab === 'compensation'" class="glass-card rounded-2xl p-5 space-y-5">
                <header class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Compensation &amp; banking</h3>
                    <span v-if="!canSeeSalary" class="text-xxs text-(--text-muted) inline-flex items-center gap-1">
                        <i class="ti ti-lock" /> Masked — requires <code class="font-mono">hrm.payroll.read</code>
                    </span>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <MetricBox label="Base salary"
                        :value="employee.baseSalary != null ? formatMoney(employee.baseSalary) : '••••'" icon="ti-cash"
                        variant="primary" />
                    <MetricBox label="Bank" :value="employee.bankName || '••••'" icon="ti-building-bank"
                        variant="info" />
                    <MetricBox label="Account" :value="employee.bankAccountNumber || '••••'"
                        :sub="employee.bankAccountName || ''" icon="ti-credit-card" variant="secondary" />
                </div>

                <p class="text-xxs text-(--text-muted)">
                    Salary &amp; bank fields are encrypted at rest and masked client-side. Updates require <code
                        class="font-mono">hrm.employee.write</code>.
                </p>
            </section>

            <!-- ============================ Tab: Attendance ========================== -->
            <section v-else-if="activeTab === 'attendance'" class="space-y-4">
                <!-- Summary tiles — derived from the visible page; sufficient for a quick read. -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Present</p>
                        <p class="text-lg font-mono font-semibold text-(--color-success) mt-1">{{
                            attendance.summary.present }}</p>
                    </div>
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Late</p>
                        <p class="text-lg font-mono font-semibold text-(--color-warning) mt-1">{{
                            attendance.summary.late }}</p>
                    </div>
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Half day</p>
                        <p class="text-lg font-mono font-semibold text-(--color-warning) mt-1">{{
                            attendance.summary.halfDay }}</p>
                    </div>
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Absent</p>
                        <p class="text-lg font-mono font-semibold text-(--color-danger) mt-1">{{
                            attendance.summary.absent }}</p>
                    </div>
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Paid leave</p>
                        <p class="text-lg font-mono font-semibold text-(--color-info, --color-primary) mt-1">{{
                            attendance.summary.paidLeave }}</p>
                    </div>
                    <div class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Unpaid leave</p>
                        <p class="text-lg font-mono font-semibold text-(--color-danger) mt-1">{{
                            attendance.summary.unpaidLeave }}</p>
                    </div>
                </div>

                <div class="glass-card rounded-2xl overflow-hidden">
                    <header class="flex items-center justify-between px-5 py-3 border-b border-(--border-color)">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Recent attendance</h3>
                        <span class="text-xxs text-(--text-muted) font-mono">{{ attendance.list.length }} row{{
                            attendance.list.length === 1 ? '' : 's' }}</span>
                    </header>
                    <div v-if="attendance.loading" class="py-14 text-center">
                        <span
                            class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>
                    <div v-else-if="attendance.list.length === 0" class="py-14 text-center text-xs text-(--text-muted)">
                        No attendance records yet.
                    </div>
                    <table v-else class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-5 py-2.5 font-semibold font-mono">Date</th>
                                <th class="px-5 py-2.5 font-semibold font-mono">In</th>
                                <th class="px-5 py-2.5 font-semibold font-mono">Out</th>
                                <th class="px-5 py-2.5 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="a in attendance.list" :key="a.id"
                                class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-5 py-2.5 font-mono text-xs">{{ a.date }}</td>
                                <td class="px-5 py-2.5 font-mono text-xxs text-(--text-muted)">{{
                                    formatAttendanceTime(a.checkIn) }}</td>
                                <td class="px-5 py-2.5 font-mono text-xxs text-(--text-muted)">{{
                                    formatAttendanceTime(a.checkOut) }}</td>
                                <td class="px-5 py-2.5">
                                    <Badge :variant="attendanceStatusVariant(a.status)" :dot="true">{{
                                        a.status.replace('_', ' ') }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================ Tab: Leaves ========================== -->
            <section v-else-if="activeTab === 'leaves'" class="space-y-4">
                <!-- Balance strip -->
                <div v-if="leaves.balanceLoading" class="glass-card rounded-2xl p-5 text-center">
                    <span
                        class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>
                <div v-else-if="leaves.balance.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="b in leaves.balance" :key="b.leaveTypeId" class="glass-card rounded-xl p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">{{ b.name }}</p>
                        <p class="text-lg font-mono font-semibold text-(--color-primary) mt-1">
                            {{ b.remaining }} <span class="text-xxs text-(--text-muted) font-normal">/ {{
                                b.annualAllowance }} d</span>
                        </p>
                        <p class="text-xxs text-(--text-muted) mt-1">{{ b.used }} day{{ b.used === 1 ? '' : 's' }} used
                        </p>
                    </div>
                </div>

                <!-- Requests -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <header class="flex items-center justify-between px-5 py-3 border-b border-(--border-color)">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Leave requests</h3>
                        <span class="text-xxs text-(--text-muted) font-mono">{{ leaves.list.length }} record{{
                            leaves.list.length === 1 ? '' : 's' }}</span>
                    </header>
                    <div v-if="leaves.loading" class="py-14 text-center">
                        <span
                            class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>
                    <div v-else-if="leaves.list.length === 0" class="py-14 text-center text-xs text-(--text-muted)">
                        No leave requests yet.
                    </div>
                    <table v-else class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-5 py-2.5 font-semibold">Type</th>
                                <th class="px-5 py-2.5 font-semibold">Dates</th>
                                <th class="px-5 py-2.5 font-semibold font-mono text-right">Days</th>
                                <th class="px-5 py-2.5 font-semibold">Reason</th>
                                <th class="px-5 py-2.5 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="lv in leaves.list" :key="lv.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-5 py-2.5 text-xs">{{ lv.leaveType?.name || '—' }}</td>
                                <td class="px-5 py-2.5 text-xs font-mono">
                                    <div>{{ formatDate(lv.startDate) }}</div>
                                    <div class="text-(--text-muted) text-xxs">→ {{ formatDate(lv.endDate) }}</div>
                                </td>
                                <td class="px-5 py-2.5 font-mono text-xs text-right">{{ lv.days }}</td>
                                <td class="px-5 py-2.5 text-xs text-(--text-body) max-w-[260px] truncate"
                                    :title="lv.reason || ''">
                                    {{ lv.reason || '—' }}
                                </td>
                                <td class="px-5 py-2.5">
                                    <Badge :variant="leaveStatusVariant(lv.status)" :dot="true">{{ lv.status }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================ Tab: Payslips ========================== -->
            <section v-else-if="activeTab === 'payslips'" class="glass-card rounded-2xl overflow-hidden">
                <header class="flex items-center justify-between px-5 py-3 border-b border-(--border-color)">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Payslips</h3>
                    <span class="text-xxs text-(--text-muted) font-mono">{{ payslips.list.length }} record{{
                        payslips.list.length === 1 ? '' : 's' }}</span>
                </header>
                <div v-if="payslips.loading" class="py-14 text-center">
                    <span
                        class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>
                <div v-else-if="payslips.list.length === 0" class="py-14 text-center text-xs text-(--text-muted)">
                    No payslips on file yet.
                </div>
                <table v-else class="w-full text-left">
                    <thead>
                        <tr
                            class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                            <th class="px-5 py-2.5 font-semibold">Period</th>
                            <th class="px-5 py-2.5 font-semibold font-mono text-right">Gross</th>
                            <th class="px-5 py-2.5 font-semibold font-mono text-right">Deductions</th>
                            <th class="px-5 py-2.5 font-semibold font-mono text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-color)">
                        <tr v-for="ps in payslips.list" :key="ps.id" class="hover:bg-(--bg-muted) transition-colors">
                            <td class="px-5 py-2.5 text-xs">
                                <div class="font-semibold text-(--text-heading)">{{ ps.payrollPeriod?.name || '—' }}
                                </div>
                                <div v-if="ps.payrollPeriod" class="text-xxs text-(--text-muted) font-mono">
                                    {{ formatDate(ps.payrollPeriod.startDate) }} → {{ formatDate(ps.payrollPeriod.endDate) }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-right">
                                {{ ps.grossSalary != null ? formatMoney(ps.grossSalary) : '••••' }}
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-right text-(--color-danger)">
                                {{ ps.deductions ? `-${formatMoney(sumValues(ps.deductions))}` : '••••' }}
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-right font-semibold">
                                {{ ps.netSalary != null ? formatMoney(ps.netSalary) : '••••' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ============================ Tab: Appraisals ========================== -->
            <section v-else-if="activeTab === 'appraisals'" class="glass-card rounded-2xl overflow-hidden">
                <header class="flex items-center justify-between px-5 py-3 border-b border-(--border-color)">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Performance appraisals</h3>
                    <span class="text-xxs text-(--text-muted) font-mono">{{ appraisals.list.length }} record{{
                        appraisals.list.length === 1 ? '' : 's' }}</span>
                </header>
                <div v-if="appraisals.loading" class="py-14 text-center">
                    <span
                        class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>
                <div v-else-if="appraisals.list.length === 0" class="py-14 text-center text-xs text-(--text-muted)">
                    No appraisals on record.
                </div>
                <table v-else class="w-full text-left">
                    <thead>
                        <tr
                            class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                            <th class="px-5 py-2.5 font-semibold font-mono">Cycle</th>
                            <th class="px-5 py-2.5 font-semibold">Period</th>
                            <th class="px-5 py-2.5 font-semibold">Reviewer</th>
                            <th class="px-5 py-2.5 font-semibold font-mono text-right">Rating</th>
                            <th class="px-5 py-2.5 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-color)">
                        <tr v-for="a in appraisals.list" :key="a.id" class="hover:bg-(--bg-muted) transition-colors">
                            <td class="px-5 py-2.5 font-mono text-xs">{{ a.cycle }}</td>
                            <td class="px-5 py-2.5 text-xxs font-mono">
                                <div>{{ formatDate(a.periodStart) }}</div>
                                <div class="text-(--text-muted)">→ {{ formatDate(a.periodEnd) }}</div>
                            </td>
                            <td class="px-5 py-2.5 text-xs">{{ a.reviewer?.fullName || '—' }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-right">
                                <span v-if="a.overallRating != null" class="font-semibold"
                                    :class="ratingColor(a.overallRating)">
                                    {{ a.overallRating.toFixed(2) }}
                                </span>
                                <span v-else class="text-(--text-muted)">—</span>
                            </td>
                            <td class="px-5 py-2.5">
                                <Badge :variant="appraisalStatusVariant(a.status)" :dot="true">{{ a.status }}</Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- ============================ Tab: Assets ========================== -->
            <section v-else-if="activeTab === 'assets'" class="glass-card rounded-2xl overflow-hidden">
                <header class="flex items-center justify-between px-5 py-3 border-b border-(--border-color)">
                    <div>
                        <h3 class="text-sm font-semibold text-(--text-heading)">Fixed assets in custody</h3>
                        <p class="text-xxs text-(--text-muted) mt-0.5">
                            Physical assets currently assigned to this employee as custodian.
                        </p>
                    </div>
                    <span class="text-xxs text-(--text-muted) font-mono">
                        {{ (employee.assets?.length ?? 0) }} item{{ (employee.assets?.length ?? 0) === 1 ? '' : 's' }}
                    </span>
                </header>
                <div v-if="!employee.assets || employee.assets.length === 0"
                    class="py-14 text-center text-xs text-(--text-muted)">
                    No assets currently in custody.
                </div>
                <table v-else class="w-full text-left">
                    <thead>
                        <tr
                            class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                            <th class="px-5 py-2.5 font-semibold font-mono">Asset code</th>
                            <th class="px-5 py-2.5 font-semibold">Name</th>
                            <th class="px-5 py-2.5 font-semibold">Category</th>
                            <th class="px-5 py-2.5 font-semibold">Condition</th>
                            <th class="px-5 py-2.5 font-semibold">Status</th>
                            <th class="px-5 py-2.5 font-semibold font-mono text-right">Net book value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--border-color)">
                        <tr v-for="a in employee.assets" :key="a.id"
                            class="hover:bg-(--bg-muted) transition-colors cursor-pointer"
                            @click="openAsset(a.id)">
                            <td class="px-5 py-2.5 font-mono text-xs text-(--color-primary)">{{ a.assetCode || '—' }}</td>
                            <td class="px-5 py-2.5 text-xs">
                                <div class="font-semibold text-(--text-heading)">{{ a.name }}</div>
                                <div v-if="a.serialNumber" class="text-xxs font-mono text-(--text-muted)">
                                    SN: {{ a.serialNumber }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-xs">{{ a.category || '—' }}</td>
                            <td class="px-5 py-2.5">
                                <Badge v-if="a.condition" :variant="assetConditionVariant(a.condition)">
                                    {{ a.condition }}
                                </Badge>
                                <span v-else class="text-xxs text-(--text-muted)">—</span>
                            </td>
                            <td class="px-5 py-2.5">
                                <Badge v-if="a.status" :variant="assetStatusVariant(a.status)" :dot="true">
                                    {{ a.status }}
                                </Badge>
                                <span v-else class="text-xxs text-(--text-muted)">—</span>
                            </td>
                            <td class="px-5 py-2.5 font-mono text-xs text-right">
                                <span v-if="a.netBookValue != null">{{ formatMoney(a.netBookValue) }}</span>
                                <span v-else class="text-(--text-muted)">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import Badge from '~/components/Badge.vue'

interface DepartmentLite { id: string; name: string }
interface PositionLite { id: string; title: string }
interface AssetRow {
    id: string
    assetCode: string | null
    name: string
    category: string | null
    status: string | null
    condition: string | null
    serialNumber: string | null
    purchaseDate: string | null
    netBookValue: number | null
}
interface Employee {
    id: string
    employeeId: string
    firstName: string
    lastName: string
    fullName: string
    email: string
    gender: 'male' | 'female' | 'other' | null
    phone: string | null
    imageUrl: string | null
    status: 'active' | 'on_leave' | 'terminated'
    hiredAt: string | null
    baseSalary: number | null
    bankName: string | null
    bankAccountName: string | null
    bankAccountNumber: string | null
    department: DepartmentLite | null
    position: PositionLite | null
    assets?: AssetRow[]
}

interface LeaveBalanceRow { leaveTypeId: string; name: string; annualAllowance: number; used: number; remaining: number }
interface Leave {
    id: string
    startDate: string
    endDate: string
    days: number
    reason: string | null
    status: 'pending' | 'approved' | 'rejected'
    leaveType?: { id: string; name: string }
}
interface PayrollPeriodLite { id: string; name: string; startDate: string; endDate: string }
interface Payslip {
    id: string
    grossSalary: number | null
    netSalary: number | null
    deductions: Record<string, number> | null
    payrollPeriod?: PayrollPeriodLite
}
type AppraisalStatus = 'draft' | 'submitted' | 'reviewed' | 'closed'
interface Appraisal {
    id: string
    cycle: string
    periodStart: string
    periodEnd: string
    overallRating: number | null
    status: AppraisalStatus
    reviewer?: { id: string; fullName: string }
}

interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const route = useRoute()
const router = useRouter()
const api = useApi()
const authStore = useAuthStore()
const breadcrumb = useBreadcrumbOverride()

const canSeeSalary = computed(() => authStore.hasPermission('hrm.payroll.read'))
const canSeeLeaves = computed(() =>
    authStore.hasPermission('hrm.leave.read') || authStore.hasPermission('hrm.leave.read.self')
)
const canSeeAppraisals = computed(() =>
    authStore.hasPermission('hrm.performance.read') || authStore.hasPermission('hrm.performance.read.self')
)
const canWrite = computed(() => authStore.hasPermission('hrm.employee.write'))

const employeeId = computed(() => String(route.params.id || ''))

const loading = ref(true)
const employee = ref<Employee | null>(null)

const loadEmployee = async () => {
    loading.value = true
    try {
        const res = await api.get<{ data: Employee } | Employee>(`/employees/${employeeId.value}`)
        employee.value = (res as { data?: Employee })?.data ?? (res as Employee)
        if (employee.value) {
            breadcrumb.set(employee.value.fullName)
        }
    } catch (err) {
        console.error('Failed to load employee', err)
        employee.value = null
    } finally {
        loading.value = false
    }
}

// ---- Tabs ----------------------------------------------------------------
type TabKey = 'overview' | 'compensation' | 'attendance' | 'leaves' | 'payslips' | 'appraisals' | 'assets'
interface Tab { key: TabKey; label: string; icon: string; visible: () => boolean }

const canSeeAttendance = computed(() =>
    authStore.hasPermission('hrm.attendance.read') || authStore.hasPermission('hrm.attendance.read.self')
)

const isOwnProfile = computed(() => {
    const myEmail = authStore.user?.email
    return !!(myEmail && employee.value && myEmail === employee.value.email)
})

const canSeeAssets = computed(() =>
    authStore.hasPermission('assets.tracking.read') || isOwnProfile.value
)

const tabs: Tab[] = [
    { key: 'overview', label: 'Overview', icon: 'ti-user', visible: () => true },
    { key: 'compensation', label: 'Compensation', icon: 'ti-cash', visible: () => canSeeSalary.value },
    { key: 'attendance', label: 'Attendance', icon: 'ti-fingerprint', visible: () => canSeeAttendance.value },
    { key: 'leaves', label: 'Leaves', icon: 'ti-calendar-event', visible: () => canSeeLeaves.value },
    { key: 'payslips', label: 'Payslips', icon: 'ti-receipt-2', visible: () => canSeeSalary.value },
    { key: 'appraisals', label: 'Appraisals', icon: 'ti-clipboard-list', visible: () => canSeeAppraisals.value },
    { key: 'assets', label: 'Assets', icon: 'ti-package', visible: () => canSeeAssets.value }
]

const visibleTabs = computed(() => tabs.filter(t => t.visible()))

const activeTab = ref<TabKey>('overview')

const setTab = (key: TabKey) => {
    activeTab.value = key
    router.replace({ query: { ...route.query, tab: key } })
}

// Resolve tab from ?tab= once the employee loads & permissions settle.
const resolveInitialTab = () => {
    const requested = route.query.tab as TabKey | undefined
    if (requested && visibleTabs.value.some(t => t.key === requested)) {
        activeTab.value = requested
    } else {
        activeTab.value = 'overview'
    }
}

// ---- Lazy data per tab ---------------------------------------------------
const leaves = reactive({
    loaded: false,
    loading: false,
    list: [] as Leave[],
    balance: [] as LeaveBalanceRow[],
    balanceLoading: false
})

const payslips = reactive({
    loaded: false,
    loading: false,
    list: [] as Payslip[]
})

const appraisals = reactive({
    loaded: false,
    loading: false,
    list: [] as Appraisal[]
})

interface AttendanceLog {
    id: string
    date: string
    checkIn: string | null
    checkOut: string | null
    status: string
}
const attendance = reactive({
    loaded: false,
    loading: false,
    list: [] as AttendanceLog[],
    summary: { present: 0, late: 0, absent: 0, halfDay: 0, paidLeave: 0, unpaidLeave: 0 }
})

const loadLeaves = async () => {
    if (leaves.loaded || leaves.loading) return
    leaves.loading = true
    leaves.balanceLoading = true
    try {
        const [reqs, bal] = await Promise.all([
            api.get<Paginated<Leave>>(`/hrm/timeoff/leaves?employeeId=${employeeId.value}&limit=100`),
            api.get<{ data: LeaveBalanceRow[] }>(`/employees/${employeeId.value}/leave-balance`)
        ])
        leaves.list = reqs.data
        leaves.balance = bal.data || []
        leaves.loaded = true
    } catch (err) {
        console.error('Failed to load leaves', err)
    } finally {
        leaves.loading = false
        leaves.balanceLoading = false
    }
}

const loadPayslips = async () => {
    if (payslips.loaded || payslips.loading) return
    payslips.loading = true
    try {
        const res = await api.get<Paginated<Payslip>>(`/payslips?employeeId=${employeeId.value}&limit=100`)
        payslips.list = res.data
        payslips.loaded = true
    } catch (err) {
        console.error('Failed to load payslips', err)
    } finally {
        payslips.loading = false
    }
}

const loadAppraisals = async () => {
    if (appraisals.loaded || appraisals.loading) return
    appraisals.loading = true
    try {
        const res = await api.get<Paginated<Appraisal>>(`/appraisals?employeeId=${employeeId.value}&limit=100`)
        appraisals.list = res.data
        appraisals.loaded = true
    } catch (err) {
        console.error('Failed to load appraisals', err)
    } finally {
        appraisals.loading = false
    }
}

const loadAttendance = async () => {
    if (attendance.loaded || attendance.loading) return
    attendance.loading = true
    try {
        const res = await api.get<Paginated<AttendanceLog>>(`/attendance/logs?employeeId=${employeeId.value}&limit=60`)
        attendance.list = res.data
        // Roll up the page into counters so the summary tiles render immediately —
        // no extra API call required. Acceptable approximation for the profile view;
        // payroll uses the precise server-side count.
        attendance.summary = res.data.reduce((acc, l) => {
            if (l.status === 'present') acc.present++
            else if (l.status === 'late') acc.late++
            else if (l.status === 'absent') acc.absent++
            else if (l.status === 'half_day') acc.halfDay++
            else if (l.status === 'paid_leave') acc.paidLeave++
            else if (l.status === 'unpaid_leave') acc.unpaidLeave++
            return acc
        }, { present: 0, late: 0, absent: 0, halfDay: 0, paidLeave: 0, unpaidLeave: 0 })
        attendance.loaded = true
    } catch (err) {
        console.error('Failed to load attendance', err)
    } finally {
        attendance.loading = false
    }
}

const attendanceStatusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    if (s === 'present') return 'success'
    if (s === 'late' || s === 'half_day' || s === 'early_out') return 'warning'
    if (s === 'absent' || s === 'unpaid_leave') return 'danger'
    if (s === 'paid_leave') return 'info'
    return 'secondary'
}

const formatAttendanceTime = (iso: string | null): string => {
    if (!iso) return '—'
    return new Date(iso).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
}

watch(activeTab, (tab) => {
    if (!employee.value) return
    if (tab === 'leaves') loadLeaves()
    else if (tab === 'payslips') loadPayslips()
    else if (tab === 'appraisals') loadAppraisals()
    else if (tab === 'attendance') loadAttendance()
}, { immediate: false })

// ---- Display helpers -----------------------------------------------------
const initials = (e: Employee) =>
    `${(e.firstName?.[0] || '').toUpperCase()}${(e.lastName?.[0] || '').toUpperCase()}` || 'EM'

const statusLabel = (s: string) =>
    s === 'active' ? 'Active' : s === 'on_leave' ? 'On leave' : 'Terminated'

const genderLabel = (g: string | null | undefined): string | null => {
    if (!g) return null
    if (g === 'male') return 'Male'
    if (g === 'female') return 'Female'
    if (g === 'other') return 'Other'
    return g
}

const statusDotClass = (s: string) =>
    s === 'active' ? 'bg-(--color-success)'
        : s === 'on_leave' ? 'bg-(--color-warning)'
            : 'bg-(--color-danger)'

const statusVariant = (s: string): 'success' | 'warning' | 'danger' =>
    s === 'active' ? 'success' : s === 'on_leave' ? 'warning' : 'danger'

const leaveStatusVariant = (s: string): 'success' | 'warning' | 'danger' =>
    s === 'approved' ? 'success' : s === 'pending' ? 'warning' : 'danger'

const appraisalStatusVariant = (s: AppraisalStatus): 'secondary' | 'info' | 'warning' | 'success' => {
    if (s === 'submitted') return 'info'
    if (s === 'reviewed') return 'warning'
    if (s === 'closed') return 'success'
    return 'secondary'
}

const assetStatusVariant = (s: string): 'success' | 'warning' | 'danger' | 'secondary' | 'info' => {
    const v = s.toLowerCase()
    if (v === 'in_use' || v === 'active' || v === 'assigned') return 'success'
    if (v === 'maintenance' || v === 'repair') return 'warning'
    if (v === 'disposed' || v === 'lost' || v === 'stolen') return 'danger'
    if (v === 'available' || v === 'idle' || v === 'in_stock') return 'info'
    return 'secondary'
}

const assetConditionVariant = (c: string): 'success' | 'info' | 'warning' | 'danger' | 'secondary' => {
    const v = c.toLowerCase()
    if (v === 'new' || v === 'excellent') return 'success'
    if (v === 'good') return 'info'
    if (v === 'fair') return 'warning'
    if (v === 'poor' || v === 'damaged' || v === 'broken') return 'danger'
    return 'secondary'
}

const openAsset = (id: string) => {
    router.push({ path: '/assets', query: { highlight: id } })
}

const ratingColor = (n: number) => {
    if (n >= 4) return 'text-(--color-success)'
    if (n >= 3) return 'text-(--color-primary)'
    if (n >= 2) return 'text-(--color-warning)'
    return 'text-(--color-danger)'
}

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

const sumValues = (obj: Record<string, number> | null): number =>
    obj ? Object.values(obj).reduce((acc, v) => acc + (Number(v) || 0), 0) : 0

const openEditOnList = () => {
    // Defer Edit to the list page (existing modal). Pass id via query — the
    // list already pre-fills its search filter from ?search=.
    router.push({ path: '/hrm/employees', query: { search: employee.value!.employeeId } })
}

// ---- Inline tiny components ----------------------------------------------
const InfoRow = (props: { label: string; value?: string | null; mono?: boolean }) =>
    h('div', { class: 'flex flex-col gap-0.5' }, [
        h('dt', { class: 'text-xxs uppercase tracking-widest font-bold text-(--text-muted)' }, props.label),
        h('dd', {
            class: ['text-(--text-body) truncate', props.mono ? 'font-mono text-xs' : 'text-xs']
        }, props.value && String(props.value).length ? String(props.value) : '—')
    ])

const MetricBox = (props: { label: string; value: string; sub?: string; icon: string; variant: 'primary' | 'info' | 'secondary' }) => {
    const tone = props.variant === 'primary' ? 'text-(--color-primary) bg-(--color-primary-subtle)'
        : props.variant === 'info' ? 'text-(--color-info, var(--color-primary)) bg-(--color-info-subtle, var(--color-primary-subtle))'
            : 'text-(--text-muted) bg-(--bg-muted)'
    return h('div', { class: 'rounded-xl border border-(--border-color) p-4 flex items-center gap-3' }, [
        h('span', { class: `w-10 h-10 rounded-lg flex items-center justify-center text-lg ${tone}` }, [
            h('i', { class: `ti ${props.icon}` })
        ]),
        h('div', { class: 'min-w-0' }, [
            h('p', { class: 'text-xxs uppercase tracking-widest font-bold text-(--text-muted)' }, props.label),
            h('p', { class: 'text-sm font-mono font-semibold text-(--text-heading) truncate' }, props.value),
            props.sub
                ? h('p', { class: 'text-xxs text-(--text-muted) truncate' }, props.sub)
                : null
        ])
    ])
}

// ---- Bootstrap -----------------------------------------------------------
onMounted(async () => {
    await loadEmployee()
    if (!employee.value) return
    resolveInitialTab()
    // Eagerly trigger lazy load for whatever tab landed (covers deep-links).
    if (activeTab.value === 'leaves') loadLeaves()
    else if (activeTab.value === 'payslips') loadPayslips()
    else if (activeTab.value === 'appraisals') loadAppraisals()
    else if (activeTab.value === 'attendance') loadAttendance()
})

onBeforeUnmount(() => {
    // Clear so the next route's breadcrumb isn't stuck on this employee's name.
    breadcrumb.clear()
})
</script>

<style scoped>
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    color: var(--text-muted);
    background: var(--bg-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.icon-btn:hover {
    background: var(--color-primary-subtle);
    color: var(--color-primary);
}

.tab-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border-radius: 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.tab-trigger:hover {
    color: var(--text-heading);
    background: var(--bg-muted);
}

.tab-trigger-active {
    color: var(--color-primary);
    background: var(--color-primary-subtle);
}
</style>
