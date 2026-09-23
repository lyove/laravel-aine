<template>
  <ui-table
    mode="remote"
    :columns="columns"
    :rows="content.data || []"
    :total-rows="content.total || 0"
    :select-options="{ enabled: !isReadonly }"
    :pagination-options="{
      enabled: true,
      perPage: each,
      position: 'bottom',
    }"
    :sort-options="{ enabled: true }"
    :show-column-toggle="true"
    @sort-change="$emit('sort-change', $event)"
    @selected-rows-change="$emit('selected-rows-change', $event)"
    @page-change="$emit('page-change', $event)"
    @per-page-change="$emit('per-page-change', $event)"
    @column-toggle="$emit('column-toggle', $event)"
  >
    <template #table-row="props">
      <span v-if="props.column.field === 'id'">{{ props.row.id }}</span>

      <span v-else-if="props.column.field === 'status'">
        <div v-if="props.row.form_id === null">
          <template v-if="isComments">
            <span
              v-if="props.row.status === 'approved'"
              class="text-white rounded-md bg-green-500 px-3 py-1 whitespace-nowrap"
              >{{ __("approved") }}</span
            >
            <span
              v-else-if="props.row.status === 'pending'"
              class="text-white rounded-md bg-amber-500 px-3 py-1 whitespace-nowrap"
              >{{ __("pending") }}</span
            >
            <span
              v-else-if="props.row.status === 'spam'"
              class="text-white rounded-md bg-gray-400 px-3 py-1 whitespace-nowrap"
              >{{ __("spam") }}</span
            >
            <span v-else class="text-white rounded-md bg-red-500 px-3 py-1 whitespace-nowrap">{{
              __("trash")
            }}</span>
          </template>
          <template v-else>
            <span
              v-if="props.row.published_at !== null"
              class="text-gray-500 rounded-md bg-green-200 px-3 py-1 whitespace-nowrap"
              >{{ __("published") }}</span
            >
            <span v-else class="text-gray-500 rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap">{{
              __("draft")
            }}</span>
          </template>
        </div>
        <div v-else>
          <span
            v-if="props.row.published_at !== null"
            class="text-gray-200 rounded-md bg-blue-400 px-3 py-1 whitespace-nowrap"
            v-tooltip="
              __('Submitted at {date}. Form name: {name}', {
                date: dateFormat(props.row.form.created_at),
                name: props.row.form.name,
              })
            "
            >{{ __("published") }}</span
          >
          <span
            v-else
            class="text-gray-500 rounded-md bg-blue-200 px-3 py-1 whitespace-nowrap"
            v-tooltip="
              __('Submitted at {date}. Form name: {name}', {
                date: dateFormat(props.row.form.created_at),
                name: props.row.form.name,
              })
            "
            >{{ __("draft") }}</span
          >
        </div>
      </span>

      <span v-else-if="props.column.field === 'created_at'">{{
        $filters.date(props.row.created_at, "D MMM YYYY, H:mm")
      }}</span>

      <span v-else-if="props.column.field === 'created_by'">
        <div
          class="bg-green-500 text-white p-2 text-md rounded-full text-center mr-2 w-9"
          v-tooltip="props.row.created_by.name"
        >
          <div class="w-full text-center">
            {{ getUserNameInitials(props.row.created_by.name) }}
          </div>
        </div>
      </span>

      <span v-else-if="props.column.field === 'updated_at'">{{
        $filters.date(props.row.updated_at, "D MMM YYYY, H:mm")
      }}</span>

      <span v-else-if="props.column.field === 'updated_by'">
        <div
          v-if="props.row.updated_by !== null"
          class="bg-green-500 text-white p-2 text-md rounded-full text-center mr-2 w-9"
          v-tooltip="props.row.updated_by.name"
        >
          <div class="w-full text-center">
            {{ getUserNameInitials(props.row.updated_by.name) }}
          </div>
        </div>
      </span>

      <span v-else-if="props.column.field === 'published_at'">{{
        $filters.date(props.row.published_at, "D MMM YYYY, H:mm")
      }}</span>

      <span v-else-if="props.column.field === 'published_by'">
        <div
          v-if="props.row.published_by !== null"
          class="bg-green-500 text-white p-2 text-md rounded-full text-center mr-2 w-9"
          v-tooltip="props.row.published_by.name"
        >
          <div class="w-full text-center">
            {{ getUserNameInitials(props.row.published_by.name) }}
          </div>
        </div>
      </span>

      <span v-else-if="props.column.field !== 'action'">
        <span v-for="field in collection.fields" :key="field.id">
          <span v-if="field.name == props.column.field">
            <span v-for="meta in props.row.meta" :key="meta.id">
              <span v-if="meta.field_name == props.column.field">
                <span
                  v-if="field.type == 'date'"
                  :class="{
                    'rounded-md bg-gray-100 p-1 mr-1':
                      field.options.repeatable && meta.value !== null,
                  }"
                >
                  <span v-if="field.options.timepicker">{{
                    $filters.date(meta.value, "YYYY-MM-DD hh:mm A")
                  }}</span>
                  <span v-else>{{ $filters.date(meta.value) }}</span>
                </span>
                <span
                  v-else-if="field.type == 'longtext' && meta.value !== null"
                  :title="meta.value"
                  :class="{
                    'rounded-md bg-gray-100 p-1 mr-1':
                      field.options.repeatable && meta.value !== null,
                  }"
                >
                  {{ meta.value.substring(0, 20) }}
                  <span v-if="meta.value.length > 20">...</span>
                </span>
                <span v-else-if="field.type == 'richtext'">
                  <span
                    v-if="meta.value !== ''"
                    class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                    @click="$emit('show-text', field, meta.value)"
                    ><i class="fas fa-align-center"></i
                  ></span>
                </span>
                <span v-else-if="field.type == 'media'">
                  <span
                    v-if="meta.value !== ''"
                    class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                    @click="$emit('show-media', field, meta.value)"
                    ><i class="fa fa-photo-video"></i
                  ></span>
                </span>
                <span v-else-if="field.type == 'relation'">
                  <span
                    v-if="meta.value !== ''"
                    class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                    @click="$emit('show-relationlist', field, meta.value)"
                    ><i class="fa fa-link"></i
                  ></span>
                </span>
                <span v-else>
                  <span
                    :class="{
                      'rounded-md bg-gray-100 p-1 mr-1':
                        field.options.repeatable && meta.value !== null,
                    }"
                    >{{ meta.value }}</span
                  >
                </span>
              </span>
            </span>
          </span>
        </span>
      </span>

      <span v-else-if="props.column.field === 'action'">
        <div class="flex items-center justify-center gap-1 py-2">
          <router-link
            v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
            :to="{
              name: 'projects.content.edit',
              params: {
                project_id: $route.params.project_id,
                col_id: collection_id,
                content_id: props.row.id,
              },
            }"
            class="text-indigo-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50"
          >
            <i class="fa fa-pencil-alt"></i>
          </router-link>
          <router-link
            v-if="isReadonly || !canProject(['owner', 'admin', 'editor'])"
            :to="{
              name: 'projects.content.edit',
              params: {
                project_id: $route.params.project_id,
                col_id: collection_id,
                content_id: props.row.id,
              },
            }"
            class="text-gray-400 p-2 px-3 rounded-md cursor-default bg-gray-50"
            v-tooltip="isReadonly ? __('Read-only') : __('View only')"
          >
            <i class="fa fa-eye"></i>
          </router-link>
          <a
            v-if="!isReadonly && !isComments && canProject(['owner', 'admin'])"
            class="text-orange-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50"
            @click="$emit('move-to-trash-content', props.row)"
          >
            <i class="fa fa-trash-restore"></i>
          </a>
          <a
            v-if="!isReadonly && isComments && canProject(['owner', 'admin', 'editor'])"
            class="text-orange-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50"
            @click="$emit('comment-trash', props.row)"
          >
            <i class="fa fa-trash-restore"></i>
          </a>
        </div>
      </span>
    </template>

    <template #emptystate>
      {{ __("No data found") }}
    </template>
  </ui-table>
</template>

<script>
import { formatDate } from "@/utils/filters";
import UiTable from "@/components/Table/index.js";
import { __ } from "@/admin/translations/engine";

export default {
  name: "ContentTable",

  components: {
    UiTable,
  },

  props: {
    columns: { type: Array, required: true },
    content: { type: Object, required: true },
    collection: { type: Object, required: true },
    collection_id: { type: [Number, String], required: true },
    isReadonly: { type: Boolean, default: false },
    isComments: { type: Boolean, default: false },
    each: { type: Number, default: 15 },
    canProject: { type: Function, required: true },
  },

  emits: [
    "sort-change",
    "selected-rows-change",
    "page-change",
    "per-page-change",
    "column-toggle",
    "show-text",
    "show-media",
    "show-relationlist",
    "move-to-trash-content",
    "comment-trash",
  ],

  methods: {
    __,
    getUserNameInitials(name) {
      let initials = name.split(" ");
      if (initials.length > 1) {
        initials = initials.shift().charAt(0) + initials.pop().charAt(0);
      } else {
        initials = name.substring(0, 2);
      }
      return initials.toUpperCase();
    },
    dateFormat(date) {
      return formatDate(date, "D MMM YYYY, H:mm");
    },
  },
};
</script>
