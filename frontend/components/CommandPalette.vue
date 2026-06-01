<template>
    <Teleport to="body">
        <transition name="backdrop">
            <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-start justify-center pt-[10vh] px-4 sm:px-6">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('update:modelValue', false)"></div>

                <!-- Palette -->
                <div class="relative w-full max-w-2xl bg-(--bg-card) rounded-2xl shadow-(--shadow-lg) border border-(--border-color) overflow-hidden flex flex-col max-h-[80vh]"
                    @click.stop>
                    
                    <!-- Search Input -->
                    <div class="flex items-center px-4 py-4 border-b border-(--border-color)">
                        <i class="ti ti-search text-(--text-muted) text-xl mr-3"></i>
                        <input 
                            ref="searchInput"
                            v-model="searchQuery"
                            type="text" 
                            class="flex-1 bg-transparent border-none outline-none text-(--text-heading) text-base placeholder:text-(--text-muted)"
                            placeholder="Search modules, apps, or settings..."
                            @keydown.esc="$emit('update:modelValue', false)"
                            @keydown.down.prevent="navigateOptions(1)"
                            @keydown.up.prevent="navigateOptions(-1)"
                            @keydown.enter.prevent="selectActive"
                        />
                        <span class="text-xxs font-mono font-semibold px-1.5 py-0.5 rounded border border-(--border-color) bg-(--bg-muted) text-(--text-muted)">ESC</span>
                    </div>

                    <!-- Results Area -->
                    <div class="flex-1 overflow-y-auto p-2 custom-scrollbar">
                        
                        <div v-if="filteredItems.length" class="px-3 py-2">
                            <h4 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-2">
                                {{ searchQuery ? 'Search Results' : 'Suggested Modules' }}
                            </h4>
                            <ul class="space-y-1" ref="resultsList">
                                <li v-for="(item, index) in filteredItems" :key="item.route">
                                    <button 
                                        class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left transition-colors text-sm"
                                        :class="activeIndex === index ? 'bg-(--color-primary)/10 text-(--color-primary)' : 'hover:bg-(--bg-muted) text-(--text-body)'"
                                        @click="gotoRoute(item.route)"
                                        @mouseenter="activeIndex = index"
                                    >
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
                                            :class="activeIndex === index ? 'bg-(--color-primary)/20 text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                                            <i :class="['ti', item.icon, 'text-lg']" />
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium flex items-center gap-2">
                                                {{ item.label }}
                                            </div>
                                            <div class="text-xxs mt-0.5" :class="activeIndex === index ? 'text-(--color-primary)/70' : 'text-(--text-muted)'">
                                                {{ item.category }}
                                            </div>
                                        </div>
                                        <i class="ti ti-chevron-right text-xs" :class="activeIndex === index ? 'opacity-100' : 'opacity-0 text-(--text-muted)'"></i>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        
                        <div v-else class="px-3 py-12 flex flex-col items-center justify-center text-center">
                            <i class="ti ti-mood-empty text-4xl text-(--text-muted)/50 mb-3"></i>
                            <p class="text-sm text-(--text-muted)">No modules found for "<span class="text-(--text-heading)">{{ searchQuery }}</span>"</p>
                        </div>
                    </div>
                    
                    <div class="bg-(--bg-muted)/50 border-t border-(--border-color) px-4 py-3 flex items-center justify-between text-xxs text-(--text-muted)">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center gap-1"><span class="px-1 py-0.5 rounded border border-(--border-color) bg-(--bg-card)">↑↓</span> to navigate</span>
                            <span class="flex items-center gap-1"><span class="px-1 py-0.5 rounded border border-(--border-color) bg-(--bg-card)">↵</span> to open</span>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps<{
    modelValue: boolean
    navGroups?: any[]
}>()

const emit = defineEmits(['update:modelValue'])
const router = useRouter()

const searchQuery = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const activeIndex = ref(0)
const resultsList = ref<HTMLElement | null>(null)

// Flatten navigation groups into a single searchable array
const searchableItems = computed(() => {
    if (!props.navGroups) return []
    
    const flat: Array<{ label: string, icon: string, route: string, category: string }> = []
    
    const traverse = (items: any[], category: string) => {
        for (const item of items) {
            if (item.children) {
                traverse(item.children, `${category} / ${item.label}`)
            } else if (item.route && item.route !== '#') {
                flat.push({
                    label: item.label,
                    icon: item.icon,
                    route: item.route,
                    category: category
                })
            }
        }
    }
    
    for (const group of props.navGroups) {
        traverse(group.items, group.label)
    }
    
    return flat
})

const filteredItems = computed(() => {
    if (!searchQuery.value) return searchableItems.value.slice(0, 8)
    
    const query = searchQuery.value.toLowerCase()
    return searchableItems.value.filter(item => 
        item.label.toLowerCase().includes(query) || 
        item.category.toLowerCase().includes(query)
    ).slice(0, 12)
})

// Reset index when search changes
watch(searchQuery, () => {
    activeIndex.value = 0
})

watch(() => props.modelValue, async (val) => {
    if (val) {
        searchQuery.value = ''
        activeIndex.value = 0
        await nextTick()
        searchInput.value?.focus()
    }
})

const navigateOptions = (dir: number) => {
    if (!filteredItems.value.length) return
    activeIndex.value = (activeIndex.value + dir + filteredItems.value.length) % filteredItems.value.length
    
    // Auto scroll handling
    nextTick(() => {
        if (!resultsList.value) return
        const activeItem = resultsList.value.children[activeIndex.value] as HTMLElement
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' })
        }
    })
}

const gotoRoute = (route: string) => {
    emit('update:modelValue', false)
    router.push(route)
}

const selectActive = () => {
    if (filteredItems.value.length > 0 && activeIndex.value >= 0 && activeIndex.value < filteredItems.value.length) {
        gotoRoute(filteredItems.value[activeIndex.value].route)
    }
}
</script>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

.backdrop-enter-active .max-w-2xl,
.backdrop-leave-active .max-w-2xl {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.backdrop-enter-from .max-w-2xl,
.backdrop-leave-to .max-w-2xl {
    transform: scale(0.95);
    opacity: 0;
}
</style>
