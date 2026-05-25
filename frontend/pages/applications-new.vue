<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Wizard header -->
            <header class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading)">Add New Candidate</h1>
                    <p class="text-xs text-(--text-muted) mt-1">{{ stepHint }}</p>
                </div>

                <!-- Stepper -->
                <ol class="flex items-center gap-1 sm:gap-2 self-start xl:self-end">
                    <li v-for="(s, i) in STEPS" :key="s.id" class="flex items-center gap-1 sm:gap-2">
                        <button type="button" class="step-pill" :class="stepPillClass(i)" :disabled="!canJumpTo(i)"
                            @click="goToStep(i)">
                            <span class="step-bubble">
                                <i v-if="isComplete(i)" class="ti ti-check text-[12px]" />
                                <span v-else>{{ i + 1 }}</span>
                            </span>
                            <span class="hidden sm:inline">{{ s.label }}</span>
                        </button>
                        <span v-if="i < STEPS.length - 1" class="step-bar"
                            :class="isComplete(i) ? 'step-bar-done' : ''" />
                    </li>
                </ol>
            </header>

            <!-- Active step body -->
            <!-- ===================== STEP 1 — UPLOAD ===================== -->
            <section v-if="activeStep === 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-5">
                    <div class="glass-card rounded-2xl p-8 transition-all"
                        :class="dragActive ? 'ring-2 ring-(--color-primary) bg-(--color-primary-subtle)' : ''"
                        @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false"
                        @drop.prevent="onResumeDrop">
                        <div class="text-center flex flex-col items-center gap-3 py-6">
                            <span
                                class="w-14 h-14 rounded-2xl bg-(--color-primary-subtle) text-(--color-primary) inline-flex items-center justify-center">
                                <i class="ti ti-cloud-upload text-2xl" />
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-(--text-heading)">Drop a resume PDF here</h3>
                                <p class="text-xs text-(--text-muted) mt-1">
                                    Or upload from disk. We parse the file to pre-fill the next step.
                                </p>
                            </div>

                            <input ref="fileInput" type="file" accept="application/pdf,.pdf,.doc,.docx" class="hidden"
                                @change="onResumePick">
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" class="btn btn-primary text-xs"
                                    :disabled="uploadState === 'uploading'" @click="fileInput?.click()">
                                    <i
                                        :class="['ti', uploadState === 'uploading' ? 'ti-loader animate-spin' : 'ti-file-upload']" />
                                    {{ uploadState === 'uploading' ? 'Uploading...' : 'Browse file' }}
                                </button>
                                <button v-if="resumeFile || uploadState === 'failed'" type="button"
                                    class="btn btn-ghost text-xs" :disabled="uploadState === 'uploading'"
                                    @click="clearResume">
                                    <i class="ti ti-x" /> Remove
                                </button>
                                <button v-if="uploadState === 'failed' && resumeFile" type="button"
                                    class="btn btn-soft-primary text-xs" @click="retryUpload">
                                    <i class="ti ti-refresh" /> Retry
                                </button>
                            </div>

                            <div v-if="resumeFile" class="mt-2 flex flex-col items-center gap-1">
                                <p class="text-xxs text-(--text-muted) font-mono">
                                    {{ resumeFile.name }} · {{ formatBytes(resumeFile.size) }}
                                </p>
                                <p v-if="uploadState === 'uploading'"
                                    class="text-xxs text-(--color-info) inline-flex items-center gap-1">
                                    <span
                                        class="w-3 h-3 rounded-full border-2 border-(--color-info)/30 border-t-(--color-info) animate-spin" />
                                    Uploading to tenant storage...
                                </p>
                                <p v-else-if="uploadState === 'success'"
                                    class="text-xxs text-(--color-success) inline-flex items-center gap-1">
                                    <i class="ti ti-circle-check" /> Uploaded · stored as <span class="font-mono">{{
                                        uploadedPath }}</span>
                                </p>
                                <p v-else-if="uploadState === 'failed'"
                                    class="text-xxs text-(--color-danger) inline-flex items-center gap-1">
                                    <i class="ti ti-alert-circle" /> {{ uploadError || 'Upload failed.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl p-5">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Skip the upload
                        </h4>
                        <p class="text-xs text-(--text-body) mb-3">
                            No resume on file? You can enter the candidate's information manually in the next step.
                        </p>
                        <button type="button" class="btn btn-ghost text-xs" @click="goToStep(1)">
                            Enter manually <i class="ti ti-arrow-right" />
                        </button>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="glass-card rounded-2xl p-5">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Target vacancy
                        </h4>
                        <label class="form-label form-label-required">Vacancy</label>
                        <select v-model="form.job_vacancy_id" required class="form-control">
                            <option value="" disabled>Select vacancy...</option>
                            <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
                        </select>
                        <p class="form-hint">The pipeline column this candidate joins after submission.</p>
                    </div>

                    <div class="glass-card rounded-2xl p-5 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">Tips</h4>
                        <ul class="text-xs text-(--text-body) space-y-2">
                            <li class="flex gap-2"><i class="ti ti-info-circle text-(--color-info) mt-0.5" /> PDF, DOC,
                                DOCX accepted — up to 10 MB.</li>
                            <li class="flex gap-2"><i class="ti ti-shield-check text-(--color-success) mt-0.5" /> Files
                                stay within tenant <span class="font-mono">@{{ tenantHandle }}</span>.</li>
                            <li class="flex gap-2"><i class="ti ti-eye text-(--color-warning) mt-0.5" /> Review parsed
                                fields before saving.</li>
                        </ul>
                    </div>
                </aside>
            </section>

            <!-- ===================== STEP 2 — REVIEW & EDIT ===================== -->
            <section v-else-if="activeStep === 1" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Left: form -->
                <div class="xl:col-span-2 space-y-8">
                    <!-- Basic info -->
                    <fieldset class="glass-card rounded-2xl p-6">
                        <legend class="flex items-center justify-between w-full mb-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-(--text-heading)">
                                <i class="ti ti-user text-(--color-primary)" /> Basic Information
                            </span>
                            <span class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Step 1 of
                                4</span>
                        </legend>

                        <div class="form-grid">
                            <div>
                                <label class="form-label form-label-required">Full name</label>
                                <input v-model="form.applicant_name" type="text" required class="form-control">
                            </div>
                            <div>
                                <label class="form-label form-label-required">Email</label>
                                <input v-model="form.applicant_email" type="email" required class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Phone</label>
                                <input v-model="form.applicant_phone" type="tel" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">Location</label>
                                <input v-model="form.location" type="text" class="form-control"
                                    placeholder="City, Country">
                            </div>
                            <div class="form-grid-full">
                                <label class="form-label">LinkedIn profile</label>
                                <div class="input-with-icon">
                                    <span class="input-icon"><i class="ti ti-link" /></span>
                                    <input v-model="form.linkedin_url" type="url" class="form-control"
                                        placeholder="https://linkedin.com/in/...">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Work experience -->
                    <fieldset class="glass-card rounded-2xl p-6">
                        <legend class="flex items-center justify-between w-full mb-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-(--text-heading)">
                                <i class="ti ti-briefcase text-(--color-primary)" /> Work Experience
                            </span>
                            <button type="button" class="btn btn-soft-primary text-xs" @click="addExperience">
                                <i class="ti ti-plus" /> Add experience
                            </button>
                        </legend>

                        <div v-if="!form.work_experience.length"
                            class="rounded-xl border border-dashed border-(--border-color) text-xxs text-(--text-muted) py-8 text-center">
                            No experience entries yet. Add one with the button above.
                        </div>

                        <div v-else class="space-y-3">
                            <div v-for="(exp, idx) in form.work_experience" :key="idx"
                                class="rounded-xl border border-(--border-color) bg-(--bg-muted) p-4 group relative">
                                <button type="button"
                                    class="absolute right-3 top-3 w-7 h-7 rounded-lg text-(--text-muted) hover:bg-(--color-danger-subtle) hover:text-(--color-danger) flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                    @click="removeExperience(idx)">
                                    <i class="ti ti-trash text-sm" />
                                </button>

                                <div class="flex items-start gap-3 mb-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) inline-flex items-center justify-center shrink-0">
                                        <i class="ti ti-building text-lg" />
                                    </div>
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <input v-model="exp.title" type="text" class="form-control"
                                            placeholder="Job title">
                                        <input v-model="exp.company" type="text" class="form-control"
                                            placeholder="Company">
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div>
                                        <label class="form-label">Period</label>
                                        <input v-model="exp.period" type="text" class="form-control"
                                            placeholder="Jan 2021 – Present">
                                    </div>
                                    <div>
                                        <label class="form-label">Location</label>
                                        <input v-model="exp.location" type="text" class="form-control"
                                            placeholder="Remote">
                                    </div>
                                    <div class="form-grid-full">
                                        <label class="form-label">Responsibilities</label>
                                        <textarea v-model="exp.description" rows="3" class="form-control"
                                            placeholder="Key responsibilities and impact..." />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Education + Skills -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <fieldset class="glass-card rounded-2xl p-6">
                            <legend class="flex items-center justify-between w-full mb-4">
                                <span class="flex items-center gap-2 text-sm font-semibold text-(--text-heading)">
                                    <i class="ti ti-school text-(--color-primary)" /> Education
                                </span>
                                <button type="button" class="btn btn-soft-primary text-xs" @click="addEducation">
                                    <i class="ti ti-plus" /> Add
                                </button>
                            </legend>

                            <div v-if="!form.education.length"
                                class="rounded-lg border border-dashed border-(--border-color) text-xxs text-(--text-muted) py-6 text-center">
                                No education entries yet.
                            </div>

                            <div v-else class="space-y-3">
                                <div v-for="(edu, idx) in form.education" :key="idx"
                                    class="rounded-lg border border-(--border-color) bg-(--bg-muted) p-3 group relative">
                                    <button type="button"
                                        class="absolute right-2 top-2 w-6 h-6 rounded-md text-(--text-muted) hover:bg-(--color-danger-subtle) hover:text-(--color-danger) flex items-center justify-center opacity-0 group-hover:opacity-100"
                                        @click="removeEducation(idx)">
                                        <i class="ti ti-x text-xs" />
                                    </button>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="form-label">Degree &amp; major</label>
                                            <input v-model="edu.degree" type="text" class="form-control"
                                                placeholder="B.S. Computer Science">
                                        </div>
                                        <div>
                                            <label class="form-label">School</label>
                                            <input v-model="edu.school" type="text" class="form-control"
                                                placeholder="University name">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="glass-card rounded-2xl p-6">
                            <legend class="flex items-center justify-between w-full mb-4">
                                <span class="flex items-center gap-2 text-sm font-semibold text-(--text-heading)">
                                    <i class="ti ti-bolt text-(--color-primary)" /> Skills
                                </span>
                            </legend>

                            <div class="flex flex-wrap gap-2 mb-3">
                                <span v-for="(skill, idx) in form.skills" :key="`${skill}-${idx}`"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-(--color-primary-subtle) text-(--color-primary) border border-(--color-primary)/20 text-xs">
                                    {{ skill }}
                                    <button type="button" class="hover:text-(--text-heading)" @click="removeSkill(idx)">
                                        <i class="ti ti-x text-[10px]" />
                                    </button>
                                </span>
                                <span v-if="!form.skills.length"
                                    class="text-xxs text-(--text-muted) italic self-center">
                                    No skills yet.
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <input v-model="skillDraft" type="text" class="form-control"
                                    placeholder="Type a skill and press Enter" @keydown.enter.prevent="addSkill"
                                    @keydown.,.prevent="addSkill">
                                <button type="button" class="btn btn-soft-primary text-xs" @click="addSkill">
                                    <i class="ti ti-plus" />
                                </button>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Compensation / extras -->
                    <fieldset class="glass-card rounded-2xl p-6">
                        <legend class="flex items-center justify-between w-full mb-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-(--text-heading)">
                                <i class="ti ti-cash text-(--color-primary)" /> Compensation &amp; sourcing
                            </span>
                        </legend>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">Expected salary</label>
                                <input v-model.number="form.expected_salary" type="number" min="0" step="0.01"
                                    class="form-control font-mono">
                            </div>
                            <div>
                                <label class="form-label">Referrer (employee)</label>
                                <select v-model="form.referrer_employee_id" class="form-control">
                                    <option :value="''">— No referrer</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{
                                        e.employeeId }})</option>
                                </select>
                            </div>
                            <div class="form-grid-full">
                                <label class="form-label">Cover letter</label>
                                <textarea v-model="form.cover_letter" rows="4" class="form-control"
                                    placeholder="Why this role?" />
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- Right: resume preview -->
                <aside class="space-y-4 xl:sticky xl:top-20 self-start">
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <header class="flex items-center justify-between px-4 py-3 border-b border-(--border-color)">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="ti ti-file-cv text-lg text-(--color-danger)" />
                                <span class="text-xs font-semibold text-(--text-heading) truncate">
                                    {{ resumeFile?.name || 'No resume attached' }}
                                </span>
                            </div>
                            <div class="inline-flex items-center gap-1 bg-(--bg-muted) rounded-lg p-1">
                                <button type="button"
                                    class="w-6 h-6 rounded text-(--text-muted) hover:text-(--text-heading) hover:bg-(--bg-card) inline-flex items-center justify-center"
                                    @click="zoom = Math.max(0.5, zoom - 0.1)">
                                    <i class="ti ti-minus text-xs" />
                                </button>
                                <span class="text-xxs font-mono px-1">{{ Math.round(zoom * 100) }}%</span>
                                <button type="button"
                                    class="w-6 h-6 rounded text-(--text-muted) hover:text-(--text-heading) hover:bg-(--bg-card) inline-flex items-center justify-center"
                                    @click="zoom = Math.min(2, zoom + 0.1)">
                                    <i class="ti ti-plus text-xs" />
                                </button>
                            </div>
                        </header>

                        <div class="relative bg-(--bg-muted) h-[520px] overflow-auto custom-scrollbar">
                            <div class="origin-top-left transition-transform mx-auto my-4"
                                :style="{ transform: `scale(${zoom})`, width: 'fit-content' }">
                                <div v-if="!resumeFile"
                                    class="w-[360px] h-[480px] rounded-lg border border-dashed border-(--border-color) bg-(--bg-card) flex flex-col items-center justify-center gap-2 text-(--text-muted)">
                                    <i class="ti ti-file-off text-3xl" />
                                    <p class="text-xs">No resume attached</p>
                                    <button type="button" class="btn btn-ghost text-xs mt-2" @click="goToStep(0)">
                                        <i class="ti ti-upload" /> Upload resume
                                    </button>
                                </div>

                                <div v-else
                                    class="w-[360px] rounded-lg border border-(--border-color) bg-white shadow-(--shadow-sm) overflow-hidden">
                                    <div class="p-6 text-slate-700 text-xs space-y-4">
                                        <div>
                                            <p class="text-base font-bold text-slate-900">{{ form.applicant_name ||
                                                'Candidate name' }}</p>
                                            <p class="text-slate-500">{{ form.applicant_email || '—' }} · {{
                                                form.applicant_phone || '—' }}</p>
                                            <p class="text-slate-500">{{ form.location || '—' }}</p>
                                        </div>

                                        <div v-if="form.work_experience.length">
                                            <p
                                                class="uppercase tracking-widest text-[10px] font-bold text-slate-400 mb-1">
                                                Experience</p>
                                            <div v-for="(exp, idx) in form.work_experience" :key="`pv-${idx}`"
                                                class="mb-2">
                                                <p class="font-semibold text-slate-900">{{ exp.title || 'Role' }}</p>
                                                <p class="text-slate-500">{{ exp.company || '—' }} · {{ exp.period ||
                                                    '—' }}</p>
                                            </div>
                                        </div>

                                        <div v-if="form.education.length">
                                            <p
                                                class="uppercase tracking-widest text-[10px] font-bold text-slate-400 mb-1">
                                                Education</p>
                                            <div v-for="(edu, idx) in form.education" :key="`ed-${idx}`" class="mb-1">
                                                <p class="font-semibold text-slate-900">{{ edu.degree || '—' }}</p>
                                                <p class="text-slate-500">{{ edu.school || '—' }}</p>
                                            </div>
                                        </div>

                                        <div v-if="form.skills.length">
                                            <p
                                                class="uppercase tracking-widest text-[10px] font-bold text-slate-400 mb-1">
                                                Skills</p>
                                            <p>{{ form.skills.join(' · ') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-(--bg-card) rounded-full px-3 py-1.5 shadow-(--shadow-md) border border-(--border-color)">
                                <button type="button" class="w-6 h-6 rounded-full hover:text-(--color-primary)"><i
                                        class="ti ti-chevron-left text-sm" /></button>
                                <span
                                    class="text-xxs font-mono text-(--text-muted) px-2 border-x border-(--border-color)">Page
                                    1 of 1</span>
                                <button type="button" class="w-6 h-6 rounded-full hover:text-(--color-primary)"><i
                                        class="ti ti-chevron-right text-sm" /></button>
                            </div>
                        </div>
                    </div>
                </aside>
            </section>

            <!-- ===================== STEP 3 — FINALIZE ===================== -->
            <section v-else-if="activeStep === 2" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-5">
                    <div class="glass-card rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-5 pb-3 border-b border-(--border-color)">
                            <div>
                                <h3 class="text-base font-semibold text-(--text-heading)">Review &amp; confirm</h3>
                                <p class="text-xxs text-(--text-muted) mt-0.5">Verify everything below — then submit to
                                    enter the pipeline.</p>
                            </div>
                            <button type="button" class="btn btn-ghost text-xs" @click="goToStep(1)">
                                <i class="ti ti-edit" /> Edit
                            </button>
                        </div>

                        <dl class="text-xs grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <dt class="form-label !mb-1">Name</dt>
                                <dd class="text-(--text-heading) font-semibold">{{ form.applicant_name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="form-label !mb-1">Email</dt>
                                <dd class="text-(--text-body) font-mono">{{ form.applicant_email || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="form-label !mb-1">Phone</dt>
                                <dd class="text-(--text-body) font-mono">{{ form.applicant_phone || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="form-label !mb-1">Location</dt>
                                <dd class="text-(--text-body)">{{ form.location || '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="form-label !mb-1">Vacancy</dt>
                                <dd class="text-(--text-heading)">{{ selectedVacancy?.title || '—' }}</dd>
                            </div>
                            <div v-if="form.linkedin_url" class="sm:col-span-2">
                                <dt class="form-label !mb-1">LinkedIn</dt>
                                <dd>
                                    <a :href="form.linkedin_url" target="_blank"
                                        class="text-(--color-primary) hover:underline font-mono break-all">{{
                                        form.linkedin_url }}</a>
                                </dd>
                            </div>
                            <div v-if="form.expected_salary != null">
                                <dt class="form-label !mb-1">Expected salary</dt>
                                <dd class="text-(--text-body) font-mono">{{ formatMoney(form.expected_salary) }}</dd>
                            </div>
                            <div v-if="selectedReferrer">
                                <dt class="form-label !mb-1">Referrer</dt>
                                <dd class="text-(--text-body)">{{ selectedReferrer.fullName }}</dd>
                            </div>
                            <div v-if="form.skills.length" class="sm:col-span-2">
                                <dt class="form-label !mb-1">Skills</dt>
                                <dd class="flex flex-wrap gap-1.5">
                                    <span v-for="skill in form.skills" :key="skill"
                                        class="px-2 py-0.5 rounded bg-(--color-primary-subtle) text-(--color-primary) font-semibold text-[10px]">
                                        {{ skill }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="form.work_experience.length" class="sm:col-span-2">
                                <dt class="form-label !mb-1">Experience ({{ form.work_experience.length }})</dt>
                                <dd class="space-y-1">
                                    <div v-for="(exp, idx) in form.work_experience" :key="`fr-${idx}`"
                                        class="text-xxs text-(--text-body)">
                                        <span class="font-semibold text-(--text-heading)">{{ exp.title || '—' }}</span>
                                        · {{ exp.company || '—' }} · {{ exp.period || '—' }}
                                    </div>
                                </dd>
                            </div>
                            <div v-if="form.education.length" class="sm:col-span-2">
                                <dt class="form-label !mb-1">Education ({{ form.education.length }})</dt>
                                <dd class="space-y-1">
                                    <div v-for="(edu, idx) in form.education" :key="`fe-${idx}`"
                                        class="text-xxs text-(--text-body)">
                                        <span class="font-semibold text-(--text-heading)">{{ edu.degree || '—' }}</span>
                                        — {{ edu.school || '—' }}
                                    </div>
                                </dd>
                            </div>
                            <div v-if="form.cover_letter" class="sm:col-span-2">
                                <dt class="form-label !mb-1">Cover letter</dt>
                                <dd
                                    class="rounded-lg bg-(--bg-muted) p-3 text-(--text-body) whitespace-pre-wrap leading-relaxed">
                                    {{ form.cover_letter }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="glass-card rounded-2xl p-5">
                        <h4 class="text-xs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Pipeline entry
                        </h4>
                        <p class="text-xs text-(--text-body) mb-3">
                            On submit, the candidate is placed in the <span
                                class="badge-soft-secondary text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full">Applied</span>
                            column for
                            <span class="font-semibold text-(--text-heading)">{{ selectedVacancy?.title || 'the selected vacancy' }}</span>.
                        </p>
                        <ul class="text-xxs text-(--text-muted) space-y-1.5">
                            <li class="flex gap-2"><i class="ti ti-check text-(--color-success) mt-0.5" /> Audit entry
                                recorded</li>
                            <li class="flex gap-2"><i class="ti ti-check text-(--color-success) mt-0.5" /> Recruiter
                                notified</li>
                            <li class="flex gap-2"><i class="ti ti-check text-(--color-success) mt-0.5" /> Tenant scope:
                                <span class="font-mono">@{{ tenantHandle }}</span></li>
                        </ul>
                    </div>

                    <div v-if="resumeFile" class="glass-card rounded-2xl p-4 flex items-center gap-3">
                        <span
                            class="w-10 h-10 rounded-lg bg-(--color-danger-subtle) text-(--color-danger) inline-flex items-center justify-center shrink-0">
                            <i class="ti ti-file-cv text-lg" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-(--text-heading) truncate">{{ resumeFile.name }}</p>
                            <p class="text-xxs text-(--text-muted) font-mono">{{ formatBytes(resumeFile.size) }}</p>
                        </div>
                    </div>
                </aside>
            </section>

            <!-- Inline error -->
            <div v-if="submitError" class="form-error">{{ submitError }}</div>

            <!-- Footer nav -->
            <footer
                class="sticky bottom-0 -mx-4 md:-mx-8 px-4 md:px-8 py-4 bg-(--header-bg) backdrop-blur-xl border-t border-(--border-color) flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 z-10">
                <button v-if="activeStep > 0" type="button" class="btn btn-ghost text-xs" :disabled="saving"
                    @click="goToStep(activeStep - 1)">
                    <i class="ti ti-arrow-left" /> Back
                </button>
                <NuxtLink v-else to="/applications" class="btn btn-ghost text-xs">
                    <i class="ti ti-arrow-left" /> Back to applications
                </NuxtLink>

                <div class="flex items-center gap-2 sm:gap-3">
                    <button type="button" class="text-xs text-(--text-muted) hover:text-(--text-heading) px-3 py-2"
                        :disabled="saving" @click="discard">
                        Discard
                    </button>
                    <button v-if="activeStep < STEPS.length - 1" type="button" class="btn btn-primary text-xs"
                        :disabled="!canAdvance" @click="goToStep(activeStep + 1)">
                        Next <i class="ti ti-arrow-right" />
                    </button>
                    <button v-else type="button" class="btn btn-success text-xs" :disabled="saving || !canSubmit"
                        @click="submit">
                        <i class="ti ti-circle-check" /> {{ saving ? 'Submitting...' : 'Save & Finalize' }}
                    </button>
                </div>
            </footer>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'
import { useToast } from '~/composables/useToast'

definePageMeta({
    path: '/applications/new',
    breadcrumb: 'Add Candidate'
})

interface VacancyLite { id: string; title: string }
interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

interface WorkExperience { title: string; company: string; period: string; location: string; description: string }
interface Education { degree: string; school: string }

const api = useApi()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const toast = useToast()

const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const tenantHandle = computed(() => tenantStore.activeHandle)

const STEPS = [
    { id: 'upload', label: 'Upload' },
    { id: 'review', label: 'Review & Edit' },
    { id: 'finalize', label: 'Finalize' }
] as const

const activeStep = ref(0)
const visited = reactive<Record<number, boolean>>({ 0: true })

const vacancies = ref<VacancyLite[]>([])
const employees = ref<EmployeeLite[]>([])
const resumeFile = ref<File | null>(null)
const dragActive = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const zoom = ref(1)

type UploadState = 'idle' | 'uploading' | 'success' | 'failed'
const uploadState = ref<UploadState>('idle')
const uploadedPath = ref<string>('')
const uploadError = ref<string | null>(null)

const skillDraft = ref('')
const saving = ref(false)
const submitError = ref<string | null>(null)

const form = reactive({
    job_vacancy_id: (route.query.vacancyId as string) || '',
    applicant_name: '',
    applicant_email: '',
    applicant_phone: '',
    location: '',
    linkedin_url: '',
    resume_path: '',
    cover_letter: '',
    work_experience: [] as WorkExperience[],
    education: [] as Education[],
    skills: [] as string[],
    expected_salary: null as number | null,
    referrer_employee_id: ''
})

const stepHint = computed(() => {
    if (activeStep.value === 0) return 'Upload a resume — we parse it to pre-fill the next step.'
    if (activeStep.value === 1) return 'Verify and refine information parsed from the resume.'
    return 'Confirm the candidate and add them to the pipeline.'
})

const isComplete = (i: number) => i < activeStep.value
const canJumpTo = (i: number) => i <= activeStep.value || visited[i] === true

const stepPillClass = (i: number) => {
    if (i === activeStep.value) return 'step-pill-active'
    if (i < activeStep.value) return 'step-pill-done'
    return 'step-pill-pending'
}

const selectedVacancy = computed(() => vacancies.value.find(v => v.id === form.job_vacancy_id) || null)
const selectedReferrer = computed(() => employees.value.find(e => e.id === form.referrer_employee_id) || null)

const uploadBlocking = computed(() =>
    uploadState.value === 'uploading' || uploadState.value === 'failed'
)

const canAdvance = computed(() => {
    if (uploadBlocking.value) return false
    if (activeStep.value === 0) {
        return Boolean(form.job_vacancy_id)
    }
    if (activeStep.value === 1) {
        return Boolean(form.applicant_name.trim() && form.applicant_email.trim() && form.job_vacancy_id)
    }
    return true
})

const canSubmit = computed(() =>
    canWrite.value &&
    !uploadBlocking.value &&
    Boolean(form.applicant_name.trim() && form.applicant_email.trim() && form.job_vacancy_id)
)

const goToStep = (i: number) => {
    if (i < 0 || i >= STEPS.length) return
    if (i > activeStep.value && !canAdvance.value) return
    activeStep.value = i
    visited[i] = true
}

const onResumePick = (ev: Event) => {
    const target = ev.target as HTMLInputElement
    const file = target.files?.[0]
    if (file) acceptResume(file)
    target.value = ''
}

const onResumeDrop = (ev: DragEvent) => {
    dragActive.value = false
    const file = ev.dataTransfer?.files?.[0]
    if (file) acceptResume(file)
}

const ALLOWED_EXT = ['pdf', 'doc', 'docx']

const acceptResume = (file: File) => {
    const ext = file.name.split('.').pop()?.toLowerCase() || ''
    if (!ALLOWED_EXT.includes(ext)) {
        toast.error('Unsupported file', 'Resume must be PDF, DOC, or DOCX.')
        return
    }
    if (file.size > 10 * 1024 * 1024) {
        toast.error('File too large', 'Resume must be 10 MB or smaller.')
        return
    }
    resumeFile.value = file
    uploadResume(file)
}

const uploadResume = async (file: File) => {
    uploadState.value = 'uploading'
    uploadError.value = null
    uploadedPath.value = ''
    form.resume_path = ''

    const data = new FormData()
    data.append('file', file)

    try {
        const res = await api.post<{ path: string; original_name: string; size: number; mime_type: string }>(
            '/applications/resumes',
            data
        )
        uploadedPath.value = res.path
        form.resume_path = res.path
        uploadState.value = 'success'
        toast.success('Resume uploaded', `${res.original_name} stored on tenant disk.`)
    } catch (err: any) {
        uploadState.value = 'failed'
        uploadError.value = err?.data?.message
            || (err?.status === 413 ? 'File too large.' : null)
            || err?.message
            || 'Upload failed. Please try again.'
    }
}

const retryUpload = () => {
    if (resumeFile.value) uploadResume(resumeFile.value)
}

const clearResume = () => {
    resumeFile.value = null
    uploadState.value = 'idle'
    uploadedPath.value = ''
    uploadError.value = null
    form.resume_path = ''
}

const addExperience = () => {
    form.work_experience.push({ title: '', company: '', period: '', location: '', description: '' })
}
const removeExperience = (idx: number) => { form.work_experience.splice(idx, 1) }

const addEducation = () => {
    form.education.push({ degree: '', school: '' })
}
const removeEducation = (idx: number) => { form.education.splice(idx, 1) }

const addSkill = () => {
    const raw = skillDraft.value.trim().replace(/,$/, '').trim()
    if (!raw) return
    if (form.skills.includes(raw)) {
        skillDraft.value = ''
        return
    }
    form.skills.push(raw)
    skillDraft.value = ''
}
const removeSkill = (idx: number) => { form.skills.splice(idx, 1) }

const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

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

const discard = () => {
    if (!confirm('Discard this candidate? All entered data will be lost.')) return
    router.push('/applications')
}

const submit = async () => {
    if (!canSubmit.value) return
    saving.value = true
    submitError.value = null
    try {
        const payload: Record<string, any> = { ...form }
        if (!payload.referrer_employee_id) payload.referrer_employee_id = null
        if (!payload.applicant_phone) payload.applicant_phone = null
        if (!payload.location) payload.location = null
        if (!payload.linkedin_url) payload.linkedin_url = null
        if (!payload.resume_path) payload.resume_path = null
        if (!payload.cover_letter) payload.cover_letter = null
        if (!payload.work_experience.length) payload.work_experience = null
        if (!payload.education.length) payload.education = null
        if (!payload.skills.length) payload.skills = null

        await api.post('/applications', payload)
        toast.success('Candidate added', `${form.applicant_name} is now in the Applied column.`)
        router.push({ path: '/candidates', query: { vacancyId: form.job_vacancy_id } })
    } catch (err: any) {
        submitError.value = err?.data?.message || 'Failed to submit application.'
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    await loadLookups()
})
</script>

<style scoped>
.text-xxs {
    font-size: 0.6875rem;
    line-height: 1rem;
}

.step-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.5rem 0.25rem 0.25rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    transition: color 0.15s ease, background 0.15s ease;
    cursor: pointer;
}

.step-pill:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.step-pill:hover:not(:disabled) {
    color: var(--text-heading);
}

.step-bubble {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    transition: all 0.15s ease;
}

.step-pill-active {
    color: var(--color-primary);
}

.step-pill-active .step-bubble {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
    box-shadow: 0 4px 12px rgb(var(--color-primary-rgb) / 0.35);
}

.step-pill-done {
    color: var(--color-primary);
}

.step-pill-done .step-bubble {
    background: var(--color-primary-subtle);
    border-color: rgb(var(--color-primary-rgb) / 0.3);
    color: var(--color-primary);
}

.step-bar {
    display: inline-block;
    width: 1.5rem;
    height: 1px;
    background: var(--border-color);
}

@media (min-width: 640px) {
    .step-bar {
        width: 2.5rem;
    }
}

.step-bar-done {
    background: rgb(var(--color-primary-rgb) / 0.4);
}

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--border-strong) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: 4px;
}
</style>
