import { toValue, type MaybeRefOrGetter } from "vue"

/**
 * Typed reader for an entity's server-resolved scoped ability flags
 * (`submission.abilities`, `publication.abilities`, …). The scoped
 * counterpart of `useCurrentUser().can()` — same call shape, entity scope:
 *
 *   const can = useCan(submission)
 *   can("update_status") // boolean, key compile-checked
 *
 * The flags are UI hints; the server still enforces every operation. Falls
 * closed: an absent entity, an absent abilities object, or a flag the page
 * query did not select all read as `false`.
 */
export function useCan<A extends Record<string, boolean>>(
  entity: MaybeRefOrGetter<{ abilities?: A | null } | null | undefined>
) {
  return (ability: keyof A & string): boolean =>
    toValue(entity)?.abilities?.[ability] === true
}
