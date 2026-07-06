import type { RouteLocationRaw } from "vue-router"

/**
 * The submission screens. Canonical screens (`preview`, `view`, `review`)
 * have exactly one right answer per status — a gate on the wrong one
 * redirects to the canonical one. Action screens (`draft`, `export`) are
 * deliberate destinations gated by ability, never redirect targets.
 */
export const CANONICAL_SCREENS = ["preview", "view", "review"] as const
export type CanonicalScreen = (typeof CANONICAL_SCREENS)[number]
export type SubmissionScreen = CanonicalScreen | "draft" | "export"

/**
 * Single source of truth for the submission status → screen state machine.
 */
export function canonicalScreenFor(status: string): CanonicalScreen {
  switch (status) {
    case "DRAFT":
      return "preview"
    case "INITIALLY_SUBMITTED":
      return "view"
    default:
      return "review"
  }
}

/**
 * The canonical route for a submission's current status. A gate rendering a
 * canonical screen that doesn't match should `router.replace` here — the URL
 * must match the state. Pure; no access decision (denial is the entity being
 * null, decided by the server).
 */
export function canonicalRouteFor(submission: {
  id: string
  status: string
}): RouteLocationRaw {
  return {
    name: `submission:${canonicalScreenFor(submission.status)}`,
    params: { id: submission.id }
  }
}
