<template>
    <NuxtLayout name="default">
        <PrefixCodeMatrix
            heading="HRM Prefix Code"
            :modules="state.modules.value"
            :draft="state.draft"
            :loading="state.loading.value"
            :saving="state.saving.value"
            :dirty="state.dirty.value"
            :alert="state.alert"
            :save="state.save"
            :reset="state.reset"
            :build-example="state.buildExample"
        />
    </NuxtLayout>
</template>

<script setup lang="ts">
import PrefixCodeMatrix from '~/components/PrefixCodeMatrix.vue'
import { usePrefixCodes } from '~/composables/usePrefixCodes'

definePageMeta({ breadcrumb: 'Prefix Code' })

const state = usePrefixCodes(['hrm'])
</script>
