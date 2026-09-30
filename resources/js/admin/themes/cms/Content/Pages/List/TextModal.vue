<template>
  <ui-modal maxWidth="5xl" :show="show" @close="$emit('close')">
    <template #title>{{ fieldName }}</template>
    <template #content>
      <div v-html="record"></div>
    </template>
    <template #footer>
      <ui-button color="gray-200" hover="gray-300" @click="$emit('close')"
        ><span class="text-gray-800">{{ __("Close") }}</span></ui-button
      >
    </template>
  </ui-modal>
</template>

<script>
import { __ } from "@/admin/translations/engine";
import UiModal from "@/components/Modal.vue";
import UiButton from "@/components/Button.vue";

export default {
  name: "TextModal",

  components: {
    UiModal,
    UiButton,
  },

  props: {
    show: { type: Boolean, default: false },
    record: { type: String, default: null },
    fieldName: { type: String, default: null },
  },

  emits: ["close"],

  methods: {
    __,
  },
};
</script>
