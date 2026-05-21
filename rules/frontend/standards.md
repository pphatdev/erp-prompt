# Skill: Frontend Architecture & Design Standards

## Context
Use this skill when developing components, pages, or state management in the Nuxt 3 frontend project. Adhering to these standards ensures a premium, high-performance UI that integrates seamlessly with the multi-tenant backend.

## Guidelines

### 1. Framework & Core Tools
- **Framework**: Nuxt 3 (Latest stable version).
- **TypeScript**: Mandatory strict mode. All props, emits, and composables must be typed.
- **Styling**: Tailwind CSS 4+ (Latest). Use utility-first approach with customized design tokens.
- **UI System**: PrimeVue for complex components (DataTables, Calendars, Dialogs).

### 2. Component Design (Atomic)
- **Base Components**: Wrap PrimeVue components to match the project's premium design language.
- **Props & Emits**:
  ```typescript
  interface Props {
    title: string;
    loading?: boolean;
  }
  const props = withDefaults(defineProps<Props>(), {
    loading: false
  });
  ```
- **Templates**: Keep templates clean. Move complex logic to the `<script setup>` or composables.

### 3. API Integration
- **Tenant Context**: All API requests must include the `X-Tenant-ID` header or use a tenant-scoped subdomain.
- **Data Fetching**: Use Nuxt's `useFetch` or `useAsyncData` with proper error handling.
- **Error Handling**: Implement global error interceptors to handle 401 (Auth) and 403 (Tenant Access) errors.

### 4. State Management (Pinia)
- Use Pinia for global state (User info, Tenant config, Shared settings).
- Avoid putting large datasets in global state; use local state or Nuxt's data fetching instead.

### 5. Date & Time Formatting (P2)
- **Single source of truth**: All datetime/date renders MUST go through `~/composables/useDateFormat.ts`. Do NOT call `Date#toLocaleString`, `Date#toLocaleDateString`, or hand-roll formatters in pages/components.
- **Canonical shapes**:
  - `formatDateTime(input)` → `"21 May 2026 03:45 PM"` (day month-name year, 12-hour clock with AM/PM).
  - `formatDate(input)` → `"21 May 2026"` (date only).
- **Inputs**: accepts ISO strings, `Date` objects, numbers, `null`, or `undefined`. Invalid/empty input returns the em-dash placeholder (`—`) so callers don't need to guard.
- **When to use which**: prefer `formatDateTime` for event stamps (applied at, converted at, audit log timestamps). Use `formatDate` for date-only domain fields (hired at, posted at, created at columns in admin tables).
- **Adding a new page**: import the named functions (`import { formatDateTime, formatDate } from '~/composables/useDateFormat'`) — do not duplicate the logic locally even "just for this one date."

### 6. Confirm Modals (P2)
- **No native browser dialogs**: `confirm()`, `alert()`, and `prompt()` are forbidden in pages/components. They break the design language, can't be themed, and can't be tested via Playwright.
- **Use `toast.confirm()`**: Destructive or state-changing actions (publish, close, archive, delete, convert) MUST go through the `confirm()` method exposed by `useToast()` (rendered by `components/ConfirmDialog.vue`). It returns a Promise resolving `true` on accept, `false` on cancel/Escape/backdrop.
- **Tone**: pick `color: 'danger'` for irreversible actions, `'warning'` for compliance/lock actions, `'primary'` for forward/publish actions. Pair with a matching `ti-*` icon.
- **Example**: see `pages/payroll.vue` (`processPeriod`, `closePeriod`) and `pages/vacancies.vue` (`publish`) for canonical usage.

### 7. Row Actions on List Tables (P2)
- **Single kebab trigger**: Any list table with ≥ 2 row-level actions MUST collapse them into a single 30×30 `action-trigger` button containing `ti-dots-vertical`. Inline icon strips (`<button><i ti-pencil/></button><button><i ti-trash/></button>...`) are forbidden — they don't scale past 3 actions and obscure which buttons are destructive.
- **Fixed-positioned dropdown**: The menu is `position: fixed` (so it escapes the table's `overflow-x-auto` clipping), anchored to the trigger via `getBoundingClientRect()`, and auto-flips above when the viewport is short.
- **Per-row visibility, not gray-out**: Each item gates on per-row predicates (`v-if="canWrite && row.status === 'draft'"`). Don't render disabled items — hide them.
- **Item colors**: neutral items use `.action-item`; primary/forward = `.action-item-primary`; warning/lock = `.action-item-warning`; destructive = `.action-item-danger`. Separate groups with `<hr class="my-1 border-(--border-color)">`.
- **Outside-click dismiss**: register a single `document.addEventListener('click', closeActionMenu)` inside `onMounted` (gated by `import.meta.client`); the trigger uses `@click.stop` so opening doesn't immediately re-close it, and the dropdown root uses `@click.stop` so clicking inside doesn't dismiss.
- **Reference + adoption list**: see `design.md` §14.1 for the canonical TypeScript snippet and §14 for the full inventory of pages that use this pattern. New list pages MUST follow this contract — do not invent a new row-actions affordance.

## Best Practices
- **Mobile First**: Always design for mobile responsiveness first using Tailwind's `sm:`, `md:`, `lg:` prefixes.
- **Performance**: Optimize images and use lazy-loading for heavy components.
- **Aesthetics**: Use smooth transitions, subtle shadows, and premium color palettes (avoid default colors).
- **Accessibility**: Ensure all interactive elements have proper ARIA labels and keyboard support.

## Troubleshooting
- **TypeScript Auto-imports**: If TypeScript cannot resolve standard Nuxt composables like `useRuntimeConfig`, ensure a `tsconfig.json` extending `./.nuxt/tsconfig.json` exists in the frontend folder, and make sure `npm run dev` or `nuxt prepare` has run to generate the auto-imported types.
- **Node.js Type Definitions**: If TypeScript throws `Cannot find name 'process'`, ensure `@types/node` is installed in `devDependencies` in `package.json` to resolve process environment variables.
- **Nuxt 3 Meta Tags**: Do not use the legacy Nuxt 2 `hid` property in `nuxt.config.ts` meta tags. Nuxt 3 automatically uses `name` or `key` to deduplicate meta tags, and using `hid` will throw a TypeScript compilation error.
- **Tailwind Classes Not Applying**: Check `tailwind.config.ts` and ensure the CSS entry point is imported in `nuxt.config.ts`.
- **Hydration Mismatch**: Avoid browser-only APIs (like `localStorage` or `window`) directly in the `setup` block; use `onMounted`.
