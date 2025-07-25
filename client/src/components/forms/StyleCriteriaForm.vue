<template>
  <q-form @submit="onSave">
    <q-separator />
    <q-item class="q-px-md q-py-lg" role="">
      <q-item-section avatar top class="column content-center justify-start">
        <q-btn
          ref="icon-button"
          data-cy="icon-button"
          color="secondary"
          :icon="v$.icon.$model"
          dense
          :aria-label="$t('publications.style_criteria.fields.icon.ariaLabel')"
          @click="editIcon"
        >
          <q-tooltip
            >{{ $t("publications.style_criteria.fields.icon.tooltip") }}
          </q-tooltip>
        </q-btn>
      </q-item-section>
      <q-item-section class="column q-gutter-sm">
        <v-q-input
          ref="name-input"
          :v="v$.name"
          t="publications.style_criteria.fields.name"
          data-cy="name-input"
          hide-hint
          @vqupdate="updateModel"
        />
        <q-editor
          ref="description-input"
          v-model="v$.description.$model"
          data-cy="description-input"
          :toolbar="[
            ['bold', 'italic', 'underline'],
            ['link', 'unordered', 'ordered', 'outdent', 'indent'],
            ['undo', 'redo']
          ]"
          :class="v$.description.$error ? 'error' : ''"
          :placeholder="
            $t('publications.style_criteria.fields.description.placeholder')
          "
        />
        <div
          v-if="v$.description.$error && v$.description.maxLength.$invalid"
          class="text-negative"
          data-cy="description-errors"
        >
          {{
            $t(
              "publications.style_criteria.fields.description.errors.maxLength"
            )
          }}
        </div>

        <div class="row" :class="isNew ? 'justify-end' : 'justify-between'">
          <q-btn
            v-if="!isNew"
            ref="button_delete"
            data-cy="button-delete"
            color="negative"
            :label="$t(`buttons.delete`)"
            @click="onDelete"
          />

          <FormActions flat :sticky="false" @reset-click="onCancel" />
        </div>
      </q-item-section>
    </q-item>
    <q-separator />
  </q-form>
</template>

<script setup>
import SelectIconDialog from "src/components/dialogs/SelectIconDialog.vue"
import { useQuasar } from "quasar"
import { reactive, onMounted, inject, watchEffect, computed } from "vue"
import useVuelidate from "@vuelidate/core"
import { required, maxLength } from "@vuelidate/validators"
import { isEqual, pick } from "lodash"
import VQInput from "src/components/atoms/VQInput.vue"
import FormActions from "../molecules/FormActions.vue"
import { useI18n } from "vue-i18n"
const { dirty, setError } = inject("formState")

const { t } = useI18n()
const props = defineProps({
  criteria: {
    type: Object,
    default: () => ({})
  }
})
const emit = defineEmits(["cancel", "save", "delete"])

const state = reactive({
  id: "",
  name: "",
  icon: "task_alt",
  description: ""
})

const rules = {
  name: {
    required,
    maxLength: maxLength(50)
  },
  description: {
    maxLength: maxLength(4096)
  },
  icon: {
    maxLength: maxLength(50)
  }
}

const v$ = useVuelidate(rules, state)

const original = computed(() => ({
  ...pick(props.criteria, ["id", "name", "icon", "description"])
}))

const isNew = computed(() => {
  return Object.keys(props.criteria).length === 0
})

onMounted(() => {
  Object.assign(state, props.criteria)
})

watchEffect(() => {
  dirty.value = !isEqual(state, original.value)
})

const { dialog } = useQuasar()

function editIcon() {
  dialog({
    component: SelectIconDialog,
    componentProps: {
      icon: state.icon
    }
  }).onOk((icon) => {
    v$.value.icon.$model = icon
  })
}

function onDelete() {
  dialog({
    title: t("publications.style_criteria.delete_header"),
    message: t("publications.style_criteria.delete_confirm", {
      name: props.criteria.name
    }),
    cancel: true,
    persistent: true
  }).onOk(() => {
    emit("delete", props.criteria)
  })
}

function onCancel() {
  emit("cancel")
}

function onSave() {
  v$.value.$touch()
  if (v$.value.$invalid) {
    setError(t("publications.style_criteria.saveError"))
  } else {
    emit("save", state)
  }
}

function updateModel(validator, value) {
  validator.$model = value
}
</script>

<style lang="sass">
.q-editor.error
  border: $negative 1px solid
</style>
