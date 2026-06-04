<template>
    <div class="min-h-screen w-full bg-(--bg-body) text-(--text-body) flex flex-col">
        <!-- Sandboxed top bar — no nav, no auth chrome -->
        <header class="border-b border-(--border-color) bg-(--bg-card)/80 backdrop-blur-sm">
            <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                        <i class="ti ti-clipboard-check text-base" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-(--text-heading)">Candidate Assessment</p>
                        <p class="text-xxs text-(--text-muted)">Sandboxed evaluation portal</p>
                    </div>
                </div>
                <span class="text-xxs text-(--text-muted) inline-flex items-center gap-1.5">
                    <i class="ti ti-shield-check" />
                    Secure session
                </span>
            </div>
        </header>

        <main class="flex-1 w-full">
            <div class="max-w-4xl mx-auto px-6 py-8">
                <slot />
            </div>
        </main>

        <footer class="border-t border-(--border-color) py-4 text-center text-xxs text-(--text-muted)">
            Your responses are graded automatically. Do not share this link.
        </footer>
    </div>
</template>
