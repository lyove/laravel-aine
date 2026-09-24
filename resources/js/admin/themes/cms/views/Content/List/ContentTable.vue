<template>
  <ui-table
    mode="remote"
    :columns="columns"
    :rows="content.data || []"
    :total-rows="content.total || 0"
    :select-options="{ enabled: !isReadonly }"
    :search-options="{
      enabled: true,
      trigger: 'enter',
      placeholder: 'Search...',
    }"
    @search="onTableSearch"
    :pagination-options="{
      enabled: true,
      perPage: each,
    }"
    :sort-options="{ enabled: true }"
    :show-column-toggle="true"
    @sort-change="$emit('sort-change', $event)"
    @selected-rows-change="$emit('selected-rows-change', $event)"
    @page-change="$emit('page-change', $event)"
    @per-page-change="$emit('per-page-change', $event)"
    @column-toggle="$emit('column-toggle', $event)"
  >
    <!-- Toolbar: title, action buttons, language filter -->
    <template #table-toolbar>
      <ContentToolbar
        :collection="collection"
        :collection_id="collection_id"
        :is-readonly="isReadonly"
        :form_count="form_count"
        :locale-filter="localeFilter"
        :locale-options="localeOptions"
        :can-project="canProject"
        @export="$emit('export')"
        @import="$emit('import', $event)"
        @locale-change="$emit('locale-change', $event)"
      />
    </template>

    <!-- Batch actions on the right side of search bar -->
    <template #table-actions>
      <div class="flex items-center gap-2">
        <ui-dropdown>
          <template #trigger>
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 shadow-sm">
              <i class="fa fa-bolt text-gray-400"></i>
              {{ __("Actions") }}
              <i class="fa fa-caret-down text-gray-400 ml-0.5"></i>
            </button>
          </template>
          <template #content>
            <div class="py-1 min-w-[160px]">
              <template v-if="selected.length === 0">
                <div class="px-3 py-2 text-sm text-gray-400 italic">
                  {{ __("No rows selected") }}
                </div>
              </template>
              <template v-else-if="isComments">
                <a
                  v-if="listOptions.getItems !== 'trash'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer"
                  @click="$emit('comment-bulk', 'approve')"
                >
                  <i class="fa fa-check text-green-500 w-4 text-center"></i>
                  {{ __("Approve") }}
                </a>
                <a
                  v-if="listOptions.getItems !== 'trash'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer"
                  @click="$emit('comment-bulk', 'spam')"
                >
                  <i class="fa fa-bug text-amber-500 w-4 text-center"></i>
                  {{ __("Mark as spam") }}
                </a>
                <a
                  v-if="listOptions.getItems !== 'trash'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer"
                  @click="$emit('comment-bulk', 'trash')"
                >
                  <i class="fa fa-trash-restore text-orange-500 w-4 text-center"></i>
                  {{ __("Move to trash") }}
                </a>
                <a
                  v-if="listOptions.getItems === 'trash'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 cursor-pointer"
                  @click="$emit('comment-bulk', 'restore')"
                >
                  <i class="fa fa-recycle text-blue-500 w-4 text-center"></i>
                  {{ __("Restore") }}
                </a>
                <div v-if="listOptions.getItems !== 'trash' && canProject(['owner', 'admin'])" class="border-t border-gray-100 my-1"></div>
                <a
                  v-if="canProject(['owner', 'admin'])"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 cursor-pointer"
                  @click="$emit('delete-selected')"
                >
                  <i class="fa fa-trash-alt w-4 text-center"></i>
                  {{ __("Delete permanently") }}
                </a>
              </template>
              <template v-else>
                <a
                  v-if="listOptions.getItems !== 'trashed' && canProject(['owner', 'admin'])"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 cursor-pointer"
                  @click="$emit('publish-selected')"
                >
                  <i class="fa fa-cloud-upload-alt text-green-500 w-4 text-center"></i>
                  {{ __("Publish") }}
                </a>
                <a
                  v-if="listOptions.getItems !== 'trashed'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                  @click="$emit('unpublish-selected')"
                >
                  <i class="fa fa-cloud-download-alt text-gray-400 w-4 text-center"></i>
                  {{ __("Unpublish") }}
                </a>
                <a
                  v-if="listOptions.getItems !== 'trashed'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer"
                  @click="$emit('move-to-trash-selected')"
                >
                  <i class="fa fa-trash-restore text-orange-500 w-4 text-center"></i>
                  {{ __("Move to trash") }}
                </a>
                <a
                  v-if="listOptions.getItems === 'trashed'"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 cursor-pointer"
                  @click="$emit('restore-selected')"
                >
                  <i class="fa fa-recycle text-blue-500 w-4 text-center"></i>
                  {{ __("Restore") }}
                </a>
                <div v-if="listOptions.getItems !== 'trashed' && canProject(['owner', 'admin'])" class="border-t border-gray-100 my-1"></div>
                <a
                  v-if="canProject(['owner', 'admin'])"
                  class="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 cursor-pointer"
                  @click="$emit('delete-selected')"
                >
                  <i class="fa fa-trash-alt w-4 text-center"></i>
                  {{ __("Delete permanently") }}
                </a>
              </template>
            </div>
          </template>
        </ui-dropdown>
      </div>
    </template>

    <!-- Status tabs in selection info bar -->
    <template #selected-row-actions>
      <div class="flex items-center gap-1">
        <template v-if="isComments">
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'all' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'all')"
          >
            {{ __("All") }} ({{ totalCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'approved' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'approved')"
          >
            {{ __("Approved") }} ({{ approvedCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'pending' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'pending')"
          >
            {{ __("Pending") }} ({{ pendingCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'spam' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'spam')"
          >
            {{ __("Spam") }} ({{ spamCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'trash' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'trash')"
          >
            {{ __("Trash") }} ({{ trashCount }})
          </button>
        </template>
        <template v-else>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'all' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'all')"
          >
            {{ __("All") }} ({{ totalCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'published' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'published')"
          >
            {{ __("Published") }} ({{ publishedCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'draft' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'draft')"
          >
            {{ __("Draft") }} ({{ draftCount }})
          </button>
          <button
            class="px-2 py-1 text-sm rounded-md hover:bg-blue-100"
            :class="listOptions.getItems == 'trashed' ? 'bg-blue-200 font-medium' : 'text-blue-700'"
            @click="$emit('change-get-items', 'trashed')"
          >
            {{ __("Trashed") }} ({{ trashedCount }})
          </button>
        </template>
      </div>
    </template>

    <!-- Cell rendering -->
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
import ContentToolbar from "./ContentToolbar.vue";
import UiDropdown from "@/components/Dropdown.vue";
import { __ } from "@/admin/translations/engine";

export default {
  name: "ContentTable",

  components: {
    UiTable,
    ContentToolbar,
    UiDropdown,
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
    form_count: { type: Number, default: 0 },
    localeFilter: { type: String, default: "" },
    localeOptions: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
    listOptions: { type: Object, required: true },
    totalCount: { type: Number, default: 0 },
    publishedCount: { type: Number, default: 0 },
    draftCount: { type: Number, default: 0 },
    trashedCount: { type: Number, default: 0 },
    approvedCount: { type: Number, default: 0 },
    pendingCount: { type: Number, default: 0 },
    spamCount: { type: Number, default: 0 },
    trashCount: { type: Number, default: 0 },
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
    // ContentToolbar events
    "export",
    "import",
    "table-search",
    "locale-change",
    // Status tabs & batch actions events
    "comment-bulk",
    "delete-selected",
    "publish-selected",
    "unpublish-selected",
    "move-to-trash-selected",
    "restore-selected",
    "change-get-items",
  ],

  methods: {
    __,
    onTableSearch({ searchTerm }) {
      this.$emit("table-search", searchTerm);
    },
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
