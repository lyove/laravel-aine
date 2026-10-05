<template>
  <div class="admin__project-content-list relative h-full flex flex-col">
    <div v-show="!embedded" class="shrink-0">
      <project-header :project="project"></project-header>
    </div>

    <div class="flex flex-1 overflow-y-auto">
      <div v-show="!embedded" class="w-3/12 bg-white overflow-x-hidden">
        <content-sidebar :project="project"></content-sidebar>
      </div>

      <div class="p-4 overflow-x-auto" :class="embedded ? 'w-full' : 'w-9/12'">
        <div
          v-if="$route.params.col_id !== undefined"
          class="admin__project-content-table"
        >
          <ui-table
            mode="remote"
            :columns="tableColumns"
            :rows="content.data || []"
            :total-rows="content.total || 0"
            :select-options="{ enabled: !isReadonly }"
            :search-options="{
              enabled: true,
              trigger: 'enter',
              placeholder: __('Search...'),
            }"
            @search="onTableSearch"
            :pagination-options="{
              enabled: true,
              perPage: each,
              position: 'bottom',
            }"
            :sort-options="{ enabled: true }"
            :show-column-toggle="!relationSelect"
            @sort-change="onSortChange"
            @selected-rows-change="onSelectedRowsChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
            @column-toggle="onColumnToggle"
          >
            <!-- Toolbar: title, action buttons, language filter -->
            <template #table-toolbar>
              <div id="table-toolbar" class="h-10 flex justify-end items-center font-bold text-lg mb-2">
                <div class="flex-1">
                  {{ __(collection.name) }}
                  <small class="text-gray-400 ml-1">#{{ collection.slug }}</small>
                </div>

                <router-link
                  v-if="
                    !relationSelect &&
                    !isReadonly &&
                    canProject(['owner', 'admin', 'editor'])
                  "
                  :to="{
                    name: 'projects.content.forms',
                    params: {
                      project_id: $route.params.project_id,
                      col_id: $route.params.col_id,
                    },
                  }"
                  class="bg-green-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150 mr-2"
                >
                  <i class="fab fa-wpforms"></i> {{ __("Forms") }} ({{
                    form_count
                  }})
                </router-link>

                <router-link
                  v-if="
                    !relationSelect &&
                    !isReadonly &&
                    canProject(['owner', 'admin', 'editor'])
                  "
                  :to="{
                    name: 'projects.content.new',
                    params: {
                      project_id: $route.params.project_id,
                      col_id: $route.params.col_id,
                    },
                  }"
                  class="bg-indigo-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150"
                >
                  {{ __("+ Create New") }}
                </router-link>

                <button
                  v-if="!relationSelect"
                  type="button"
                  class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
                  @click="exportContent()"
                >
                  <i class="fa fa-download"></i> {{ __("Export") }}
                </button>

                <input
                  v-if="!relationSelect && !isReadonly && canProject(['owner', 'admin'])"
                  ref="importFile"
                  type="file"
                  accept=".json,.csv"
                  class="hidden"
                  @change="importContent($event)"
                />

                <button
                  v-if="
                    !relationSelect &&
                    !isReadonly &&
                    canProject(['owner', 'admin'])
                  "
                  type="button"
                  class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
                  @click="$refs.importFile.click()"
                >
                  <i class="fa fa-upload"></i> {{ __("Import") }}
                </button>

                <ui-button
                  color="green-500"
                  v-if="relationSelect && selected.length !== 0"
                  @click="addSelected()"
                  ><i class="fa fa-link"></i> {{ __("Add Selected") }}</ui-button
                >
              </div>
            </template>

            <!-- Status filter tabs -->
            <template #table-filters>
              <div class="flex items-center gap-4">
                <div v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])" class="flex">
                  <div
                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                    @click="changeGetItems('all')"
                    :class="{ 'bg-gray-200': listOptions.getItems == 'all' }"
                  >
                    {{ __("All") }}({{ totalCount }})
                  </div>
                  <div
                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                    @click="changeGetItems('published')"
                    :class="{
                      'bg-gray-200': listOptions.getItems == 'published',
                    }"
                  >
                    {{ __("Published") }}({{ publishedCount }})
                  </div>
                  <div
                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                    @click="changeGetItems('draft')"
                    :class="{ 'bg-gray-200': listOptions.getItems == 'draft' }"
                  >
                    {{ __("Draft") }}({{ draftCount }})
                  </div>
                  <div
                    v-if="!hasAddSelectedListener"
                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                    @click="changeGetItems('trashed')"
                    :class="{
                      'bg-gray-200': listOptions.getItems == 'trashed',
                    }"
                  >
                    {{ __("Trashed") }}({{ trashedCount }})
                  </div>
                </div>
              </div>
            </template>

            <template #table-row="props">
              <span v-if="props.column.field === 'id'">{{ props.row.id }}</span>

              <span v-else-if="props.column.field === 'status'">
                <div v-if="props.row.form_id === null">
                  <span
                    v-if="props.row.published_at !== null"
                    class="text-gray-500 rounded-md bg-green-200 px-3 py-1 whitespace-nowrap"
                    >{{ __("published") }}</span
                  >
                  <span
                    v-else
                    class="text-gray-500 rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap"
                    >{{ __("draft") }}</span
                  >
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
                              safeOptions(field).repeatable && meta.value !== null,
                          }"
                        >
                          <span v-if="safeOptions(field).timepicker">{{
                            $filters.date(meta.value, "YYYY-MM-DD hh:mm A")
                          }}</span>
                          <span v-else>{{ $filters.date(meta.value) }}</span>
                        </span>
                        <span
                          v-else-if="
                            field.type == 'longtext' && meta.value !== null
                          "
                          :title="meta.value"
                          :class="{
                            'rounded-md bg-gray-100 p-1 mr-1':
                              safeOptions(field).repeatable && meta.value !== null,
                          }"
                        >
                          {{ meta.value.substring(0, 20) }}
                          <span v-if="meta.value.length > 20">...</span>
                        </span>
                        <span v-else-if="field.type == 'richtext'">
                          <span
                            v-if="meta.value"
                            class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                            @click="showText(field, meta.value)"
                            ><i class="fas fa-align-center"></i
                          ></span>
                        </span>
                        <span v-else-if="field.type == 'media'">
                          <span
                            v-if="meta.value"
                            class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                            @click="showMedia(field, meta.value)"
                            ><i class="fa fa-photo-video"></i
                          ></span>
                        </span>
                        <span v-else-if="field.type == 'relation'">
                          <span
                            v-if="meta.value"
                            class="text-indigo-500 cursor-pointer hover:bg-gray-100 rounded-md p-2"
                            @click="showRelationlist(field, meta.value)"
                            ><i class="fa fa-link"></i
                          ></span>
                        </span>
                        <span v-else>
                          <span
                            :class="{
                              'rounded-md bg-gray-100 p-1 mr-1':
                                safeOptions(field).repeatable && meta.value !== null,
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
                <div v-if="!relationSelect" class="flex items-center justify-center gap-1 py-2">
                  <router-link
                    v-if="
                      !isReadonly && canProject(['owner', 'admin', 'editor'])
                    "
                    :to="{
                      name: 'projects.content.edit',
                      params: {
                        project_id: $route.params.project_id,
                        col_id: $route.params.col_id,
                        content_id: props.row.id,
                      },
                    }"
                    class="text-indigo-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50"
                  >
                    <i class="fa fa-pencil-alt"></i>
                  </router-link>
                  <router-link
                    v-if="
                      isReadonly || !canProject(['owner', 'admin', 'editor'])
                    "
                    :to="{
                      name: 'projects.content.edit',
                      params: {
                        project_id: $route.params.project_id,
                        col_id: $route.params.col_id,
                        content_id: props.row.id,
                      },
                    }"
                    class="text-gray-400 p-2 px-3 rounded-md cursor-default bg-gray-50"
                    v-tooltip="isReadonly ? __('Read-only') : __('View only')"
                  >
                    <i class="fa fa-eye"></i>
                  </router-link>
                  <a
                    v-if="
                      !isReadonly &&
                      canProject(['owner', 'admin'])
                    "
                    class="text-orange-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50"
                    @click="moveToTrashContent(props.row)"
                  >
                    <i class="fa fa-trash-restore"></i>
                  </a>
                </div>
              </span>
            </template>

            <!-- Table actions -->
            <template #table-actions>
              <div v-if="!relationSelect && !isReadonly && canProject(['owner', 'admin', 'editor'])" class="flex items-center gap-2">
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
                      <template v-else>
                        <a
                          v-if="listOptions.getItems !== 'trashed' && canProject(['owner', 'admin'])"
                          class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700 cursor-pointer"
                          @click="publishSelected"
                        >
                          <i class="fa fa-cloud-upload-alt text-green-500 w-4 text-center"></i>
                          {{ __("Publish") }}
                        </a>
                        <a
                          v-if="listOptions.getItems !== 'trashed'"
                          class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                          @click="unPublishSelected"
                        >
                          <i class="fa fa-cloud-download-alt text-gray-400 w-4 text-center"></i>
                          {{ __("Unpublish") }}
                        </a>
                        <a
                          v-if="listOptions.getItems !== 'trashed'"
                          class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer"
                          @click="moveToTrashSelected"
                        >
                          <i class="fa fa-trash-restore text-orange-500 w-4 text-center"></i>
                          {{ __("Move to trash") }}
                        </a>
                        <a
                          v-if="listOptions.getItems === 'trashed'"
                          class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 cursor-pointer"
                          @click="restoreSelected"
                        >
                          <i class="fa fa-recycle text-blue-500 w-4 text-center"></i>
                          {{ __("Restore") }}
                        </a>
                        <div v-if="listOptions.getItems !== 'trashed' && canProject(['owner', 'admin'])" class="border-t border-gray-100 my-1"></div>
                        <a
                          v-if="canProject(['owner', 'admin'])"
                          class="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 cursor-pointer"
                          @click="deleteSelected"
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

            <template #emptystate>
              {{ __("No data found") }}
            </template>
          </ui-table>

          <ui-modal
            maxWidth="5xl"
            :show="openTextModal"
            @close="closeTextModal"
          >
            <template #title>
              {{ textModalFieldName }}
            </template>

            <template #content>
              <div v-html="textRecord"></div>
            </template>

            <template #footer>
              <ui-button
                color="gray-200"
                hover="gray-300"
                @click="closeTextModal"
              >
                <span class="text-gray-800">{{ __("Close") }}</span>
              </ui-button>
            </template>
          </ui-modal>

          <ui-modal
            maxWidth="5xl"
            :show="openMediaModal"
            @close="closeMediaModal"
          >
            <template #title>
              {{ mediaModalFieldName }}
            </template>

            <template #content>
              <div
                class="my-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4"
              >
                <div
                  v-for="file in mediaRecords"
                  :key="file.id"
                  class="relative"
                >
                  <div class="rounded-md bg-white">
                    <div
                      class="w-full h-48 absolute top-0 rounded-md left-0 bg-black bg-opacity-70 items-center text-center flex opacity-0 hover:opacity-100 z-10"
                    >
                      <div class="w-full z-20">
                        <div
                          class="text-sm text-white w-full mb-3"
                          :title="file.name"
                        >
                          {{ file.name.substring(0, 20) }}
                          <span v-if="file.name.length > 20">...</span>
                        </div>
                        <div class="text-sm w-full">
                          <a :href="file.full_url" target="_blank">
                            <i
                              v-if="
                                file.type == 'jpg' ||
                                file.type == 'jpeg' ||
                                file.type == 'png' ||
                                file.type == 'bmp' ||
                                file.type == 'gif' ||
                                file.type == 'webp'
                              "
                              class="fa fa-eye text-gray-100 cursor-pointer hover:text-gray-200 text-lg mr-2"
                            ></i>
                            <i
                              v-else
                              class="fa fa-file-download text-gray-100 cursor-pointer hover:text-gray-200 text-lg mr-2"
                            ></i>
                          </a>
                        </div>
                      </div>
                    </div>

                    <img
                      class="w-full h-48 object-cover rounded-md border rounded-md"
                      v-if="
                        file.type == 'jpg' ||
                        file.type == 'jpeg' ||
                        file.type == 'png' ||
                        file.type == 'gif' ||
                        file.type == 'webp'
                      "
                      :src="file.full_url_thumb"
                    />

                    <div
                      class="w-full h-48 object-cover flex items-center text-center border rounded-md"
                      v-else
                    >
                      <div class="w-full">
                        <i
                          v-if="file.type == 'pdf'"
                          class="far fa-file-pdf text-5xl text-red-500"
                        ></i>
                        <i
                          v-else-if="
                            file.type == 'avi' ||
                            file.type == 'mp4' ||
                            file.type == 'mov' ||
                            file.type == 'webm'
                          "
                          class="far fa-file-video text-5xl text-blue-500"
                        ></i>
                        <i
                          v-else-if="
                            file.type == 'wav' ||
                            file.type == 'ogg' ||
                            file.type == 'mpeg'
                          "
                          class="far fa-file-audio text-5xl text-yellow-500"
                        ></i>
                        <i
                          v-else-if="file.type == 'xls' || file.type == 'xlsx'"
                          class="far fa-file-excel text-5xl text-green-500"
                        ></i>
                        <i
                          v-else-if="file.type == 'doc' || file.type == 'docx'"
                          class="far fa-file-word text-5xl text-blue-500"
                        ></i>
                        <i
                          v-else-if="file.type == 'zip'"
                          class="far fa-file-archive text-5xl text-yellow-300"
                        ></i>
                        <i
                          v-else
                          class="far fa-file text-5xl text-gray-400"
                        ></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <template #footer>
              <ui-button
                color="gray-100"
                hover="gray-200"
                @click="closeMediaModal"
              >
                <span class="text-gray-800">{{ __("Close") }}</span>
              </ui-button>
            </template>
          </ui-modal>

          <ui-modal
            maxWidth="5xl"
            :show="openRelationModal"
            @close="closeRelationModal"
          >
            <template #title>
              {{ relationModalFieldName }}
            </template>

            <template #content>
              <div
                class="overflow-x-auto sm:rounded-md"
                v-if="relationRecords.length > 0"
              >
                <table class="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr>
                      <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"
                      >
                        {{ __("Collection") }}
                      </th>
                      <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"
                      >
                        {{ __("Status") }}
                      </th>

                      <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"
                      >
                        <div class="w-full flex justify-between item-center">
                          {{ __("Created At") }}
                        </div>
                      </th>
                      <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"
                        :class="{ 'w-full': index === 0 }"
                        v-for="(field, index) in relationRecords.collection
                          .fields"
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

                  <tr v-for="item in relationRecords" :key="item.id">
                    <td class="pl-2 py-4 text-sm text-center w-px">
                      <span
                        class="text-gray-500 text-sm rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap"
                      >
                        {{ __(relationRecords.collection.name) }}
                      </span>
                    </td>
                    <td class="pl-2 py-4 text-sm text-center w-px">
                      <span
                        v-if="item.published_at !== null"
                        class="text-gray-500 text-sm rounded-md bg-green-200 px-3 py-1 whitespace-nowrap"
                        >{{ __("published") }}</span
                      >
                      <span
                        v-else
                        class="text-gray-500 text-sm rounded-md bg-gray-200 px-3 py-1 whitespace-nowrap"
                        >{{ __("draft") }}</span
                      >
                    </td>
                    <td
                      class="px-6 py-3 text-sm w-px whitespace-nowrap text-gray-600"
                    >
                      {{ $filters.date(item.created_at, "D MMM YYYY, H:mm") }}
                    </td>
                    <td
                      class="px-6 py-3 text-sm whitespace-nowrap"
                      :class="{ 'w-full': index === 0, 'w-auto': index !== 0 }"
                      v-for="(field, index) in relationRecords.collection
                        .fields"
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
                            'rounded-md bg-gray-100 p-1 mr-1':
                              safeOptions(field).repeatable &&
                              meta.value !== null,
                          }"
                        >
                          <span v-if="field.type == 'date'">{{
                            $filters.date(meta.value)
                          }}</span>
                          <span
                            v-else-if="
                              field.type == 'longtext' && meta.value !== null
                            "
                            :title="meta.value"
                          >
                            {{ meta.value.substring(0, 20) }}
                            <span v-if="meta.value.length > 20">...</span>
                          </span>
                          <span v-else>{{ meta.value }}</span>
                        </span>
                      </span>
                    </td>
                  </tr>
                </table>
              </div>
            </template>

            <template #footer>
              <ui-button
                color="gray-100"
                hover="gray-200"
                @click="closeRelationModal"
              >
                <span class="text-gray-800">{{ __("Close") }}</span>
              </ui-button>
            </template>
          </ui-modal>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  ref,
  computed,
  watch,
  onMounted,
  getCurrentInstance,
  useAttrs,
} from "vue";
import { useRoute } from "vue-router";
import axios from "axios";

import { formatDate } from "@/utils/filters";
import { __ } from "@/admin/translations/engine";
import { useAdminStore } from "@/admin/store";

import ProjectHeader from "@/admin/components/ProjectHeader.vue";
import ContentSidebar from "@/admin/components/ContentSidebar.vue";
import projectBreadcrumb from "@/admin/mixins/projectBreadcrumb";

import UiTable from "@/components/Table";
import UiModal from "@/components/Modal.vue";
import UiButton from "@/components/Button.vue";
import UiDropdown from "@/components/Dropdown.vue";


/**
 * @param {Object} options
 * @param {Function} [options.collectionId] getter returning the current collection id (number|undefined)
 * @param {number} [options.eachProp] items per page (default 15)
 * @param {Function} [options.relationSelect] getter indicating relation-select mode
 * @param {Function} [options.relationType] getter for the relation type (==1 makes row selection single-choice)
 * @param {Function} [options.onAddSelected] callback when relation selection completes (replaces $emit('addSelected'))
 * @param {boolean} [options.enableCollectionWatch] whether to auto-refresh the list when the collection changes
 * @param {Function} [options.initialProject] getter for the initial project value
 */
function useContentList(options = {}) {
  const {
    collectionId = () => undefined,
    eachProp = 15,
    relationSelect = () => false,
    relationType = () => undefined,
    onAddSelected = null,
    enableCollectionWatch = false,
    initialProject = () => ({}),
  } = options;

  const route = useRoute();
  const store = useAdminStore();
  const instance = getCurrentInstance();
  const attrs = useAttrs();

  /* ---------------- State ---------------- */
  const project = ref(initialProject());
  const collection = ref({});
  const content = ref({});
  const totalCount = ref(0);
  const publishedCount = ref(0);
  const draftCount = ref(0);
  const trashedCount = ref(0);
  const search = ref("");
  const columns = ref({});
  const listOptions = ref({
    orderBy: "created_at",
    criteria: "ASC",
    sortByMeta: 0,
    getItems: "all",
  });
  const openTextModal = ref(false);
  const textRecord = ref(null);
  const textModalFieldName = ref(null);
  const openMediaModal = ref(false);
  const mediaRecords = ref({});
  const mediaModalFieldName = ref(null);
  const selected = ref([]);
  const selectAll = ref(false);
  const openRelationModal = ref(false);
  const relationRecords = ref({
    collection: {
      fields: {},
    },
  });
  const relationModalFieldName = ref(null);
  const each = ref(eachProp);
  const form_count = ref(0);

  /* ---------------- Computed ---------------- */
  const isReadonly = computed(() => {
    const p = store.currentProject || project.value;
    return p && (p.is_readonly || !p.status);
  });

  const hasAddSelectedListener = computed(() => attrs && attrs.onAddSelected);

  /* ---------------- Methods ---------------- */
  function safeOptions(field) {
    if (!field.options) {
      return {};
    }
    if (typeof field.options === "string") {
      try {
        return JSON.parse(field.options);
      } catch (e) {
        return {};
      }
    }
    return field.options;
  }

  function sanitizeHtml(html) {
    if (!html) {
      return "";
    }
    const doc = new DOMParser().parseFromString(html, "text/html");
    const dangerous = doc.querySelectorAll(
      "script, iframe, object, embed, link, meta",
    );
    dangerous.forEach((el) => el.remove());
    doc.querySelectorAll("*").forEach((el) => {
      for (const attr of Array.from(el.attributes)) {
        if (attr.name.startsWith("on") || attr.name === "srcdoc") {
          el.removeAttribute(attr.name);
        }
      }
    });
    return doc.body.innerHTML;
  }

  function canProject(roles) {
    const p = store.currentProject || project.value;
    return Array.isArray(roles) && roles.includes(p && p.my_role);
  }

  function getContent(page) {
    if (typeof page === "undefined") {
      page = 1;
    }

    const cid = collectionId();

    if (cid === undefined || route.params.project_id === undefined) {
      return;
    }

    axios
      .get(
        "projects/" +
          route.params.project_id +
          "/collections/" +
          cid + "/entries" +
          "?page=" +
          page +
          "&search=" +
          search.value +
          "&orderBy=" +
          listOptions.value.orderBy +
          "&cr=" +
          listOptions.value.criteria +
          "&sbm=" +
          listOptions.value.sortByMeta +
          "&each=" +
          each.value +
          "&getItems=" +
          listOptions.value.getItems,
      )
      .then((response) => {
        project.value = response.data.project;
        collection.value = response.data.collection;
        content.value = response.data.content;
        form_count.value = response.data.forms;

        totalCount.value = response.data.totalCount;
        publishedCount.value = response.data.published;
        draftCount.value = response.data.draft;
        trashedCount.value = response.data.trashed;

        if (content.value.data == 0) {
          selectAll.value = false;
        }

        if (store.columnSettings.length == 0) {
          setInitialColumns();
          store.setColumns({
            project_id: route.params.project_id,
            collection_id: cid,
            columns: columns.value,
          });
        } else {
          let storeHasSettings = store.columnSettings.some(
            (o) =>
              o.project_id == route.params.project_id && o.collection_id == cid,
          );

          if (!storeHasSettings) {
            setInitialColumns();
            store.setColumns({
              project_id: route.params.project_id,
              collection_id: cid,
              columns: columns.value,
            });
          } else {
            columns.value = store.columnSettings.find(
              (o) =>
                o.project_id == route.params.project_id &&
                o.collection_id == cid,
            ).columns;
          }
        }

        selectAll.value = false;
      });
  }

  function setInitialColumns() {
    columns.value = {
      created_at: true,
      updated_at: true,
      published_at: false,
      created_by: false,
      updated_by: false,
      published_by: false,
    };

    (collection.value.fields || []).forEach((field) => {
      if (safeOptions(field).hideInContentList) {
        columns.value[field.name] = false;
      } else {
        columns.value[field.name] = true;
      }
    });
  }

  function changeColumnSettings() {
    store.updateColumn({
      project_id: route.params.project_id,
      collection_id: collectionId(),
      columns: columns.value,
    });
  }

  function addSelected() {
    if (onAddSelected) {
      onAddSelected({
        selected: selected.value.slice(),
        collection_id: collectionId(),
      });
    }
    selected.value = [];
  }

  function exportContent() {
    axios({
      url:
        "projects/" +
        route.params.project_id +
        "/collections/" +
        collectionId() +
        "/export?format=json",
      method: "GET",
      responseType: "blob",
    }).then((response) => {
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", "content-export.json");
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }

  function importContent(event) {
    const file = event.target.files[0];
    if (!file) {
      return;
    }

    const formData = new FormData();
    formData.append("file", file);

    axios
      .post(
        "projects/" + route.params.project_id + "/collections/" + collectionId() + "/import",
        formData,
      )
      .then((response) => {
        const rawMsg = response.data.message;
        instance?.proxy?.$toast.success(
          typeof rawMsg === 'string' ? __(rawMsg) : __("Content imported."),
        );
        getContent();
      })
      .catch((error) => {
        const raw = error.response?.data?.message;
        if (typeof raw === 'string') {
          instance?.proxy?.$toast.error(__(raw));
        }
      })
      .finally(() => {
        event.target.value = "";
      });
  }

  function closeTextModal() {
    openTextModal.value = false;
  }

  function showText(field, value) {
    openTextModal.value = true;
    textRecord.value = sanitizeHtml(value);
    textModalFieldName.value = __(field.label);
  }

  function closeMediaModal() {
    openMediaModal.value = false;
  }

  async function showMedia(field, files) {
    if (!files) {
      return;
    }
    await axios
      .post("projects/" + route.params.project_id + "/entries/get-selected-files", {
        data: files.split(","),
      })
      .then((response) => {
        openMediaModal.value = true;
        mediaRecords.value = response.data;
        mediaModalFieldName.value = __(field.label);
        instance?.proxy?.$forceUpdate();
      })
      .catch((error) => {
        console.error("showMedia error:", error);
        instance?.proxy?.$toast.error(__("Failed to load media files."));
      });
  }

  function closeRelationModal() {
    openRelationModal.value = false;
  }

  async function showRelationlist(field, value) {
    if (!value) {
      return;
    }
    let options;
    try {
      options = typeof field.options === "string" ? JSON.parse(field.options) : (field.options || {});
    } catch (e) {
      options = {};
    }
    if (options.relation === undefined) {
      return;
    }

    let data = {
      selected: value.split(","),
      collection_id: options.relation.collection,
    };

    await axios
      .post("projects/" + route.params.project_id + "/entries/get-selected-records", {
        data: data,
      })
      .then((response) => {
        openRelationModal.value = true;
        relationRecords.value = response.data.content;
        relationRecords.value.collection = response.data.collection;
        relationModalFieldName.value = __(field.label);
        instance?.proxy?.$forceUpdate();
      })
      .catch((error) => {
        console.error("showRelationlist error:", error);
        instance?.proxy?.$toast.error(__("Failed to load related records."));
      });
  }

  function changeGetItems(status) {
    listOptions.value.getItems = status;
    getContent();
    selected.value = [];
    selectAll.value = false;
  }

  function publishSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to publish all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/entries/publish-selected",
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Selected items has been published"),
              );
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function unPublishSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to unpublish all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/entries/unpublish-selected",
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Selected items has been unpublished"),
              );
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function moveToTrashContent(item) {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to move this item to the trash?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .delete(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/" +
                item.id +
                "/move-to-trash",
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Content moved to the trash."),
              );
              getContent();
            });
        }
      });
  }

  function moveToTrashSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to move all selected items to the trash?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/entries/move-to-trash-selected",
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Selected items has been moved to the trash"),
              );
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function deleteSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to delete all selected items permanently?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/entries/delete-selected",
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Selected items has been deleted"),
              );
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function restoreSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to restore all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "projects/" +
                route.params.project_id +
                "/collections/" +
                route.params.col_id +
                "/entries/restore-selected",
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(
                __("Selected items has been restored"),
              );
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function getUserNameInitials(name) {
    let initials = name.split(" ");

    if (initials.length > 1) {
      initials = initials.shift().charAt(0) + initials.pop().charAt(0);
    } else {
      initials = name.substring(0, 2);
    }

    return initials.toUpperCase();
  }

  function dateFormat(date) {
    return formatDate(date, "D MMM YYYY, H:mm");
  }

  /* ---------------- Lifecycle ---------------- */
  onMounted(() => {
    const cid = collectionId();
    if (cid !== undefined) {
      const filter = route.query.filter;
      if (filter && ["all", "published", "draft", "trashed"].includes(filter)) {
        listOptions.value.getItems = filter;
      }
      getContent();
    }
  });

  if (enableCollectionWatch) {
    watch(collectionId, (newVal) => {
      search.value = "";
      selected.value = [];
      selectAll.value = false;
      if (newVal !== undefined && route.params.project_id !== undefined) {
        getContent();
      }
    });
  }

  /* ---------------- Exposed API ---------------- */
  return {
    // state
    project,
    collection,
    content,
    totalCount,
    publishedCount,
    draftCount,
    trashedCount,
    search,
    columns,
    listOptions,
    openTextModal,
    textRecord,
    textModalFieldName,
    openMediaModal,
    mediaRecords,
    mediaModalFieldName,
    selected,
    openRelationModal,
    relationRecords,
    relationModalFieldName,
    each,
    form_count,
    // computed
    isReadonly,
    hasAddSelectedListener,
    // methods
    safeOptions,
    canProject,
    getContent,
    changeColumnSettings,
    addSelected,
    exportContent,
    importContent,
    closeTextModal,
    showText,
    closeMediaModal,
    showMedia,
    closeRelationModal,
    showRelationlist,
    changeGetItems,
    publishSelected,
    unPublishSelected,
    moveToTrashContent,
    moveToTrashSelected,
    deleteSelected,
    restoreSelected,
    getUserNameInitials,
    dateFormat,
  };
}

export default {
  components: {
    ProjectHeader,
    ContentSidebar,
    UiTable,
    UiModal,
    UiButton,
    UiDropdown,
  },

  mixins: [projectBreadcrumb],

  props: {
    relationSelect: {
      type: Boolean,
      default: false,
    },
    collection_id: {
      type: Number,
    },
    eachProp: {
      type: Number,
      default: 15,
    },
    relation_type: {
      type: Number,
    },
    // Embedded mode renders only the table body (used by Edit/New relation pickers)
    embedded: {
      type: Boolean,
      default: false,
    },
  },

  emits: ["addSelected"],

  props: {
    relationSelect: {
      type: Boolean,
      default: false,
    },
    collection_id: {
      type: Number,
      default: undefined,
    },
    eachProp: {
      type: Number,
      default: 15,
    },
    relation_type: {
      type: Number,
      default: undefined,
    },
    embedded: {
      type: Boolean,
      default: false,
    },
  },

  emits: ["addSelected"],

  data() {
    return {
      project: useAdminStore().currentProject || {},
    };
  },

  setup(props, { emit }) {
    const route = useRoute();
    const cl = useContentList({
      collectionId: () =>
        props.collection_id !== undefined
          ? props.collection_id
          : route.params.col_id !== undefined
            ? parseInt(route.params.col_id)
            : undefined,
      eachProp: props.eachProp,
      relationSelect: () => props.relationSelect,
      relationType: () => props.relation_type,
      onAddSelected: (payload) => emit("addSelected", payload),
      enableCollectionWatch: true,
      initialProject: () => ({}),
    });

    // Column definitions driving the shared UiTable (presentation layer only)
    const tableColumns = computed(() => {
      const cols = [];
      cols.push({
        field: "id",
        label: __("ID"),
        sortable: false,
        toggleable: false,
      });

      (cl.collection.value.fields || []).forEach((field) => {
        if (
          field.type === "password" ||
          field.type === "json" ||
          field.type === "block"
        ) {
          return;
        }
        cols.push({
          field: field.name,
          label: __(field.label),
          sortable: true,
          hidden: !cl.columns.value[field.name],
          toggleable: true,
        });
      });

      cols.push({
        field: "status",
        label: __("Status"),
        sortable: false,
        toggleable: false,
      });

      const timeCols = [
        { field: "created_at", label: __("Created At") },
        { field: "created_by", label: __("Created By") },
        { field: "updated_at", label: __("Updated At") },
        { field: "updated_by", label: __("Updated By") },
        { field: "published_at", label: __("Published At") },
        { field: "published_by", label: __("Published By") },
      ];
      timeCols.forEach((timeCol) => {
        cols.push({
          field: timeCol.field,
          label: timeCol.label,
          sortable: true,
          hidden: !cl.columns.value[timeCol.field],
          toggleable: true,
        });
      });

      if (!props.relationSelect && cl.listOptions.value.getItems !== "trashed") {
        cols.push({
          field: "action",
          label: __("Action"),
          sortable: false,
          toggleable: false,
          sticky: true,
        });
      }
      return cols;
    });

    const onSortChange = (sorts) => {
      if (!sorts || !sorts.length) {
        return;
      }
      const sort = sorts[0];
      const field = sort.field;
      const criteria = sort.type === "desc" ? "DESC" : "ASC";

      // Determine if this is a meta field sort
      const isMetaField =
        [
          "created_at",
          "created_by",
          "updated_at",
          "updated_by",
          "published_at",
          "published_by",
          "id",
          "status",
          "action",
        ].indexOf(field) === -1;
      const sortByMeta = isMetaField ? 1 : 0;

      if (cl.listOptions.value.orderBy !== field) {
        cl.listOptions.value.criteria = "ASC";
      } else {
        cl.listOptions.value.criteria = criteria;
      }
      cl.listOptions.value.orderBy = field;
      cl.listOptions.value.sortByMeta = sortByMeta;
      cl.getContent(1);
    };

    const onSelectedRowsChange = ({ selectedRows }) => {
      cl.selected.value = selectedRows.map((r) => r.id);
    };

    const onPageChange = ({ currentPage }) => {
      cl.getContent(currentPage);
    };

    const onPerPageChange = ({ currentPerPage }) => {
      cl.each.value = Number(currentPerPage);
      cl.getContent(1);
    };

    const onColumnToggle = (field, isVisible) => {
      cl.columns.value[field] = isVisible;
      cl.changeColumnSettings();
    };

    const onTableSearch = ({ searchTerm }) => {
      cl.search.value = searchTerm;
      cl.getContent(1);
    };

    return {
      ...cl,
      tableColumns,
      onSortChange,
      onSelectedRowsChange,
      onPageChange,
      onPerPageChange,
      onColumnToggle,
      onTableSearch,
    };
  },
};
</script>