import { useApi } from './useApi'

export interface EDocsTag {
    id: string
    name: string
    slug: string
    documentsCount?: number
    createdAt: string | null
    updatedAt: string | null
}

export interface EDocsUploaderRef {
    id: string
    name: string
    email: string
}

export interface EDocsFolderRef {
    id: string
    name: string
    parentId: string | null
}

export interface EDocsDocument {
    id: string
    title: string
    filename: string
    mimeType: string
    sizeBytes: number
    folderId: string | null
    uploaderId: string | null
    documentableType: string | null
    documentableId: string | null
    uploader?: EDocsUploaderRef | null
    folder?: EDocsFolderRef | null
    tags?: EDocsTag[]
    versionsCount?: number
    createdAt: string | null
    updatedAt: string | null
}

export interface EDocsFolder {
    id: string
    name: string
    parentId: string | null
    childrenCount?: number
    documentsCount?: number
    children?: EDocsFolder[]
    documents?: EDocsDocument[]
    createdAt: string | null
    updatedAt: string | null
}

export interface EDocsShare {
    id: string
    documentId: string
    token: string
    expiresAt: string | null
    hasPassword: boolean
    maxDownloads: number | null
    downloadsCount: number
    createdBy: string | null
    createdAt: string | null
}

export interface EDocsVersion {
    id: string
    documentId: string
    versionNumber: number
    filename: string
    mimeType: string
    sizeBytes: number
    changeSummary: string | null
    uploadedById: string | null
    uploader?: EDocsUploaderRef | null
    createdAt: string | null
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (params: Record<string, unknown>): string => {
    const entries: [string, string][] = []
    for (const [k, v] of Object.entries(params)) {
        if (v === '' || v === null || v === undefined) continue
        if (Array.isArray(v)) {
            for (const item of v) entries.push([`${k}[]`, String(item)])
        } else {
            entries.push([k, String(v)])
        }
    }
    if (entries.length === 0) return ''
    return `?${new URLSearchParams(entries).toString()}`
}

export const useEDocuments = () => {
    const api = useApi()

    // Folders ---------------------------------------------------------------
    const getFolders = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<EDocsFolder>>(`/folders${buildQuery(params)}`)

    const getFolder = (id: string) =>
        api.get<{ data: EDocsFolder }>(`/folders/${id}`)

    const createFolder = (payload: { name: string; parent_id?: string | null }) =>
        api.post<{ data: EDocsFolder }>('/folders', payload)

    const renameFolder = (id: string, name: string) =>
        api.put<{ data: EDocsFolder }>(`/folders/${id}`, { name })

    const moveFolder = (id: string, parentId: string | null) =>
        api.patch<{ data: EDocsFolder }>(`/folders/${id}/move`, { parent_id: parentId })

    const deleteFolder = (id: string, force = false) =>
        api.delete<void>(`/folders/${id}${force ? '?force=1' : ''}`)

    // Documents -------------------------------------------------------------
    const getDocuments = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<EDocsDocument>>(`/documents${buildQuery(params)}`)

    const getDocument = (id: string) =>
        api.get<{ data: EDocsDocument }>(`/documents/${id}`)

    const uploadDocument = (formData: FormData) =>
        api.post<{ data: EDocsDocument }>('/documents', formData)

    const updateDocument = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: EDocsDocument }>(`/documents/${id}`, payload)

    const moveDocument = (id: string, folderId: string | null) =>
        api.patch<{ data: EDocsDocument }>(`/documents/${id}/move`, { folder_id: folderId })

    const deleteDocument = (id: string) =>
        api.delete<void>(`/documents/${id}`)

    const downloadUrl = (id: string): string => {
        const config = useRuntimeConfig()
        return `${config.public.apiBase}/documents/${id}/download`
    }

    // Versions --------------------------------------------------------------
    const getVersions = (documentId: string) =>
        api.get<{ data: EDocsVersion[] }>(`/documents/${documentId}/versions`)

    const createVersion = (documentId: string, formData: FormData) =>
        api.post<{ data: EDocsVersion }>(`/documents/${documentId}/versions`, formData)

    // Shares ----------------------------------------------------------------
    const getShares = (documentId: string) =>
        api.get<{ data: EDocsShare[] }>(`/documents/${documentId}/shares`)

    const createShare = (documentId: string, payload: { expires_at?: string; password?: string; max_downloads?: number }) =>
        api.post<{ data: EDocsShare }>(`/documents/${documentId}/shares`, payload)

    const revokeShare = (shareId: string) =>
        api.delete<void>(`/document-shares/${shareId}`)

    const publicShareUrl = (token: string): string => {
        const config = useRuntimeConfig()
        return `${config.public.apiBase}/public/shares/${token}`
    }

    // Tags ------------------------------------------------------------------
    const getTags = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<EDocsTag>>(`/document-tags${buildQuery(params)}`)

    const createTag = (payload: { name: string; slug?: string }) =>
        api.post<{ data: EDocsTag }>('/document-tags', payload)

    const updateTag = (id: string, payload: { name?: string; slug?: string }) =>
        api.put<{ data: EDocsTag }>(`/document-tags/${id}`, payload)

    const deleteTag = (id: string) =>
        api.delete<void>(`/document-tags/${id}`)

    return {
        // folders
        getFolders, getFolder, createFolder, renameFolder, moveFolder, deleteFolder,
        // documents
        getDocuments, getDocument, uploadDocument, updateDocument, moveDocument, deleteDocument, downloadUrl,
        // versions
        getVersions, createVersion,
        // shares
        getShares, createShare, revokeShare, publicShareUrl,
        // tags
        getTags, createTag, updateTag, deleteTag,
    }
}
