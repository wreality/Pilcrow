<template>
  <q-btn
    color="black"
    outline
    dense
    size="sm"
    :class="{ 'is-active': props.isActive }"
    :disabled="props.isDisabled"
    @click="props.clickHandler"
  >
    <q-tooltip
      class="bg-primary"
      anchor="top middle"
      self="bottom middle"
      :offset="[0, 5]"
      >{{ props.tooltipText }}</q-tooltip
    >
    <q-icon :name="props.iconName"></q-icon>
  </q-btn>
</template>

<script setup>
const props = defineProps({
  isActive: {
    type: Boolean,
    default: false
  },
  isDisabled: {
    type: Boolean,
    default: false
  },
  clickHandler: {
    type: Function,
    default: () => {}
  },
  tooltipText: {
    type: String,
    default: ""
  },
  iconName: {
    type: String,
    default: ""
  }
})
</script>
