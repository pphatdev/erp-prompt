# Skill: Frontend UI & Feature Implementation

## Context
Use this skill when building new pages, complex forms, or dashboard features in the Nuxt 3 environment. This ensures that the frontend remains performant, responsive, and adheres to the premium "Enterprise ERP" design language.

## Guidelines

### 1. Composition API & Component Structure
- **Script Setup**: Always use `<script setup lang="ts">`.
- **Atomic Design**: Break down large pages into small, reusable components located in `components/` (flat — there is no per-module components folder).
- **Props Validation**: Use TypeScript interfaces and `withDefaults` for all props.
- **Emits**: Explicitly define emits using `defineEmits`.
- **4-space indent** in `.vue` / `.ts` / `.css` / `.json` — matches backend.

### 2. UI Development (Tailwind 4 + optional PrimeVue)
- **Default chrome is custom Tailwind**, not PrimeVue. Most pages use hand-rolled `.glass-card` containers and custom modals (`fixed inset-0 bg-black/50 backdrop-blur-sm`). PrimeVue is reserved for richer widgets (Kanban drag/drop, full DataTables, Calendar). When you do use PrimeVue, theme via its `pt` (Pass-Through) property.
- **CSS Variables drive branding.** Use the existing tokens (`--color-primary-rgb`, `--bg-card`, `--text-heading`, `--shadow-md`, ...). Never hardcode a brand color — it breaks tenant theming.
- **Tailwind 4** is consumed via `@tailwindcss/vite`; design tokens live in `@theme { ... }` blocks inside `assets/css/main.css`. There is **no `tailwind.config.ts`**.
- **Icons**: Tabler Icons CDN — `<i class="ti ti-users"></i>`.
- **Layouts**: Use Nuxt layouts (`layouts/default.vue`, `layouts/auth.vue`) to manage persistent UI elements. The default layout owns the sidebar, topbar, and breadcrumb — pages just render their content.

### 3. Reactive Data Fetching
- **useApi Composable**: ALWAYS go through `useApi()`. It auto-injects the `X-Tenant-Handle` header (from `tenantStore.activeHandle`) and the `Authorization: Bearer` token; on 401 it awaits `authStore.rotateToken()` (single-flight, concurrent-safe) and retries once. Never call `$fetch` or `useFetch` directly with raw URLs.
- **Module composables wrap useApi**: e.g. `useInventory()`, `useDashboard()`. Don't sprinkle `api.get('/x')` calls across pages — group them in the composable so endpoint changes touch one file.
- **Singleton + fail-open**: `useModules`, `useDashboard` use module-level refs to share state and return `true` from gating checks before data is loaded — UI never hides items on a backend error.
- **Watchers**: Use `watch` or `watchEffect` to reactively fetch when route params/filters change.

### 4. Form Handling & Validation
- **Reactive `form` + `showErrors` flag** is the default pattern: show validation errors only after first submit attempt. Use VeeValidate only for complex multi-step forms.
- **Debouncing**: Use debounced inputs for search fields (live handle-availability check, etc.).
- **Loading States**: Always show a skeleton or spinner during async operations. The codebase uses `.nav-skeleton` / `.dash-skeleton` classes (shimmer keyframe in `main.css`).
- **Confirm dialogs**: `await useToast().confirm({ title, message, color: 'danger' })` — never `window.confirm`. See `rules/frontend/standards.md` §6.

## Best Practices
- **Composable Logic**: Extract reusable business logic (e.g., `useInvoicing`, `useInventory`) into composables.
- **Performance**: Use `v-once` for static content and `v-memo` for complex lists to optimize rendering.
- **Accessibility**: Use semantic HTML (e.g., `<main>`, `<section>`, `<nav>`) and ensure all interactive elements have focus states.
- **Dark Mode**: Use Tailwind's `dark:` modifier and PrimeVue's theme switching for a first-class dark mode experience.

## Troubleshooting
- **Missing tsconfig or Auto-imports**: If TypeScript cannot find Nuxt composables (e.g., `Cannot find name 'useRuntimeConfig'`), verify that `frontend/tsconfig.json` exists and extends `./.nuxt/tsconfig.json`. Also, ensure `nuxt prepare` or `npm run dev` has run to build the `.nuxt` directory.
- **Missing Node.js Types (`process` not found)**: If the compiler throws `Cannot find name 'process'` in configurations, ensure `@types/node` is installed in `devDependencies` within `package.json`.
- **Obsolete `hid` tag errors**: Nuxt 3 uses `unhead` for meta tags. Do not use the Nuxt 2 `hid` attribute in `meta` arrays within `nuxt.config.ts`. Use standard `name` or `key` attributes instead to avoid typescript compilation errors.
- **Hydration Errors**: Ensure that the DOM structure generated on the server matches the client. Avoid using `Date.now()` or random IDs in the root template.
- **Z-Index Conflicts**: Use PrimeVue's `ZIndexUtils` or standard Tailwind `z-` classes consistently to avoid overlay issues.
- **State Desync**: If Pinia state is out of sync, check if the store is being reset or if actions are not awaiting promises correctly.
- **Layout Shift**: Set explicit dimensions for images and use skeletons to reserve space for content during loading.
