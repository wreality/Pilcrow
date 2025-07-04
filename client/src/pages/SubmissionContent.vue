<template>
  <nav class="q-px-lg q-pt-md q-gutter-sm">
    <q-breadcrumbs>
      <q-breadcrumbs-el :label="$t('header.publications')" />
      <q-breadcrumbs-el
        :label="
          submission?.publication?.name ?? $t(`publications.term`, { count: 1 })
        "
      />
      <q-breadcrumbs-el
        :to="{
          name: 'submission:draft',
          params: { id: submission?.id }
        }"
      >
        {{
          $t(`submissions.create.draft_title`, {
            submission_title:
              submission?.title ?? $t(`submissions.term`, { count: 1 })
          })
        }}
      </q-breadcrumbs-el>
      <q-breadcrumbs-el>{{
        $t(`submissions.content.heading`)
      }}</q-breadcrumbs-el>
    </q-breadcrumbs>
  </nav>
  <div class="row flex-center q-pa-md">
    <div class="col-lg-6 col-md-7 col-sm-9 col-xs-12">
      <article class="q-py-lg q-px-sm">
        <div
          v-if="
            formStatus !== 'enter_text_success' &&
            formStatus !== 'upload_success'
          "
          class="q-gutter-md"
        >
          <h1 class="text-h3" data-cy="submission_content_title">
            {{ $t(`submissions.content.heading`) }}
          </h1>
          <q-banner
            v-if="updateMethod === 'upload' || updateMethod == ''"
            data-cy="upload_option"
            class="bg-primary text-white cursor-pointer"
            inline-actions
            @click="setMethod('upload')"
          >
            <q-radio
              v-model="updateMethod"
              class="text-bold"
              color="secondary"
              val="upload"
              :label="$t(`submissions.content.upload${updateModifier}.label`)"
            />
            <div
              class="text-caption"
              style="padding: 0 0 0.5em 2.5em; margin-top: -0.4em"
            >
              {{ $t(`submissions.content.upload${updateModifier}.caption`) }}
            </div>

            <template v-if="updateMethod !== ''" #action>
              <q-btn
                flat
                :label="$t(`submissions.content.back_btn_label`)"
                @click.stop="clearMethod"
              />
            </template>
          </q-banner>
          <q-banner
            v-if="updateMethod === 'enter' || updateMethod == ''"
            data-cy="enter_text_option"
            class="bg-primary text-white cursor-pointer"
            inline-actions
            @click="setMethod('enter')"
          >
            <q-radio
              v-model="updateMethod"
              class="text-bold"
              color="secondary"
              val="enter"
              :label="$t(`submissions.content.enter${updateModifier}.label`)"
            />
            <div
              class="text-caption"
              style="padding: 0 0 0.5em 2.5em; margin-top: -0.4em"
            >
              {{ $t(`submissions.content.enter${updateModifier}.caption`) }}
            </div>
            <template v-if="updateMethod !== ''" #action>
              <q-btn
                flat
                :label="$t(`submissions.content.back_btn_label`)"
                @click.stop="clearMethod"
              />
            </template>
          </q-banner>
          <div v-if="updateMethod == 'upload'">
            <q-card class="q-pa-md" flat>
              <q-list>
                <q-item>
                  <q-item-section avatar>
                    <q-icon color="primary" name="description" />
                  </q-item-section>

                  <q-item-section>
                    <q-item-label>{{
                      $t(`submissions.content.support.file_caption`)
                    }}</q-item-label>
                    <q-item-label caption>{{
                      $t(`submissions.content.support.file_types`)
                    }}</q-item-label>
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card>
            <q-file
              v-model="uploadFile"
              data-cy="file_picker"
              clearable
              outlined
              color="accent"
              :label="$t(`file_picker_label`)"
            >
              <template #prepend>
                <q-icon name="attach_file" />
              </template>
            </q-file>
            <q-btn
              data-cy="submit_upload_btn"
              color="primary"
              class="q-mt-md"
              :label="$t(`submissions.content.submit.btn_label`)"
              @click="submitUpload"
            />
          </div>
          <div v-if="formStatus === 'upload_error'">
            <q-banner class="bg-negative text-white">
              {{ $t(`submissions.content.submit.error`) }}
            </q-banner>
          </div>
          <div v-if="updateMethod == 'enter'">
            <q-editor
              v-model="submissionContent"
              data-cy="content_editor"
              min-height="10rem"
              :toolbar="[
                [
                  {
                    label: $q.lang.editor.formatting,
                    icon: $q.iconSet.editor.formatting,
                    list: 'no-icons',
                    options: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']
                  }
                ],
                ['bold', 'italic', 'underline'],
                ['link', 'unordered', 'ordered', 'outdent', 'indent'],
                ['undo', 'redo']
              ]"
            />
            <q-btn
              data-cy="submit_entered_text_btn"
              color="primary"
              class="q-mt-md"
              :label="$t(`submissions.content.submit.btn_label`)"
              @click="submitEnteredText"
            />
          </div>
          <div v-if="formStatus === 'submit_error'">
            <q-banner class="bg-negative text-white">
              {{ $t(`submissions.content.submit.error`) }}
            </q-banner>
          </div>
        </div>
        <div v-else class="column text-center flex-center q-px-lg">
          <q-icon color="positive" name="check_circle" size="2em" />
          <strong class="text-h3">{{
            $t(`submissions.content.submit.success.title`)
          }}</strong>
          <p>{{ $t(`submissions.content.submit.success.message`) }}</p>
          <div>
            <q-btn
              data-cy="content_submit_success_btn"
              class="q-mr-sm"
              color="accent"
              size="md"
              :label="$t(`submissions.content.submit.success.btn_label`)"
              :to="{
                name: 'submission:draft',
                params: { id: props.id }
              }"
            />
            <q-btn
              color="primary"
              :label="$t(`submission.action.preview`)"
              :to="{
                name: 'submission:preview',
                params: { id: props.id }
              }"
            />
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { GET_SUBMISSION } from "src/graphql/queries"
import {
  UPDATE_SUBMISSION_CONTENT,
  UPDATE_SUBMISSION_CONTENT_WITH_FILE
} from "src/graphql/mutations"
import { useQuery, useMutation } from "@vue/apollo-composable"
import { watchEffect, computed, ref } from "vue"

const props = defineProps({
  id: {
    type: String,
    required: true
  }
})

const { result } = useQuery(GET_SUBMISSION, props)
const submission = computed(() => result.value?.submission)
const updateMethod = ref("")
const uploadFile = ref(null)
const submissionContent = ref("")
const formStatus = ref("incomplete")
const updateModifier = ref("")

watchEffect(() => {
  if (submission.value?.content?.data) {
    submissionContent.value = submission.value.content.data
    updateModifier.value = "_update"
  }
})

function clearMethod() {
  formStatus.value = "incomplete"
  updateMethod.value = ""
  uploadFile.value = null
}

function setMethod(value) {
  updateMethod.value = value
}

const { mutate: updateContent } = useMutation(UPDATE_SUBMISSION_CONTENT)
async function submitEnteredText() {
  try {
    await updateContent({ id: props.id, content: submissionContent.value })
    formStatus.value = "enter_text_success"
  } catch (error) {
    formStatus.value = "enter_text_error"
  }
}

const uploadOpts = {
  variables: {
    submission_id: props.id,
    file_upload: uploadFile.value
  },
  context: {
    hasUpload: true
  }
}
const { mutate: updateContentWithFile } = useMutation(
  UPDATE_SUBMISSION_CONTENT_WITH_FILE,
  uploadOpts
)
async function submitUpload() {
  try {
    uploadOpts.variables.file_upload = uploadFile.value
    await updateContentWithFile()
    formStatus.value = "upload_success"
  } catch (error) {
    formStatus.value = "upload_error"
  }
}
</script>
