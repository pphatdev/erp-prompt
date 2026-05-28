<script setup lang="ts">
import { navigateTo } from '#app'

// The monolithic index.vue has been completely split into standalone pages inside configuration/
// We redirect the base /settings route to the first tab (branding).
navigateTo('/settings/configuration/branding', { replace: true })
</script>
