<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Documents</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Central repository for company files, policies, and uploads.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary text-xs" @click="openFolderModal()">
                        <i class="ti ti-folder-plus" />New Folder
                    </button>
                    <button type="button" class="btn btn-primary text-xs" @click="openUploadModal()">
                        <i class="ti ti-upload" />Upload
                    </button>
                </div>
            </header>

            <!-- Breadcrumb -->
            <nav class="glass-card rounded-xl px-4 py-2 flex items-center gap-2 text-xs overflow-x-auto">
                <template v-for="(crumb, idx) in store.breadcrumbs" :key="crumb.id ?? 'home'">
                    <button type="button"
                        class="flex items-center gap-1 px-2 py-1 rounded hover:bg-(--bg-muted) text-(--text-heading)"
                        :class="idx === store.breadcrumbs.length - 1 ? 'font-semibold' : 'text-(--text-muted)'"
                        @click="navigateTo(crumb.id)">
                        <i v-if="crumb.id === null" class="ti ti-home text-xs" />
                        {{ crumb.name }}
                    </button>
                    <i v-if="idx < store.breadcrumbs.length - 1" class="ti ti-chevron-right text-xxs text-(--text-muted)" />
                </template>
            </nav>

            <!-- KPI Cards -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Folders</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                            <i class="ti ti-folder text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ folderCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">In this view</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Documents</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                            <i class="ti ti-file-text text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ documentTotalAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Total in folder</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">This Page</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                            <i class="ti ti-eye text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ documentsShownAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">of {{ documentTotal }} shown</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Size</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                            <i class="ti ti-database text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatBytes(totalSizeAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across visible files</p>
                </div>
            </section>

            <!-- Search -->
            <div class="glass-card rounded-xl p-3 flex items-center gap-2">
                <i class="ti ti-search text-(--text-muted)" />
                <input v-model="search" type="text" placeholder="Search documents by title or filename..."
                    class="flex-1 bg-transparent border-0 outline-none text-xs" @input="onSearchInput" />
                <button v-if="search" type="button" class="text-xxs text-(--text-muted) hover:text-(--text-heading)" @click="clearSearch">
                    Clear
                </button>
            </div>

            <!-- Sub-folders -->
            <section v-if="folders.length > 0" class="space-y-2">
                <h2 class="text-xxs uppercase font-bold tracking-widest text-(--text-muted)">Folders</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div v-for="folder in folders" :key="folder.id"
                        class="glass-card rounded-xl p-3 cursor-pointer hover:border-(--color-primary)/40 group relative"
                        @click="navigateTo(folder.id)">
                        <div class="flex items-start justify-between">
                            <i class="ti ti-folder text-2xl text-(--color-primary)" />
                            <button type="button" class="w-7 h-7 rounded-full hover:bg-(--bg-muted) flex items-center justify-center opacity-0 group-hover:opacity-100"
                                @click.stop="toggleKebab(`folder-${folder.id}`)">
                                <i class="ti ti-dots-vertical text-xs" />
                            </button>
                        </div>
                        <p class="text-xs font-semibold text-(--text-heading) mt-2 truncate" :title="folder.name">{{ folder.name }}</p>
                        <p class="text-xxs text-(--text-muted)">{{ folder.documentsCount ?? 0 }} files · {{ folder.childrenCount ?? 0 }} subfolders</p>

                        <!-- Folder kebab dropdown -->
                        <div v-if="openKebab === `folder-${folder.id}`"
                            class="absolute right-2 top-10 bg-(--bg-card) border border-(--border-color) rounded-lg shadow-(--shadow-lg) py-1 w-40 z-20"
                            @click.stop>
                            <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="navigateTo(folder.id); openKebab = null">
                                <i class="ti ti-eye" />Open
                            </button>
                            <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="openRenameFolder(folder)">
                                <i class="ti ti-pencil" />Rename
                            </button>
                            <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted) text-(--color-danger)" @click="confirmDeleteFolder(folder)">
                                <i class="ti ti-trash" />Delete
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Documents Table -->
            <section class="glass-card rounded-2xl overflow-hidden">
                <div v-if="loading" class="py-16 flex flex-col items-center gap-3">
                    <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    <span class="text-xs text-(--text-muted)">Loading...</span>
                </div>

                <div v-else-if="documents.length === 0 && folders.length === 0" class="py-20 text-center">
                    <i class="ti ti-file-text text-4xl text-(--text-muted)" />
                    <h4 class="text-sm font-semibold text-(--text-heading) mt-3">This folder is empty</h4>
                    <p class="text-xs text-(--text-muted) mt-1">Upload a file or create a subfolder to get started.</p>
                </div>

                <table v-else class="w-full text-xs">
                    <thead class="bg-(--bg-muted) text-(--text-muted) uppercase text-xxs tracking-widest">
                        <tr>
                            <th class="text-left px-4 py-2.5">Name</th>
                            <th class="text-left px-4 py-2.5 hidden md:table-cell">Type</th>
                            <th class="text-right px-4 py-2.5 hidden sm:table-cell">Size</th>
                            <th class="text-left px-4 py-2.5 hidden lg:table-cell">Uploader</th>
                            <th class="text-left px-4 py-2.5 hidden lg:table-cell">Uploaded</th>
                            <th class="px-4 py-2.5 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in documents" :key="doc.id" class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                            <td class="px-4 py-2.5">
                                <button type="button" class="flex items-center gap-2 text-(--text-heading) font-medium hover:text-(--color-primary)"
                                    @click="previewDocument(doc)">
                                    <i :class="['ti', mimeIcon(doc.mimeType)]" />
                                    <span class="truncate max-w-xs" :title="doc.title">{{ doc.title }}</span>
                                </button>
                                <p class="text-xxs text-(--text-muted) ml-6 truncate max-w-xs" :title="doc.filename">{{ doc.filename }}</p>
                            </td>
                            <td class="px-4 py-2.5 hidden md:table-cell text-(--text-muted)">{{ shortMime(doc.mimeType) }}</td>
                            <td class="px-4 py-2.5 hidden sm:table-cell text-right text-(--text-muted)">{{ formatBytes(doc.sizeBytes) }}</td>
                            <td class="px-4 py-2.5 hidden lg:table-cell text-(--text-muted)">{{ doc.uploader?.name ?? '—' }}</td>
                            <td class="px-4 py-2.5 hidden lg:table-cell text-(--text-muted)">{{ formatDate(doc.createdAt) }}</td>
                            <td class="px-4 py-2.5 text-right relative">
                                <button type="button" class="w-[30px] h-[30px] rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                                    @click.stop="toggleKebab(`doc-${doc.id}`)">
                                    <i class="ti ti-dots-vertical text-xs" />
                                </button>
                                <div v-if="openKebab === `doc-${doc.id}`"
                                    class="absolute right-4 top-10 bg-(--bg-card) border border-(--border-color) rounded-lg shadow-(--shadow-lg) py-1 w-44 z-20 text-left"
                                    @click.stop>
                                    <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="previewDocument(doc)">
                                        <i class="ti ti-eye" />Preview
                                    </button>
                                    <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="downloadDoc(doc)">
                                        <i class="ti ti-download" />Download
                                    </button>
                                    <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="openRenameDoc(doc)">
                                        <i class="ti ti-pencil" />Rename
                                    </button>
                                    <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted)" @click="openShareModal(doc)">
                                        <i class="ti ti-link" />Share
                                    </button>
                                    <button type="button" class="w-full text-left px-3 py-1.5 text-xs flex items-center gap-2 hover:bg-(--bg-muted) text-(--color-danger)" @click="confirmDeleteDoc(doc)">
                                        <i class="ti ti-trash" />Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <footer v-if="totalPages > 1" class="px-4 py-3 border-t border-(--border-color) flex items-center justify-between text-xxs">
                    <span class="text-(--text-muted)">Page {{ page }} of {{ totalPages }} · {{ documentTotal }} files</span>
                    <div class="flex gap-1">
                        <button type="button" class="btn btn-ghost text-xxs" :disabled="page <= 1" @click="goToPage(page - 1)">
                            <i class="ti ti-chevron-left" />Prev
                        </button>
                        <button type="button" class="btn btn-ghost text-xxs" :disabled="page >= totalPages" @click="goToPage(page + 1)">
                            Next<i class="ti ti-chevron-right" />
                        </button>
                    </div>
                </footer>
            </section>
        </div>

        <!-- New / Rename Folder Modal -->
        <div v-if="folderModal.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ folderModal.editId ? 'Rename Folder' : 'New Folder' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="folderModal.show = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveFolder">
                    <div class="p-5 space-y-3">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="folderModal.name" type="text" required maxlength="255" placeholder="e.g. HR Policies"
                                class="form-control text-xs" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="folderModal.show = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="folderModal.saving">
                            <i v-if="folderModal.saving" class="ti ti-loader animate-spin" /><i v-else class="ti ti-check" />
                            Save
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="uploadModal.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Upload File</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="closeUpload">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="submitUpload">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Title (optional)</label>
                            <input v-model="uploadModal.title" type="text" maxlength="255" placeholder="Defaults to filename"
                                class="form-control text-xs" />
                        </div>

                        <label
                            class="block border-2 border-dashed border-(--border-color) rounded-xl p-8 text-center cursor-pointer hover:border-(--color-primary)/60 transition"
                            :class="{ 'border-(--color-primary) bg-(--color-primary)/5': uploadModal.dragging }"
                            @dragover.prevent="uploadModal.dragging = true"
                            @dragleave.prevent="uploadModal.dragging = false"
                            @drop.prevent="onDropFile">
                            <i class="ti ti-cloud-upload text-3xl text-(--color-primary)" />
                            <p v-if="!uploadModal.file" class="text-xs text-(--text-muted) mt-2">Drag and drop a file here, or click to browse.</p>
                            <p v-else class="text-xs text-(--text-heading) font-semibold mt-2 break-all">{{ uploadModal.file.name }} ({{ formatBytes(uploadModal.file.size) }})</p>
                            <input ref="fileInputRef" type="file" class="hidden" @change="onFilePicked" />
                        </label>

                        <p v-if="uploadModal.error" class="text-xxs text-(--color-danger)">{{ uploadModal.error }}</p>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="closeUpload">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!uploadModal.file || uploadModal.uploading">
                            <i v-if="uploadModal.uploading" class="ti ti-loader animate-spin" /><i v-else class="ti ti-upload" />
                            Upload
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Rename Document Modal -->
        <div v-if="renameModal.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Rename Document</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="renameModal.show = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveRenameDoc">
                    <div class="p-5 space-y-3">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Title *</label>
                            <input v-model="renameModal.title" type="text" required maxlength="255" class="form-control text-xs" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="renameModal.show = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="renameModal.saving">
                            <i v-if="renameModal.saving" class="ti ti-loader animate-spin" /><i v-else class="ti ti-check" />
                            Save
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Share Modal -->
        <div v-if="shareModal.show" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Share Link</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="shareModal.show = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-4">
                    <p class="text-xs text-(--text-muted)">Generate an expiring public link for <strong class="text-(--text-heading)">{{ shareModal.doc?.title }}</strong>.</p>
                    <form @submit.prevent="generateShare" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expires At</label>
                                <input v-model="shareModal.expiresAt" type="datetime-local" class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Max Downloads</label>
                                <input v-model.number="shareModal.maxDownloads" type="number" min="1" max="10000" placeholder="Unlimited" class="form-control text-xs" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Password (optional)</label>
                            <input v-model="shareModal.password" type="text" minlength="4" maxlength="128" placeholder="Leave blank for no password" class="form-control text-xs" />
                        </div>
                        <button type="submit" class="btn btn-primary text-xs w-full" :disabled="shareModal.creating">
                            <i v-if="shareModal.creating" class="ti ti-loader animate-spin" /><i v-else class="ti ti-plus" />
                            Create Link
                        </button>
                    </form>

                    <div v-if="shareModal.links.length > 0" class="space-y-2">
                        <h4 class="text-xxs uppercase font-bold tracking-widest text-(--text-muted)">Active Links</h4>
                        <div v-for="link in shareModal.links" :key="link.id" class="border border-(--border-color) rounded-lg p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <input :value="publicUrl(link.token)" readonly
                                    class="form-control text-xxs font-mono flex-1 bg-(--bg-muted)" @focus="selectAll($event)" />
                                <button type="button" class="btn btn-secondary text-xxs" @click="copyLink(link.token)">
                                    <i class="ti ti-copy" />Copy
                                </button>
                                <button type="button" class="w-8 h-8 rounded hover:bg-(--bg-muted) flex items-center justify-center text-(--color-danger)" @click="revokeLink(link)">
                                    <i class="ti ti-trash" />
                                </button>
                            </div>
                            <p class="text-xxs text-(--text-muted)">
                                {{ link.hasPassword ? 'Password-protected · ' : '' }}
                                <span v-if="link.expiresAt">Expires {{ formatDate(link.expiresAt) }}</span>
                                <span v-else>No expiry</span>
                                <span v-if="link.maxDownloads !== null"> · {{ link.downloadsCount }}/{{ link.maxDownloads }} downloads</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div v-if="previewModal.show" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
            <div class="bg-(--bg-card) rounded-2xl w-full max-w-4xl h-[85vh] flex flex-col shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-4 border-b border-(--border-color)">
                    <div class="flex items-center gap-3">
                        <i :class="['ti', mimeIcon(previewModal.doc?.mimeType ?? '')]" class="text-lg" />
                        <h3 class="font-semibold text-sm truncate" :title="previewModal.doc?.title">{{ previewModal.doc?.title }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="previewModal.doc" type="button" class="btn btn-secondary text-xxs" @click="downloadDoc(previewModal.doc)">
                            <i class="ti ti-download" />Download
                        </button>
                        <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="closePreview">
                            <i class="ti ti-x" />
                        </button>
                    </div>
                </header>
                <div class="flex-1 overflow-auto bg-(--bg-muted)/40 flex items-center justify-center">
                    <iframe v-if="previewable && previewModal.objectUrl" :src="previewModal.objectUrl" class="w-full h-full bg-white"></iframe>
                    <img v-else-if="isImage && previewModal.objectUrl" :src="previewModal.objectUrl" class="max-w-full max-h-full object-contain" :alt="previewModal.doc?.title" />
                    <div v-else class="p-12 text-center">
                        <i class="ti ti-file-text text-5xl text-(--text-muted)" />
                        <p class="text-xs text-(--text-muted) mt-3">Preview not available for this file type.</p>
                        <button v-if="previewModal.doc" type="button" class="btn btn-primary text-xs mt-3 inline-flex" @click="downloadDoc(previewModal.doc)">
                            <i class="ti ti-download" />Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useEDocuments, type EDocsDocument, type EDocsFolder, type EDocsShare } from '~/composables/useEDocuments'
import { useEDocumentsStore } from '~/stores/edocuments'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'

const api = useEDocuments()
const store = useEDocumentsStore()
const toast = useToast()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const runtime = useRuntimeConfig()

const loading = ref(false)
const folders = ref<EDocsFolder[]>([])
const documents = ref<EDocsDocument[]>([])
const folderCount = ref(0)
const documentTotal = ref(0)
const totalSize = ref(0)

// Animated counters for the KPI cards — RAF-driven ease-out cubic with
// prefers-reduced-motion respected.
const folderCountAnim = useCountUp(() => folderCount.value)
const documentTotalAnim = useCountUp(() => documentTotal.value)
const documentsShownAnim = useCountUp(() => documents.value.length)
const totalSizeAnim = useCountUp(() => totalSize.value)
const page = ref(1)
const totalPages = ref(1)
const search = ref('')
let searchDebounce: ReturnType<typeof setTimeout> | null = null

const openKebab = ref<string | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

interface FolderModalState { show: boolean; name: string; editId: string | null; saving: boolean }
const folderModal = reactive<FolderModalState>({ show: false, name: '', editId: null, saving: false })

interface UploadModalState { show: boolean; title: string; file: File | null; dragging: boolean; uploading: boolean; error: string }
const uploadModal = reactive<UploadModalState>({ show: false, title: '', file: null, dragging: false, uploading: false, error: '' })

interface RenameModalState { show: boolean; id: string | null; title: string; saving: boolean }
const renameModal = reactive<RenameModalState>({ show: false, id: null, title: '', saving: false })

interface ShareModalState {
    show: boolean
    doc: EDocsDocument | null
    expiresAt: string
    password: string
    maxDownloads: number | null
    links: EDocsShare[]
    creating: boolean
}
const shareModal = reactive<ShareModalState>({
    show: false, doc: null, expiresAt: '', password: '', maxDownloads: null, links: [], creating: false,
})

interface PreviewModalState { show: boolean; doc: EDocsDocument | null; objectUrl: string | null }
const previewModal = reactive<PreviewModalState>({ show: false, doc: null, objectUrl: null })

const previewable = computed(() => previewModal.doc?.mimeType === 'application/pdf')
const isImage = computed(() => previewModal.doc?.mimeType?.startsWith('image/') ?? false)

// Page-wide click handler closes any open kebab dropdown.
const handleDocumentClick = () => { openKebab.value = null }
onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    refresh()
})
onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    if (previewModal.objectUrl) URL.revokeObjectURL(previewModal.objectUrl)
})

watch(() => store.currentFolderId, () => {
    page.value = 1
    refresh()
})

const toggleKebab = (key: string) => {
    openKebab.value = openKebab.value === key ? null : key
}

const refresh = async () => {
    loading.value = true
    try {
        const folderRes = await api.getFolders({
            parentId: store.currentFolderId,
            limit: 50,
        })
        folders.value = folderRes.data
        folderCount.value = folderRes.pagination.total

        const docParams: Record<string, unknown> = {
            page: page.value,
            limit: 20,
            search: search.value || undefined,
        }
        if (store.currentFolderId) docParams.folderId = store.currentFolderId
        else docParams.rootOnly = 1

        const docRes = await api.getDocuments(docParams)
        documents.value = docRes.data
        documentTotal.value = docRes.pagination.total
        totalPages.value = docRes.pagination.totalPages
        totalSize.value = docRes.data.reduce((sum, d) => sum + d.sizeBytes, 0)
    } catch (err: any) {
        toast.error('Failed to load documents', err?.data?.message ?? err.message)
    } finally {
        loading.value = false
    }
}

const navigateTo = async (folderId: string | null) => {
    openKebab.value = null
    await store.openFolder(folderId)
}

const goToPage = (target: number) => {
    if (target < 1 || target > totalPages.value) return
    page.value = target
    refresh()
}

const onSearchInput = () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        page.value = 1
        refresh()
    }, 300)
}

const clearSearch = () => {
    search.value = ''
    page.value = 1
    refresh()
}

// Folder operations -----------------------------------------------------
const openFolderModal = () => {
    folderModal.show = true
    folderModal.editId = null
    folderModal.name = ''
}
const openRenameFolder = (folder: EDocsFolder) => {
    openKebab.value = null
    folderModal.show = true
    folderModal.editId = folder.id
    folderModal.name = folder.name
}
const saveFolder = async () => {
    folderModal.saving = true
    try {
        if (folderModal.editId) {
            await api.renameFolder(folderModal.editId, folderModal.name)
            toast.success('Folder renamed')
        } else {
            await api.createFolder({ name: folderModal.name, parent_id: store.currentFolderId })
            toast.success('Folder created')
        }
        folderModal.show = false
        await refresh()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message ?? err.message)
    } finally {
        folderModal.saving = false
    }
}
const confirmDeleteFolder = async (folder: EDocsFolder) => {
    openKebab.value = null
    const ok = await toast.confirm({
        title: `Delete "${folder.name}"?`,
        description: 'Empty folders only — folders containing files or subfolders are rejected.',
        color: 'danger',
        confirmLabel: 'Delete',
    })
    if (!ok) return
    try {
        await api.deleteFolder(folder.id)
        toast.success('Folder deleted')
        await refresh()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message ?? err.message)
    }
}

// Upload ---------------------------------------------------------------
const openUploadModal = () => {
    uploadModal.show = true
    uploadModal.title = ''
    uploadModal.file = null
    uploadModal.error = ''
}
const closeUpload = () => {
    uploadModal.show = false
    uploadModal.file = null
}
const onFilePicked = (e: Event) => {
    const target = e.target as HTMLInputElement
    uploadModal.file = target.files?.[0] ?? null
}
const onDropFile = (e: DragEvent) => {
    uploadModal.dragging = false
    const file = e.dataTransfer?.files?.[0]
    if (file) uploadModal.file = file
}
const submitUpload = async () => {
    if (!uploadModal.file) return
    uploadModal.error = ''
    uploadModal.uploading = true
    try {
        const fd = new FormData()
        fd.append('file', uploadModal.file)
        if (uploadModal.title) fd.append('title', uploadModal.title)
        if (store.currentFolderId) fd.append('folder_id', store.currentFolderId)
        await api.uploadDocument(fd)
        toast.success('Upload complete')
        closeUpload()
        await refresh()
    } catch (err: any) {
        uploadModal.error = err?.data?.message ?? err.message ?? 'Upload failed'
    } finally {
        uploadModal.uploading = false
    }
}

// Document rename / delete ----------------------------------------------
const openRenameDoc = (doc: EDocsDocument) => {
    openKebab.value = null
    renameModal.show = true
    renameModal.id = doc.id
    renameModal.title = doc.title
}
const saveRenameDoc = async () => {
    if (!renameModal.id) return
    renameModal.saving = true
    try {
        await api.updateDocument(renameModal.id, { title: renameModal.title })
        toast.success('Renamed')
        renameModal.show = false
        await refresh()
    } catch (err: any) {
        toast.error('Rename failed', err?.data?.message ?? err.message)
    } finally {
        renameModal.saving = false
    }
}
const confirmDeleteDoc = async (doc: EDocsDocument) => {
    openKebab.value = null
    const ok = await toast.confirm({
        title: `Delete "${doc.title}"?`,
        description: 'The file and all its version history will be removed from storage.',
        color: 'danger',
        confirmLabel: 'Delete',
    })
    if (!ok) return
    try {
        await api.deleteDocument(doc.id)
        toast.success('Document deleted')
        await refresh()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message ?? err.message)
    }
}

// Share ----------------------------------------------------------------
const openShareModal = async (doc: EDocsDocument) => {
    openKebab.value = null
    shareModal.show = true
    shareModal.doc = doc
    shareModal.expiresAt = ''
    shareModal.password = ''
    shareModal.maxDownloads = null
    try {
        const res = await api.getShares(doc.id)
        shareModal.links = res.data
    } catch (err: any) {
        toast.error('Could not load existing share links', err?.data?.message ?? err.message)
    }
}
const generateShare = async () => {
    if (!shareModal.doc) return
    shareModal.creating = true
    try {
        const payload: { expires_at?: string; password?: string; max_downloads?: number } = {}
        if (shareModal.expiresAt) payload.expires_at = shareModal.expiresAt
        if (shareModal.password) payload.password = shareModal.password
        if (shareModal.maxDownloads) payload.max_downloads = shareModal.maxDownloads

        const res = await api.createShare(shareModal.doc.id, payload)
        shareModal.links = [res.data, ...shareModal.links]
        toast.success('Share link created')
        shareModal.expiresAt = ''
        shareModal.password = ''
        shareModal.maxDownloads = null
    } catch (err: any) {
        toast.error('Share failed', err?.data?.message ?? err.message)
    } finally {
        shareModal.creating = false
    }
}
const revokeLink = async (link: EDocsShare) => {
    const ok = await toast.confirm({ title: 'Revoke this link?', color: 'danger', confirmLabel: 'Revoke' })
    if (!ok) return
    try {
        await api.revokeShare(link.id)
        shareModal.links = shareModal.links.filter(l => l.id !== link.id)
        toast.success('Link revoked')
    } catch (err: any) {
        toast.error('Revoke failed', err?.data?.message ?? err.message)
    }
}
const publicUrl = (token: string) => {
    // Recipients land on the no-layout /share/:token page which calls the
    // public API and threads the tenant handle through the X-Tenant-Handle
    // header. The tenant slug travels in the query string so the public page
    // knows which backend tenant to talk to.
    if (typeof window === 'undefined') return ''
    return `${window.location.origin}/share/${token}?tenant=${tenantStore.activeHandle}`
}
const copyLink = async (token: string) => {
    try {
        await navigator.clipboard.writeText(publicUrl(token))
        toast.success('Link copied')
    } catch {
        toast.error('Copy failed')
    }
}

// Preview --------------------------------------------------------------
const previewDocument = async (doc: EDocsDocument) => {
    openKebab.value = null
    previewModal.show = true
    previewModal.doc = doc
    previewModal.objectUrl = null

    try {
        const url = `${runtime.public.apiBase}/documents/${doc.id}/download`
        const res = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${authStore.accessToken}`,
                'X-Tenant-Handle': tenantStore.activeHandle,
                'Accept': '*/*',
            },
        })
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const blob = await res.blob()
        previewModal.objectUrl = URL.createObjectURL(blob)
    } catch (err: any) {
        toast.error('Preview failed', err?.message)
    }
}
const closePreview = () => {
    if (previewModal.objectUrl) URL.revokeObjectURL(previewModal.objectUrl)
    previewModal.show = false
    previewModal.doc = null
    previewModal.objectUrl = null
}

// Downloads — the API requires Bearer + X-Tenant-Handle headers which
// can't ride on a plain <a href>. We fetch with auth, build a blob URL,
// and trigger a synthetic anchor click so the browser names the file.
const downloadDoc = async (doc: EDocsDocument) => {
    openKebab.value = null
    try {
        const url = `${runtime.public.apiBase}/documents/${doc.id}/download`
        const res = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${authStore.accessToken}`,
                'X-Tenant-Handle': tenantStore.activeHandle,
            },
        })
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const blob = await res.blob()
        const blobUrl = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = blobUrl
        a.download = doc.filename
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000)
    } catch (err: any) {
        toast.error('Download failed', err?.message)
    }
}

const selectAll = (e: FocusEvent) => {
    const target = e.target as HTMLInputElement | null
    target?.select()
}

// Formatting helpers ---------------------------------------------------
const formatBytes = (bytes: number): string => {
    if (!bytes || bytes < 0) return '—'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let value = bytes
    let unitIdx = 0
    while (value >= 1024 && unitIdx < units.length - 1) {
        value /= 1024
        unitIdx++
    }
    return `${value.toFixed(unitIdx === 0 ? 0 : 1)} ${units[unitIdx]}`
}

const formatDate = (iso: string | null): string => {
    if (!iso) return '—'
    try {
        return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' })
    } catch {
        return iso
    }
}

const mimeIcon = (mime: string): string => {
    if (!mime) return 'ti-file'
    if (mime === 'application/pdf') return 'ti-file-type-pdf'
    if (mime.startsWith('image/')) return 'ti-photo'
    if (mime.startsWith('video/')) return 'ti-video'
    if (mime.startsWith('audio/')) return 'ti-music'
    if (mime.includes('spreadsheet') || mime.includes('excel')) return 'ti-file-spreadsheet'
    if (mime.includes('word')) return 'ti-file-type-doc'
    if (mime.includes('zip') || mime.includes('compressed')) return 'ti-file-zip'
    return 'ti-file-text'
}

const shortMime = (mime: string): string => {
    if (!mime) return '—'
    const parts = mime.split('/')
    return parts[1] ?? parts[0]
}

definePageMeta({ breadcrumb: 'eDocuments' })
</script>
