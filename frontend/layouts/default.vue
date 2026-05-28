<template>
    <div class="min-h-screen flex bg-(--bg-layout) text-(--text-body)">
        <!-- Mobile backdrop -->
        <transition name="backdrop">
            <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/40 backdrop-blur-sm md:hidden" aria-hidden="true"
                @click="sidebarOpen = false" />
        </transition>

        <!-- ============================ Sidenav §4.2 ============================ -->
        <aside
            class="fixed inset-y-0 left-0 z-30 bg-(--bg-card) border-r border-(--border-color) flex flex-col transition-all duration-300 w-[260px]"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                isCompact ? 'md:w-[70px]' : 'md:w-[260px]',
                // Collapsed + hovered: float above main content instead of pushing
                // it. Lift z-index and swap to a deeper shadow so the flyout reads
                // as a temporary panel, not a layout shift.
                isFlyout ? 'md:z-40 shadow-(--shadow-lg)' : 'shadow-(--shadow-sm)'
            ]" @mouseenter="onSidebarEnter" @mouseleave="onSidebarLeave">
            <!-- Mobile close button -->
            <button type="button"
                class="md:hidden absolute right-3 top-4 w-8 h-8 rounded-lg text-(--text-muted) hover:bg-(--bg-muted) hover:text-(--text-heading) flex items-center justify-center transition-colors"
                title="Close menu" @click="sidebarOpen = false">
                <i class="ti ti-x text-lg" />
            </button>

            <button type="button"
                class="hidden md:flex absolute -right-3 top-5 w-6 h-6 rounded-full bg-(--bg-card) border border-(--border-color) text-(--text-muted) hover:text-(--color-primary) hover:border-(--color-primary)/40 items-center justify-center shadow-(--shadow-sm) z-40 transition-all"
                :title="sidebarCollapsed ? 'Expand' : 'Collapse'" @click="sidebarCollapsed = !sidebarCollapsed"
                @mouseenter="onSidebarEnter" @mouseleave="onSidebarLeave">
                <i class="ti text-[12px] transition-transform"
                    :class="sidebarCollapsed ? 'ti-chevron-right' : 'ti-chevron-left'" />
            </button>

            <!-- Brand -->
            <div class="h-16 border-b border-(--border-color) flex items-center"
                :class="isCompact ? 'px-3 justify-center' : 'px-5'">
                <NuxtLink to="/dashboard" class="flex items-center gap-3 min-w-0" @click="closeMobileSidebar">
                    <span
                        class="w-9 h-9 rounded-lg bg-linear-to-tr from-(--color-primary) to-(--color-info) flex items-center justify-center text-white shadow-(--shadow-sm) shrink-0">
                        <i class="ti ti-chart-pie text-lg" />
                    </span>
                    <span v-show="!isCompact" class="overflow-hidden">
                        <span class="block text-sm font-semibold text-(--text-heading) tracking-tight">Smart ERP</span>
                        <span
                            class="block text-[10px] uppercase tracking-widest font-mono text-(--text-muted)">Enterprise
                            Suite</span>
                    </span>
                </NuxtLink>
            </div>

            <!-- Profile card §4.2 -->
            <!-- <div
        v-show="!sidebarCollapsed"
        class="mx-4 mt-4 mb-2 rounded-xl border border-(--border-color) bg-(--bg-muted) p-3 flex items-center gap-3"
      >
        <div class="relative">
          <div class="w-10 h-10 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-semibold">
            {{ initials }}
          </div>
          <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-(--color-success) border-2 border-(--bg-card)" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-semibold text-(--text-heading) truncate">{{ authStore.user?.name || 'Guest User' }}</p>
          <p class="text-xxs text-(--text-muted) truncate">{{ primaryRole }}</p>
        </div>
      </div> -->

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-5 overflow-y-auto custom-scrollbar">

                <!-- Skeleton while modules load -->
                <template v-if="modulesLoading">
                    <div v-for="g in skeletonGroups" :key="g.label" class="space-y-1">
                        <div v-show="!isCompact" class="h-2.5 rounded mb-2 mt-1 mx-3 nav-skeleton"
                            :style="{ width: g.labelWidth }" />
                        <div v-for="(w, i) in g.items" :key="i" class="nav-link nav-skeleton-row">
                            <span class="nav-icon">
                                <span class="w-4 h-4 rounded nav-skeleton block" />
                            </span>
                            <span v-show="!isCompact" class="h-2.5 rounded nav-skeleton" :style="{ width: w }" />
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div v-for="group in visibleNavGroups" :key="group.id" class="space-y-1">
                        <span v-show="!isCompact"
                            class="block px-3 mb-1 text-[10px] font-bold uppercase tracking-widest text-(--text-muted)">
                            {{ group.label }}
                        </span>

                        <template v-for="item in group.items" :key="item.label">
                            <!-- Single operational link -->
                            <NuxtLink v-if="!item.children && item.operational" :to="item.route!" class="nav-link"
                                :class="isRouteActive(item.route) ? 'nav-link-active' : ''">
                                <span class="nav-icon"><i :class="['ti', item.icon]" /></span>
                                <span v-show="!isCompact" class="truncate flex-1">{{ item.label }}</span>
                                <Badge v-show="!isCompact && item.badge" :variant="item.badgeVariant || 'success'">{{
                                    item.badge }}</Badge>
                            </NuxtLink>

                            <!-- Single coming-soon stub -->
                            <button v-else-if="!item.children" type="button" class="nav-link nav-link-disabled w-full"
                                @click="comingSoon(item.label)">
                                <span class="nav-icon"><i :class="['ti', item.icon]" /></span>
                                <span v-show="!isCompact" class="truncate flex-1 text-left">{{ item.label }}</span>
                                <span v-show="!isCompact"
                                    class="text-xxs font-mono uppercase tracking-wider text-(--text-muted)">soon</span>
                            </button>

                            <!-- Group with children -->
                            <div v-else>
                                <button type="button" class="nav-link w-full"
                                    :class="isGroupActive(item) ? 'nav-link-active' : ''"
                                    @click="toggleOpen(item.label)">
                                    <span class="nav-icon"><i :class="['ti', item.icon]" /></span>
                                    <span v-show="!isCompact" class="truncate flex-1 text-left">{{ item.label }}</span>
                                    <i v-show="!isCompact" class="ti ti-chevron-right text-xs transition-transform"
                                        :class="openGroups[item.label] ? 'rotate-90' : ''" />
                                </button>
                                <div v-show="!isCompact && openGroups[item.label]"
                                    class="pl-2 mt-1 space-y-0.5 border-l border-(--border-color) ml-5">
                                    <template v-for="child in item.children" :key="child.label">
                                        <!-- No sub-children -->
                                        <template v-if="!child.children">
                                            <NuxtLink v-if="child.operational" :to="child.route!"
                                                class="nav-link nav-link-sub"
                                                :class="isRouteActive(child.route) ? 'nav-link-active' : ''">
                                                <span class="nav-icon"><i :class="['ti', child.icon]" /></span>
                                                <span class="truncate">{{ child.label }}</span>
                                            </NuxtLink>
                                            <button v-else type="button"
                                                class="nav-link nav-link-sub nav-link-disabled w-full"
                                                @click="comingSoon(child.label)">
                                                <span class="nav-icon"><i :class="['ti', child.icon]" /></span>
                                                <span class="truncate text-left flex-1">{{ child.label }}</span>
                                            </button>
                                        </template>

                                        <!-- With sub-children (3rd level) -->
                                        <div v-else>
                                            <button type="button" class="nav-link nav-link-sub w-full"
                                                :class="isGroupActive(child) ? 'nav-link-active' : ''"
                                                @click="toggleOpen(item.label + '-' + child.label)">
                                                <span class="nav-icon"><i :class="['ti', child.icon]" /></span>
                                                <span class="truncate flex-1 text-left">{{ child.label }}</span>
                                                <i class="ti ti-chevron-right text-xs transition-transform"
                                                    :class="openGroups[item.label + '-' + child.label] ? 'rotate-90' : ''" />
                                            </button>
                                            <div v-show="openGroups[item.label + '-' + child.label]"
                                                class="pl-2 mt-1 space-y-0.5 border-l border-(--border-color) ml-5">
                                                <template v-for="grandchild in child.children" :key="grandchild.label">
                                                    <NuxtLink v-if="grandchild.operational" :to="grandchild.route!"
                                                        class="nav-link nav-link-sub"
                                                        :class="isRouteActive(grandchild.route) ? 'nav-link-active' : ''">
                                                        <span class="nav-icon"><i
                                                                :class="['ti', grandchild.icon]" /></span>
                                                        <span class="truncate">{{ grandchild.label }}</span>
                                                    </NuxtLink>
                                                    <button v-else type="button"
                                                        class="nav-link nav-link-sub nav-link-disabled w-full"
                                                        @click="comingSoon(grandchild.label)">
                                                        <span class="nav-icon"><i
                                                                :class="['ti', grandchild.icon]" /></span>
                                                        <span class="truncate text-left flex-1">{{ grandchild.label
                                                            }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </nav>

            <!-- Sign out -->
            <div class="p-3 border-t border-(--border-color)">
                <button type="button"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-(--color-danger-subtle) hover:text-(--color-danger) text-xs font-semibold text-(--text-body) transition-colors"
                    @click="handleLogout">
                    <i class="ti ti-logout text-base" />
                    <span v-show="!isCompact">Sign out</span>
                </button>
            </div>
        </aside>

        <!-- ============================ Main viewport ============================ -->
        <div class="flex-1 flex flex-col min-w-0 transition-all duration-300"
            :class="sidebarCollapsed ? 'md:pl-[70px]' : 'md:pl-[260px]'">
            <!-- ----------------------- Topbar §4.1 ----------------------- -->
            <header
                class="sticky top-0 z-20 h-16 bg-(--header-bg) backdrop-blur-xl border-b border-(--border-color) flex items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-4 min-w-0">
                    <button type="button" class="md:hidden p-2 rounded-lg hover:bg-(--bg-muted) text-(--text-body)"
                        @click="sidebarOpen = !sidebarOpen">
                        <i class="ti ti-menu-2 text-lg" />
                    </button>

                    <button type="button"
                        class="hidden md:flex lg:hidden w-9 h-9 rounded-lg hover:bg-(--bg-muted) text-(--text-body) items-center justify-center"
                        @click="sidebarCollapsed = !sidebarCollapsed">
                        <i class="ti ti-menu-4 text-lg" />
                    </button>
                </div>

                <div class="hidden lg:flex items-center w-80 relative">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input type="search" placeholder="Search transactions, files, roles..."
                        class="form-control pl-9 pr-12 py-1.5 text-xs" />
                    <span
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xxs font-mono font-semibold px-1.5 py-0.5 rounded border border-(--border-color) bg-(--bg-muted) text-(--text-muted)">⌘K</span>
                </div>

                <div class="flex items-center gap-1.5">
                    <!-- Mega menu -->
                    <!-- <div class="relative hidden xl:block">
            <button class="topbar-btn" type="button" @click.stop="toggle('mega')">
              <i class="ti ti-layout-grid text-lg" />
            </button>
            <transition name="popover">
              <div v-if="open.mega" class="popover w-[460px] p-5" @click.stop>
                <div class="grid grid-cols-3 gap-5">
                  <div v-for="group in megaMenu" :key="group.title">
                    <h6 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-2">{{ group.title }}</h6>
                    <ul class="space-y-1">
                      <li v-for="item in group.items" :key="item">
                        <a class="block px-2 py-1 rounded text-xs hover:bg-(--bg-muted) text-(--text-body) hover:text-(--color-primary) cursor-pointer">{{ item }}</a>
                      </li>
                    </ul>
                  </div>
                </div>
                <div class="mt-5 p-4 rounded-lg bg-linear-to-tr from-(--color-primary) to-(--color-info) text-white">
                  <p class="text-xs font-semibold">Welcome back, {{ firstName }}</p>
                  <p class="text-xxs opacity-80 mt-0.5">Premium themes & new modules ship every Friday.</p>
                </div>
              </div>
            </transition>
          </div> -->

                    <!-- Apps grid -->
                    <!-- <div class="relative">
            <button class="topbar-btn" type="button" @click.stop="toggle('apps')">
              <i class="ti ti-grid-dots text-lg" />
            </button>
            <transition name="popover">
              <div v-if="open.apps" class="popover w-72 p-3" @click.stop>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="app in appsGrid"
                    :key="app.label"
                    type="button"
                    class="rounded-lg p-3 text-center hover:bg-(--bg-muted) flex flex-col items-center gap-1"
                  >
                    <i :class="['ti', app.icon, 'text-lg', app.color]" />
                    <span class="text-xxs font-semibold text-(--text-body)">{{ app.label }}</span>
                  </button>
                </div>
              </div>
            </transition>
          </div> -->

                    <!-- Theme toggle -->
                    <!-- <button type="button" class="topbar-btn" :title="themeMode === 'dark' ? 'Switch to light' : 'Switch to dark'" @click="toggleTheme">
            <i class="ti text-lg" :class="themeMode === 'dark' ? 'ti-sun' : 'ti-moon'" />
          </button> -->

                    <!-- Notifications -->
                    <div class="relative">
                        <button class="topbar-btn" type="button" @click.stop="toggle('notif')">
                            <i class="ti ti-bell text-lg" />
                            <span
                                class="absolute top-1 right-1 min-w-[14px] h-[14px] px-1 rounded-full bg-(--color-danger) text-[9px] font-bold text-white flex items-center justify-center font-mono">7</span>
                        </button>
                        <transition name="popover">
                            <div v-if="open.notif" class="popover w-80 p-0" @click.stop>
                                <header
                                    class="px-4 py-3 border-b border-(--border-color) flex items-center justify-between">
                                    <span class="text-xs font-semibold text-(--text-heading)">Notifications</span>
                                    <Badge variant="primary">7 New</Badge>
                                </header>
                                <ul class="max-h-72 overflow-y-auto divide-y divide-(--border-color)">
                                    <li v-for="n in notifications" :key="n.id"
                                        class="px-4 py-3 hover:bg-(--bg-muted) flex gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                            :class="iconWrapMap[n.tone]">
                                            <i :class="['ti', n.icon]" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-(--text-heading) truncate">{{ n.title
                                            }}</p>
                                            <p class="text-xxs text-(--text-muted) truncate">{{ n.detail }} · {{ n.time
                                            }}</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </transition>
                    </div>

                    <!-- Language -->
                    <!-- <div class="relative hidden md:block">
            <button class="topbar-btn" type="button" @click.stop="toggle('lang')">
              <span class="text-base">{{ activeLang.flag }}</span>
            </button>
            <transition name="popover">
              <div v-if="open.lang" class="popover w-44 p-1.5" @click.stop>
                <button
                  v-for="lang in languages"
                  :key="lang.code"
                  type="button"
                  class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs hover:bg-(--bg-muted)"
                  :class="activeLang.code === lang.code ? 'text-(--color-primary) font-semibold' : 'text-(--text-body)'"
                  @click="activeLang = lang; open.lang = false"
                >
                  <span class="text-base">{{ lang.flag }}</span>
                  <span>{{ lang.label }}</span>
                </button>
              </div>
            </transition>
          </div> -->

                    <!-- Customizer -->
                    <button class="topbar-btn" type="button" title="Customizer" @click="customizerOpen = true">
                        <i class="ti ti-settings text-lg" />
                    </button>

                    <!-- Tenant -->
                    <div v-if="!hasSubdomain" class="relative">
                        <button class="topbar-btn px-3 gap-2" type="button" @click.stop="toggle('tenant')">
                            <span class="w-2 h-2 rounded-full"
                                :style="{ background: `rgb(${tenantStore.currentTenant?.primaryColor || '59 130 246'})` }" />
                            <span class="text-xs font-semibold text-(--text-heading) truncate max-w-[120px]">{{
                                tenantStore.activeName }}</span>
                            <i class="ti ti-chevron-down text-[10px]" />
                        </button>
                        <transition name="popover">
                            <div v-if="open.tenant" class="popover w-60 p-1.5" @click.stop>
                                <div class="px-3 py-2 border-b border-(--border-color) mb-1">
                                    <span
                                        class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Select
                                        tenant</span>
                                </div>
                                <button v-for="t in tenantStore.availableTenants" :key="t.id" type="button"
                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs hover:bg-(--bg-muted)"
                                    :class="tenantStore.activeHandle === t.handle ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'text-(--text-body)'"
                                    @click="selectTenant(t.handle)">
                                    <span class="w-2 h-2 rounded-full"
                                        :style="{ background: `rgb(${t.primaryColor})` }" />
                                    <span class="truncate flex-1 text-left">{{ t.name }}</span>
                                    <span class="text-xxs font-mono text-(--text-muted)">@{{ t.handle }}</span>
                                </button>
                            </div>
                        </transition>
                    </div>

                    <!-- Profile -->
                    <div class="relative">
                        <button class="topbar-btn p-1" type="button" @click.stop="toggle('profile')">
                            <div class="relative">
                                <div
                                    class="w-8 h-8 rounded-full bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-semibold text-xs">
                                    {{ initials }}
                                </div>
                                <span
                                    class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-(--color-success) border-2 border-(--bg-card)" />
                            </div>
                        </button>
                        <transition name="popover">
                            <div v-if="open.profile" class="popover w-60 p-1.5" @click.stop>
                                <div class="px-3 py-2 border-b border-(--border-color) mb-1">
                                    <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                        authStore.user?.name ||
                                        'Guest User' }}</p>
                                    <p class="text-xxs text-(--text-muted) truncate">{{ authStore.user?.email || 'no session' }}
                                    </p>
                                </div>
                                <button v-for="entry in profileMenu" :key="entry.label" type="button"
                                    class="profile-item">
                                    <i :class="['ti', entry.icon]" />
                                    <span>{{ entry.label }}</span>
                                </button>
                                <hr class="my-1 border-(--border-color)" />
                                <button type="button"
                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-(--color-danger) hover:bg-(--color-danger-subtle)"
                                    @click="handleLogout">
                                    <i class="ti ti-logout" />
                                    Sign out
                                </button>
                            </div>
                        </transition>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-8" @click="closeAll">
                <!-- Breadcrumbs above content slot -->
                <nav
                    class="flex items-center flex-wrap gap-x-2 gap-y-1 text-[10px] text-(--text-muted) font-mono uppercase tracking-wider mb-5 select-none">
                    <NuxtLink to="/" class="hover:text-(--text-heading) transition-colors">Home</NuxtLink>
                    <template v-for="(crumb, idx) in breadcrumbItems" :key="crumb.label + idx">
                        <i class="ti ti-chevron-right text-[8px]" />
                        <NuxtLink v-if="crumb.to && idx < breadcrumbItems.length - 1" :to="crumb.to"
                            class="hover:text-(--text-heading) transition-colors">
                            {{ crumb.label }}
                        </NuxtLink>
                        <span v-else-if="idx < breadcrumbItems.length - 1" class="text-(--text-muted)">
                            {{ crumb.label }}
                        </span>
                        <span v-else class="text-(--text-heading) font-semibold">{{ crumb.label }}</span>
                    </template>
                </nav>

                <slot />
            </main>

            <!-- <footer
                class="px-6 py-4 border-t border-(--border-color) bg-(--footer-bg) flex flex-col sm:flex-row justify-between items-center gap-2 text-xxs text-(--text-muted)">
                <div>
                    © {{ new Date().getFullYear() }} Smart ERP · Tenant <span class="font-mono text-(--text-body)">@{{
                        tenantStore.activeHandle }}</span>
                </div>
                <div class="flex items-center gap-4 font-mono uppercase tracking-wider">
                    <a class="hover:text-(--text-heading) cursor-pointer">Security</a>
                    <a class="hover:text-(--text-heading) cursor-pointer">Audit Ledger</a>
                    <a class="hover:text-(--text-heading) cursor-pointer">Support</a>
                </div>
            </footer> -->
        </div>

        <CustomizerOffcanvas v-model="customizerOpen" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'
import { useToast } from '~/composables/useToast'

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const toast = useToast()
const { load: loadModules, hasModule, loading: modulesLoading, modules } = useModules()

const skeletonGroups = [
    { label: 'main', labelWidth: '2.5rem', items: ['60%', '55%'] },
    { label: 'workspace', labelWidth: '4rem', items: ['45%', '50%', '55%', '50%'] },
    { label: 'apps', labelWidth: '2rem', items: ['50%', '55%', '60%', '40%', '45%', '50%', '40%', '35%'] },
]

const hasSubdomain = ref(false)

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const sidebarHovered = ref(false)
const customizerOpen = ref(false)

// Compact (icon-only) mode applies on desktop only. When the mobile drawer is
// open OR the user hovers the collapsed rail, we render the full-width sidebar
// so labels show — but the main content's left padding is kept locked to the
// persistent `sidebarCollapsed` state so the rail-flyout pattern doesn't shift
// the page underneath.
const isCompact = computed(() =>
    sidebarCollapsed.value && !sidebarOpen.value && !sidebarHovered.value
)
const isFlyout = computed(() =>
    sidebarCollapsed.value && !sidebarOpen.value && sidebarHovered.value
)

// Small grace period on mouseleave so a brief stray of the cursor (e.g. moving
// to a submenu, or crossing the collapse toggle that sits outside the aside's
// right edge) doesn't snap the flyout closed.
let leaveTimer: ReturnType<typeof setTimeout> | null = null
const onSidebarEnter = () => {
    if (leaveTimer) { clearTimeout(leaveTimer); leaveTimer = null }
    if (sidebarCollapsed.value) sidebarHovered.value = true
}
const onSidebarLeave = () => {
    if (leaveTimer) clearTimeout(leaveTimer)
    leaveTimer = setTimeout(() => { sidebarHovered.value = false; leaveTimer = null }, 120)
}

const closeMobileSidebar = () => { sidebarOpen.value = false }

// Collapsing the rail manually should drop hover state immediately so it
// doesn't stick open after the user clicks the chevron.
watch(sidebarCollapsed, (collapsed) => {
    if (!collapsed) sidebarHovered.value = false
})

watch(() => router.currentRoute.value.fullPath, () => { sidebarOpen.value = false })

// Lock background scroll while the mobile drawer is open.
watch(sidebarOpen, (open) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = open ? 'hidden' : ''
})

const open = reactive({
    mega: false,
    apps: false,
    notif: false,
    lang: false,
    tenant: false,
    profile: false
})

const toggle = (key: keyof typeof open) => {
    const current = open[key]
        ; (Object.keys(open) as (keyof typeof open)[]).forEach(k => { open[k] = false })
    open[key] = !current
}
const closeAll = () => { (Object.keys(open) as (keyof typeof open)[]).forEach(k => { open[k] = false }) }

const openGroups = reactive<Record<string, boolean>>({})
const toggleOpen = (label: string) => { openGroups[label] = !openGroups[label] }

interface NavItem {
    label: string
    icon: string
    route?: string
    operational?: boolean
    badge?: string
    badgeVariant?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary'
    /**
     * Permission slug(s) required to see this item. Array means "any one of"
     * (OR), so an item can surface for both an admin grant and its `.self`
     * counterpart. Omit to show unconditionally to authenticated users.
     */
    permission?: string | string[]
    /**
     * DB module slug this item maps to. When set, the item is hidden unless the
     * current tenant has that module active (is_core or entitled via subscription).
     * Omit for items that should always be visible (e.g. Dashboard).
     */
    moduleSlug?: string
    children?: NavItem[]
}
interface NavGroup { id: string; label: string; items: NavItem[] }

const navGroups = reactive<NavGroup[]>([
    {
        id: 'main',
        label: 'Main',
        items: [
            { label: 'Dashboard', icon: 'ti-layout-dashboard', route: '/dashboard', operational: true, permission: 'reporting.dashboard.read' }
        ]
    },
    {
        id: 'self-service',
        label: 'My Workspace',
        items: [
            { label: 'My Profile', icon: 'ti-user-circle', route: '#', operational: false, permission: 'hrm.employee.read.self' },
            { label: 'My Leaves', icon: 'ti-calendar-event', route: '/hrm/timeoff/leaves', operational: true, permission: 'hrm.leave.read.self', moduleSlug: 'my-leaves' },
            { label: 'My Payslips', icon: 'ti-cash', route: '#', operational: false, permission: 'hrm.payslip.read.self', moduleSlug: 'my-payslips' },
            { label: 'My Appraisals', icon: 'ti-clipboard-list', route: '/hrm/appraisals', operational: true, permission: 'hrm.performance.read.self', moduleSlug: 'my-appraisals' }
        ]
    },
    {
        id: 'apps',
        label: 'Apps',
        items: [
            {
                label: 'Ecommerce',
                icon: 'ti-shopping-cart',
                moduleSlug: 'ecommerce',
                children: [
                    { label: 'Orders', icon: 'ti-receipt', route: '#', operational: false },
                    { label: 'Refunds', icon: 'ti-receipt-refund', route: '#', operational: false }
                ]
            },
            {
                label: 'CRM',
                icon: 'ti-users',
                moduleSlug: 'crm',
                children: [
                    { label: 'Leads', icon: 'ti-address-book', route: '/crm/leads', operational: true, permission: ['crm.leads.read', 'crm.leads.write'] },
                    { label: 'Sales Pipeline', icon: 'ti-layout-kanban', route: '/crm/opportunities', operational: true, permission: ['crm.opportunities.read', 'crm.opportunities.write'] },
                    { label: 'Schedules', icon: 'ti-calendar-event', route: '/crm/schedules', operational: true, permission: ['crm.appointments.read', 'crm.appointments.write'] },
                    { label: 'Interaction Timeline', icon: 'ti-notes', route: '/crm/activities', operational: true, permission: ['crm.activities.read', 'crm.activities.write'] },
                    { label: 'B2B Contacts', icon: 'ti-users-group', route: '/crm/contacts', operational: true, permission: ['crm.contacts.read', 'crm.contacts.write'] }
                ]
            },
            {
                label: 'Sales',
                icon: 'ti-address-book',
                moduleSlug: 'sales',
                children: [
                    { label: 'Customers', icon: 'ti-users', route: '/sales/customers', operational: true, permission: ['sales.crm.read', 'sales.crm.write'] },
                    { label: 'Quotations', icon: 'ti-file-text', route: '/sales/quotations', operational: true, permission: ['sales.crm.read', 'sales.crm.write'] },
                    { label: 'Sales Orders', icon: 'ti-shopping-cart', route: '/sales/orders', operational: true, permission: ['sales.orders.read', 'sales.orders.write'] }
                ]
            },
            {
                label: 'Finance',
                icon: 'ti-coin',
                moduleSlug: 'fms',
                children: [
                    // Invoices/Subscriptions live under the Sales namespace today; surfaced here
                    // to match the Finance org chart. Backend paths unchanged — see rules/hybrid_sales_business_flow.md.
                    { label: 'Invoices', icon: 'ti-receipt', route: '/sales/invoices', operational: true, permission: ['sales.orders.read', 'sales.orders.write'] },
                    { label: 'Subscriptions', icon: 'ti-cloud', route: '/sales/subscriptions', operational: true, permission: ['sales.orders.read', 'sales.orders.write'] },
                    { label: 'Payments', icon: 'ti-cash', route: '/finance/payments', operational: false },
                    { label: 'Estimates', icon: 'ti-file-invoice', route: '/finance/estimates', operational: false },
                    { label: 'Exchange Rates', icon: 'ti-currency-dollar', route: '/finance/exchange-rates', operational: true, permission: ['fms.exchange_rate.read', 'fms.exchange_rate.write'] }
                ]
            },
            {
                label: 'Inventory',
                icon: 'ti-building-warehouse',
                moduleSlug: 'inventory',
                children: [
                    { label: 'Products', icon: 'ti-package', route: '/inventory/products', operational: true, permission: ['inventory.product.read', 'inventory.product.write'] },
                    { label: 'Categories', icon: 'ti-category', route: '/inventory/categories', operational: true, permission: ['inventory.category.read', 'inventory.category.write'] },
                    { label: 'Warehouses', icon: 'ti-building-warehouse', route: '/inventory/warehouses', operational: true, permission: ['inventory.warehouse.read', 'inventory.warehouse.write'] },
                    { label: 'Suppliers', icon: 'ti-truck-delivery', route: '/inventory/suppliers', operational: true, permission: ['inventory.suppliers.read', 'inventory.suppliers.write'] },
                    { label: 'Purchase Orders', icon: 'ti-shopping-bag', route: '/inventory/purchase-orders', operational: true, permission: ['inventory.procurement.read', 'inventory.procurement.write'] }
                ]
            },
            {
                label: 'Human Resource',
                icon: 'ti-users',
                moduleSlug: 'hrm',
                children: [
                    { label: 'Employees', icon: 'ti-user-circle', route: '/hrm/employees', operational: true, permission: 'hrm.employee.read' },
                    { label: 'Departments', icon: 'ti-building', route: '/hrm/departments', operational: true, permission: ['hrm.employee.read', 'hrm.employee.read.self'] },
                    { label: 'Positions', icon: 'ti-briefcase', route: '/hrm/positions', operational: true, permission: ['hrm.employee.read', 'hrm.employee.read.self'] },
                    { label: 'Leave Requests', icon: 'ti-calendar-event', route: '/hrm/timeoff/leaves', operational: true, permission: 'hrm.leave.read' },
                    { label: 'Shifts', icon: 'ti-clock-hour-8', route: '/hrm/timeoff/shifts', operational: true, permission: ['hrm.shift.read', 'hrm.attendance.read', 'hrm.attendance.clock.self'] },
                    { label: 'Attendance', icon: 'ti-fingerprint', route: '/hrm/timeoff/attendance', operational: true, permission: ['hrm.attendance.read', 'hrm.attendance.read.self', 'hrm.attendance.clock.self'] },
                    { label: 'Overtime', icon: 'ti-clock-up', route: '/hrm/timeoff/overtime', operational: true, permission: ['hrm.overtime.read', 'hrm.overtime.read.self', 'hrm.overtime.write.self'] },
                    { label: 'Payroll', icon: 'ti-cash', route: '/hrm/payroll', operational: true, permission: 'hrm.payroll.read' },
                    { label: 'Vacancies', icon: 'ti-briefcase-2', route: '/hrm/recruitments/vacancies', operational: true, permission: 'hrm.recruitment.read' },
                    { label: 'Applications', icon: 'ti-user-search', route: '/hrm/recruitments/applications', operational: true, permission: 'hrm.recruitment.read' },
                    { label: 'Candidates', icon: 'ti-layout-kanban', route: '/hrm/recruitments/candidates', operational: true, permission: 'hrm.recruitment.read' },
                    { label: 'Appraisals', icon: 'ti-clipboard-list', route: '/hrm/appraisals', operational: true, permission: 'hrm.performance.read' }
                ]
            },
            {
                label: 'eApprovals', icon: 'ti-checks', route: '#', operational: true,
                children: [
                    { label: 'Forms Portal', icon: 'ti-forms', route: '/approvals/forms', operational: true },
                    { label: 'My Requests', icon: 'ti-inbox', route: '/approvals/requests', operational: true },
                    { label: 'Review Portal', icon: 'ti-user-check', route: '/approvals/review', operational: true }
                ]
            },
            { label: 'Fleets', icon: 'ti-truck', route: '#', operational: false, moduleSlug: 'fleets' },
            { label: 'Project Management', icon: 'ti-presentation', route: '#', operational: false, moduleSlug: 'projects' },
            { label: 'eDocuments', icon: 'ti-file-text', route: '#', operational: false, moduleSlug: 'edocuments' },
            { label: 'Reports & Analytics', icon: 'ti-chart-bar', route: '#', operational: false, moduleSlug: 'reporting' },
        ]
    },
    {
        id: 'configurations',
        label: 'Configurations',
        items: [
            {
                label: 'Apps Management',
                icon: 'ti-box',
                children: [
                    {
                        label: 'Human Resource',
                        icon: 'ti-users',
                        children: [
                            { label: 'Leave Types', icon: 'ti-list', route: '/settings/apps/hrm/leave-types', operational: true, permission: 'hrm.leave.read' }
                        ]
                    },
                ]
            },
            { label: 'User Directory', icon: 'ti-users-group', route: '/settings/users', operational: true, permission: 'iam.users.read' },
            { label: 'Roles Matrix', icon: 'ti-shield-check', route: '/settings/roles', operational: true, permission: 'iam.roles.read' },
            {
                label: 'Configuration',
                icon: 'ti-settings',
                children: [
                    { label: 'Branding', icon: 'ti-palette', route: '/settings/configuration/branding', operational: true, permission: 'settings.read' },
                    { label: 'Locale', icon: 'ti-language', route: '/settings/configuration/locale', operational: true, permission: 'settings.read' },
                    { label: 'Notifications', icon: 'ti-bell', route: '/settings/configuration/notifications', operational: true, permission: 'settings.read' },
                    { label: 'Security', icon: 'ti-shield-lock', route: '/settings/configuration/security', operational: true, permission: 'settings.read' },
                    { label: 'Modules', icon: 'ti-puzzle', route: '/settings/configuration/modules', operational: true, permission: 'settings.read' },
                    { label: 'Numbering', icon: 'ti-hash', route: '/settings/configuration/numbering', operational: true, permission: 'settings.read' },
                    { label: 'Platform', icon: 'ti-server', route: '/settings/configuration/platform', operational: true, permission: 'settings.read' },
                ]
            },
        ]
    }
])

/**
 * Returns true if the current user can see this nav item.
 * - No `permission` field → always visible (legacy items + dashboard/tasks).
 * - Array of slugs → OR semantics; any one match unlocks the entry. This is
 *   how items like "Departments" surface for both admin and `.self` users.
 * - Group with children → visible iff at least one child is visible. The
 *   parent group inherits visibility from its descendants so we never
 *   render an empty disclosure.
 */
const canSeeItem = (item: NavItem): boolean => {
    if (item.moduleSlug && !hasModule(item.moduleSlug)) return false
    if (item.children) {
        return item.children.some(canSeeItem)
    }
    if (!item.permission) return true
    const slugs = Array.isArray(item.permission) ? item.permission : [item.permission]
    return slugs.some(slug => authStore.hasPermission(slug))
}

const getModIndex = (item: NavItem, parentModSlug?: string) => {
    const mods = modules.value || []
    if (parentModSlug) {
        const parentMod = mods.find(m => m.slug === parentModSlug)
        if (parentMod && parentMod.children) {
            const idx = parentMod.children.findIndex(c => c.route === item.route || c.slug === item.moduleSlug)
            return idx === -1 ? 9999 : idx
        }
        return 9999
    } else {
        const idx = mods.findIndex(m => m.slug === item.moduleSlug || m.route === item.route)
        return idx === -1 ? 9999 : idx
    }
}

const visibleNavGroups = computed<NavGroup[]>(() => {
    return navGroups
        // Hide the self-service group for admins — they get the full module
        // surface elsewhere on the sidebar and the `My …` shortcuts would just
        // duplicate links (their hasPermission() short-circuits make every .self
        // permission resolve true otherwise).
        .filter(group => !(group.id === 'self-service' && authStore.isAdmin))
        .map(group => {
            const sortedItems = [...group.items].sort((a, b) => getModIndex(a) - getModIndex(b))

            return {
                ...group,
                items: sortedItems
                    .map(item => {
                        if (!item.children) return item
                        // Recurse into child links so a group only shows the children the
                        // user actually has access to.
                        const sortedChildren = [...item.children].sort((a, b) => getModIndex(a, item.moduleSlug) - getModIndex(b, item.moduleSlug))
                        const visibleChildren = sortedChildren.filter(canSeeItem)
                        return visibleChildren.length ? { ...item, children: visibleChildren } : null
                    })
                    .filter((item): item is NavItem => item !== null && canSeeItem(item))
            }
        })
        .filter(group => group.items.length > 0)
})

const isRouteActive = (target?: string): boolean => {
    if (!target || target === '#') return false
    const path = router.currentRoute.value.path
    // Strip query/hash from the target so deep-links like
    // /crm/opportunities?stage=won still highlight when on /crm/opportunities.
    const targetPath = target.split(/[?#]/)[0]
    if (targetPath === '/settings' && path !== '/settings') return false
    if (path !== targetPath && !path.startsWith(targetPath + '/')) return false
    // If the target carries query params, require each to match the current
    // query so the "Won (Qualified)" sub-link only highlights when stage=won.
    if (target.includes('?')) {
        const targetQuery = new URLSearchParams(target.split('?')[1])
        const current = router.currentRoute.value.query
        for (const [k, v] of targetQuery.entries()) {
            const cv = current[k]
            const match = typeof cv === 'string' ? cv === v : (Array.isArray(cv) ? cv.includes(v) : false)
            if (!match) return false
        }
    }
    return true
}

const isGroupActive = (item: NavItem): boolean =>
    Boolean(item.children?.some(c => isRouteActive(c.route) || isGroupActive(c)))

const megaMenu = [
    { title: 'Dashboards', items: ['Ecommerce', 'Analytics', 'CRM', 'Finance', 'Projects'] },
    { title: 'Project Mgmt', items: ['Tasks', 'Kanban', 'Timeline', 'Backlog', 'Sprint'] },
    { title: 'User Mgmt', items: ['Directory', 'Roles', 'Permissions', 'Audit logs', 'Sessions'] }
]

const appsGrid = [
    { label: 'Figma', icon: 'ti-brand-figma', color: 'text-(--color-info)' },
    { label: 'Slack', icon: 'ti-brand-slack', color: 'text-(--color-warning)' },
    { label: 'GitHub', icon: 'ti-brand-github', color: 'text-(--text-heading)' },
    { label: 'Dropbox', icon: 'ti-brand-dropbox', color: 'text-(--color-info)' },
    { label: 'Trello', icon: 'ti-brand-trello', color: 'text-(--color-primary)' },
    { label: 'Asana', icon: 'ti-brand-asana', color: 'text-(--color-danger)' },
    { label: 'Notion', icon: 'ti-notebook', color: 'text-(--text-body)' },
    { label: 'Zoom', icon: 'ti-video', color: 'text-(--color-info)' },
    { label: 'Drive', icon: 'ti-cloud', color: 'text-(--color-success)' }
]

const iconWrapMap: Record<string, string> = {
    primary: 'badge-soft-primary',
    success: 'badge-soft-success',
    warning: 'badge-soft-warning',
    danger: 'badge-soft-danger',
    info: 'badge-soft-info'
}

const notifications = [
    { id: 1, icon: 'ti-key', title: 'Token rotated', detail: 'auth.refresh completed', time: 'Just now', tone: 'primary' },
    { id: 2, icon: 'ti-shield', title: 'Tenant schema synced', detail: 'isolation verification ok', time: '10m ago', tone: 'success' },
    { id: 3, icon: 'ti-alert-triangle', title: 'Stock alert', detail: 'SKU FC-31220 hit 0 units', time: '32m ago', tone: 'warning' },
    { id: 4, icon: 'ti-message-circle', title: 'New comment', detail: 'Sarah replied on Atlas', time: '1h ago', tone: 'info' }
]

const profileMenu = [
    { icon: 'ti-user', label: 'My profile' },
    { icon: 'ti-message', label: 'Chat messages' },
    { icon: 'ti-settings', label: 'Account settings' },
    { icon: 'ti-help', label: 'Support & FAQ' },
    { icon: 'ti-lock', label: 'Lock screen' }
]

const languages = [
    { code: 'en', label: 'English', flag: '🇺🇸' },
    { code: 'es', label: 'Español', flag: '🇪🇸' },
    { code: 'de', label: 'Deutsch', flag: '🇩🇪' },
    { code: 'fr', label: 'Français', flag: '🇫🇷' },
    { code: 'it', label: 'Italiano', flag: '🇮🇹' },
    { code: 'zh', label: '中文', flag: '🇨🇳' }
]
const activeLang = ref(languages[0])

const themeMode = ref<'light' | 'dark'>('light')
const applyTheme = (mode: 'light' | 'dark') => {
    const root = document.documentElement
    root.classList.add('no-transitions')
    if (mode === 'dark') root.setAttribute('data-bs-theme', 'dark')
    else root.removeAttribute('data-bs-theme')
    void root.offsetHeight
    setTimeout(() => root.classList.remove('no-transitions'), 20)
}
const toggleTheme = () => {
    themeMode.value = themeMode.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem('theme', themeMode.value)
    applyTheme(themeMode.value)
}

const initials = computed(() => {
    const name = authStore.user?.name || 'User'
    return name.split(' ').map(s => s[0]).slice(0, 2).join('').toUpperCase()
})
const firstName = computed(() => (authStore.user?.name || 'there').split(' ')[0])
const primaryRole = computed(() => authStore.user?.roles?.[0]?.name || 'Administrator')

// Friendly labels for known route slugs. Falls back to title-cased slug for
// anything missing (e.g. dynamic id segments stay readable enough).
const SLUG_LABELS: Record<string, string> = {
    dashboard: 'Dashboard',
    tasks: 'Tasks',
    products: 'Products',
    employees: 'Employees',
    departments: 'Departments',
    positions: 'Positions',
    leaves: 'Leave Requests',
    'leave-types': 'Leave Types',
    payroll: 'Payroll',
    vacancies: 'Vacancies',
    applications: 'Applications',
    candidates: 'Candidates',
    appraisals: 'Appraisals',
    users: 'User Directory',
    roles: 'Roles Matrix',
    new: 'New',
    settings: 'Settings',
    hrm: 'HRM',
    sales: 'Sales',
    customers: 'Customers',
    quotations: 'Quotations',
    orders: 'Sales Orders',
    invoices: 'Invoices',
    subscriptions: 'Subscriptions',
    finance: 'Finance',
    payments: 'Payments',
    estimates: 'Estimates',
    crm: 'CRM',
    leads: 'Leads',
    opportunities: 'Sales Pipeline',
    schedules: 'Schedules',
    contacts: 'B2B Contacts',
    activities: 'Interaction Timeline',
    approvals: 'eApprovals',
    forms: 'Forms Portal',
    leave: 'Leave Request',
    'requests': 'My Requests',
    review: 'Review Portal',
}

const titleize = (slug: string) =>
    slug.split('-').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(' ')

interface Crumb { label: string; to?: string }

// Detail pages can override the final breadcrumb segment (e.g. swap a raw
// UUID for the employee's name) via useBreadcrumbOverride().set(...).
// Nested pages (e.g. /customers/:id/edit) can additionally call setEntityName()
// to replace the UUID segment that sits before the final page segment.
const { value: breadcrumbOverride, entityName: breadcrumbEntityName } = useBreadcrumbOverride()

const breadcrumbItems = computed<Crumb[]>(() => {
    const path = router.currentRoute.value.path
    if (path === '/' || path === '') {
        return [{ label: 'Dashboard', to: '/dashboard' }]
    }

    const segments = path.split('/').filter(Boolean)
    const crumbs: Crumb[] = []

    // 1. Attempt to resolve the parent module category from navGroups
    let parentLabel: string | null = null
    for (const group of navGroups) {
        for (const item of group.items) {
            if (item.children) {
                for (const child of item.children) {
                    if (child.route === `/${segments[0]}` || child.route === path) {
                        parentLabel = item.label
                        break
                    }
                }
            }
            if (parentLabel) break
        }
        if (parentLabel) break
    }

    if (parentLabel) {
        crumbs.push({ label: parentLabel })
    }

    const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

    // 2. Build the breadcrumb trail based on URL segments
    let trail = ''
    segments.forEach((seg, i) => {
        trail += `/${seg}`
        const segLabel = SLUG_LABELS[seg] || titleize(seg)

        const isLast = i === segments.length - 1
        // For the last segment use the page-level override (highest priority),
        // then route meta, then the slug label.
        const lastLabel = isLast
            ? breadcrumbOverride.value
            || (router.currentRoute.value.meta.breadcrumb as string | undefined)
            || (router.currentRoute.value.meta.title as string | undefined)
            : undefined
        // For non-last UUID segments (e.g. /customers/:id/edit), replace with
        // the entity name set by the active page via setEntityName().
        const entityLabel = !isLast && UUID_RE.test(seg) && breadcrumbEntityName.value
            ? breadcrumbEntityName.value
            : undefined

        const finalLabel = lastLabel || entityLabel || segLabel

        // Skip the first segment when its label duplicates the parent-module
        // crumb we just pushed (e.g. /sales/customers — parent "Sales" already
        // covers the "sales" segment, so we don't want "Sales > Sales > …").
        if (i === 0 && parentLabel && finalLabel === parentLabel) return

        crumbs.push({
            label: finalLabel,
            to: trail
        })
    })
    return crumbs
})

const selectTenant = (handle: string) => {
    tenantStore.setTenantByHandle(handle)
    localStorage.setItem('tenant_handle', handle)
    open.tenant = false
    authStore.fetchProfile()
    // New tenant → re-fetch branding so primary color / logo refresh.
    tenantStore.syncBranding()
}

const comingSoon = (label: string) => {
    toast.info(
        `${label} module — coming soon`,
        `Connected on the backend (tenant: ${tenantStore.activeHandle}). UI assembly in progress.`,
    )
}

const handleLogout = async () => {
    await authStore.logout()
    router.push('/login')
}

onMounted(() => {
    const hostname = window.location.hostname
    const parts = hostname.split('.')
    const isIP = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(hostname)
    if (!isIP) {
        if (parts.length > 2 || (parts.length === 2 && parts[1] === 'localhost')) {
            const subdomain = parts[0].toLowerCase()
            if (subdomain !== 'www' && subdomain !== 'app' && subdomain !== 'dev') {
                hasSubdomain.value = true
                tenantStore.setTenantByHandle(subdomain)
            }
        }
    }

    tenantStore.initializeTenant()
    authStore.initializeAuth()
    loadModules()

    // Fetch pending approvals for badge
    if (authStore.isAuthenticated) {
        try {
            const { getRequests } = useApprovals()
            getRequests(1, 1, true).then(res => {
                if (res.data && res.pagination && res.pagination.total > 0) {
                    const appsGroup = navGroups.find(g => g.id === 'apps')
                    if (appsGroup) {
                        const eAppItem = appsGroup.items.find(i => i.label === 'eApprovals')
                        if (eAppItem && eAppItem.children) {
                            const reviewItem = eAppItem.children.find(c => c.label === 'Review Portal')
                            if (reviewItem) {
                                reviewItem.badge = res.pagination.total.toString()
                                reviewItem.badgeVariant = 'warning'
                            }
                        }
                    }
                }
            }).catch(() => { })
        } catch (e) {
            // ignore if useApprovals is not yet fully resolvable
        }
    }

    // Resolve theme in this priority order so navigating between modules
    // (each page mounts its own NuxtLayout) never silently flips back to
    // light: explicit user choice → already-applied attribute (set by app.vue
    // before this layout mounted) → system preference → light.
    const storedTheme = localStorage.getItem('theme') as 'light' | 'dark' | null
    const currentAttr = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : null
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : null
    const resolved: 'light' | 'dark' = storedTheme || currentAttr || prefersDark || 'light'
    themeMode.value = resolved
    applyTheme(resolved)

    // Recursively open parent groups if any of their children are active.
    const initializeOpenGroups = (items: NavItem[], parentLabel?: string) => {
        for (const i of items) {
            if (i.children) {
                if (isGroupActive(i)) {
                    const key = parentLabel ? `${parentLabel}-${i.label}` : i.label
                    openGroups[key] = true
                }
                initializeOpenGroups(i.children, i.label)
            }
        }
    }
    visibleNavGroups.value.forEach(g => initializeOpenGroups(g.items))

    if (!authStore.isAuthenticated) router.push('/login')
})
</script>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-body);
    transition: color 0.15s ease, background 0.15s ease;
}

.nav-link:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.nav-link-active {
    background: var(--color-primary-subtle);
    color: var(--color-primary) !important;
    font-weight: 600;
}

.nav-link-sub {
    padding: 0.4rem 0.5rem;
    font-size: 0.75rem;
}

.nav-link-disabled {
    opacity: 0.7;
}

.nav-link-disabled:hover {
    color: var(--text-heading);
}

.nav-icon {
    display: inline-flex;
    width: 1.25rem;
    justify-content: center;
    color: inherit;
    font-size: 1rem;
}

.topbar-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.25rem;
    min-width: 2.25rem;
    padding: 0 0.5rem;
    border-radius: 0.5rem;
    color: var(--text-body);
    transition: background 0.15s ease, color 0.15s ease;
    cursor: pointer;
}

.topbar-btn:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.profile-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.profile-item:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.popover {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: var(--shadow-lg);
    z-index: 60;
}

.popover-enter-active,
.popover-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.popover-enter-from,
.popover-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

.nav-skeleton {
    background: linear-gradient(90deg, var(--bg-muted) 25%, var(--border-color) 50%, var(--bg-muted) 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s ease infinite;
}

.nav-skeleton-row {
    pointer-events: none;
    opacity: 0.7;
}

@keyframes skeleton-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
