<template>
    <NuxtLayout name="candidate">
        <!-- =============================== Token errors =============================== -->
        <section v-if="phase === 'error'" class="glass-card rounded-2xl p-8 text-center max-w-xl mx-auto">
            <div class="mx-auto w-12 h-12 rounded-full bg-(--color-danger-subtle) text-(--color-danger) flex items-center justify-center">
                <i class="ti ti-alert-triangle text-xl" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-(--text-heading)">{{ errorTitle }}</h2>
            <p class="mt-2 text-xs text-(--text-muted)">{{ errorMessage }}</p>
            <p class="mt-4 text-xxs text-(--text-muted)">
                If you believe this is an error, contact the recruiter who sent your invitation.
            </p>
        </section>

        <!-- =============================== Loading ====================================== -->
        <section v-else-if="phase === 'loading'" class="py-24 text-center">
            <span class="w-10 h-10 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <p class="mt-4 text-xs text-(--text-muted)">Loading your assessment...</p>
        </section>

        <!-- =============================== Landing ====================================== -->
        <section v-else-if="phase === 'landing'" class="space-y-6">
            <div class="glass-card rounded-2xl p-8">
                <p class="text-xxs uppercase tracking-widest font-bold text-(--color-primary)">Welcome</p>
                <h1 class="mt-1 text-2xl font-semibold text-(--text-heading)">{{ quiz!.title }}</h1>
                <p v-if="attempt!.candidateName" class="mt-1 text-sm text-(--text-body)">
                    Hi {{ attempt!.candidateName }}, you've been invited to complete this assessment.
                </p>

                <p v-if="quiz!.description" class="mt-4 text-sm text-(--text-body) whitespace-pre-line">
                    {{ quiz!.description }}
                </p>

                <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl border border-(--border-color) p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Questions</p>
                        <p class="text-lg font-mono font-semibold text-(--text-heading) mt-1">
                            {{ quiz!.questions.length }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-(--border-color) p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Time limit</p>
                        <p class="text-lg font-mono font-semibold text-(--text-heading) mt-1">
                            {{ quiz!.timeLimitMinutes ? `${quiz!.timeLimitMinutes} min` : 'No limit' }}
                        </p>
                    </div>
                    <div v-if="attempt!.expiresAt" class="rounded-xl border border-(--border-color) p-4">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Expires</p>
                        <p class="text-xs font-mono text-(--text-heading) mt-1">
                            {{ formatExpiry(attempt!.expiresAt) }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 rounded-xl bg-(--bg-muted) border border-(--border-color)/60 p-4 text-xs space-y-1.5">
                    <p class="font-semibold text-(--text-heading) text-sm">Before you start</p>
                    <p class="text-(--text-muted)">. Find a quiet space; once started, you cannot pause the assessment.</p>
                    <p class="text-(--text-muted)">. Answers submit automatically when the time limit ends.</p>
                    <p class="text-(--text-muted)">. Do not refresh the page until you submit.</p>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row justify-end gap-2">
                    <button class="btn btn-primary text-sm" :disabled="starting" @click="onStart">
                        <i :class="['ti', starting ? 'ti-loader-2 animate-spin' : 'ti-player-play']" />
                        {{ starting ? 'Starting...' : 'Start assessment' }}
                    </button>
                </div>
            </div>
        </section>

        <!-- =============================== In-progress ================================== -->
        <section v-else-if="phase === 'in_progress'" class="space-y-4">
            <!-- Timer + progress bar -->
            <div class="glass-card rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center font-mono font-semibold text-sm"
                        :class="timerVariant">
                        <i class="ti ti-clock text-base" />
                    </div>
                    <div class="leading-tight flex-1 min-w-0">
                        <p class="text-xs text-(--text-muted)">Time remaining</p>
                        <p class="text-lg font-mono font-semibold" :class="timerTextClass">
                            {{ timerDisplay }}
                        </p>
                    </div>
                </div>
                <div class="flex-1 min-w-[160px]">
                    <div class="flex justify-between text-xxs font-mono text-(--text-muted) mb-1">
                        <span>Question {{ activeQuestionIndex + 1 }} of {{ quiz!.questions.length }}</span>
                        <span>{{ answeredCount }}/{{ quiz!.questions.length }} answered</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-(--bg-muted) overflow-hidden">
                        <div class="h-full bg-(--color-primary) transition-all"
                            :style="{ width: progressPercent + '%' }" />
                    </div>
                </div>
            </div>

            <!-- Question navigator pills -->
            <nav class="glass-card rounded-2xl p-3 flex flex-wrap gap-1.5">
                <button v-for="(q, idx) in quiz!.questions" :key="q.id" type="button" class="q-nav-pill"
                    :class="{
                        'q-nav-pill-active': idx === activeQuestionIndex,
                        'q-nav-pill-answered': idx !== activeQuestionIndex && hasAnswer(q.id),
                    }" @click="activeQuestionIndex = idx">
                    {{ idx + 1 }}
                </button>
            </nav>

            <!-- Active question -->
            <article class="glass-card rounded-2xl p-6 space-y-5">
                <header>
                    <p class="text-xxs uppercase tracking-widest font-bold text-(--color-primary)">
                        Question {{ activeQuestion.sequence }}
                        . <span class="text-(--text-muted) capitalize">{{ formatType(activeQuestion.questionType) }}</span>
                        . <span class="text-(--text-muted) font-mono">{{ activeQuestion.points }} pts</span>
                    </p>
                    <h3 class="mt-2 text-base font-semibold text-(--text-heading) whitespace-pre-line">
                        {{ activeQuestion.prompt }}
                    </h3>
                </header>

                <!-- Single-choice -->
                <div v-if="activeQuestion.questionType === 'single_choice'" class="space-y-2">
                    <label v-for="(opt, idx) in (activeQuestion.options ?? [])" :key="idx"
                        class="option-row"
                        :class="{ 'option-row-active': answers[activeQuestion.id] === opt }">
                        <input type="radio" :name="`q-${activeQuestion.id}`" :value="opt"
                            :checked="answers[activeQuestion.id] === opt"
                            @change="answers[activeQuestion.id] = opt" />
                        <span class="text-sm">{{ opt }}</span>
                    </label>
                </div>

                <!-- Multiple-choice -->
                <div v-else-if="activeQuestion.questionType === 'multiple_choice'" class="space-y-2">
                    <label v-for="(opt, idx) in (activeQuestion.options ?? [])" :key="idx"
                        class="option-row"
                        :class="{ 'option-row-active': isMultipleSelected(activeQuestion.id, opt) }">
                        <input type="checkbox" :value="opt"
                            :checked="isMultipleSelected(activeQuestion.id, opt)"
                            @change="toggleMultiple(activeQuestion.id, opt)" />
                        <span class="text-sm">{{ opt }}</span>
                    </label>
                </div>

                <!-- Short text -->
                <textarea v-else
                    v-model="answers[activeQuestion.id]"
                    rows="4"
                    placeholder="Type your answer..."
                    class="form-control text-sm" />

                <footer class="flex justify-between items-center pt-2">
                    <button class="btn btn-ghost text-xs" :disabled="activeQuestionIndex === 0"
                        @click="activeQuestionIndex--">
                        <i class="ti ti-chevron-left" /> Previous
                    </button>
                    <button v-if="activeQuestionIndex < quiz!.questions.length - 1"
                        class="btn text-xs text-(--text-body) border border-(--border-color)"
                        @click="activeQuestionIndex++">
                        Next <i class="ti ti-chevron-right" />
                    </button>
                    <button v-else class="btn btn-primary text-xs" @click="showConfirmSubmit = true">
                        <i class="ti ti-send" /> Review &amp; submit
                    </button>
                </footer>
            </article>

            <!-- Submit confirmation modal -->
            <div v-if="showConfirmSubmit"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 bg-(--bg-card)">
                    <h3 class="text-base font-semibold text-(--text-heading)">Submit your assessment?</h3>
                    <p class="mt-2 text-xs text-(--text-muted)">
                        You've answered <strong>{{ answeredCount }} of {{ quiz!.questions.length }}</strong> questions.
                        Unanswered questions will be graded as incorrect. This action cannot be undone.
                    </p>
                    <div v-if="submitError"
                        class="mt-3 text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
                        {{ submitError }}
                    </div>
                    <footer class="mt-5 flex justify-end gap-2">
                        <button class="btn btn-ghost text-xs" @click="showConfirmSubmit = false">Keep working</button>
                        <button class="btn btn-primary text-xs" :disabled="submitting" @click="onSubmit">
                            <i :class="['ti', submitting ? 'ti-loader-2 animate-spin' : 'ti-send']" />
                            {{ submitting ? 'Submitting...' : 'Submit now' }}
                        </button>
                    </footer>
                </div>
            </div>
        </section>

        <!-- =============================== Result ======================================= -->
        <section v-else-if="phase === 'result'" class="glass-card rounded-2xl p-8 text-center max-w-xl mx-auto">
            <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center"
                :class="result.passed
                    ? 'bg-(--color-success-subtle) text-(--color-success)'
                    : 'bg-(--bg-muted) text-(--text-muted)'">
                <i :class="['ti', result.passed ? 'ti-circle-check' : 'ti-circle-dashed', 'text-2xl']" />
            </div>
            <h2 class="mt-4 text-xl font-semibold text-(--text-heading)">
                {{ result.passed ? 'Assessment complete' : 'Assessment submitted' }}
            </h2>
            <p class="mt-2 text-sm text-(--text-body)">
                Thank you for completing the assessment. Your responses are recorded.
            </p>

            <div v-if="result.score !== null" class="mt-6 grid grid-cols-2 gap-3 max-w-sm mx-auto">
                <div class="rounded-xl border border-(--border-color) p-4">
                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Score</p>
                    <p class="text-2xl font-mono font-semibold text-(--text-heading) mt-1">
                        {{ result.score.toFixed(1) }}%
                    </p>
                </div>
                <div class="rounded-xl border border-(--border-color) p-4">
                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Result</p>
                    <p class="text-lg font-semibold mt-1"
                        :class="result.passed ? 'text-(--color-success)' : 'text-(--text-muted)'">
                        {{ result.passed ? 'Passed' : 'Submitted' }}
                    </p>
                </div>
            </div>

            <p class="mt-6 text-xxs text-(--text-muted)">
                The recruitment team will follow up with the next steps. You can close this tab.
            </p>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCandidateQuiz, type QuizAttempt, type QuizPayload, type QuestionType } from '~/composables/useCandidateQuiz'

definePageMeta({
    layout: false,
    // No Pinia auth, no admin chrome - the page boots from URL query params.
})

type Phase = 'loading' | 'landing' | 'in_progress' | 'result' | 'error'

const route = useRoute()
const token = computed(() => String(route.query.token || ''))
const handle = computed(() => String(route.query.handle || ''))

const phase = ref<Phase>('loading')
const errorTitle = ref('Invitation invalid')
const errorMessage = ref('The link you used is missing required information.')

const attempt = ref<QuizAttempt | null>(null)
const quiz = ref<QuizPayload | null>(null)
const answers = reactive<Record<string, unknown>>({})

const starting = ref(false)
const submitting = ref(false)
const submitError = ref<string | null>(null)
const showConfirmSubmit = ref(false)

const activeQuestionIndex = ref(0)
const remainingSeconds = ref<number | null>(null)
let timerHandle: ReturnType<typeof setInterval> | null = null

const result = reactive<{ score: number | null; passed: boolean }>({ score: null, passed: false })

const activeQuestion = computed(() => quiz.value!.questions[activeQuestionIndex.value])

const hasAnswer = (qid: string): boolean => {
    const v = answers[qid]
    if (Array.isArray(v)) return v.length > 0
    return v !== undefined && v !== null && v !== ''
}

const answeredCount = computed(() =>
    quiz.value ? quiz.value.questions.filter(q => hasAnswer(q.id)).length : 0
)

const progressPercent = computed(() => {
    if (!quiz.value || quiz.value.questions.length === 0) return 0
    return Math.round((answeredCount.value / quiz.value.questions.length) * 100)
})

const isMultipleSelected = (qid: string, opt: string): boolean => {
    const v = answers[qid]
    return Array.isArray(v) && v.includes(opt)
}

const toggleMultiple = (qid: string, opt: string) => {
    const current = Array.isArray(answers[qid]) ? [...(answers[qid] as string[])] : []
    const idx = current.indexOf(opt)
    if (idx === -1) current.push(opt)
    else current.splice(idx, 1)
    answers[qid] = current
}

const formatType = (t: QuestionType): string => {
    if (t === 'single_choice') return 'Single choice'
    if (t === 'multiple_choice') return 'Multiple choice'
    return 'Short text'
}

const formatExpiry = (iso: string): string => {
    const d = new Date(iso)
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

const timerDisplay = computed(() => {
    if (remainingSeconds.value === null) return '--:--'
    const s = Math.max(0, remainingSeconds.value)
    const min = Math.floor(s / 60)
    const sec = s % 60
    return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const timerTextClass = computed(() => {
    if (remainingSeconds.value === null) return 'text-(--text-heading)'
    if (remainingSeconds.value <= 60) return 'text-(--color-danger)'
    if (remainingSeconds.value <= 300) return 'text-(--color-warning)'
    return 'text-(--text-heading)'
})

const timerVariant = computed(() => {
    if (remainingSeconds.value === null) return 'bg-(--bg-muted) text-(--text-muted)'
    if (remainingSeconds.value <= 60) return 'bg-(--color-danger-subtle) text-(--color-danger)'
    if (remainingSeconds.value <= 300) return 'bg-(--color-warning-subtle) text-(--color-warning)'
    return 'bg-(--color-primary-subtle) text-(--color-primary)'
})

const startTimer = () => {
    if (!quiz.value?.timeLimitMinutes) return
    remainingSeconds.value = quiz.value.timeLimitMinutes * 60
    timerHandle = setInterval(() => {
        if (remainingSeconds.value === null) return
        remainingSeconds.value -= 1
        if (remainingSeconds.value <= 0) {
            stopTimer()
            // Auto-submit on expiry. Don't await so the timer thread stays free.
            autoSubmit()
        }
    }, 1000)
}

const stopTimer = () => {
    if (timerHandle) {
        clearInterval(timerHandle)
        timerHandle = null
    }
}

const autoSubmit = async () => {
    if (submitting.value || phase.value !== 'in_progress') return
    showConfirmSubmit.value = false
    await onSubmit()
}

const showError = (title: string, message: string) => {
    errorTitle.value = title
    errorMessage.value = message
    phase.value = 'error'
}

const showStatusError = (status: string) => {
    if (status === 'completed') {
        showError(
            'Assessment already submitted',
            'You have already completed this assessment. The recruiter has your results.'
        )
    } else if (status === 'expired') {
        showError(
            'Invitation expired',
            'This invitation link has expired. Please contact the recruiter for a new one.'
        )
    } else if (status === 'abandoned') {
        showError(
            'Assessment closed',
            'This assessment session was closed. Please contact the recruiter for help.'
        )
    } else {
        phase.value = 'landing'
    }
}

const onStart = async () => {
    if (!attempt.value) return
    starting.value = true
    try {
        const api = useCandidateQuiz(handle.value, token.value)
        const res = await api.start(attempt.value.id)
        attempt.value = res.data
        phase.value = 'in_progress'
        startTimer()
    } catch (err: any) {
        showError('Could not start', err?.data?.message || 'Something went wrong starting your assessment.')
    } finally {
        starting.value = false
    }
}

const onSubmit = async () => {
    if (!attempt.value) return
    submitting.value = true
    submitError.value = null
    try {
        const api = useCandidateQuiz(handle.value, token.value)
        const res = await api.submit(attempt.value.id, answers)
        attempt.value = res.data
        result.score = res.data.score
        result.passed = res.data.passed === true
        stopTimer()
        phase.value = 'result'
        showConfirmSubmit.value = false
    } catch (err: any) {
        submitError.value = err?.data?.message || 'Failed to submit. Please try again.'
    } finally {
        submitting.value = false
    }
}

onMounted(async () => {
    if (!token.value || !handle.value) {
        showError(
            'Invitation invalid',
            !token.value
                ? 'The link you used is missing the assessment token.'
                : 'The link you used is missing the workspace handle.'
        )
        return
    }

    try {
        const api = useCandidateQuiz(handle.value, token.value)
        const res = await api.auth()
        attempt.value = res.data.attempt
        quiz.value = res.data.quiz
        if (attempt.value.status === 'in_progress') {
            // Resuming an already-started attempt - skip the landing screen.
            phase.value = 'in_progress'
            startTimer()
        } else if (['completed', 'expired', 'abandoned'].includes(attempt.value.status)) {
            showStatusError(attempt.value.status)
        } else {
            phase.value = 'landing'
        }
    } catch (err: any) {
        if (err?.status === 404 || err?.response?.status === 404) {
            showError(
                'Invitation invalid or expired',
                'This link is not valid. Please contact the recruiter for a new invitation.'
            )
        } else if (err?.status === 401 || err?.response?.status === 401) {
            showError('Authentication failed', 'The assessment token is missing or malformed.')
        } else {
            showError(
                'Could not load assessment',
                err?.data?.message || 'Please refresh the page or contact your recruiter.'
            )
        }
    }
})

onBeforeUnmount(() => {
    stopTimer()
})
</script>

<style scoped>
.q-nav-pill {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-family: var(--font-mono, monospace);
    color: var(--text-muted);
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.q-nav-pill:hover {
    color: var(--text-heading);
    border-color: var(--color-primary);
}
.q-nav-pill-answered {
    background: var(--color-success-subtle, var(--bg-muted));
    color: var(--color-success, var(--text-heading));
    border-color: var(--color-success, var(--border-color));
}
.q-nav-pill-active {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

.option-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.875rem;
    border: 1px solid var(--border-color);
    border-radius: 0.625rem;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.option-row:hover {
    background: var(--bg-muted);
}
.option-row-active {
    border-color: var(--color-primary);
    background: var(--color-primary-subtle);
}
</style>
