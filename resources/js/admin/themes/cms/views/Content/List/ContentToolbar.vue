<template>
  <div class="flex justify-between items-center mb-3">
    <div>
      <h4 class="text-lg font-bold">
        {{ __(collection.name) }}
        <small class="text-gray-400 ml-1 text-sm font-normal">#{{ collection.slug }}</small>
      </h4>
    </div>

    <div class="flex items-center gap-2">
      <router-link
        v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
        :to="{
          name: 'projects.content.forms',
          params: {
            project_id: $route.params.project_id,
            col_id: collection_id,
          },
        }"
        class="bg-green-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150"
      >
        <i class="fab fa-wpforms mr-1"></i> {{ __("Forms") }} ({{ form_count }})
      </router-link>

      <router-link
        v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
        :to="{
          name: 'projects.content.new',
          params: {
            project_id: $route.params.project_id,
            col_id: collection_id,
          },
        }"
        class="bg-indigo-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150"
      >
        <i class="fa fa-plus mr-1"></i> {{ __("Create New") }}
      </router-link>

      <button
        type="button"
        class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150"
        @click="$emit('export')"
      >
        <i class="fa fa-download mr-1"></i> {{ __("Export") }}
      </button>

      <input
        v-if="!isReadonly && canProject(['owner', 'admin'])"
        ref="importFile"
        type="file"
        accept=".json,.csv"
        class="hidden"
        @change="$emit('import', $event)"
      />

      <button
        v-if="!isReadonly && canProject(['owner', 'admin'])"
        type="button"
        class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150"
        @click="$refs.importFile.click()"
      >
        <i class="fa fa-upload mr-1"></i> {{ __("Import") }}
      </button>

      <div class="ml-2">
        <select
          :value="localeFilter"
          @change="$emit('locale-change', $event.target.value)"
          v-formselect
          class="px-3 pr-8 pl-3 text-gray-700 bg-white focus:border-gray-300 cursor-pointer"
          :title="__('Language')"
        >
          <option v-for="opt in localeOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>
    </div>
  </div>
</template>

<script>
import { __ } from "@/admin/translations/engine";

export default {
  name: "ContentToolbar",

  props: {
    collection: { type: Object, required: true },
    collection_id: { type: [Number, String], required: true },
    isReadonly: { type: Boolean, default: false },
    form_count: { type: Number, default: 0 },
    localeFilter: { type: String, default: "" },
    localeOptions: { type: Array, default: () => [] },
    canProject: { type: Function, required: true },
  },

  emits: ["export", "import", "locale-change"],

  methods: {
    __,
  },
};
</script>
