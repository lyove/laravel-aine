<template>
  <ui-table
    mode="remote"
    :columns="columns"
    :rows="content.data || []"
    :total-rows="content.total || 0"
    :select-options="{ enabled: !isReadonly, disableSelectInfo: true }"
    :search-options="{
      enabled: true,
      trigger: 'input',
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

    <!-- Filters: status tabs -->
    <template #table-filters>
      <ContentBatchBar
        :is-readonly="isReadonly"
        :selected="selected"
        :list-options="listOptions"
        :is-comments="isComments"
        :total-count="totalCount"
        :published-count="publishedCount"
        :draft-count="draftCount"
        :trashed-count="trashedCount"
        :approved-count="approvedCount"
        :pending-count="pendingCount"
        :spam-count="spamCount"
        :trash-count="trashCount"
        :can-project="canProject"
        @comment-bulk="$emit('comment-bulk', $event)"
        @delete-selected="$emit('delete-selected')"
        @publish-selected="$emit('publish-selected')"
        @unpublish-selected="$emit('unpublish-selected')"
        @move-to-trash-selected="$emit('move-to-trash-selected')"
        @restore-selected="$emit('restore-selected')"
        @change-get-items="$emit('change-get-items', $event)"
      />
    </template>

    <!-- Batch actions (shown in selection info bar) -->
    <template #selected-row-actions>
      <div class="flex gap-1">
        <template v-if="isComments">
          <button
            v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
            class="cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('comment-bulk', 'approve')"
          >
            <i class="fa fa-check"></i> {{ __("approve") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
            class="cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('comment-bulk', 'spam')"
          >
            <i class="fa fa-bug"></i> {{ __("mark as spam") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
            class="cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('comment-bulk', 'trash')"
          >
            <i class="fa fa-trash-restore"></i> {{ __("move to trash") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems === 'trash'"
            class="cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('comment-bulk', 'restore')"
          >
            <i class="fa fa-recycle"></i> {{ __("restore") }}
          </button>
          <button
            v-if="selected.length !== 0 && canProject(['owner', 'admin'])"
            class="cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('delete-selected')"
          >
            <i class="fa fa-trash-alt"></i> {{ __("delete") }}
          </button>
        </template>
        <template v-else>
          <button
            v-if="
              selected.length !== 0 &&
              listOptions.getItems !== 'trashed' &&
              canProject(['owner', 'admin'])
            "
            class="cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('publish-selected')"
          >
            <i class="fa fa-cloud-upload-alt"></i> {{ __("publish") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
            class="cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('unpublish-selected')"
          >
            <i class="fa fa-cloud-download-alt"></i> {{ __("unpublish") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
            class="cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('move-to-trash-selected')"
          >
            <i class="fa fa-trash-restore"></i> {{ __("move to trash") }}
          </button>
          <button
            v-if="selected.length !== 0 && listOptions.getItems === 'trashed'"
            class="cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('restore-selected')"
          >
            <i class="fa fa-recycle"></i> {{ __("restore") }}
          </button>
          <button
            v-if="selected.length !== 0 && canProject(['owner', 'admin'])"
            class="cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-blue-100"
            @click="$emit('delete-selected')"
          >
            <i class="fa fa-trash-alt"></i> {{ __("delete") }}
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
import ContentBatchBar from "./ContentBatchBar.vue";
import { __ } from "@/admin/translations/engine";

export default {
  name: "ContentTable",

  components: {
    UiTable,
    ContentToolbar,
    ContentBatchBar,
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
    // ContentToolbar props
    form_count: { type: Number, default: 0 },
    localeFilter: { type: String, default: "" },
    localeOptions: { type: Array, default: () => [] },
    // ContentBatchBar props
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
    // ContentBatchBar events
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
