<template>
  <q-scroll-area class="fit col xlight-grey">
    <section ref="inline_comments_section">
      <div class="q-pa-md">
        <span class="text-h3">{{
          $t("submissions.inline_comments_heading")
        }}</span>
      </div>

      <component
        :is="comment.new ? NewInlineComment : InlineComment"
        v-for="comment in inline_comments"
        :key="comment.id"
        ref="commentRefs"
        :comment="comment"
        @submit="closeEditor"
        @cancel="closeEditor"
      />
      <div class="row justify-center q-pa-md q-pb-xl">
        <q-btn
          ref="scroll_to_top_button"
          color="dark"
          class="accent-dark"
          icon="arrow_upward"
          @click="scrollToTop"
        >
          {{ $t("submissions.comment.scroll_to_top") }}
        </q-btn>
      </div>
    </section>
  </q-scroll-area>
</template>

<script setup>
import { ref, watch, inject, computed, nextTick } from "vue"
import NewInlineComment from "../NewInlineCommentComponent.vue"
import InlineComment from "src/components/atoms/InlineComment.vue"
import { scroll } from "quasar"
const { getScrollTarget, setVerticalScrollPosition } = scroll

const submission = inject("submission")
const activeComment = inject("activeComment")

const commentRefs = ref([])
const inline_comments_section = ref(null)

const inline_comments = computed(() => {
  const comments = Array.isArray(submission.value?.inline_comments)
    ? [...submission.value.inline_comments]
    : []

  if (activeComment.value?.new === true) {
    comments.push(activeComment.value)
  }
  return comments
    .filter((c) => {
      return c.deleted_at === null || c.replies?.length > 0
    })
    .sort((a, b) => {
      return a.from - b.from
    })
})

function scrollToTop() {
  const target = getScrollTarget(inline_comments_section.value)
  setVerticalScrollPosition(target, 0, 250)
}

function closeEditor() {
  activeComment.value = null
}
watch(
  activeComment,
  (newValue) => {
    if (!newValue) return
    if (!newValue.__typename.startsWith("InlineComment")) return
    nextTick(() => {
      let scrollTarget = null
      for (const commentRef of commentRefs.value) {
        if (commentRef.comment.id === newValue.id) {
          scrollTarget = commentRef.scrollTarget
          break
        }
        if (commentRef.replyIds.includes(newValue.id)) {
          const reply = commentRef.replyRefs.find(
            (r) => r.comment.id === newValue.id
          )
          scrollTarget = reply.scrollTarget
          break
        }
      }
      if (!scrollTarget) return
      const getOffsetTop = function (element) {
        if (!element) return 0
        return getOffsetTop(element.offsetParent) + element.offsetTop
      }
      const secondaryNavHeight = 75
      const negativeSpaceAdjustment = 14
      const offset =
        getOffsetTop(scrollTarget) -
        secondaryNavHeight -
        negativeSpaceAdjustment
      const target = getScrollTarget(scrollTarget)
      setVerticalScrollPosition(target, offset, 250)
    })
  },
  { deep: false }
)
</script>
