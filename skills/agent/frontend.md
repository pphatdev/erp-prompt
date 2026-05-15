# Skill: Nuxt 3 Frontend Development Protocol

## Context
Use this skill when implementing UI features, adding new pages, or managing state in the Nuxt 3 project. This protocol ensures consistency in component structure, styling, and backend communication.

## Guidelines

### 1. Feature Scaffolding
When asked to create a new feature:
1.  **Define the Route**: Determine the file structure in `pages/`.
2.  **Identify Components**: Break the UI into reusable components in `components/`.
3.  **State Logic**: Determine if a Pinia store is required in `stores/`.

### 2. Implementation Workflow
1.  **Setup Logic**:
    ```typescript
    <script setup lang="ts">
    const { $api } = useNuxtApp();
    const { data, pending, error } = await useFetch('/api/v1/resource');
    </script>
    ```
2.  **Styling**: Use Tailwind utility classes. For complex layouts, leverage Grid and Flexbox.
3.  **UI Components**: Use `<p-datatable>`, `<p-button>`, etc., from PrimeVue.

### 3. Multi-Tenant API Communication
1.  **Header Injection**: Ensure the tenant ID is injected into every request.
2.  **Subdomain Handling**: If the app uses subdomains, ensure the frontend correctly identifies the current tenant from the URL.
3.  **Base URL**: Use environment variables for the API base URL.

## Best Practices
- **Premium Aesthetics**:
  - Use `backdrop-blur` and `bg-white/80` for modern glassmorphism.
  - Implement subtle `hover:scale-[1.02]` transitions for interactive cards.
  - Use a consistent spacing scale (e.g., multiples of 4px).
- **SEO**:
  ```typescript
  useHead({
    title: 'Page Title - ERP System',
    meta: [{ name: 'description', content: 'Page description' }]
  });
  ```
- **Code Quality**: Use `eslint` and `prettier` (if configured) to maintain style consistency.

## Troubleshooting
- **API CORS Errors**: Verify that the Laravel backend has the correct frontend domain in its `cors.php` config.
- **Type Errors**: Check that the API response interface matches the actual backend resource output.
