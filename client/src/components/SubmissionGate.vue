<template>
  <route-gate :loading="loading" :entity="submission ?? null">
    <template #loading>
      <slot name="loading" />
    </template>
    <template v-if="!redirecting" #default>
      <slot />
    </template>
  </route-gate>
</template>

<script setup lang="ts">
import { computed, provide, watchEffect } from "vue"
import { useRouter } from "vue-router"
import RouteGate from "./RouteGate.vue"
import {
  canonicalScreenFor,
  canonicalRouteFor,
  CANONICAL_SCREENS,
  type SubmissionScreen,
  type CanonicalScreen
} from "src/router/canonicalRoute"
import {
  submissionKey,
  type SubmissionContext
} from "src/use/submissionContext"

/**
 * The submission-page gate: RouteGate's authz mechanics plus the status →
 * screen state machine. Wrong canonical screen for the submission's status →
 * `router.replace` to the canonical one (the URL must match the state).
 * Action screens (`draft`, `export`) never redirect — their pages gate on
 * the relevant ability and deny in place.
 *
 * Provides the submission under the existing `submissionContext` key, so
 * `useSubmission()` descendants work unchanged.
 */
interface Props {
  loading: boolean
  submission: SubmissionContext | null | undefined
  screen: SubmissionScreen
}

const props = defineProps<Props>()

const router = useRouter()

const redirecting = computed(() => {
  if (!props.submission) {
    return false
  }
  return (
    CANONICAL_SCREENS.includes(props.screen as CanonicalScreen) &&
    canonicalScreenFor(props.submission.status) !== props.screen
  )
})

watchEffect(() => {
  if (redirecting.value && props.submission) {
    router.replace(canonicalRouteFor(props.submission))
  }
})

provide(
  submissionKey,
  computed(() => props.submission ?? undefined)
)
</script>
