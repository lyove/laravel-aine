<template>
  <div
    v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
    class="w-full flex justify-between text-sm text-gray-700 mb-2 pl-1"
  >
    <!-- 左侧：批量操作按钮 -->
    <div class="flex">
      <div class="py-1">
        {{ selected.length }} {{ __("items selected") }}
      </div>
      <template v-if="isComments">
        <div
          v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
          class="ml-2 cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('comment-bulk', 'approve')"
        >
          <i class="fa fa-check"></i> {{ __("approve") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
          class="ml-2 cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('comment-bulk', 'spam')"
        >
          <i class="fa fa-bug"></i> {{ __("mark as spam") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
          class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('comment-bulk', 'trash')"
        >
          <i class="fa fa-trash-restore"></i> {{ __("move to trash") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems === 'trash'"
          class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('comment-bulk', 'restore')"
        >
          <i class="fa fa-recycle"></i> {{ __("restore") }}
        </div>
        <div
          v-if="selected.length !== 0 && canProject(['owner', 'admin'])"
          class="ml-2 cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('delete-selected')"
        >
          <i class="fa fa-trash-alt"></i> {{ __("delete") }}
        </div>
      </template>
      <template v-else>
        <div
          v-if="
            selected.length !== 0 &&
            listOptions.getItems !== 'trashed' &&
            canProject(['owner', 'admin'])
          "
          class="ml-2 cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('publish-selected')"
        >
          <i class="fa fa-cloud-upload-alt"></i> {{ __("publish") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
          class="ml-2 cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('unpublish-selected')"
        >
          <i class="fa fa-cloud-download-alt"></i> {{ __("unpublish") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
          class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('move-to-trash-selected')"
        >
          <i class="fa fa-trash-restore"></i> {{ __("move to trash") }}
        </div>
        <div
          v-if="selected.length !== 0 && listOptions.getItems === 'trashed'"
          class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('restore-selected')"
        >
          <i class="fa fa-recycle"></i> {{ __("restore") }}
        </div>
        <div
          v-if="selected.length !== 0 && canProject(['owner', 'admin'])"
          class="ml-2 cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
          @click="$emit('delete-selected')"
        >
          <i class="fa fa-trash-alt"></i> {{ __("delete") }}
        </div>
      </template>
    </div>

    <!-- 右侧：状态标签 -->
    <div class="flex">
      <template v-if="isComments">
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'all')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'all' }"
        >
          {{ __("All") }}({{ totalCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'approved')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'approved' }"
        >
          {{ __("Approved") }}({{ approvedCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'pending')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'pending' }"
        >
          {{ __("Pending") }}({{ pendingCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'spam')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'spam' }"
        >
          {{ __("Spam") }}({{ spamCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'trash')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'trash' }"
        >
          {{ __("Trash") }}({{ trashCount }})
        </div>
      </template>
      <template v-else>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'all')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'all' }"
        >
          {{ __("All") }}({{ totalCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'published')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'published' }"
        >
          {{ __("Published") }}({{ publishedCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'draft')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'draft' }"
        >
          {{ __("Draft") }}({{ draftCount }})
        </div>
        <div
          class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
          @click="$emit('change-get-items', 'trashed')"
          :class="{ 'bg-gray-200': listOptions.getItems == 'trashed' }"
        >
          {{ __("Trashed") }}({{ trashedCount }})
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { __ } from "@/admin/translations/engine";

export default {
  name: "ContentBatchBar",

  props: {
    isReadonly: { type: Boolean, default: false },
    selected: { type: Array, default: () => [] },
    listOptions: { type: Object, required: true },
    isComments: { type: Boolean, default: false },
    totalCount: { type: Number, default: 0 },
    publishedCount: { type: Number, default: 0 },
    draftCount: { type: Number, default: 0 },
    trashedCount: { type: Number, default: 0 },
    approvedCount: { type: Number, default: 0 },
    pendingCount: { type: Number, default: 0 },
    spamCount: { type: Number, default: 0 },
    trashCount: { type: Number, default: 0 },
    canProject: { type: Function, required: true },
  },

  emits: [
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
  },
};
</script>
