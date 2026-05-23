/**
 * Override the layout breadcrumb's final crumb with a dynamic value.
 *
 * Vue Router's route.meta is exposed via a shallowRef, so mutating
 * `meta.breadcrumb` after navigation does NOT re-trigger the layout's
 * breadcrumb computed. This composable wraps a Nuxt `useState` so detail
 * pages (e.g. /employees/:id) can set a human-readable label that the
 * default layout reads as the highest-priority source for the last segment.
 *
 * Usage in a detail page:
 *   const crumb = useBreadcrumbOverride()
 *   crumb.set(employee.fullName)         // after data loads
 *   onBeforeUnmount(() => crumb.clear()) // tidy up so the next route is clean
 *
 * For nested routes (e.g. /customers/:id/edit), use setEntityName() to replace
 * the UUID segment that precedes the final page segment:
 *   crumb.setEntityName(customer.name)   // replaces the UUID in the trail
 *   onBeforeUnmount(() => crumb.clear()) // clears both overrides
 */
export const useBreadcrumbOverride = () => {
    const value = useState<string | null>('breadcrumb-override', () => null)
    const entityName = useState<string | null>('breadcrumb-entity-name', () => null)
    return {
        value,
        set: (label: string) => { value.value = label },
        setEntityName: (name: string) => { entityName.value = name },
        clear: () => { value.value = null; entityName.value = null },
        entityName
    }
}
