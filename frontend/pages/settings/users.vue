<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">User directory</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Manage personnel access profiles and assigned roles.</p>
                </div>
                <button class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-user-plus" />Add employee
                </button>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-80">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="searchQuery" type="search" placeholder="Search by name or email..."
                        class="form-control pl-9" />
                </div>
                <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                    <button v-for="s in (['all', 'active', 'inactive'] as const)" :key="s"
                        class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                        :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                        @click="filterStatus = s">
                        {{ s }}
                    </button>
                </div>
            </section>

            <!-- Loading / empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Retrieving active profiles...</span>
            </div>

            <div v-else-if="filteredUsers.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No personnel profiles resolved</h4>
                <p class="text-xs text-(--text-muted) mt-1">Try adjusting filters, or register a new employee.</p>
            </div>

            <!-- User cards -->
            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="user in filteredUsers" :key="user.id"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-4 group relative overflow-hidden transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40">
                    
                    <!-- Glowing shape behind card -->
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="space-y-4 relative z-10 flex-1 flex flex-col">
                        <header class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div
                                    class="w-12 h-12 rounded-xl bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-semibold shrink-0 transition-transform duration-300 group-hover:scale-105">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        class="text-sm font-semibold text-(--text-heading) group-hover:text-(--color-primary) transition-colors truncate">
                                        {{ user.name }}</h3>
                                    <p class="text-xxs text-(--text-muted) truncate">{{ user.email }}</p>
                                </div>
                            </div>
                            <Badge :variant="user.is_active ? 'success' : 'danger'" :dot="true" class="shrink-0">
                                {{ user.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </header>

                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="role in user.roles" :key="role.id" variant="secondary">{{ role.name }}</Badge>
                            <span v-if="!user.roles?.length" class="text-xxs text-(--text-muted) italic">No assigned roles</span>
                        </div>
                    </div>

                    <footer class="mt-auto pt-3 border-t border-(--border-color)/50 flex justify-end gap-2 relative z-10">
                        <button class="btn btn-ghost text-xs" @click="openEditModal(user)">
                            <i class="ti ti-pencil" />Edit
                        </button>
                        <button class="btn btn-ghost text-xs text-(--color-warning)"
                            @click="openResetPasswordModal(user)" title="Reset password">
                            <i class="ti ti-key" />Password
                        </button>
                        <button
                            class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                            @click="deleteUserProfile(user.id)">
                            <i class="ti ti-user-off" />Terminate
                        </button>
                    </footer>
                </article>
            </section>

            <!-- Reset Password Modal -->
            <div v-if="showResetModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-sm p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3>Reset password</h3>
                            <p class="text-xxs text-(--text-muted) mt-0.5 truncate">{{ resetTarget?.name }}</p>
                        </div>
                        <button class="topbar-btn" @click="showResetModal = false"><i class="ti ti-x" /></button>
                    </header>

                    <form @submit.prevent="submitResetPassword" class="space-y-4">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">New
                                password</label>
                            <input v-model="resetForm.password" type="password" required minlength="8"
                                class="form-control" placeholder="Min. 8 characters" />
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Confirm
                                password</label>
                            <input v-model="resetForm.password_confirmation" type="password" required minlength="8"
                                class="form-control" placeholder="Repeat new password" />
                            <p v-if="resetForm.password && resetForm.password_confirmation && resetForm.password !== resetForm.password_confirmation"
                                class="text-xxs text-(--color-danger) mt-1">
                                Passwords do not match.
                            </p>
                        </div>

                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs"
                                @click="showResetModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs"
                                :disabled="!resetForm.password || resetForm.password !== resetForm.password_confirmation || resetForm.password.length < 8">
                                <i class="ti ti-key" />Set password
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Create / Edit Modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3>{{ editingUser ? 'Modify access profile' : 'Register new personnel' }}</h3>
                        <button class="topbar-btn" @click="showModal = false"><i class="ti ti-x" /></button>
                    </header>

                    <form @submit.prevent="saveUser" class="space-y-4">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Full
                                name</label>
                            <input v-model="form.name" type="text" required class="form-control" />
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Email
                                address</label>
                            <input v-model="form.email" type="email" required class="form-control" />
                        </div>
                        <div v-if="!editingUser">
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Security
                                password</label>
                            <input v-model="form.password" type="password" required class="form-control" />
                        </div>

                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Bind
                                authority roles</label>
                            <div
                                class="space-y-1 max-h-32 overflow-y-auto p-2 bg-(--bg-muted) border border-(--border-color) rounded-lg custom-scrollbar">
                                <label v-for="role in rolesList" :key="role.id"
                                    class="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-(--bg-card) cursor-pointer">
                                    <input type="checkbox" :value="role.id" v-model="form.role_ids"
                                        class="w-3.5 h-3.5 rounded border-(--border-color) text-(--color-primary)" />
                                    <span class="text-xs text-(--text-heading)">{{ role.name }}</span>
                                </label>
                            </div>
                        </div>

                        <label v-if="editingUser" class="flex items-center gap-2 py-2 text-xs text-(--text-heading)">
                            <input v-model="form.is_active" type="checkbox"
                                class="w-4 h-4 rounded border-(--border-color) text-(--color-primary)" />
                            Profile active
                        </label>

                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs"
                                @click="showModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs">
                                <i class="ti ti-device-floppy" />Commit changes
                            </button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

interface User { id: string; name: string; email: string; is_active: boolean; roles: { id: string; name: string }[] }
interface Role { id: string; name: string }

const api = useApi()
const toast = useToast()

const users = ref<User[]>([])
const rolesList = ref<Role[]>([])
const loading = ref(false)

const searchQuery = ref('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')

const showModal = ref(false)
const editingUser = ref<User | null>(null)
const form = ref({ name: '', email: '', password: '', role_ids: [] as string[], is_active: true })

const showResetModal = ref(false)
const resetTarget = ref<User | null>(null)
const resetForm = ref({ password: '', password_confirmation: '' })

const filteredUsers = computed(() => users.value.filter(u => {
    const matchSearch = u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchStatus = filterStatus.value === 'all' ||
        (filterStatus.value === 'active' && u.is_active) ||
        (filterStatus.value === 'inactive' && !u.is_active)
    return matchSearch && matchStatus
}))

const loadData = async () => {
    loading.value = true
    try {
        const [usersRes, rolesRes] = await Promise.all([api.get('/users'), api.get('/roles')])
        users.value = usersRes.data || usersRes
        rolesList.value = rolesRes.data || rolesRes
    } catch (err) {
        console.error('Failed to load user directory', err)
    } finally {
        loading.value = false
    }
}

const openCreateModal = () => {
    editingUser.value = null
    form.value = { name: '', email: '', password: '', role_ids: [], is_active: true }
    showModal.value = true
}

const openEditModal = (user: User) => {
    editingUser.value = user
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        role_ids: user.roles.map(r => r.id),
        is_active: user.is_active
    }
    showModal.value = true
}

const saveUser = async () => {
    try {
        if (editingUser.value) {
            const payload = {
                name: form.value.name,
                email: form.value.email,
                role_ids: form.value.role_ids,
                is_active: form.value.is_active
            }
            const updated = await api.put(`/users/${editingUser.value.id}`, payload)
            const idx = users.value.findIndex(u => u.id === editingUser.value?.id)
            if (idx !== -1) users.value[idx] = updated.data || updated
        } else {
            const created = await api.post('/users', form.value)
            users.value.push(created.data || created)
        }
        showModal.value = false
    } catch (err: any) {
        toast.error('Failed to save user.', err?.data?.message || 'Verify form inputs and try again.')
    }
}

const openResetPasswordModal = (user: User) => {
    resetTarget.value = user
    resetForm.value = { password: '', password_confirmation: '' }
    showResetModal.value = true
}

const submitResetPassword = async () => {
    if (!resetTarget.value) return
    try {
        await api.post(`/users/${resetTarget.value.id}/reset-password`, {
            password: resetForm.value.password,
            password_confirmation: resetForm.value.password_confirmation,
        })
        showResetModal.value = false
        toast.success('Password reset', `Password for ${resetTarget.value.name} has been updated.`)
    } catch (err: any) {
        toast.error('Reset failed.', err?.data?.message || 'Verify the password meets requirements.')
    }
}

const deleteUserProfile = async (id: string) => {
    if (!confirm('Terminate this access profile?')) return
    try {
        await api.delete(`/users/${id}`)
        users.value = users.value.filter(u => u.id !== id)
    } catch (err: any) {
        toast.error('Failed to delete user.', err?.data?.message)
    }
}

onMounted(loadData)
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

.topbar-btn:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}
</style>
