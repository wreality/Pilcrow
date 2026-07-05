import type { InjectionKey, Ref, ComputedRef } from "vue"
import { inject, provide, ref } from "vue"
import type {
  Submission,
  OverallComment,
  OverallCommentReply,
  InlineComment,
  InlineCommentReply
} from "src/graphql/generated/graphql"

export interface NewInlineComment {
  __typename: "InlineComment"
  new: true
  from: number
  to: number
  parent_id: null
  reply_to_id: null
  deleted_at: null
  id: string
}

export type ActiveComment =
  | OverallComment
  | OverallCommentReply
  | InlineComment
  | InlineCommentReply
  | NewInlineComment

/**
 * The contract of the submission inject seam: the fields descendants of a
 * submission page actually read through `useSubmission()`, plus the ability
 * flags their gates read. Page-owned queries must satisfy this at the provide
 * site (compile-checked) — the explicit alternative to lying casts while
 * fragment masking is off. Extend it when an injector legitimately needs a
 * new field; it dissolves into fragment-ref props if masking lands later.
 */
export type SubmissionContext = Pick<
  Submission,
  | "id"
  | "title"
  | "status"
  | "content"
  | "inline_comments"
  | "overall_comments"
  | "publication"
  | "abilities"
>

export const submissionKey: InjectionKey<
  ComputedRef<SubmissionContext | undefined>
> = Symbol("submission")

export const activeCommentKey: InjectionKey<Ref<ActiveComment | null>> =
  Symbol("activeComment")

export const forExportKey: InjectionKey<Ref<boolean>> = Symbol("forExport")

export const commentDrawerOpenKey: InjectionKey<Ref<boolean>> =
  Symbol("commentDrawerOpen")

export function provideSubmissionReviewContext(options: {
  submission: ComputedRef<Submission | undefined>
  activeComment?: Ref<ActiveComment | null>
  forExport?: Ref<boolean>
  commentDrawerOpen?: Ref<boolean>
}) {
  provide(submissionKey, options.submission)
  provide(activeCommentKey, options.activeComment ?? ref(null))
  provide(forExportKey, options.forExport ?? ref(false))
  provide(commentDrawerOpenKey, options.commentDrawerOpen ?? ref(false))
}

export function useSubmission() {
  return inject(submissionKey)!
}

export function useActiveComment() {
  return inject(activeCommentKey)!
}

export function useForExport() {
  return inject(forExportKey)!
}

export function useCommentDrawerOpen() {
  return inject(commentDrawerOpenKey)!
}
