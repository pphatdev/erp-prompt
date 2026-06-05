<template>
    <NuxtLayout name="default">
        <!-- ============================ Loading ============================ -->
        <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted) font-medium">Loading candidate profile...</span>
        </div>

        <!-- ============================ Not found ========================== -->
        <div v-else-if="!app" class="py-24 flex flex-col items-center justify-center gap-3">
            <i class="ti ti-user-question text-4xl text-(--text-muted)" />
            <p class="text-sm text-(--text-heading) font-semibold">Candidate not found</p>
            <p class="text-xs text-(--text-muted)">It may have been removed or you don't have access.</p>
            <NuxtLink to="/hrm/recruitments/candidates" class="btn btn-soft-primary text-xs mt-2">
                <i class="ti ti-arrow-left" /> Back to pipeline
            </NuxtLink>
        </div>

        <!-- ============================ Profile ============================ -->
        <div v-else class="space-y-6">
            <!-- ===== Hero header ===== -->
            <section class="glass-card rounded-2xl p-6 md:p-7">
                <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
                    <!-- Avatar -->
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-xl md:text-2xl font-bold bg-(--color-primary-subtle) text-(--color-primary) shrink-0"
                        :title="app.applicantName">
                        {{ initials(app.applicantName) }}
                    </div>

                    <!-- Identity -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                            <h1 class="text-lg md:text-xl font-semibold text-(--text-heading) truncate">
                                {{ app.applicantName }}
                            </h1>
                            <span v-if="app.candidateCode"
                                class="font-mono text-xxs text-(--text-muted) bg-(--bg-muted) px-1.5 py-0.5 rounded">
                                {{ app.candidateCode }}
                            </span>
                        </div>
                        <p class="text-xs text-(--text-muted) mt-1 truncate">
                            <span class="text-(--text-body)">{{ app.vacancy?.title || 'Open Position' }}</span>
                            <span v-if="app.location"> · {{ app.location }}</span>
                            <span> · Applied {{ relativeTime(app.appliedAt) }}</span>
                        </p>
                    </div>

                    <!-- Quick contact -->
                    <div class="flex items-center gap-1.5 self-start md:self-center">
                        <a v-if="app.applicantEmail" :href="`mailto:${app.applicantEmail}`" class="icon-btn"
                            title="Send email">
                            <i class="ti ti-mail" />
                        </a>
                        <a v-if="app.applicantPhone" :href="`tel:${app.applicantPhone}`" class="icon-btn" title="Call">
                            <i class="ti ti-phone" />
                        </a>
                        <a v-if="app.linkedinUrl" :href="app.linkedinUrl" target="_blank" rel="noopener"
                            class="icon-btn" title="LinkedIn">
                            <i class="ti ti-brand-linkedin" />
                        </a>
                    </div>
                </div>
            </section>

            <!-- ===== Quick actions ===== -->
            <section class="flex flex-wrap items-center gap-3 relative">
                <div class="relative">
                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs"
                        :disabled="!nextStages.length || advancing" @click="openAdvanceMenu = !openAdvanceMenu">
                        <i :class="['ti', advancing ? 'ti-loader animate-spin' : 'ti-arrow-forward-up']" />
                        {{ advancing ? 'Updating...' : 'Advance stage' }}
                        <i class="ti ti-chevron-down text-[12px]" />
                    </button>

                    <div v-if="openAdvanceMenu && nextStages.length"
                        class="absolute top-full mt-2 left-0 z-30 glass-card rounded-xl p-1.5 shadow-(--shadow-lg) min-w-[200px]">
                        <button v-for="s in nextStages" :key="s" type="button"
                            class="w-full text-left text-xs px-3 py-2 rounded-md hover:bg-(--bg-muted) flex items-center gap-2"
                            @click="advanceTo(s)">
                            <Badge :variant="statusVariant(s)" :dot="true">{{ statusLabel(s) }}</Badge>
                        </button>
                    </div>
                </div>

                <a v-if="app.resumePath" :href="resumeHref" target="_blank" rel="noopener"
                    class="btn btn-ghost text-xs">
                    <i class="ti ti-file-cv" /> View resume
                </a>

                <button type="button" class="btn btn-ghost text-xs" @click="copyEmail">
                    <i class="ti ti-mail-share" /> {{ copied ? 'Copied!' : 'Copy email' }}
                </button>

                <div class="flex-1" />

                <Badge :variant="statusVariant(app.status)" :dot="true">
                    {{ statusLabel(app.status) }}
                </Badge>
            </section>

            <!-- ===== Tabs ===== -->
            <nav class="glass-card rounded-xl px-2 py-1.5 flex items-center gap-1 overflow-x-auto">
                <button type="button" class="tab-btn" :class="{ active: activeTab === 'overview' }"
                    @click="activeTab = 'overview'">
                    <i class="ti ti-user-circle" /> Overview
                </button>
                <button v-if="canOffer" type="button" class="tab-btn" :class="{ active: activeTab === 'offer' }"
                    @click="activeTab = 'offer'">
                    <i class="ti ti-file-certificate" /> Offer &amp; Onboarding
                </button>
            </nav>

            <!-- ===== Bento grid ===== -->
            <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- ============== LEFT COLUMN ============== -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Cover letter / Summary -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <header class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-user-search text-(--color-primary)" /> Cover letter
                            </h2>
                            <button v-if="canWrite" type="button"
                                class="text-xxs text-(--color-primary) hover:underline" @click="goToEdit">
                                Edit
                            </button>
                        </header>
                        <p v-if="app.coverLetter"
                            class="text-xs text-(--text-body) leading-relaxed whitespace-pre-wrap">{{ app.coverLetter }}
                        </p>
                        <p v-else class="text-xs text-(--text-muted) italic">
                            No cover letter submitted.
                        </p>

                        <!-- Stat row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-(--border-color)">
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-star-filled text-[12px] text-(--color-warning)" />
                                    Rating</p>
                                <p class="stat-value text-(--color-warning)">{{ rating.toFixed(1) }}</p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-calendar-time text-[12px] text-(--color-info)" />
                                    Days in pipeline</p>
                                <p class="stat-value text-(--color-info)">{{ daysInPipeline }}</p>
                            </div>
                            <div v-if="canSeeSalary" class="stat-tile">
                                <p class="stat-label"><i class="ti ti-coin text-[12px] text-(--color-primary)" />
                                    Expected</p>
                                <p class="stat-value text-(--color-primary) font-mono">
                                    {{ app.expectedSalary != null ? formatMoney(app.expectedSalary) : '—' }}
                                </p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-route text-[12px] text-(--color-secondary)" />
                                    Source</p>
                                <p class="stat-value text-(--color-secondary)">{{ shortSource(app) }}</p>
                            </div>
                        </div>
                    </article>

                    <!-- Vacancy + Referrer + Documents -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Vacancy / Referrer -->
                        <article class="glass-card rounded-2xl p-6 space-y-4">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-target text-(--color-secondary)" /> Pipeline context
                            </h2>

                            <div>
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) mb-2">Target
                                    vacancy</p>
                                <NuxtLink v-if="app.vacancy"
                                    :to="`/hrm/recruitments/applications?vacancyId=${app.vacancy.id}`"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-(--bg-muted) border border-(--border-color) hover:border-(--color-primary)/40 transition-colors">
                                    <span
                                        class="w-10 h-10 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) inline-flex items-center justify-center shrink-0">
                                        <i class="ti ti-briefcase text-lg" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                            app.vacancy.title }}</p>
                                        <p class="text-xxs text-(--text-muted)">Open pipeline · View board</p>
                                    </div>
                                    <i class="ti ti-arrow-up-right text-(--text-muted) ml-auto" />
                                </NuxtLink>
                                <p v-else class="text-xxs text-(--text-muted) italic">No vacancy linked.</p>
                            </div>

                            <div>
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) mb-2">
                                    Referred by</p>
                                <div v-if="referrer"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-(--bg-muted) border border-(--border-color)">
                                    <span
                                        class="w-9 h-9 rounded-full bg-(--color-secondary-subtle) text-(--color-secondary) inline-flex items-center justify-center font-bold text-xxs shrink-0">
                                        {{ initials(referrer.fullName) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                            referrer.fullName }}</p>
                                        <p class="text-xxs text-(--text-muted) font-mono">{{ referrer.employeeId }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-xxs text-(--text-muted) italic">No referrer — direct application.
                                </p>
                            </div>
                        </article>

                        <!-- Documents -->
                        <article class="glass-card rounded-2xl p-6 space-y-3">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-folder text-(--color-info)" /> Documents
                            </h2>

                            <a v-if="app.resumePath" :href="resumeHref" target="_blank" rel="noopener" class="doc-row">
                                <span class="doc-icon bg-(--color-danger-subtle) text-(--color-danger)">
                                    <i class="ti ti-file-cv text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading) truncate">Resume /
                                        CV</span>
                                    <span class="block text-xxs text-(--text-muted) font-mono truncate">{{
                                        app.resumePath }}</span>
                                </span>
                                <Badge variant="success" :dot="true">On file</Badge>
                            </a>
                            <div v-else class="doc-row doc-row--empty">
                                <span class="doc-icon bg-(--bg-muted) text-(--text-muted)">
                                    <i class="ti ti-file-off text-lg" />
                                </span>
                                <span class="flex-1 text-xs text-(--text-muted)">No resume uploaded</span>
                            </div>

                            <div class="doc-row">
                                <span class="doc-icon bg-(--color-primary-subtle) text-(--color-primary)">
                                    <i class="ti ti-file-description text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading)">Cover letter</span>
                                    <span class="block text-xxs text-(--text-muted)">
                                        {{ app.coverLetter ? `${app.coverLetter.length} chars` : 'Not provided' }}
                                    </span>
                                </span>
                                <Badge :variant="app.coverLetter ? 'info' : 'secondary'" :dot="true">
                                    {{ app.coverLetter ? 'Submitted' : 'Missing' }}
                                </Badge>
                            </div>

                            <a v-if="app.linkedinUrl" :href="app.linkedinUrl" target="_blank" rel="noopener"
                                class="doc-row">
                                <span class="doc-icon bg-(--color-secondary-subtle) text-(--color-secondary)">
                                    <i class="ti ti-brand-linkedin text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading)">LinkedIn
                                        profile</span>
                                    <span class="block text-xxs text-(--text-muted) font-mono truncate">{{
                                        app.linkedinUrl }}</span>
                                </span>
                                <i class="ti ti-arrow-up-right text-(--text-muted)" />
                            </a>
                        </article>
                    </div>

                    <!-- Work experience -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-briefcase-2 text-(--color-primary)" /> Work experience
                        </h2>

                        <div v-if="!workExperience.length"
                            class="rounded-lg border border-dashed border-(--border-color) text-xxs text-(--text-muted) py-6 text-center">
                            No work experience recorded for this candidate.
                        </div>

                        <ol v-else class="relative border-l-2 border-(--border-color) ml-2 space-y-5 pl-5">
                            <li v-for="(exp, idx) in workExperience" :key="idx" class="relative">
                                <span
                                    class="absolute left-[-26px] top-1 w-4 h-4 rounded-full bg-(--color-primary) border-2 border-(--bg-card)" />
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-xs font-bold text-(--text-heading)">{{ exp.title || '—' }}</p>
                                    <p class="text-xxs text-(--text-muted) font-mono">{{ exp.period || '—' }}</p>
                                </div>
                                <p class="text-xxs text-(--color-primary) font-semibold mt-0.5">
                                    {{ exp.company || '—' }}
                                    <span v-if="exp.location" class="text-(--text-muted) font-normal"> · {{ exp.location
                                        }}</span>
                                </p>
                                <p v-if="exp.description"
                                    class="text-xxs text-(--text-body) mt-2 leading-relaxed whitespace-pre-line">
                                    {{ exp.description }}
                                </p>
                            </li>
                        </ol>
                    </article>
                </div>

                <!-- ============== RIGHT COLUMN ============== -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- Skills -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <header class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-bolt text-(--color-primary)" /> Skills
                            </h2>
                            <span class="text-xxs text-(--text-muted)">{{ skills.length }}</span>
                        </header>

                        <div v-if="!skills.length" class="text-xxs text-(--text-muted) italic">
                            No skills listed.
                        </div>

                        <div v-else class="flex flex-wrap gap-1.5">
                            <span v-for="skill in skills" :key="skill"
                                class="px-2.5 py-1 rounded-full bg-(--color-primary-subtle) text-(--color-primary) border border-(--color-primary)/20 text-[11px] font-semibold">
                                {{ skill }}
                            </span>
                        </div>
                    </article>

                    <!-- Education -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-school text-(--color-info)" /> Education
                        </h2>

                        <div v-if="!education.length" class="text-xxs text-(--text-muted) italic">
                            No education recorded.
                        </div>

                        <ul v-else class="space-y-3">
                            <li v-for="(edu, idx) in education" :key="idx" class="flex items-start gap-3">
                                <span
                                    class="w-8 h-8 rounded-lg bg-(--color-info-subtle) text-(--color-info) inline-flex items-center justify-center shrink-0">
                                    <i class="ti ti-certificate" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-(--text-heading)">{{ edu.degree || '—' }}</p>
                                    <p class="text-xxs text-(--text-muted)">{{ edu.school || '—' }}</p>
                                </div>
                            </li>
                        </ul>
                    </article>

                    <!-- Timeline -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-timeline-event text-(--color-secondary)" /> Application timeline
                        </h2>

                        <ol
                            class="relative space-y-4 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-(--border-color)">
                            <li v-for="event in timeline" :key="event.key" class="relative pl-8">
                                <span
                                    class="absolute left-0 top-0.5 w-6 h-6 rounded-full inline-flex items-center justify-center border-2"
                                    :class="event.done ? 'bg-(--color-primary) border-(--color-primary) text-white' : 'bg-(--bg-muted) border-(--border-color) text-(--text-muted)'">
                                    <i :class="['ti text-[12px]', event.icon]" />
                                </span>
                                <p class="text-xs font-semibold text-(--text-heading)">{{ event.label }}</p>
                                <p class="text-[11px] text-(--text-muted) mt-0.5">{{ event.detail }}</p>
                            </li>
                        </ol>
                    </article>

                    <!-- Conversion footer (if hired) -->
                    <article v-if="app.status === 'hired'"
                        class="glass-card rounded-2xl p-6 space-y-3 border border-(--color-success)/30">
                        <h2 class="text-sm font-semibold text-(--color-success) flex items-center gap-2">
                            <i class="ti ti-confetti" /> Hired!
                        </h2>
                        <p v-if="app.employeeId" class="text-xxs text-(--text-muted)">
                            This candidate is linked to an employee record.
                        </p>
                        <p v-else-if="app.pendingAppointmentRequest" class="text-xxs text-(--text-muted)">
                            An appointment request is awaiting HR approval. The employee record will be created once approved.
                        </p>
                        <p v-else class="text-xxs text-(--text-muted)">
                            Submit an appointment request for HR approval. Once approved, the employee record is created from the data below.
                        </p>
                        <NuxtLink v-if="app.employeeId" :to="`/hrm/employees/${app.employeeId}`"
                            class="btn btn-soft-primary text-xs w-full justify-center">
                            <i class="ti ti-user-check" /> View employee
                        </NuxtLink>
                        <NuxtLink v-else-if="app.pendingAppointmentRequest"
                            :to="`/approvals/requests/${app.pendingAppointmentRequest.id}`"
                            class="btn btn-soft-warning text-xs w-full justify-center">
                            <i class="ti ti-hourglass-high" /> View pending request
                        </NuxtLink>
                        <NuxtLink v-else-if="canWrite"
                            :to="`/approvals/forms/employee-appointment?applicationId=${app.id}`"
                            class="btn btn-primary text-xs w-full justify-center">
                            <i class="ti ti-send" /> Request Appointment of Employee
                        </NuxtLink>
                    </article>
                </div>
            </div>

            <!-- ===== Offer & Onboarding tab ===== -->
            <section v-else-if="activeTab === 'offer' && canOffer" class="space-y-6">
                <div v-if="offerLoading" class="py-16 flex flex-col items-center gap-3">
                    <span
                        class="w-7 h-7 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    <span class="text-xxs text-(--text-muted)">Loading offer...</span>
                </div>

                <template v-else>
                    <!-- Too early in pipeline -->
                    <article v-if="!offer && !canDraftFromStage"
                        class="glass-card rounded-2xl p-6 text-center space-y-3">
                        <i class="ti ti-info-circle text-3xl text-(--color-info)" />
                        <h3 class="text-sm font-semibold text-(--text-heading)">Not ready for an offer yet</h3>
                        <p class="text-xs text-(--text-muted)">
                            Advance the candidate to the <strong>Offer</strong> stage from "Advance stage" above
                            before drafting the letter.
                        </p>
                    </article>

                    <!-- Ready to draft (status === 'offer') -->
                    <article v-else-if="!offer && canDraftFromStage"
                        class="glass-card rounded-2xl p-6 text-center space-y-3">
                        <i class="ti ti-file-certificate text-3xl text-(--color-primary)" />
                        <h3 class="text-sm font-semibold text-(--text-heading)">Ready to extend an offer</h3>
                        <p class="text-xs text-(--text-muted)">
                            Draft a binding offer letter to send via e-signature.
                            The candidate's acceptance is what advances them to <strong>Hired</strong>.
                        </p>
                        <button v-if="canWrite" type="button" class="btn btn-primary text-xs"
                            :disabled="draftShortcutBusy" @click="draftOfferShortcut">
                            <i :class="['ti', draftShortcutBusy ? 'ti-loader-2 animate-spin' : 'ti-file-plus']" />
                            Draft Offer Letter
                        </button>
                    </article>

                    <!-- Hired without an offer record (legacy / admin path) — surface the appointment-request CTA -->
                    <article v-else-if="!offer && app.status === 'hired'"
                        class="glass-card rounded-2xl p-6 text-center space-y-3">
                        <i class="ti ti-hourglass-high text-3xl text-(--color-warning)" />
                        <h3 class="text-sm font-semibold text-(--text-heading)">No offer record on file</h3>
                        <p class="text-xs text-(--text-muted)">
                            This candidate reached <strong>Hired</strong> without an offer letter
                            (legacy or admin path). Submit an Employee Appointment request to provision the employee.
                        </p>
                        <NuxtLink v-if="canWrite" :to="`/approvals/forms/employee-appointment?applicationId=${app.id}`"
                            class="btn btn-primary text-xs">
                            <i class="ti ti-send" /> Submit Appointment Request
                        </NuxtLink>
                    </article>

                    <!-- Active offer -->
                    <template v-else-if="offer">
                        <article class="glass-card rounded-2xl p-6 space-y-5">
                            <header class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                                        Offer Letter
                                    </p>
                                    <h2 class="text-base font-semibold text-(--text-heading) truncate">
                                        {{ offer.title }}
                                    </h2>
                                    <p class="text-xxs text-(--text-muted) font-mono">{{ offer.referenceNumber }}</p>
                                </div>
                                <Badge :variant="OFFER_STATUS_META[offer.status].variant" :dot="true">
                                    <i :class="['ti', OFFER_STATUS_META[offer.status].icon]" />
                                    {{ OFFER_STATUS_META[offer.status].label }}
                                </Badge>
                            </header>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="stat-tile">
                                    <p class="stat-label">Effective</p>
                                    <p class="stat-value text-(--text-heading) font-mono text-sm">
                                        {{ formatOfferDate(offer.effectiveDate) }}
                                    </p>
                                </div>
                                <div class="stat-tile">
                                    <p class="stat-label">Expires</p>
                                    <p class="stat-value text-(--text-heading) font-mono text-sm">
                                        {{ formatOfferDate(offer.expiresAt) }}
                                    </p>
                                </div>
                                <div v-if="canSeePayroll" class="stat-tile">
                                    <p class="stat-label">Base Salary</p>
                                    <p class="stat-value font-mono text-sm text-(--color-primary)">
                                        {{ offer.baseSalary != null
                                            ? formatOfferMoney(offer.baseSalary, offer.currency)
                                            : '—' }}
                                    </p>
                                </div>
                                <div class="stat-tile">
                                    <p class="stat-label">Probation</p>
                                    <p class="stat-value text-(--text-heading) font-mono text-sm">
                                        {{ offer.probationMonths != null ? `${offer.probationMonths} mo` : '—' }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="canSeePayroll && offer.signingBonus" class="text-xs text-(--text-muted)">
                                Signing bonus
                                <span class="font-mono text-(--color-primary)">
                                    {{ formatOfferMoney(offer.signingBonus, offer.currency) }}
                                </span>
                            </div>

                            <div v-if="offer.notes"
                                class="rounded-lg bg-(--bg-muted)/40 border border-(--border-color) p-3 text-xs text-(--text-body) whitespace-pre-wrap">
                                {{ offer.notes }}
                            </div>

                            <!-- Status-specific metadata + actions -->
                            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-(--border-color)">
                                <!-- DRAFT -->
                                <template v-if="offer.status === 'draft'">
                                    <button v-if="canWrite" type="button" class="btn btn-soft-primary text-xs"
                                        @click="openOfferForm(offer)">
                                        <i class="ti ti-pencil" /> Edit
                                    </button>
                                    <button v-if="canWrite" type="button" class="btn btn-soft-danger text-xs"
                                        @click="openDeleteOffer">
                                        <i class="ti ti-trash" /> Delete
                                    </button>
                                    <div class="flex-1" />
                                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs"
                                        @click="openSendOffer">
                                        <i class="ti ti-send" /> Send Offer
                                    </button>
                                </template>

                                <!-- SENT -->
                                <template v-else-if="offer.status === 'sent'">
                                    <div class="text-xxs text-(--text-muted) space-y-1">
                                        <p>
                                            <i class="ti ti-send text-[10px]" />
                                            Sent {{ formatRelative(offer.sentAt) }} via
                                            <span class="font-mono">{{ offer.esignProvider || '—' }}</span>
                                        </p>
                                        <p v-if="offer.esignEnvelopeId" class="font-mono">
                                            Envelope: {{ offer.esignEnvelopeId }}
                                        </p>
                                    </div>
                                    <div class="flex-1" />
                                    <button v-if="canWrite" type="button" class="btn btn-soft-danger text-xs"
                                        @click="openDeclineOffer">
                                        <i class="ti ti-x" /> Decline
                                    </button>
                                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs"
                                        @click="openAcceptOffer">
                                        <i class="ti ti-check" /> Accept manually
                                    </button>
                                </template>

                                <!-- ACCEPTED -->
                                <template v-else-if="offer.status === 'accepted'">
                                    <div class="text-xxs text-(--text-muted) space-y-1">
                                        <p>
                                            <i class="ti ti-check text-(--color-success)" />
                                            Signed {{ formatRelative(offer.signedAt) }}.
                                        </p>
                                        <p v-if="app.status === 'hired'" class="font-semibold text-(--color-warning)">
                                            <i class="ti ti-hourglass-high" />
                                            HR appointment request needed to provision the employee.
                                        </p>
                                        <p v-else-if="app.status === 'onboarding'" class="font-semibold text-(--color-success)">
                                            <i class="ti ti-checklist" /> Onboarding in progress.
                                        </p>
                                    </div>
                                    <div class="flex-1" />
                                    <NuxtLink v-if="app.status === 'hired' && !app.pendingAppointmentRequest && canWrite"
                                        :to="`/approvals/forms/employee-appointment?applicationId=${app.id}`"
                                        class="btn btn-primary text-xs">
                                        <i class="ti ti-send" /> Submit Appointment Request
                                    </NuxtLink>
                                    <NuxtLink v-else-if="app.status === 'hired' && app.pendingAppointmentRequest"
                                        :to="`/approvals/requests/${app.pendingAppointmentRequest.id}`"
                                        class="btn btn-soft-warning text-xs">
                                        <i class="ti ti-hourglass-high" /> View pending request
                                    </NuxtLink>
                                    <NuxtLink v-else-if="offer.employeeId" :to="`/hrm/employees/${offer.employeeId}`"
                                        class="btn btn-soft-success text-xs">
                                        <i class="ti ti-user-check" /> View Employee
                                    </NuxtLink>
                                </template>

                                <!-- DECLINED -->
                                <template v-else-if="offer.status === 'declined'">
                                    <div class="text-xxs text-(--text-muted) space-y-0.5">
                                        <p>
                                            <i class="ti ti-x text-(--color-danger)" />
                                            Declined {{ formatRelative(offer.declinedAt) }}.
                                        </p>
                                        <p v-if="offer.declineReason" class="italic">
                                            "{{ offer.declineReason }}"
                                        </p>
                                    </div>
                                    <div class="flex-1" />
                                    <button v-if="canWrite && app.status === 'offer'" type="button"
                                        class="btn btn-soft-primary text-xs" @click="openOfferForm()">
                                        <i class="ti ti-plus" /> Draft new offer
                                    </button>
                                </template>

                                <!-- EXPIRED -->
                                <template v-else-if="offer.status === 'expired'">
                                    <p class="text-xxs text-(--text-muted)">
                                        <i class="ti ti-clock-off text-(--color-warning)" /> Offer expired.
                                    </p>
                                    <div class="flex-1" />
                                    <button v-if="canWrite && app.status === 'offer'" type="button"
                                        class="btn btn-soft-primary text-xs" @click="openOfferForm()">
                                        <i class="ti ti-plus" /> Draft new offer
                                    </button>
                                </template>
                            </div>
                        </article>

                        <!-- Onboarding checklist (live progress when accepted) -->
                        <article v-if="checklist" class="glass-card rounded-2xl p-6 space-y-4">
                            <header class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                        <i class="ti ti-checklist text-(--color-primary)" /> Onboarding Checklist
                                    </h3>
                                    <p class="text-xxs text-(--text-muted) mt-1">
                                        {{ checklist.completedTasks }} of {{ checklist.totalTasks }} tasks complete
                                    </p>
                                </div>
                                <NuxtLink :to="`/hrm/onboarding?focus=${checklist.id}`"
                                    class="text-xxs text-(--color-primary) hover:underline whitespace-nowrap">
                                    Open workspace <i class="ti ti-arrow-up-right text-[10px]" />
                                </NuxtLink>
                            </header>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xxs font-mono text-(--text-muted)">
                                    <span>Progress</span>
                                    <span>{{ checklist.progressPercent }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-(--bg-muted) overflow-hidden">
                                    <div class="h-full rounded-full transition-all"
                                        :class="progressTint(checklist.progressPercent)"
                                        :style="{ width: `${checklist.progressPercent}%` }" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div v-for="role in OWNER_ROLES" :key="role" class="rounded-lg bg-(--bg-muted)/40 border border-(--border-color) p-3">
                                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) flex items-center gap-1">
                                        <i :class="['ti text-[10px]', OWNER_ROLE_META[role].icon]" />
                                        {{ OWNER_ROLE_META[role].label }}
                                    </p>
                                    <p class="font-mono text-sm text-(--text-heading) mt-1">
                                        {{ countDoneByRole(role) }} / {{ countByRole(role) }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </template>
                </template>
            </section>
        </div>

        <!-- ===== Offer Form Modal (create / edit) ===== -->
        <div v-if="offerFormOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">
                        {{ offerForm.id ? 'Edit Draft Offer' : 'Draft Offer Letter' }}
                    </h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="offerFormOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveOffer">
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Title *</label>
                            <input v-model="offerForm.title" type="text" required maxlength="160"
                                placeholder="e.g. Senior Backend Engineer — Permanent"
                                class="form-control text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Effective date *</label>
                            <input v-model="offerForm.effectiveDate" type="date" required class="form-control text-xs font-mono" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expires</label>
                            <input v-model="offerForm.expiresAt" type="date" class="form-control text-xs font-mono" />
                        </div>
                        <template v-if="canSeePayroll">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Base salary</label>
                                <input v-model.number="offerForm.baseSalary" type="number" step="0.01" min="0"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Signing bonus</label>
                                <input v-model.number="offerForm.signingBonus" type="number" step="0.01" min="0"
                                    class="form-control text-xs font-mono" />
                            </div>
                        </template>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Currency</label>
                            <input v-model="offerForm.currency" type="text" maxlength="3"
                                placeholder="USD" class="form-control text-xs font-mono uppercase" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Probation (months)</label>
                            <input v-model.number="offerForm.probationMonths" type="number" min="0" max="120"
                                class="form-control text-xs font-mono" />
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="offerForm.notes" rows="3" maxlength="2000"
                                class="form-control text-xs resize-none"
                                placeholder="Optional terms, perks, or special conditions..." />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="offerFormOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!offerFormValid || offerBusy">
                            <i v-if="offerBusy" class="ti ti-loader-2 animate-spin" />
                            {{ offerForm.id ? 'Save' : 'Create draft' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete draft confirm -->
        <div v-if="offerDeleteOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Draft Offer</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="offerDeleteOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 text-xs text-(--text-muted)">
                    Permanently delete draft <span class="font-mono text-(--text-heading)">{{ offer?.referenceNumber }}</span>?
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="offerDeleteOpen = false">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="offerBusy" @click="confirmDeleteOffer">
                        <i v-if="offerBusy" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>

        <!-- Send modal (provider picker) -->
        <div v-if="offerSendOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Send Offer for Signature</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="offerSendOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Choose an e-signature provider. The candidate receives a signing link by email.
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="provider-btn"
                            :class="{ active: sendProvider === 'mock' }" @click="sendProvider = 'mock'">
                            <i class="ti ti-flask text-(--color-info)" />
                            <span class="font-semibold text-xs">Mock</span>
                            <span class="text-xxs text-(--text-muted)">Demo / sandbox</span>
                        </button>
                        <button type="button" class="provider-btn"
                            :class="{ active: sendProvider === 'docusign' }" @click="sendProvider = 'docusign'">
                            <i class="ti ti-signature text-(--color-primary)" />
                            <span class="font-semibold text-xs">DocuSign</span>
                            <span class="text-xxs text-(--text-muted)">Production</span>
                        </button>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="offerSendOpen = false">Cancel</button>
                    <button type="button" class="btn btn-primary text-xs" :disabled="offerBusy" @click="confirmSendOffer">
                        <i v-if="offerBusy" class="ti ti-loader-2 animate-spin" />
                        Send
                    </button>
                </footer>
            </div>
        </div>

        <!-- Accept (manual / wet-ink) -->
        <div v-if="offerAcceptOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Confirm Wet-Ink Signature</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="offerAcceptOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 text-xs text-(--text-muted) space-y-2">
                    <p>
                        Mark <span class="font-mono text-(--text-heading)">{{ offer?.referenceNumber }}</span>
                        as accepted? This will:
                    </p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Convert the candidate into an Employee record</li>
                        <li>Seed the default onboarding checklist</li>
                    </ul>
                    <p class="italic">Only use this for off-band signatures (printed copy, in-person).</p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="offerAcceptOpen = false">Cancel</button>
                    <button type="button" class="btn btn-primary text-xs" :disabled="offerBusy" @click="confirmAcceptOffer">
                        <i v-if="offerBusy" class="ti ti-loader-2 animate-spin" />
                        Accept &amp; Convert
                    </button>
                </footer>
            </div>
        </div>

        <!-- Decline -->
        <div v-if="offerDeclineOpen" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Decline Offer</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="offerDeclineOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Decline offer <span class="font-mono text-(--text-heading)">{{ offer?.referenceNumber }}</span>?
                    </p>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reason (optional)</label>
                        <textarea v-model="declineReason" rows="3" maxlength="500"
                            class="form-control text-xs resize-none"
                            placeholder="e.g. Candidate accepted a competing offer." />
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="offerDeclineOpen = false">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="offerBusy" @click="confirmDeclineOffer">
                        <i v-if="offerBusy" class="ti ti-loader-2 animate-spin" />
                        Decline
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useBreadcrumbOverride } from '~/composables/useBreadcrumbOverride'
import { formatDate } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'
import {
    useOffers,
    OFFER_STATUS_META,
    type Offer,
    type OfferPayload,
} from '~/composables/useOffers'
import {
    useOnboarding,
    OWNER_ROLE_META,
    type OnboardingChecklist,
    type OnboardingOwnerRole,
} from '~/composables/useOnboarding'

definePageMeta({
    breadcrumb: 'Candidate Profile'
})

type ApplicationStatus =
    | 'applied'
    | 'screening'
    | 'shortlisted'
    | 'assessment'
    | 'assessment_completed'
    | 'interview'
    | 'final_interview'
    | 'offer'
    | 'hired'
    | 'onboarding'
    | 'rejected'
    | 'withdrawn'

interface VacancyLite { id: string; title: string }
interface ReferrerLite { id: string; employeeId: string; fullName: string }
interface WorkExperience { title?: string; company?: string; period?: string; location?: string; description?: string }
interface Education { degree?: string; school?: string }

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
    workExperience: WorkExperience[] | null
    education: Education[] | null
    skills: string[] | null
    expectedSalary: number | null
    notes: string | null
    status: ApplicationStatus
    appliedAt: string | null
    convertedAt: string | null
    vacancy?: VacancyLite
    referrer?: ReferrerLite
    referrerEmployeeId?: string | null
    pendingAppointmentRequest?: { id: string; status: string; createdAt: string } | null
}

// Manual recruiter advancement caps at `offer`. Both downstream transitions
// are event-driven and intentionally absent from this map:
//   offer → hired       fires when OfferService::markAccepted runs (offer
//                       accept webhook or wet-ink admin click).
//   hired → onboarding  fires when SyncEmployeeAppointmentFromApproval runs
//                       on appointment-request approval.
// rejected / withdrawn remain manual escape hatches from any non-terminal row.
const STATUS_FLOW: Record<ApplicationStatus, ApplicationStatus[]> = {
    applied:              ['screening', 'rejected', 'withdrawn'],
    screening:            ['shortlisted', 'assessment', 'interview', 'rejected', 'withdrawn'],
    shortlisted:          ['assessment', 'interview', 'rejected', 'withdrawn'],
    assessment:           ['assessment_completed', 'rejected', 'withdrawn'],
    assessment_completed: ['interview', 'final_interview', 'offer', 'rejected', 'withdrawn'],
    interview:            ['final_interview', 'offer', 'rejected', 'withdrawn'],
    final_interview:      ['offer', 'rejected', 'withdrawn'],
    offer:                ['rejected', 'withdrawn'],
    hired:                ['rejected', 'withdrawn'],
    onboarding:           [],
    rejected:             [],
    withdrawn:            [],
}

const STAGE_INDEX: Record<ApplicationStatus, number> = {
    applied: 0,
    screening: 1,
    shortlisted: 2,
    assessment: 3,
    assessment_completed: 3,
    interview: 4,
    final_interview: 5,
    offer: 6,
    hired: 7,
    onboarding: 8,
    rejected: -1,
    withdrawn: -1,
}

const route = useRoute()
const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const breadcrumb = useBreadcrumbOverride()

const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const canSeeSalary = computed(() => authStore.hasPermission('hrm.recruitment.read'))
const canOffer = computed(() => authStore.hasPermission('hrm.recruitment.offer'))
const canSeePayroll = computed(() => authStore.hasPermission('hrm.payroll.read'))

const loading = ref(true)
const app = ref<Application | null>(null)
const advancing = ref(false)
const openAdvanceMenu = ref(false)
const copied = ref(false)

// ----- Tab state -----
type TabKey = 'overview' | 'offer'
const activeTab = ref<TabKey>(route.hash === '#offer' ? 'offer' : 'overview')

// ----- Offer + onboarding state -----
const offers = useOffers()
const onboarding = useOnboarding()
const offer = ref<Offer | null>(null)
const checklist = ref<OnboardingChecklist | null>(null)
const offerLoading = ref(false)
const offerBusy = ref(false)

const offerFormOpen = ref(false)
const offerDeleteOpen = ref(false)
const offerSendOpen = ref(false)
const offerAcceptOpen = ref(false)
const offerDeclineOpen = ref(false)

const sendProvider = ref<'mock' | 'docusign'>('mock')
const declineReason = ref('')
const draftShortcutBusy = ref(false)

// Drafting a Job Offer requires the application to be at the `offer` stage
// — see backend OfferService::createOffer. The previous shortcut that also
// allowed `hired` was removed in Phase 8.5: at `hired` the candidate has
// already accepted; the next action is to submit the appointment request.
const canDraftFromStage = computed(() => app.value?.status === 'offer')

interface OfferFormState {
    id: string | null
    title: string
    effectiveDate: string
    expiresAt: string
    baseSalary: number | null
    signingBonus: number | null
    currency: string
    probationMonths: number | null
    notes: string
}

const blankOfferForm = (): OfferFormState => ({
    id: null,
    title: '',
    effectiveDate: new Date().toISOString().slice(0, 10),
    expiresAt: '',
    baseSalary: null,
    signingBonus: null,
    currency: 'USD',
    probationMonths: 3,
    notes: '',
})

const offerForm = reactive<OfferFormState>(blankOfferForm())

const offerFormValid = computed(() =>
    !!offerForm.title.trim() && !!offerForm.effectiveDate
)

const OWNER_ROLES: OnboardingOwnerRole[] = ['hr', 'it', 'finance', 'manager', 'facilities', 'other']

const countByRole = (role: OnboardingOwnerRole) =>
    (checklist.value?.tasks ?? []).filter(t => t.ownerRole === role).length

const countDoneByRole = (role: OnboardingOwnerRole) =>
    (checklist.value?.tasks ?? []).filter(t => t.ownerRole === role && t.status === 'completed').length

const progressTint = (pct: number) => {
    if (pct >= 100) return 'bg-(--color-success)'
    if (pct >= 50) return 'bg-(--color-primary)'
    if (pct > 0) return 'bg-(--color-info)'
    return 'bg-(--text-muted)/40'
}

const formatOfferDate = (iso: string | null) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return isNaN(d.getTime()) ? iso : d.toISOString().slice(0, 10)
}

const formatOfferMoney = (n: number, currency: string | null) => {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
            maximumFractionDigits: 0,
        }).format(n)
    } catch {
        return `${n}`
    }
}

const formatRelative = (iso: string | null) => {
    if (!iso) return '—'
    const diff = Date.now() - new Date(iso).getTime()
    if (diff < 0) return 'just now'
    const min = Math.floor(diff / 60_000)
    if (min < 60) return `${min || 1}m ago`
    const hr = Math.floor(min / 60)
    if (hr < 24) return `${hr}h ago`
    const days = Math.floor(hr / 24)
    if (days < 30) return `${days}d ago`
    return formatDate(iso)
}

const loadCandidate = async () => {
    const id = route.params.id as string
    if (!id) {
        loading.value = false
        return
    }
    loading.value = true
    try {
        const res = await api.get<{ data: Application } | Application>(`/applications/${id}`)
        app.value = (res as { data?: Application })?.data ?? (res as Application)
        if (app.value?.applicantName) {
            breadcrumb.set(app.value.applicantName)
        }
    } catch (err) {
        console.error('Failed to load candidate', err)
        app.value = null
    } finally {
        loading.value = false
    }
}

// ---------- helpers ----------
const initials = (name?: string | null): string =>
    (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]!.toUpperCase()).join('') || '?'

const formatMoney = (n: number): string =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

const relativeTime = (iso: string | null): string => {
    if (!iso) return '—'
    const diffMs = Date.now() - new Date(iso).getTime()
    if (diffMs < 0) return 'just now'
    const min = Math.floor(diffMs / 60_000)
    if (min < 60) return `${min || 1}m ago`
    const hr = Math.floor(min / 60)
    if (hr < 24) return `${hr}h ago`
    const days = Math.floor(hr / 24)
    if (days < 7) return `${days}d ago`
    const wk = Math.floor(days / 7)
    if (wk < 4) return `${wk}w ago`
    return formatDate(iso)
}

const statusLabel = (s: ApplicationStatus): string => {
    const labels: Record<ApplicationStatus, string> = {
        applied: 'Applied',
        screening: 'Screening',
        shortlisted: 'Shortlisted',
        assessment: 'Assessment',
        assessment_completed: 'Assessment Done',
        interview: 'Interview',
        final_interview: 'Final Interview',
        offer: 'Job Offer',
        hired: 'Hired',
        onboarding: 'Onboarding',
        rejected: 'Rejected',
        withdrawn: 'Withdrawn',
    }
    return labels[s]
}

const statusVariant = (s: ApplicationStatus): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (s) {
        case 'applied': return 'secondary'
        case 'screening': return 'info'
        case 'shortlisted': return 'primary'
        case 'assessment': return 'info'
        case 'assessment_completed': return 'info'
        case 'interview': return 'warning'
        case 'final_interview': return 'warning'
        case 'offer': return 'primary'
        case 'hired': return 'success'
        case 'onboarding': return 'success'
        case 'rejected': return 'danger'
        case 'withdrawn': return 'secondary'
    }
}

const sourceIcon = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'ti-user-check'
    if (a.resumePath) return 'ti-file-cv'
    return 'ti-mail'
}
const sourceLabel = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'Employee referral'
    if (a.resumePath) return 'Resume submission'
    return 'Direct email'
}
const shortSource = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'Referral'
    if (a.resumePath) return 'Resume'
    return 'Direct'
}

// ---------- derived ----------
const skills = computed(() => app.value?.skills ?? [])
const education = computed(() => app.value?.education ?? [])
const workExperience = computed(() => app.value?.workExperience ?? [])
const referrer = computed(() => app.value?.referrer ?? null)

const nextStages = computed<ApplicationStatus[]>(() => {
    if (!app.value) return []
    return STATUS_FLOW[app.value.status] ?? []
})

const daysInPipeline = computed(() => {
    if (!app.value?.appliedAt) return 0
    const ms = Date.now() - new Date(app.value.appliedAt).getTime()
    return Math.max(0, Math.floor(ms / 86_400_000))
})

const rating = computed(() => {
    const a = app.value
    if (!a) return 0
    let r = 3
    if (a.coverLetter) r += 0.5
    if (a.resumePath) r += 0.5
    if (a.skills?.length) r += Math.min(0.5, a.skills.length * 0.1)
    if (a.referrerEmployeeId || a.referrer) r += 0.5
    return Math.max(1, Math.min(5, r))
})

const resumeHref = computed(() => {
    const path = app.value?.resumePath
    if (!path) return '#'
    return path
})

const timeline = computed(() => {
    const a = app.value
    if (!a) return []

    type TimelineEvent = { key: string; label: string; detail: string; done: boolean; icon: string }
    const reached = (key: ApplicationStatus): boolean => STAGE_INDEX[a.status] >= STAGE_INDEX[key]
    const touched = (key: ApplicationStatus): boolean => a.status === key || reached(key)

    const events: TimelineEvent[] = [
        {
            key: 'applied',
            label: 'Application received',
            detail: a.appliedAt ? formatDate(a.appliedAt) : 'Not recorded',
            done: true,
            icon: 'ti-send',
        },
        {
            key: 'screening',
            label: 'Screening',
            detail: reached('screening') ? 'Reviewed by recruiter' : 'Awaiting screening',
            done: reached('screening'),
            icon: 'ti-search',
        },
        {
            key: 'shortlisted',
            label: 'Shortlisted',
            detail: reached('shortlisted') ? 'Shortlisted for next round' : 'Pending shortlist',
            done: reached('shortlisted'),
            icon: 'ti-list-check',
        },
    ]

    // Optional middle stages — only render when the candidate touched them.
    if (touched('assessment') || touched('assessment_completed')) {
        events.push({
            key: 'assessment',
            label: 'Assessment',
            detail: reached('assessment_completed') ? 'Assignment submitted' : 'Assignment issued',
            done: reached('assessment_completed'),
            icon: 'ti-clipboard-list',
        })
    }

    events.push({
        key: 'interview',
        label: 'Interview',
        detail: reached('interview') ? 'Advanced to interview' : 'Pending interview',
        done: reached('interview'),
        icon: 'ti-microphone-2',
    })

    if (touched('final_interview')) {
        events.push({
            key: 'final_interview',
            label: 'Final interview',
            detail: reached('final_interview') ? 'Final round complete' : 'Pending final round',
            done: reached('final_interview'),
            icon: 'ti-microphone-2',
        })
    }

    events.push(
        {
            key: 'offer',
            label: 'Offer extended',
            detail: reached('offer') ? 'Offer letter drafted' : 'No offer yet',
            done: reached('offer'),
            icon: 'ti-file-certificate',
        },
        {
            key: 'hired',
            label: 'Offer accepted',
            detail: reached('hired') ? 'Candidate signed the offer' : 'Awaiting candidate acceptance',
            done: reached('hired'),
            icon: 'ti-check',
        },
        {
            key: 'onboarding',
            label: 'Onboarding',
            detail: reached('onboarding')
                ? (a.convertedAt ? `Approved ${formatDate(a.convertedAt)}` : 'Approved by HR')
                : 'Pending appointment approval',
            done: reached('onboarding'),
            icon: 'ti-confetti',
        },
    )

    if (a.status === 'rejected') {
        events.push({
            key: 'rejected', label: 'Rejected',
            detail: 'Candidate no longer in pipeline', done: true, icon: 'ti-x',
        })
    } else if (a.status === 'withdrawn') {
        events.push({
            key: 'withdrawn', label: 'Withdrawn',
            detail: 'Candidate withdrew from pipeline', done: true, icon: 'ti-arrow-back',
        })
    }
    return events
})

// ---------- actions ----------
const advanceTo = async (target: ApplicationStatus) => {
    if (!app.value || advancing.value) return
    openAdvanceMenu.value = false
    advancing.value = true
    const original = app.value.status
    app.value.status = target
    try {
        await api.patch(`/applications/${app.value.id}/status`, { status: target })
        toast.success('Stage updated', `${app.value.applicantName} → ${statusLabel(target)}`)
    } catch (err: any) {
        app.value.status = original
        toast.error('Failed to update stage.', err?.data?.message)
    } finally {
        advancing.value = false
    }
}

const copyEmail = async () => {
    if (!app.value?.applicantEmail) return
    try {
        await navigator.clipboard.writeText(app.value.applicantEmail)
        copied.value = true
        setTimeout(() => { copied.value = false }, 1500)
    } catch {
        toast.error('Failed to copy email')
    }
}

const goToEdit = () => {
    if (!app.value) return
    navigateTo(`/hrm/recruitments/applications?id=${app.value.id}`)
}

// ---------- Offer + Onboarding ----------
const loadOffer = async () => {
    if (!app.value || !canOffer.value) return
    offerLoading.value = true
    try {
        const res = await offers.list({ applicationId: app.value.id, limit: 5 })
        offer.value = res.data[0] ?? null
        if (offer.value) {
            const fresh = await offers.show(offer.value.id)
            offer.value = fresh.data
            const cid = fresh.data.onboardingChecklist?.id
            if (cid) {
                const cl = await onboarding.showChecklist(cid)
                checklist.value = cl.data
            } else {
                checklist.value = null
            }
        } else {
            checklist.value = null
        }
    } catch (err: any) {
        if (err?.status !== 403) {
            toast.error('Failed to load offer', err?.data?.message)
        }
    } finally {
        offerLoading.value = false
    }
}

const openOfferForm = (existing?: Offer | null) => {
    Object.assign(offerForm, blankOfferForm())
    if (existing) {
        offerForm.id = existing.id
        offerForm.title = existing.title
        offerForm.effectiveDate = existing.effectiveDate ?? offerForm.effectiveDate
        offerForm.expiresAt = existing.expiresAt ?? ''
        offerForm.baseSalary = existing.baseSalary
        offerForm.signingBonus = existing.signingBonus
        offerForm.currency = existing.currency ?? 'USD'
        offerForm.probationMonths = existing.probationMonths
        offerForm.notes = existing.notes ?? ''
    }
    offerFormOpen.value = true
}

const saveOffer = async () => {
    if (!app.value || !offerFormValid.value) return
    offerBusy.value = true
    try {
        const payload: OfferPayload = {
            title: offerForm.title.trim(),
            effectiveDate: offerForm.effectiveDate,
            expiresAt: offerForm.expiresAt || null,
            currency: offerForm.currency.trim().toUpperCase() || null,
            probationMonths: offerForm.probationMonths,
            notes: offerForm.notes.trim() || null,
        }
        if (canSeePayroll.value) {
            payload.baseSalary = offerForm.baseSalary
            payload.signingBonus = offerForm.signingBonus
        }
        if (offerForm.id) {
            await offers.update(offerForm.id, payload)
            toast.success('Draft updated')
        } else {
            payload.applicationId = app.value.id
            await offers.create(payload)
            toast.success('Draft created')
        }
        offerFormOpen.value = false
        await loadOffer()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        offerBusy.value = false
    }
}

const openDeleteOffer = () => { offerDeleteOpen.value = true }
const confirmDeleteOffer = async () => {
    if (!offer.value) return
    offerBusy.value = true
    try {
        await offers.destroy(offer.value.id)
        toast.success('Draft deleted')
        offerDeleteOpen.value = false
        await loadOffer()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        offerBusy.value = false
    }
}

const openSendOffer = () => {
    sendProvider.value = 'mock'
    offerSendOpen.value = true
}
const confirmSendOffer = async () => {
    if (!offer.value) return
    offerBusy.value = true
    try {
        await offers.send(offer.value.id, sendProvider.value)
        toast.success('Offer sent', `Via ${sendProvider.value}.`)
        offerSendOpen.value = false
        await loadOffer()
    } catch (err: any) {
        toast.error('Send failed', err?.data?.message)
    } finally {
        offerBusy.value = false
    }
}

const openAcceptOffer = () => { offerAcceptOpen.value = true }
const confirmAcceptOffer = async () => {
    if (!offer.value) return
    offerBusy.value = true
    try {
        await offers.accept(offer.value.id)
        toast.success('Offer accepted', 'Candidate moved to Hired — submit the Employee Appointment Request next.')
        offerAcceptOpen.value = false
        await loadOffer()
        await loadCandidate()
    } catch (err: any) {
        toast.error('Accept failed', err?.data?.message)
    } finally {
        offerBusy.value = false
    }
}

const openDeclineOffer = () => {
    declineReason.value = ''
    offerDeclineOpen.value = true
}
const confirmDeclineOffer = async () => {
    if (!offer.value) return
    offerBusy.value = true
    try {
        await offers.decline(offer.value.id, declineReason.value.trim() || undefined)
        toast.success('Offer declined')
        offerDeclineOpen.value = false
        await loadOffer()
    } catch (err: any) {
        toast.error('Decline failed', err?.data?.message)
    } finally {
        offerBusy.value = false
    }
}

const draftOfferShortcut = async () => {
    if (!app.value || draftShortcutBusy.value) return
    if (app.value.status !== 'offer') return
    draftShortcutBusy.value = true
    try {
        openOfferForm()
    } finally {
        draftShortcutBusy.value = false
    }
}

const bootstrap = async () => {
    await loadCandidate()
    if (!app.value) return
    await loadOffer()
    // Auto-open the Offer & Onboarding tab when the candidate is at or past
    // the offer stage — that's where the next actionable step lives at every
    // status in the chain (draft offer at `offer`, submit appointment request
    // at `hired`, watch checklist at `onboarding`). Honor an explicit
    // `#overview` hash so deep-links still win.
    const offerStages: ApplicationStatus[] = ['offer', 'hired', 'onboarding']
    if (
        route.hash !== '#overview'
        && canOffer.value
        && app.value
        && offerStages.includes(app.value.status)
    ) {
        activeTab.value = 'offer'
    }
}

onMounted(bootstrap)

onBeforeUnmount(() => {
    breadcrumb.clear()
})
</script>

<style scoped>
.text-xxs {
    font-size: 0.6875rem;
    line-height: 1rem;
}

.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    transition: background 0.15s ease, transform 0.1s ease, border-color 0.15s ease;
}

.icon-btn:hover {
    background: var(--bg-muted);
    border-color: var(--border-strong);
}

.icon-btn:active {
    transform: scale(0.95);
}

.stat-tile {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.625rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-value {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25;
}

.doc-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem;
    border-radius: 0.625rem;
    background: color-mix(in srgb, var(--bg-muted) 60%, transparent);
    border: 1px solid var(--border-color);
    transition: background 0.15s ease, border-color 0.15s ease;
    text-decoration: none;
}

.doc-row:hover:not(.doc-row--empty) {
    background: var(--bg-muted);
    border-color: var(--border-strong);
}

.doc-row--empty {
    border-style: dashed;
    opacity: 0.85;
}

.doc-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    flex-shrink: 0;
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-body);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    white-space: nowrap;
}
.tab-btn:hover { background: var(--bg-muted); }
.tab-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    font-weight: 600;
}

.provider-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 12px;
    border-radius: 10px;
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.provider-btn:hover { background: var(--bg-muted); }
.provider-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.08);
    border-color: var(--color-primary);
}
.provider-btn i { font-size: 20px; }
</style>
