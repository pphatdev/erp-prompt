<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Applications</h1>
          <p class="text-xs text-(--text-muted) mt-1">
            <span v-if="activeVacancy">For <span class="font-semibold text-(--text-heading)">{{ activeVacancy.title }}</span>.</span>
            <span v-else>Applicant pipeline across all vacancies.</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <NuxtLink to="/candidates" class="btn btn-ghost text-xs" title="Board view">
            <i class="ti ti-layout-kanban" />Board view
          </NuxtLink>
          <NuxtLink to="/applications/new" class="btn btn-primary text-xs">
            <i class="ti ti-user-plus" />Add candidate
          </NuxtLink>
          <button class="btn btn-ghost text-xs" @click="openSubmitModal">
            <i class="ti ti-plus" />Quick submit
          </button>
        </div>
      </header>

      <!-- Filters -->
      <section class="glass-card rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <div class="relative md:col-span-5">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
            <input v-model="filters.search" type="search" placeholder="Search by name or email..." class="form-control pl-9" />
          </div>

          <div class="md:col-span-4">
            <select v-model="filters.jobVacancyId" class="form-control">
              <option :value="''">All vacancies</option>
              <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
            </select>
          </div>

          <div class="md:col-span-3">
            <select v-model="filters.status" class="form-control">
              <option :value="''">All status</option>
              <option value="applied">Applied</option>
              <option value="screening">Screening</option>
              <option value="interview">Interview</option>
              <option value="offer">Offer</option>
              <option value="hired">Hired</option>
              <option value="rejected">Rejected</option>
              <option value="deleted">Deleted</option>
            </select>
          </div>
        </div>
      </section>

      <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading applications...</span>
      </div>

      <div v-else-if="applications.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-user-question text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No applications</h4>
        <p class="text-xs text-(--text-muted) mt-1">Submit an application or share the vacancy link.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <!-- Bulk action toolbar -->
        <transition name="bulkbar">
          <div
            v-if="selectedCount > 0"
            class="bulk-toolbar"
          >
            <div class="flex items-center gap-2 text-xs">
              <span class="font-semibold text-(--color-primary)">{{ selectedCount }} selected</span>
              <span class="text-(--text-muted)">·</span>
              <button type="button" class="text-(--text-muted) hover:text-(--text-heading) underline-offset-2 hover:underline" @click="clearSelection">
                Clear
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="canConvert"
                type="button"
                class="btn btn-soft-primary text-xs px-3 py-1.5"
                :disabled="bulkConverting || selectedConvertible.length === 0"
                :title="selectedConvertible.length === 0 ? 'No hired (and not yet linked) rows in the selection' : 'Convert the hired rows in your selection to employees'"
                @click="bulkConvert"
              >
                <i :class="['ti', bulkConverting ? 'ti-loader animate-spin' : 'ti-user-plus']" />
                {{ bulkConverting
                    ? 'Converting...'
                    : `Convert ${selectedConvertible.length} to Employee` }}
              </button>
              <button
                v-if="canWrite"
                type="button"
                class="btn btn-ghost text-xs px-3 py-1.5 text-(--color-danger) hover:bg-(--color-danger-subtle) hover:text-(--color-danger)"
                :disabled="bulkDeleting || selectedDeletable.length === 0"
                :title="selectedDeletable.length === 0 ? 'No applied/screening rows in the selection' : 'Delete the applied/screening rows in your selection'"
                @click="bulkDelete"
              >
                <i :class="['ti', bulkDeleting ? 'ti-loader animate-spin' : 'ti-trash']" />
                {{ bulkDeleting
                    ? 'Deleting...'
                    : `Delete ${selectedDeletable.length}` }}
              </button>
            </div>
          </div>
        </transition>

        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="pl-4 pr-1 py-3 w-8">
                  <input
                    type="checkbox"
                    class="row-checkbox"
                    :checked="allSelectableSelected"
                    :indeterminate.prop="someSelectableSelected && !allSelectableSelected"
                    :disabled="selectableRows.length === 0"
                    :title="selectableRows.length === 0 ? 'No selectable rows on this page' : 'Select all eligible rows'"
                    @change="toggleSelectAll"
                  >
                </th>
                <th class="px-4 py-3 font-semibold">Code</th>
                <th class="px-4 py-3 font-semibold">Applicant</th>
                <th class="px-4 py-3 font-semibold">Vacancy</th>
                <th class="px-4 py-3 font-semibold">Applied</th>
                <th v-if="canSeeSalary" class="px-4 py-3 font-semibold font-mono text-right">Expected</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr
                v-for="a in applications"
                :key="a.id"
                class="transition-colors"
                :class="selectedIds.has(a.id) ? 'bg-(--color-primary-subtle)/30' : 'hover:bg-(--bg-muted)'"
              >
                <td class="pl-4 pr-1 py-3 w-8">
                  <input
                    type="checkbox"
                    class="row-checkbox"
                    :checked="selectedIds.has(a.id)"
                    :disabled="!isSelectable(a)"
                    :title="isSelectable(a) ? 'Select' : 'Only applied/screening or unconverted-hired rows can be selected'"
                    @change="toggleRow(a)"
                  >
                </td>
                <td class="px-4 py-3">
                  <span class="font-mono text-xxs text-(--text-muted)">{{ a.candidateCode || '—' }}</span>
                </td>
                <td class="px-4 py-3">
                  <NuxtLink
                    :to="`/candidates/${a.id}`"
                    class="text-xs font-semibold text-(--text-heading) hover:text-(--color-primary) hover:underline underline-offset-2"
                  >
                    {{ a.applicantName }}
                  </NuxtLink>
                  <div class="text-xxs text-(--text-muted)">{{ a.applicantEmail }}</div>
                </td>
                <td class="px-4 py-3 text-xs">{{ a.vacancy?.title || '—' }}</td>
                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatDateTime(a.appliedAt) }}</td>
                <td v-if="canSeeSalary" class="px-4 py-3 font-mono text-xs text-right">
                  {{ a.expectedSalary != null ? formatMoney(a.expectedSalary) : '—' }}
                </td>
                <td class="px-4 py-3">
                  <Badge :variant="statusVariant(a.status)" :dot="true">{{ a.status }}</Badge>
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    class="action-trigger"
                    :class="{ 'action-trigger-open': actionMenu.open && actionMenu.app?.id === a.id }"
                    title="Actions"
                    @click.stop="openActionMenu(a, $event)"
                  >
                    <i class="ti ti-dots-vertical" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <Pagination
          :page="pagination.page"
          :limit="pagination.limit"
          :total="pagination.total"
          :total-pages="pagination.totalPages"
          @update:page="(p) => { pagination.page = p; loadApplications() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadApplications() }"
        />
      </section>

      <!-- Edit modal -->
      <div v-if="showEditModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-base font-semibold text-(--text-heading)">Edit application</h3>
              <p class="text-xxs text-(--text-muted) mt-1">
                Vacancy &amp; status are locked. Use the kanban or Move action to change status.
              </p>
            </div>
            <button class="topbar-btn" @click="closeEditModal"><i class="ti ti-x" /></button>
          </header>

          <form class="form-grid" @submit.prevent="submitEdit">
            <div class="form-grid-full">
              <label class="form-label">Vacancy</label>
              <input :value="editApp?.vacancy?.title || '—'" type="text" disabled class="form-control" />
            </div>

            <div>
              <label class="form-label form-label-required">Applicant name</label>
              <input v-model="editForm.applicant_name" type="text" required class="form-control" />
            </div>
            <div>
              <label class="form-label form-label-required">Email</label>
              <input v-model="editForm.applicant_email" type="email" required class="form-control" />
            </div>

            <div>
              <label class="form-label">Phone</label>
              <input v-model="editForm.applicant_phone" type="tel" class="form-control" />
            </div>
            <div>
              <label class="form-label">Location</label>
              <input v-model="editForm.location" type="text" class="form-control" placeholder="City, Country" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">LinkedIn URL</label>
              <input v-model="editForm.linkedin_url" type="url" class="form-control" placeholder="https://linkedin.com/in/..." />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Resume path</label>
              <input v-model="editForm.resume_path" type="text" class="form-control font-mono" />
            </div>

            <div>
              <label class="form-label">Expected salary</label>
              <input v-model.number="editForm.expected_salary" type="number" min="0" step="0.01" class="form-control font-mono" />
            </div>
            <div>
              <label class="form-label">Referrer (employee)</label>
              <select v-model="editForm.referrer_employee_id" class="form-control">
                <option :value="''">— No referrer</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
              </select>
            </div>

            <div class="form-grid-full">
              <label class="form-label">Skills (comma-separated)</label>
              <input v-model="editForm._skillsText" type="text" class="form-control" placeholder="TypeScript, React, Node.js" />
              <span class="form-hint">Separated by commas. Trailing/leading whitespace is ignored.</span>
            </div>

            <div class="form-grid-full">
              <label class="form-label">Cover letter</label>
              <textarea v-model="editForm.cover_letter" rows="4" class="form-control" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Notes</label>
              <textarea v-model="editForm.notes" rows="3" class="form-control" placeholder="Internal recruiter notes (visible to staff only)." />
            </div>

            <div v-if="editError" class="form-grid-full form-error">{{ editError }}</div>

            <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
              <button type="button" class="btn btn-ghost text-xs" @click="closeEditModal">Cancel</button>
              <button type="submit" class="btn btn-primary text-xs" :disabled="editSaving">
                <i class="ti ti-device-floppy" />{{ editSaving ? 'Saving...' : 'Save changes' }}
              </button>
            </footer>
          </form>
        </div>
      </div>

      <!-- Submit modal -->
      <div v-if="showSubmitModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-(--text-heading)">Submit application</h3>
            <button class="topbar-btn" @click="showSubmitModal = false"><i class="ti ti-x" /></button>
          </header>

          <form class="form-grid" @submit.prevent="submitApplication">
            <div class="form-grid-full">
              <label class="form-label form-label-required">Vacancy</label>
              <select v-model="form.job_vacancy_id" required class="form-control">
                <option value="" disabled>Select vacancy...</option>
                <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
              </select>
            </div>

            <div>
              <label class="form-label form-label-required">Applicant name</label>
              <input v-model="form.applicant_name" type="text" required class="form-control" />
            </div>
            <div>
              <label class="form-label form-label-required">Email</label>
              <input v-model="form.applicant_email" type="email" required class="form-control" />
            </div>

            <div>
              <label class="form-label">Phone</label>
              <input v-model="form.applicant_phone" type="tel" class="form-control" />
            </div>
            <div>
              <label class="form-label">Expected salary</label>
              <input v-model.number="form.expected_salary" type="number" min="0" step="0.01" class="form-control font-mono" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Resume URL / path</label>
              <input v-model="form.resume_path" type="text" class="form-control" placeholder="storage/resumes/dara-kim.pdf" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Cover letter</label>
              <textarea v-model="form.cover_letter" rows="4" class="form-control" placeholder="Why this role?" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Referrer (employee)</label>
              <select v-model="form.referrer_employee_id" class="form-control">
                <option :value="''">— No referrer</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
              </select>
            </div>

            <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

            <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
              <button type="button" class="btn btn-ghost text-xs" @click="showSubmitModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                <i class="ti ti-send" />{{ saving ? 'Submitting...' : 'Submit' }}
              </button>
            </footer>
          </form>
        </div>
      </div>

      <!-- Details modal -->
      <div v-if="detailsOpen" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-base font-semibold text-(--text-heading)">{{ detailsApp?.applicantName }}</h3>
              <p class="text-xxs text-(--text-muted) mt-1">
                <span v-if="detailsApp?.candidateCode" class="font-mono text-(--color-primary)">{{ detailsApp.candidateCode }}</span>
                <span v-if="detailsApp?.candidateCode" class="px-1.5">·</span>
                {{ detailsApp?.applicantEmail }}
              </p>
            </div>
            <button class="topbar-btn" @click="detailsOpen = false"><i class="ti ti-x" /></button>
          </header>

          <dl class="text-xs space-y-3">
            <div class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Vacancy</dt>
              <dd class="text-(--text-heading)">{{ detailsApp?.vacancy?.title || '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Phone</dt>
              <dd class="text-(--text-body) font-mono">{{ detailsApp?.applicantPhone || '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Location</dt>
              <dd class="text-(--text-body)">{{ detailsApp?.location || '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">LinkedIn</dt>
              <dd class="text-(--text-body)">
                <a v-if="detailsApp?.linkedinUrl" :href="detailsApp.linkedinUrl" target="_blank" class="text-(--color-primary) hover:underline font-mono">
                  View Profile
                </a>
                <span v-else>—</span>
              </dd>
            </div>
            <div v-if="canSeeSalary" class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Expected</dt>
              <dd class="text-(--text-body) font-mono">{{ detailsApp?.expectedSalary != null ? formatMoney(detailsApp.expectedSalary) : '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Applied</dt>
              <dd class="text-(--text-body) font-mono">{{ formatDateTime(detailsApp?.appliedAt || null) }}</dd>
            </div>
            <div v-if="detailsApp?.resumePath" class="flex justify-between gap-3">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Resume</dt>
              <dd class="text-(--color-primary) font-mono truncate max-w-[60%]" :title="detailsApp.resumePath">{{ detailsApp.resumePath }}</dd>
            </div>
            <div v-if="detailsApp?.skills?.length" class="flex flex-col gap-1.5 pt-2">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Skills</dt>
              <dd class="flex flex-wrap gap-1">
                <span v-for="skill in detailsApp.skills" :key="skill" class="px-2 py-0.5 rounded bg-(--color-primary-subtle) text-(--color-primary) font-semibold text-[10px]">
                  {{ skill }}
                </span>
              </dd>
            </div>
            <div v-if="detailsApp?.education?.length" class="flex flex-col gap-1.5 pt-2 border-t border-(--border-color)">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Education</dt>
              <dd class="space-y-2">
                <div v-for="(edu, index) in detailsApp.education" :key="index" class="text-xxs">
                  <p class="font-bold text-(--text-heading)">{{ edu.degree }}</p>
                  <p class="text-(--text-muted)">{{ edu.school }}</p>
                </div>
              </dd>
            </div>
            <div v-if="detailsApp?.workExperience?.length" class="flex flex-col gap-1.5 pt-2 border-t border-(--border-color)">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Work Experience</dt>
              <dd class="space-y-3">
                <div v-for="(exp, index) in detailsApp.workExperience" :key="index" class="text-xxs space-y-0.5">
                  <div class="flex justify-between font-bold text-(--text-heading)">
                    <span>{{ exp.title }}</span>
                    <span class="text-(--text-muted) font-normal font-mono">{{ exp.period }}</span>
                  </div>
                  <p class="text-(--color-primary)">{{ exp.company }}</p>
                  <p class="text-(--text-body) mt-1 leading-normal whitespace-pre-line">{{ exp.description }}</p>
                </div>
              </dd>
            </div>
            <div v-if="detailsApp?.coverLetter" class="pt-2 border-t border-(--border-color)">
              <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold mb-1">Cover letter</dt>
              <dd class="rounded-lg bg-(--bg-muted) p-3 text-(--text-body) whitespace-pre-wrap">{{ detailsApp.coverLetter }}</dd>
            </div>
          </dl>

          <!-- Hired → employee conversion. Shown only for hired applications;
               flips to a View employee link once the link is established. -->
          <div v-if="isHired(detailsApp)" class="mt-5 pt-4 border-t border-(--border-color) flex items-center justify-between gap-3">
            <div class="text-xxs text-(--text-muted) flex-1">
              <p v-if="isConverted(detailsApp)" class="text-(--color-success) font-semibold inline-flex items-center gap-1.5">
                <i class="ti ti-circle-check" /> Linked to employee
              </p>
              <p v-else>
                Create the Employee record for this hire.<br>
                Department, position, and base salary will be copied from the vacancy.
              </p>
            </div>
            <NuxtLink
              v-if="isConverted(detailsApp)"
              :to="`/employees?id=${detailsApp!.employeeId}`"
              class="btn btn-soft-primary text-xs"
            >
              <i class="ti ti-user-check" /> View employee
            </NuxtLink>
            <button
              v-else-if="canConvert"
              type="button"
              class="btn btn-primary text-xs"
              :disabled="converting"
              @click="convertFromDetails"
            >
              <i :class="['ti', converting ? 'ti-loader animate-spin' : 'ti-user-plus']" />
              {{ converting ? 'Converting...' : 'Convert to Employee' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Action dropdown -->
      <div
        v-if="actionMenu.open && actionMenu.app"
        class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
        :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }"
        @click.stop
      >
        <button
          class="action-item"
          @click="actionOpenProfile"
        >
          <i class="ti ti-user-circle" /> Open profile
        </button>
        <button
          class="action-item"
          @click="actionView"
        >
          <i class="ti ti-eye" /> Quick details
        </button>
        <button
          v-if="canWrite"
          class="action-item"
          @click="actionEdit"
        >
          <i class="ti ti-pencil" /> Edit
        </button>
        <template v-if="isHired(actionMenu.app)">
          <hr class="my-1 border-(--border-color)" />
          <button
            v-if="isConverted(actionMenu.app)"
            class="action-item"
            @click="actionViewEmployee"
          >
            <i class="ti ti-user-check" /> View employee
          </button>
          <button
            v-else-if="canConvert"
            class="action-item action-item-primary"
            @click="actionConvert"
          >
            <i class="ti ti-user-plus" /> Convert to Employee
          </button>
        </template>
        <template v-if="canDelete(actionMenu.app)">
          <hr class="my-1 border-(--border-color)" />
          <button
            class="action-item action-item-danger"
            @click="actionDelete"
          >
            <i class="ti ti-trash" /> Delete
          </button>
        </template>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { formatDateTime } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface VacancyLite { id: string; title: string }
interface EmployeeLite { id: string; employeeId: string; fullName: string }

type ApplicationStatus = 'applied' | 'screening' | 'interview' | 'offer' | 'hired' | 'rejected' | 'withdrawn'

interface Application {
  id: string
  candidateCode: string | null
  jobVacancyId: string
  employeeId: string | null
  applicantName: string
  applicantEmail: string
  applicantPhone: string | null
  location: string | null
  linkedinUrl: string | null
  resumePath: string | null
  coverLetter: string | null
  workExperience: any[] | null
  education: any[] | null
  skills: string[] | null
  expectedSalary: number | null
  notes: string | null
  status: ApplicationStatus
  appliedAt: string | null
  vacancy?: VacancyLite
  referrer?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const canSeeSalary = computed(() => authStore.hasPermission('hrm.recruitment.read'))
const canConvert = computed(() =>
  authStore.hasPermission('hrm.recruitment.write') &&
  authStore.hasPermission('hrm.employee.write')
)

const isHired = (a: Application | null) => !!a && a.status === 'hired'
const isConverted = (a: Application | null) => !!a && !!a.employeeId

const applications = ref<Application[]>([])
const vacancies = ref<VacancyLite[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({
  search: '',
  jobVacancyId: (route.query.vacancyId as string) || '',
  status: '' as '' | ApplicationStatus
})

const showSubmitModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
  job_vacancy_id: (route.query.vacancyId as string) || '',
  applicant_name: '',
  applicant_email: '',
  applicant_phone: '',
  location: '',
  linkedin_url: '',
  resume_path: '',
  cover_letter: '',
  work_experience: [] as any[],
  education: [] as any[],
  skills: [] as string[],
  expected_salary: null as number | null,
  referrer_employee_id: ''
})

const detailsOpen = ref(false)
const detailsApp = ref<Application | null>(null)

const showEditModal = ref(false)
const editApp = ref<Application | null>(null)
const editSaving = ref(false)
const editError = ref<string | null>(null)
const editForm = reactive({
  applicant_name: '',
  applicant_email: '',
  applicant_phone: '',
  location: '',
  linkedin_url: '',
  resume_path: '',
  cover_letter: '',
  notes: '',
  expected_salary: null as number | null,
  referrer_employee_id: '',
  _skillsText: ''
})

const actionMenu = reactive({
  open: false,
  x: 0,
  y: 0,
  app: null as Application | null
})

const canDelete = (a: Application | null) =>
  !!a && (a.status === 'applied' || a.status === 'screening')

// --- Bulk selection -------------------------------------------------------
// Selection is keyed by application id. We deliberately KEEP selections
// across pages/filters so a user can build up a batch — but clear them after
// a successful bulk delete.
const selectedIds = ref<Set<string>>(new Set())
const bulkDeleting = ref(false)

// A row is selectable if EITHER of the bulk actions can act on it:
// - deletable (applied/screening), per canDelete
// - convertible (hired + not yet linked to an employee), gated by canConvert
const isBulkConvertible = (a: Application) =>
  canConvert.value && a.status === 'hired' && !a.employeeId

const isSelectable = (a: Application) =>
  canDelete(a) || isBulkConvertible(a)

const selectableRows = computed(() => applications.value.filter(isSelectable))

// Per-action eligibility within the current selection — drives the toolbar
// button counts and disabled states.
const selectedApps = computed(() =>
  applications.value.filter(a => selectedIds.value.has(a.id))
)
const selectedDeletable = computed(() => selectedApps.value.filter(canDelete))
const selectedConvertible = computed(() => selectedApps.value.filter(isBulkConvertible))

const selectedCount = computed(() => selectedIds.value.size)

const allSelectableSelected = computed(() =>
  selectableRows.value.length > 0 &&
  selectableRows.value.every(a => selectedIds.value.has(a.id))
)

const someSelectableSelected = computed(() =>
  selectableRows.value.some(a => selectedIds.value.has(a.id))
)

const toggleRow = (a: Application) => {
  if (!isSelectable(a)) return
  const next = new Set(selectedIds.value)
  if (next.has(a.id)) next.delete(a.id)
  else next.add(a.id)
  selectedIds.value = next
}

const toggleSelectAll = () => {
  const next = new Set(selectedIds.value)
  if (allSelectableSelected.value) {
    selectableRows.value.forEach(a => next.delete(a.id))
  } else {
    selectableRows.value.forEach(a => next.add(a.id))
  }
  selectedIds.value = next
}

const clearSelection = () => { selectedIds.value = new Set() }

const activeVacancy = computed(() =>
  vacancies.value.find(v => v.id === filters.jobVacancyId) || null
)

const statusVariant = (s: ApplicationStatus): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
  switch (s) {
    case 'applied': return 'secondary'
    case 'screening': return 'info'
    case 'interview': return 'warning'
    case 'offer': return 'primary'
    case 'hired': return 'success'
    case 'rejected': return 'danger'
    case 'withdrawn': return 'secondary'
  }
}

const formatMoney = (n: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

const loadLookups = async () => {
  try {
    const [v, e] = await Promise.all([
      api.get<Paginated<VacancyLite>>('/job-vacancies?limit=100&status=open'),
      api.get<Paginated<EmployeeLite>>('/employees?limit=100')
    ])
    vacancies.value = v.data
    employees.value = e.data
  } catch (err) {
    console.error('Failed to load lookups', err)
  }
}

const loadApplications = async () => {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
    if (filters.search) q.set('search', filters.search)
    if (filters.jobVacancyId) q.set('jobVacancyId', filters.jobVacancyId)
    if (filters.status) q.set('status', filters.status)

    const res = await api.get<Paginated<Application>>(`/applications?${q.toString()}`)
    applications.value = res.data
    pagination.total = res.pagination.total
    pagination.totalPages = res.pagination.totalPages
  } catch (err) {
    console.error('Failed to load applications', err)
    applications.value = []
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.search, filters.jobVacancyId, filters.status], () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    pagination.page = 1
    loadApplications()
  }, 300)
})

const openSubmitModal = () => {
  Object.assign(form, {
    job_vacancy_id: filters.jobVacancyId || '',
    applicant_name: '', applicant_email: '', applicant_phone: '',
    resume_path: '', cover_letter: '', expected_salary: null, referrer_employee_id: ''
  })
  formError.value = null
  showSubmitModal.value = true
}

const submitApplication = async () => {
  saving.value = true
  formError.value = null
  try {
    const payload: Record<string, any> = { ...form }
    if (!payload.referrer_employee_id) payload.referrer_employee_id = null
    if (!payload.applicant_phone) payload.applicant_phone = null
    if (!payload.resume_path) payload.resume_path = null
    if (!payload.cover_letter) payload.cover_letter = null

    await api.post('/applications', payload)
    showSubmitModal.value = false
    await loadApplications()
  } catch (err: any) {
    formError.value = err.data?.message || 'Failed to submit application.'
  } finally {
    saving.value = false
  }
}

const openDetailsModal = (a: Application) => {
  detailsApp.value = a
  detailsOpen.value = true
}

const openEditModal = (a: Application) => {
  editApp.value = a
  Object.assign(editForm, {
    applicant_name: a.applicantName ?? '',
    applicant_email: a.applicantEmail ?? '',
    applicant_phone: a.applicantPhone ?? '',
    location: a.location ?? '',
    linkedin_url: a.linkedinUrl ?? '',
    resume_path: a.resumePath ?? '',
    cover_letter: a.coverLetter ?? '',
    notes: a.notes ?? '',
    expected_salary: a.expectedSalary,
    referrer_employee_id: '',
    _skillsText: (a.skills ?? []).join(', ')
  })
  editError.value = null
  showEditModal.value = true
}

const closeEditModal = () => {
  if (editSaving.value) return
  showEditModal.value = false
  editApp.value = null
}

const submitEdit = async () => {
  if (!editApp.value) return
  editSaving.value = true
  editError.value = null
  try {
    const skills = editForm._skillsText
      .split(',')
      .map(s => s.trim())
      .filter(Boolean)

    const payload: Record<string, any> = {
      applicant_name: editForm.applicant_name.trim(),
      applicant_email: editForm.applicant_email.trim(),
      applicant_phone: editForm.applicant_phone || null,
      location: editForm.location || null,
      linkedin_url: editForm.linkedin_url || null,
      resume_path: editForm.resume_path || null,
      cover_letter: editForm.cover_letter || null,
      notes: editForm.notes || null,
      expected_salary: editForm.expected_salary,
      referrer_employee_id: editForm.referrer_employee_id || null,
      skills: skills.length ? skills : null
    }

    await api.put(`/applications/${editApp.value.id}`, payload)
    showEditModal.value = false
    editApp.value = null
    await loadApplications()
    toast.success('Application updated', 'Changes saved.')
  } catch (err: any) {
    editError.value = err?.data?.message || 'Failed to update application.'
  } finally {
    editSaving.value = false
  }
}

// Position the dropdown anchored to the trigger button, flipped above when
// there isn't enough room below. The menu is fixed-positioned so it escapes
// the table's overflow clipping.
const openActionMenu = (a: Application, ev: MouseEvent) => {
  const target = ev.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const menuWidth = 180
  const menuMaxHeight = 160
  const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
  const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
  actionMenu.app = a
  actionMenu.x = Math.max(8, left)
  actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
  actionMenu.open = true
}

const closeActionMenu = () => {
  actionMenu.open = false
  actionMenu.app = null
}

const actionOpenProfile = () => {
  const app = actionMenu.app
  closeActionMenu()
  if (app) router.push(`/candidates/${app.id}`)
}

const actionView = () => {
  const app = actionMenu.app
  closeActionMenu()
  if (app) openDetailsModal(app)
}

const actionEdit = () => {
  const app = actionMenu.app
  closeActionMenu()
  if (app) openEditModal(app)
}

const actionDelete = async () => {
  const app = actionMenu.app
  closeActionMenu()
  if (!app) return
  const ok = await toast.confirm({
    title: `Delete ${app.applicantName}'s application?`,
    description: 'This cannot be undone.',
    confirmLabel: 'Delete',
    color: 'danger'
  })
  if (!ok) return
  try {
    await api.delete(`/applications/${app.id}`)
    selectedIds.value.delete(app.id)
    await loadApplications()
  } catch (err: any) {
    toast.error('Failed to delete.', err?.data?.message)
  }
}

// ---- Hire → Employee conversion ------------------------------------------
const converting = ref(false)

interface ConvertedEmployee { id: string; employeeId?: string; fullName?: string }
interface ConvertResponse {
  data: ConvertedEmployee
  created: boolean
  linkedExisting: boolean
}

const convertApplicationToEmployee = async (app: Application): Promise<boolean> => {
  if (!isHired(app) || isConverted(app)) return false
  const ok = await toast.confirm({
    title: `Convert ${app.applicantName} to an employee?`,
    description: 'Creates an Employee record using the vacancy’s department, position, and expected salary. If an Employee with the same email already exists, the application links to that one instead.',
    confirmLabel: 'Convert',
    color: 'primary',
    icon: 'ti-user-plus'
  })
  if (!ok) return false
  converting.value = true
  try {
    const res = await api.post<ConvertResponse>(`/applications/${app.id}/convert-to-employee`)
    const emp = res.data
    const empCode = emp.employeeId || emp.id

    const openEmployeeOnList = () => router.push({ path: '/employees', query: { search: empCode } })

    if (res.linkedExisting) {
      toast.warning(
        'Linked to existing employee',
        `${app.applicantName}'s email is already on file as ${emp.fullName || empCode}. No new employee row was created.`,
        { duration: 10000, actionLabel: 'View on employee list', onAction: openEmployeeOnList }
      )
    } else {
      toast.success(
        'Employee created',
        `${app.applicantName} is now on the employee list as ${empCode}.`,
        { duration: 10000, actionLabel: 'View on employee list', onAction: openEmployeeOnList }
      )
    }

    await loadApplications()
    if (detailsApp.value?.id === app.id) {
      detailsApp.value = { ...detailsApp.value!, employeeId: emp.id }
    }
    return true
  } catch (err: any) {
    toast.error('Conversion failed.', err?.data?.message)
    return false
  } finally {
    converting.value = false
  }
}

const actionConvert = async () => {
  const app = actionMenu.app
  closeActionMenu()
  if (!app) return
  await convertApplicationToEmployee(app)
}

const actionViewEmployee = () => {
  const app = actionMenu.app
  closeActionMenu()
  if (!app?.employeeId) return
  router.push(`/employees?id=${app.employeeId}`)
}

const convertFromDetails = async () => {
  if (!detailsApp.value) return
  await convertApplicationToEmployee(detailsApp.value)
}

const bulkDelete = async () => {
  if (bulkDeleting.value) return
  const ids = selectedDeletable.value.map(a => a.id)
  if (ids.length === 0) return
  const ok = await toast.confirm({
    title: `Delete ${ids.length} application${ids.length === 1 ? '' : 's'}?`,
    description: 'This cannot be undone.',
    confirmLabel: 'Delete all',
    color: 'danger'
  })
  if (!ok) return

  bulkDeleting.value = true
  try {
    const res = await api.post<{ deleted: number; skipped: string[]; missing: string[] }>(
      '/applications/bulk-delete',
      { ids }
    )
    // Drop the deleted ids from the selection set, but keep any other selected
    // (e.g. hired/convertible) rows so the user can chain a Convert next.
    ids.forEach(id => selectedIds.value.delete(id))
    await loadApplications()
    if (res.skipped?.length || res.missing?.length) {
      const parts: string[] = [`${res.deleted} deleted`]
      if (res.skipped?.length) parts.push(`${res.skipped.length} skipped (past screening)`)
      if (res.missing?.length) parts.push(`${res.missing.length} not found`)
      toast.info('Bulk delete completed', parts.join(' · '))
    } else {
      toast.success('Bulk delete complete', `${res.deleted} application${res.deleted === 1 ? '' : 's'} deleted.`)
    }
  } catch (err: any) {
    toast.error('Bulk delete failed.', err?.data?.message)
  } finally {
    bulkDeleting.value = false
  }
}

const bulkConverting = ref(false)
interface BulkConvertResult {
  converted: number
  linkedExisting: string[]
  alreadyLinked: string[]
  ineligible: string[]
  missing: string[]
  errors: Array<{ id: string; message: string }>
}

const bulkConvert = async () => {
  if (bulkConverting.value) return
  const ids = selectedConvertible.value.map(a => a.id)
  if (ids.length === 0) return
  const ok = await toast.confirm({
    title: `Convert ${ids.length} candidate${ids.length === 1 ? '' : 's'} to employees?`,
    description: 'Creates Employee records using each vacancy’s department, position, and the candidate’s expected salary. Existing employees (matched by email) are reused, not duplicated.',
    confirmLabel: 'Convert all',
    color: 'primary',
    icon: 'ti-user-plus'
  })
  if (!ok) return

  bulkConverting.value = true
  try {
    const res = await api.post<BulkConvertResult>('/applications/bulk-convert-to-employee', { ids })
    ids.forEach(id => selectedIds.value.delete(id))
    await loadApplications()

    const errored = res.errors?.length ?? 0
    const linked = res.linkedExisting?.length ?? 0
    const partial = linked > 0 || (res.alreadyLinked?.length ?? 0) > 0
      || (res.ineligible?.length ?? 0) > 0 || (res.missing?.length ?? 0) > 0 || errored > 0

    if (partial) {
      const parts: string[] = [`${res.converted} new`]
      if (linked)                    parts.push(`${linked} linked to existing email`)
      if (res.alreadyLinked?.length) parts.push(`${res.alreadyLinked.length} already linked`)
      if (res.ineligible?.length)    parts.push(`${res.ineligible.length} not hired`)
      if (res.missing?.length)       parts.push(`${res.missing.length} not found`)
      if (errored)                   parts.push(`${errored} failed`)
      toast.info('Bulk conversion completed', parts.join(' · '))
    } else {
      toast.success('Bulk conversion complete', `${res.converted} employee${res.converted === 1 ? '' : 's'} created.`)
    }
  } catch (err: any) {
    toast.error('Bulk conversion failed.', err?.data?.message)
  } finally {
    bulkConverting.value = false
  }
}

onMounted(async () => {
  if (import.meta.client) {
    document.addEventListener('click', closeActionMenu)
  }
  await Promise.all([loadLookups(), loadApplications()])
})
</script>

<style scoped>
.topbar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: var(--text-muted);
  cursor: pointer;
}
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }

.action-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); }
.action-trigger-open { background: var(--bg-muted); color: var(--color-primary); }

.action-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  color: var(--text-heading);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.action-item:hover { background: var(--bg-muted); }
.action-item-danger { color: var(--color-danger); }
.action-item-danger:hover { background: var(--color-danger-subtle); }
.action-item-primary { color: var(--color-primary); font-weight: 600; }
.action-item-primary:hover { background: var(--color-primary-subtle); }

.row-checkbox {
  width: 1rem;
  height: 1rem;
  border-radius: 4px;
  border: 1px solid var(--border-strong);
  background: var(--bg-card);
  accent-color: var(--color-primary);
  cursor: pointer;
  transition: border-color 0.15s ease;
}
.row-checkbox:hover:not(:disabled) { border-color: var(--color-primary); }
.row-checkbox:disabled { opacity: 0.4; cursor: not-allowed; }
.row-checkbox:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.bulk-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.625rem 1rem;
  background: var(--color-primary-subtle);
  border-bottom: 1px solid rgb(var(--color-primary-rgb) / 0.2);
}

.bulkbar-enter-active, .bulkbar-leave-active {
  transition: opacity 0.15s ease, max-height 0.2s ease;
  overflow: hidden;
}
.bulkbar-enter-from, .bulkbar-leave-to { opacity: 0; max-height: 0; }
.bulkbar-enter-to, .bulkbar-leave-from { opacity: 1; max-height: 60px; }
</style>
