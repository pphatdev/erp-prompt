import { ref, computed } from 'vue'

export interface AppModule {
  id: string
  slug: string
  prefix: string
  name: string
  icon: string | null
  description: string | null
  route: string | null
  group: string
  sortOrder: number
  isActive: boolean
  isCore: boolean
  parentId: string | null
  children: AppModule[]
}

const _modules = ref<AppModule[]>([])
const _loaded = ref(false)
const _loading = ref(false)

/**
 * Loads and caches the accessible module list for the current tenant.
 * Modules are stored as DB rows so the seller's tenant returns all modules
 * while a customer's tenant returns only core + entitled modules.
 */
export const useModules = () => {
  const api = useApi()

  const load = async () => {
    if (_loaded.value || _loading.value) return
    _loading.value = true
    try {
      const res = await api.get<{ data: AppModule[] }>('modules')
      _modules.value = res.data
      _loaded.value = true
    } catch {
      // fail silently — sidebar falls back to showing all items
    } finally {
      _loading.value = false
    }
  }

  /** Flat set of all accessible module slugs (parents + children). */
  const accessibleSlugs = computed<Set<string>>(() => {
    const slugs = new Set<string>()
    const traverse = (mods: AppModule[]) => {
      for (const m of mods) {
        slugs.add(m.slug)
        if (m.children?.length) traverse(m.children)
      }
    }
    traverse(_modules.value)
    return slugs
  })

  /**
   * Returns true when:
   *  - modules have not loaded yet (fail-open so nothing is hidden on error)
   *  - OR the slug appears in the accessible set
   */
  const hasModule = (slug: string): boolean => {
    if (!_loaded.value) return true
    return accessibleSlugs.value.has(slug)
  }

  const reload = async () => {
    _loaded.value = false
    await load()
  }

  return { modules: _modules, load, reload, hasModule, loaded: _loaded, loading: _loading }
}
