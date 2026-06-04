<template>
    <div class="space-y-5">
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Attendance &amp; Clocking</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Geofencing, IP whitelist, and auto clock-out reconciliation window for mobile clock-in / out.
            </p>
        </header>

        <GuidancePanel>
            <template #title>Notes</template>
            <p>
                Geofencing falls back to a per-department radius when one is configured on the
                department row; the tenant-level radius below is the default.
            </p>
        </GuidancePanel>

        <section class="glass-card rounded-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <ToggleField label="Geofence mobile clock-in / out"
                    hint="Validate GPS coordinate against the department office location."
                    v-model="draft['hrm.attendance.enable_geofencing']" />
                <NumberField label="Geofence radius (meters)" min="1" max="100000"
                    hint="Haversine distance threshold from the department office coordinate."
                    v-model="draft['hrm.attendance.geofence_radius_meters']" />
                <ToggleField label="IP-whitelist clock-in / out"
                    hint="Block clock requests from unlisted source addresses (fails closed)."
                    v-model="draft['hrm.attendance.enable_ip_whitelisting']" />
                <NumberField label="Auto clock-out window (hours)" min="1" max="72"
                    hint="Reconciler closes un-ended attendance sessions after this many hours."
                    v-model="draft['hrm.attendance.auto_clock_out_hours']" />
                <div class="md:col-span-2">
                    <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                        Allowed clock-in IPs
                    </label>
                    <textarea v-model="draft['hrm.attendance.ip_whitelist']" rows="2"
                        placeholder="203.0.113.10, 198.51.100.0/24"
                        class="form-control font-mono text-xs"></textarea>
                    <p class="text-xxs text-(--text-muted) mt-1">
                        Comma-separated list of corporate IPs or CIDR ranges. Only consulted when
                        IP-whitelisting is enabled.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import GuidancePanel from '~/components/hrm-settings/GuidancePanel.vue'
import NumberField from '~/components/hrm-settings/NumberField.vue'
import ToggleField from '~/components/hrm-settings/ToggleField.vue'

defineProps<{
    draft: Record<string, unknown>
}>()
</script>
