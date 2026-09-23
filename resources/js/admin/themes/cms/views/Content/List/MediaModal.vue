<template>
  <ui-modal maxWidth="5xl" :show="show" @close="$emit('close')">
    <template #title>{{ fieldName }}</template>
    <template #content>
      <div class="my-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div v-for="file in records" :key="file.id" class="relative">
          <div class="rounded-md bg-white">
            <div
              class="w-full h-48 absolute top-0 rounded-md left-0 bg-black bg-opacity-70 items-center text-center flex opacity-0 hover:opacity-100 z-10"
            >
              <div class="w-full z-20">
                <div class="text-sm text-white w-full mb-3" :title="file.name">
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
                    <i v-else class="fa fa-file-download text-gray-100 cursor-pointer hover:text-gray-200 text-lg mr-2"></i>
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
            <div class="w-full h-48 object-cover flex items-center text-center border rounded-md" v-else>
              <div class="w-full">
                <i v-if="file.type == 'pdf'" class="far fa-file-pdf text-5xl text-red-500"></i>
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
                  v-else-if="file.type == 'wav' || file.type == 'ogg' || file.type == 'mpeg'"
                  class="far fa-file-audio text-5xl text-yellow-500"
                ></i>
                <i v-else-if="file.type == 'xls' || file.type == 'xlsx'" class="far fa-file-excel text-5xl text-green-500"></i>
                <i v-else-if="file.type == 'doc' || file.type == 'docx'" class="far fa-file-word text-5xl text-blue-500"></i>
                <i v-else-if="file.type == 'zip'" class="far fa-file-archive text-5xl text-yellow-300"></i>
                <i v-else class="far fa-file text-5xl text-gray-400"></i>
              </div>
            </div>
          </div>
        </div>
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
  name: "MediaModal",

  props: {
    show: { type: Boolean, default: false },
    records: { type: Object, default: () => ({}) },
    fieldName: { type: String, default: null },
  },

  emits: ["close"],

  methods: {
    __,
  },
};
</script>
