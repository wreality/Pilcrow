<template>
  <slot v-if="loading" name="loading">
    <div class="q-pa-lg" data-cy="route_gate_loading">{{ $t("loading") }}</div>
  </slot>
  <slot v-else-if="denied" name="denied">
    <error403-page />
  </slot>
  <slot v-else :entity="entity as NonNullable<T>" />
</template>

<script setup lang="ts" generic="T">
import { computed } from "vue"
import Error403Page from "src/pages/Error403Page.vue"

/**
 * Generic render-time authorization gate over a page's own query result.
 * Pure decider — it never fetches. Denial renders a 403 in place (the URL
 * stays put, refreshable); redirects are for state-machine canonicalization,
 * not for denial, and live in SubmissionGate.
 *
 * Two modes, combinable:
 * - entity mode: bind `:entity="result?.thing ?? null"`. `null` after loading
 *   means the server denied (or the record doesn't exist) → 403.
 * - allowed mode: bind `:allowed` for gates with no entity (e.g. the admin
 *   area gating on a global ability flag).
 */
interface Props {
  loading?: boolean
  /** null = server denied; leave unbound when gating on `allowed` alone. */
  entity?: T | null
  allowed?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  entity: undefined,
  allowed: true
})

const denied = computed(() => props.allowed === false || props.entity === null)
</script>
