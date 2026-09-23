<template>
  <div>
    <!-- 标题栏 -->
    <h4 class="h-10 flex justify-end items-center font-bold text-lg mb-2">
      <div class="flex-1">
        {{ __(collection.name) }}
        <small class="text-gray-400 ml-1">#{{ collection.slug }}</small>
      </div>

      <router-link
        v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
        :to="{
          name: 'projects.content.forms',
          params: {
            project_id: $route.params.project_id,
            col_id: collection_id,
          },
        }"
        class="bg-green-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150 mr-2"
      >
        <i class="fab fa-wpforms"></i> {{ __("Forms") }} ({{ form_count }})
      </router-link>

      <router-link
        :to="{
          name: 'projects.content.new',
          params: {
            project_id: $route.params.project_id,
            col_id: collection_id,
          },
        }"
        class="bg-indigo-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150"
        v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
      >
        {{ __("+ Create New") }}
      </router-link>

      <button
        type="button"
        class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
        @click="$emit('export')"
      >
        <i class="fa fa-download"></i> {{ __("Export") }}
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
        class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
        @click="$refs.importFile.click()"
      >
        <i class="fa fa-upload"></i> {{ __("Import") }}
      </button>
    </h4>

    <!-- 搜索和语言过滤 -->
    <div class="flex space-between mb-2">
      <div class="relative flex w-full flex-wrap items-stretch">
        <span
          class="h-full leading-snug font-normal absolute text-center text-gray-400 absolute bg-transparent rounded-md text-base items-center justify-center w-8 pl-3 py-2"
        >
          <i class="fas fa-search"></i>
        </span>
        <form class="w-full" @submit.prevent="$emit('search')">
          <input
            type="text"
            :value="search"
            @input="$emit('update:search', $event.target.value)"
            :placeholder="__('Type something and press enter')"
            class="px-3 py-2 placeholder-gray-400 text-gray-700 bg-white rounded-md text-sm w-full pl-10 border-gray-200 focus:border-gray-300"
          />
        </form>
        <span
          v-show="search != ''"
          class="h-full leading-snug font-normal absolute text-center text-gray-400 absolute bg-transparent rounded-md text-base items-center justify-center w-8 py-2 right-0 pr-3 cursor-pointer"
          @click="$emit('clear-search')"
        >
          <i class="fas fa-times-circle"></i>
        </span>
      </div>

      <div class="w-auto h-auto ml-2">
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
    search: { type: String, default: "" },
    localeFilter: { type: String, default: "" },
    localeOptions: { type: Array, default: () => [] },
    canProject: { type: Function, required: true },
  },

  emits: ["export", "import", "search", "update:search", "clear-search", "locale-change"],

  methods: {
    __,
  },
};
</script>
