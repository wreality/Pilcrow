import { toValue, type MaybeRefOrGetter } from "vue"

/**
 * Typed reader for an entity's server-resolved granted abilities
 * (`submission.abilities`, `publication.abilities`, …) — an array of the
 * ability-enum values the viewer holds. The scoped counterpart of
 * `useCurrentUser().can()` — same call shape, entity scope:
 *
 *   const can = useCan(submission)
 *   can("update_status") // boolean, key compile-checked against the enum
 *
 * The array is a UI hint; the server still enforces every operation. Falls
 * closed: an absent entity, an absent abilities array, or an ability the
 * viewer wasn't granted all read as `false`.
 */
export function useCan<A extends string>(
  entity: MaybeRefOrGetter<
    { abilities?: readonly A[] | null } | null | undefined
  >
) {
  return (ability: `${A}`): boolean =>
    toValue(entity)?.abilities?.includes(ability as A) === true
}
