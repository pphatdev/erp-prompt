import { defineStore } from 'pinia'
import { useEDocuments, type EDocsFolder } from '~/composables/useEDocuments'

interface BreadcrumbCrumb {
    id: string | null
    name: string
}

interface State {
    currentFolderId: string | null
    breadcrumbs: BreadcrumbCrumb[]
    foldersIndex: Record<string, EDocsFolder>
}

export const useEDocumentsStore = defineStore('edocuments', {
    state: (): State => ({
        currentFolderId: null,
        breadcrumbs: [{ id: null, name: 'Home' }],
        foldersIndex: {},
    }),

    actions: {
        async openFolder(folderId: string | null) {
            this.currentFolderId = folderId
            if (folderId === null) {
                this.breadcrumbs = [{ id: null, name: 'Home' }]
                return
            }

            const { getFolder } = useEDocuments()
            const { data } = await getFolder(folderId)
            this.foldersIndex[data.id] = data

            // Walk the ancestor chain to rebuild breadcrumbs. Bounded by the
            // tree depth so the loop terminates even on a corrupt parent FK.
            const chain: BreadcrumbCrumb[] = [{ id: data.id, name: data.name }]
            let parentId = data.parentId
            let safety = 0
            while (parentId && safety++ < 50) {
                if (this.foldersIndex[parentId]) {
                    const f = this.foldersIndex[parentId]
                    chain.unshift({ id: f.id, name: f.name })
                    parentId = f.parentId
                } else {
                    const { data: parent } = await getFolder(parentId)
                    this.foldersIndex[parent.id] = parent
                    chain.unshift({ id: parent.id, name: parent.name })
                    parentId = parent.parentId
                }
            }

            this.breadcrumbs = [{ id: null, name: 'Home' }, ...chain]
        },

        reset() {
            this.currentFolderId = null
            this.breadcrumbs = [{ id: null, name: 'Home' }]
            this.foldersIndex = {}
        },
    },
})
