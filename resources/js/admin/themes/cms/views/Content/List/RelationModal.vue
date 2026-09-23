<template>
  <ui-modal maxWidth="5xl" :show="show" @close="$emit('close')">
    <template #title>{{ fieldName }}</template>
    <template #content>
      <div class="overflow-x-auto sm:rounded-md" v-if="records.length > 0">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                {{ __("Collection") }}
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                {{ __("Status") }}
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                <div class="w-full flex justify-between item-center">{{ __("Created At") }}</div>
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"
                :class="{ 'w-full': index === 0 }"
                v-for="(field, index) in records.collection.fields"
                :key="field.id"
                v-show="
                  field.type != 'richtext' &&
                  field.type != 'password' &&
                  field.type != 'media' &&
                  field.type != 'json' &&
                  field.type != 'block' &&
                  field.type != 'relation' &&
                  !safeOptions(field).hideInContentList
                "
              >
                <div class="w-full flex justify-between item-center">
                  {{ __(field.label) }}
                </div>
              </th>
            </tr>
          </thead>
          <tr v-for="item in records" :key="item.id">
            <td class="pl-2 py-4 text-sm text-center w-px">
              <span class="text-gray-500 text-sm rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap">{{
                __(records.collection.name)
              }}</span>
            </td>
            <td class="pl-2 py-4 text-sm text-center w-px">
              <span v-if="item.published_at !== null" class="text-gray-500 text-sm rounded-md bg-green-200 px-3 py-1 whitespace-nowrap">{{
                __("published")
              }}</span>
              <span v-else class="text-gray-500 text-sm rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap">{{
                __("draft")
              }}</span>
            </td>
            <td class="px-6 py-3 text-sm w-px whitespace-nowrap text-gray-600">
              {{ $filters.date(item.created_at, "D MMM YYYY, H:mm") }}
            </td>
            <td
              class="px-6 py-3 text-sm whitespace-nowrap"
              :class="{ 'w-full': index === 0, 'w-auto': index !== 0 }"
              v-for="(field, index) in records.collection.fields"
              :key="field.id"
              v-show="
                field.type != 'richtext' &&
                field.type != 'password' &&
                field.type != 'media' &&
                field.type != 'json' &&
                field.type != 'block' &&
                field.type != 'relation' &&
                !safeOptions(field).hideInContentList
              "
            >
              <span v-for="meta in item.meta" :key="meta.id">
                <span
                  v-if="meta.field_name == field.name"
                  :class="{
                    'rounded-md bg-gray-100 p-1 mr-1': safeOptions(field).repeatable && meta.value !== null,
                  }"
                >
                  <span v-if="field.type == 'date'">{{ $filters.date(meta.value) }}</span>
                  <span v-else-if="field.type == 'longtext' && meta.value !== null" :title="meta.value"
                    >{{ meta.value.substring(0, 20) }}<span v-if="meta.value.length > 20">...</span></span
                  >
                  <span v-else>{{ meta.value }}</span>
                </span>
              </span>
            </td>
          </tr>
        </table>
      </div>
    </template>
    <template #footer>
      <ui-button color="gray-100" hover="gray-200" @click="$emit('close')"
        ><span class="text-gray-800">{{ __("Close") }}</span></ui-button
      >
    </template>
  </ui-modal>
</template>

<script>
import { __ } from "@/admin/translations/engine";

export default {
  name: "RelationModal",

  props: {
    show: { type: Boolean, default: false },
    records: { type: Object, default: () => ({ collection: { fields: {} } }) },
    fieldName: { type: String, default: null },
  },

  emits: ["close"],

  methods: {
    __,
    safeOptions(field) {
      if (!field.options) return {};
      if (typeof field.options === "string") {
        try {
          return JSON.parse(field.options);
        } catch (e) {
          return {};
        }
      }
      return field.options;
    },
  },
};
</script>
