# Sidebar — Task Tracker

Status: COMPLETED (2026-05-23)

## Checklist
- [x] `useModules` composable — module-level singleton (`_modules`, `_loaded`, `_loading` outside fn)
- [x] `hasModule(slug)` — fail-open (returns true when not yet loaded)
- [x] `reload()` function added
- [x] `loading: _loading` exported
- [x] `default.vue` — `modulesLoading` destructured from `useModules()`
- [x] `skeletonGroups` constant added to script (3 groups, varied widths)
- [x] Nav template wrapped in `v-if="modulesLoading"` skeleton / `v-else` real nav
- [x] `</template>` closing tag for v-else block (before `</nav>`)
- [x] `nav-skeleton` CSS class (shimmer animation using `--bg-muted`/`--border-color` tokens)
- [x] `nav-skeleton-row` CSS class (pointer-events: none, opacity 0.7)

## Key decisions
- Skeleton shows 14 fake nav rows (3 groups) to match approximate real nav shape
- Shimmer uses CSS token variables so it works in both light and dark themes automatically
- `skeletonGroups` is a plain const (not reactive) — static shape data
