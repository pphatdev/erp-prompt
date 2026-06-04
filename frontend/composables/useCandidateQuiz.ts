/**
 * Candidate-side API client. Lives outside the admin auth flow:
 *   - X-Tenant-Handle comes from the URL's `handle` query param (the admin
 *     bakes it into the magic-link URL via QuizController@assignToApplication).
 *   - No Authorization bearer; the magic-link token in `?token=` is the
 *     authentication primitive.
 *
 * Deliberately separate from useApi() so a stale admin auth store can't
 * leak credentials into the candidate session.
 */

export type QuestionType = 'single_choice' | 'multiple_choice' | 'short_text'

export interface QuizQuestion {
    id: string
    sequence: number
    prompt: string
    questionType: QuestionType
    options: string[] | null
    points: number
}

export interface QuizPayload {
    id: string
    title: string
    description: string | null
    timeLimitMinutes: number | null
    questions: QuizQuestion[]
}

export interface QuizAttempt {
    id: string
    quizId: string
    applicationId: string
    candidateEmail: string | null
    candidateName: string | null
    status: 'invited' | 'in_progress' | 'completed' | 'expired' | 'abandoned'
    invitedAt: string | null
    expiresAt: string | null
    startedAt: string | null
    submittedAt: string | null
    score: number | null
    passed: boolean | null
}

export interface AuthResponse {
    data: { attempt: QuizAttempt; quiz: QuizPayload }
}

export interface AttemptResponse {
    data: QuizAttempt
}

export const useCandidateQuiz = (handle: string, token: string) => {
    const config = useRuntimeConfig()
    const base = config.public.apiBase

    const fetchWithTenant = async <T = any>(path: string, opts: any = {}): Promise<T> => {
        const url = `${base}/${path.replace(/^\//, '')}`
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Tenant-Handle': handle,
            ...((opts.headers as Record<string, string>) || {}),
        }
        return await $fetch<T>(url, { ...opts, headers })
    }

    const auth = () =>
        fetchWithTenant<AuthResponse>(`candidate/auth?token=${encodeURIComponent(token)}`, {
            method: 'GET',
        })

    const start = (attemptId: string) =>
        fetchWithTenant<AttemptResponse>(
            `candidate/quizzes/${attemptId}/start?token=${encodeURIComponent(token)}`,
            { method: 'POST' }
        )

    const submit = (attemptId: string, answers: Record<string, unknown>) =>
        fetchWithTenant<AttemptResponse>(
            `candidate/quizzes/${attemptId}/submit?token=${encodeURIComponent(token)}`,
            { method: 'POST', body: { answers } }
        )

    return { auth, start, submit }
}
