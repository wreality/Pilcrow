<template>
  <div class="row items-center justify-end q-pa-md">
    <q-select
      v-model="selectedFont"
      outlined
      :options="fonts"
      :label="$t(`submissions.style_controls.font`)"
      style="min-width: 150px"
    />
    <div class="q-ml-md row items-center">
      <div>
        <q-btn
          :aria-label="$t(`submissions.style_controls.decrease`)"
          data-cy="decrease_font"
          round
          flat
          icon="remove_circle"
          color="white"
          :disable="fontSize === 1"
          text-color="grey-7"
          @click="decreaseFontSize()"
        />
        <q-tooltip>{{ $t("submissions.style_controls.decrease") }}</q-tooltip>
      </div>
      <div>
        <q-btn
          :aria-label="$t(`submissions.style_controls.increase`)"
          data-cy="increase_font"
          round
          flat
          icon="add_circle"
          color="white"
          text-color="grey-7"
          @click="increaseFontSize()"
        />
        <q-tooltip>{{ $t("submissions.style_controls.increase") }}</q-tooltip>
      </div>
      <div>
        <q-toggle
          v-model="darkModeStatus"
          size="xl"
          checked-icon="dark_mode"
          color="grey-7"
          unchecked-icon="light_mode"
          @click="toggleDarkMode()"
        >
          <template #default>
            <div style="width: 100px">
              {{
                darkModeStatus
                  ? $t("submissions.style_controls.dark")
                  : $t("submissions.style_controls.light")
              }}
            </div>
          </template>
        </q-toggle>
        <q-tooltip>{{
          $t("submissions.style_controls.toggle_dark")
        }}</q-tooltip>
      </div>
      <div>
        <q-btn
          :aria-label="$t(`submissions.style_controls.view_overall`)"
          data-cy="view_overall_comments"
          round
          flat
          icon="visibility"
          color="white"
          text-color="grey-7"
          @click="scrollToOverallComments()"
        />
        <q-tooltip>{{
          $t("submissions.style_controls.view_overall")
        }}</q-tooltip>
      </div>
      <div>
        <q-btn
          :aria-label="$t(`submissions.style_controls.new_overall`)"
          data-cy="new_overall_comment"
          round
          flat
          icon="add_comment"
          color="white"
          text-color="grey-7"
          @click="scrollNewOverallComment()"
        />
        <q-tooltip>{{
          $t("submissions.style_controls.new_overall")
        }}</q-tooltip>
      </div>
    </div>
  </div>
  <article
    ref="contentRef"
    data-cy="content"
    class="col-sm-9 submission-content"
    :data-visibility="props.highlightVisibility"
  >
    <bubble-menu
      v-if="editor"
      :editor="editor"
      :tippy-options="{ duration: 100 }"
      :should-show="bubbleMenuVisibility"
    >
      <q-btn color="white" text-color="primary" @click="addComment">
        <q-icon name="add_comment" />
      </q-btn>
    </bubble-menu>
    <div data-cy="highlight-click-handler" @click="highlightClickHandler">
      <editor-content
        :editor="editor"
        data-cy="submission-content"
        :style="{
          'font-size': fontSize + 'rem',
          'font-family': selectedFont.value
        }"
      />
    </div>
  </article>
</template>
<script setup>
import { BubbleMenu, Editor, EditorContent } from "@tiptap/vue-3"
import SubmissionContentKit from "src/tiptap/extension-submission-content-kit"
import {
  MARK_INLINE_COMMENTS_READ,
  MARK_INLINE_COMMENT_REPLIES_READ
} from "src/graphql/mutations"
import { computed, inject, ref, watch, nextTick } from "vue"
import { scroll } from "quasar"
import { useMutation } from "@vue/apollo-composable"
import { useDarkMode } from "src/use/guiElements"

const { getScrollTarget, setVerticalScrollPosition } = scroll
const { darkModeStatus, toggleDarkMode } = useDarkMode()

const { mutate: markRead } = useMutation(MARK_INLINE_COMMENTS_READ)
const { mutate: markReplyRead } = useMutation(MARK_INLINE_COMMENT_REPLIES_READ)

const props = defineProps({
  annotationEnabled: {
    type: Boolean,
    default: true
  },
  highlightVisibility: {
    type: Boolean,
    default: true
  }
})

const commentDrawerOpen = inject("commentDrawerOpen")
const submission = inject("submission")
const activeComment = inject("activeComment")
const contentRef = ref(null)

const emit = defineEmits([
  "scrollToOverallComments",
  "scrollAddNewOverallComment"
])

function scrollToOverallComments() {
  emit("scrollToOverallComments")
}

function scrollNewOverallComment() {
  emit("scrollAddNewOverallComment")
}

const fonts = [
  {
    label: "Sans-serif",
    value: "Atkinson, Sans-serif"
  },
  {
    label: "Serif",
    value: "Georgia, Serif"
  }
]
let selectedFont = ref("Sans-serif")
let fontSize = ref(1)

let headingSizes = ref([2.125, 1.5, 1.25, 1, 0.75, 0.5])

function increaseFontSize() {
  fontSize.value += 0.05
  for (let index in headingSizes.value) {
    headingSizes.value[index] += 0.05
  }
}

function decreaseFontSize() {
  fontSize.value -= 0.05
  for (let index in headingSizes.value) {
    headingSizes.value[index] -= 0.05
  }
}

const findCommentFromId = (id) =>
  submission.value.inline_comments.find((c) => c.id === id)

const onAnnotationClick = (context, { target }) => {
  // Open the inline comment drawer
  commentDrawerOpen.value = true

  //First we need to get all the comment widget elements with the same Y index
  const { top: targetTop } = target.getBoundingClientRect()
  const widgets = [...contentRef.value.querySelectorAll(".comment-widget")]
    .filter((e) => e.getBoundingClientRect().top === targetTop)
    .map((e) => e.dataset.comment)

  //Only one comment here. We're done
  if (widgets.length === 1) {
    activeComment.value = findCommentFromId(context.id)
    return
  }
  const currentIndex = widgets.indexOf(activeComment.value?.id)
  //The active comment isn't one of these, show the first
  if (currentIndex === -1) {
    activeComment.value = findCommentFromId(widgets[0])
    return
  }
  //We're at the last in the list, start over
  if (currentIndex + 1 === widgets.length) {
    activeComment.value = findCommentFromId(widgets[0])
    return
  }
  //Next in the list
  activeComment.value = findCommentFromId(widgets[currentIndex + 1])
}

const inlineComments = computed(() => submission.value?.inline_comments ?? [])
const annotations = computed(() =>
  props.highlightVisibility
    ? inlineComments.value.map(({ from, to, id, deleted_at }) =>
        deleted_at == null
          ? {
              from,
              to,
              context: { id },
              active: id === activeComment.value?.id,
              click: onAnnotationClick
            }
          : {
              context: { id },
              active: id === activeComment.value?.id,
              click: () => false
            }
      )
    : []
)

const editor = new Editor({
  editable: false,
  content: submission.value.content.data,
  extensions: [SubmissionContentKit.configure({ annotation: { annotations } })]
})

function bubbleMenuVisibility({ state }) {
  if (!props.annotationEnabled) {
    return false
  }
  return !state.selection.empty
}

function addComment() {
  const [from, to] = [
    editor.state.selection.$anchor.pos,
    editor.state.selection.$head.pos
  ].sort((a, b) => a - b)
  activeComment.value = {
    __typename: "InlineComment",
    new: true,
    from,
    to,
    parent_id: null,
    reply_to_id: null,
    deleted_at: null,
    id: "new"
  }
}

function highlightClickHandler(event) {
  const id = event.target.dataset["contextId"]
  if (id === undefined) {
    return
  }
  // Open the inline comment drawer and set the active comment
  commentDrawerOpen.value = true
  activeComment.value = findCommentFromId(id)
}

watch(
  activeComment,
  (newValue) => {
    if (!newValue) return
    if (!newValue.__typename.startsWith("InlineComment")) return
    ;(async () => {
      if (newValue.__typename === "InlineComment") {
        await markRead({
          submission_id: submission.value.id,
          comment_ids: [parseInt(newValue.id)]
        })
      } else {
        await markReplyRead({
          submission_id: submission.value.id,
          comment_ids: [parseInt(newValue.id)]
        })
      }
    })()
    nextTick(() => {
      let scrollTarget = null
      scrollTarget = contentRef.value.querySelector(
        `button[data-comment="${newValue.id}"]`
      )
      if (!scrollTarget) return
      const getOffsetTop = function (element) {
        if (!element) return 0
        return getOffsetTop(element.offsetParent) + element.offsetTop
      }
      const getOffset = scrollTarget.getBoundingClientRect().top
      if (getOffset > 200 && getOffset < 500) return
      const primaryNavHeight = 70
      const secondaryNavHeight = 48
      const tertiaryNavHeight = 75
      const negativeSpaceAdjustment = 14
      const offset =
        getOffsetTop(scrollTarget) -
        primaryNavHeight -
        secondaryNavHeight -
        tertiaryNavHeight -
        negativeSpaceAdjustment
      const target = getScrollTarget(scrollTarget)
      setVerticalScrollPosition(target, offset, 250)
    })
  },
  { deep: false }
)
</script>

<style lang="scss">
.comment-highlight {
  background: #c9e5f8;
}
.comment-highlight.active {
  background: #f8db8b;
}
.comment-widget {
  display: inline-block;
  cursor: pointer;
  position: absolute;
  right: -50px;
  font-size: 1.4rem;
  color: $primary;
  text-align: center;
  line-height: 1.1em;
}
.submission-content {
  counter-reset: paragraph_counter;
  font-size: 16px;
  margin: 0 auto;
  max-width: 700px;
  padding: 10px 60px 60px;
}

.submission-content p {
  position: relative;
}

.submission-content div.ProseMirror > p:before {
  color: #555;
  content: "¶ " counter(paragraph_counter);
  counter-increment: paragraph_counter;
  display: block;
  font-family: Helvetica, Arial, san-serif;
  font-size: 1em;
  left: 0;
  margin-left: -80px;
  min-width: 50px;
  position: absolute;
  text-align: right;
  top: 0;
  white-space: nowrap;
}

.submission-content blockquote p:before {
  // This compensates for the extra padding, border, and margin on blockquotes
  left: -28px;
}

.submission-content li p:before {
  // This compensates for the extra inline padding on lists
  left: -40px;
}

mark {
  color: #000;
  background-color: #bbe2e8;
}
.submission-content a[role="doc-noteref"] {
  /* Superscript */
  vertical-align: super;
  text-decoration: none;
}

.submission-content h1 {
  font-size: (v-bind('headingSizes[0] + "rem"'));
}
.submission-content h2 {
  font-size: (v-bind('headingSizes[1] + "rem"'));
}
.submission-content h3 {
  font-size: (v-bind('headingSizes[2] + "rem"'));
}
.submission-content h4 {
  font-size: (v-bind('headingSizes[3] + "rem"'));
}
.submission-content h5 {
  font-size: (v-bind('headingSizes[4] + "rem"'));
}
.submission-content h6 {
  font-size: (v-bind('headingSizes[5] + "rem"'));
}

// :style="`font-size:${fontSize}rem; font-family: ${selectedFont}`"
</style>
