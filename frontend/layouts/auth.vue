<template>
    <div class="min-h-screen w-full mesh-gradient flex items-center justify-center p-4 relative overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-72 h-72 rounded-full blur-3xl pulse-primary bg-(--color-primary)/15" />
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 rounded-full blur-3xl bg-(--color-info)/10"
            style="animation: pulse-glow 4s infinite 1.5s;" />

        <div class="relative z-10 w-full max-w-md transition-all duration-500 ease-out">
            <slot />
        </div>
    </div>
</template>
