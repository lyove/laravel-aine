<template>
    <div class="admin__project-content-index relative h-full flex flex-col">
        <project-header :project="project"></project-header>
        <div class="flex flex-1 overflow-y-auto">
            <div class="w-96 bg-white border-r border-gray-100 overflow-y-auto flex-shrink-0">
                <content-sidebar :project="project" class="h-full"></content-sidebar>
            </div>
            <div class="flex-1 flex items-center justify-center bg-gray-50">
                <div class="text-center max-w-md px-8">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-indigo-50 flex items-center justify-center">
                        <i class="fas fa-edit text-3xl text-indigo-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Content') }}</h3>
                    <p class="text-sm text-gray-500 mb-6">{{ __('Select a collection from the left to view and manage its content.') }}</p>
                    <div v-if="collectionCount > 0" class="flex justify-center gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-800">{{ collectionCount }}</div>
                            <div class="text-xs text-gray-400">{{ __('Collections') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ProjectHeader from "../components/ProjectHeader.vue";
import ContentSidebar from "./sections/ContentSidebar.vue";
import projectBreadcrumb from "../../mixins/projectBreadcrumb";
import { useAdminStore } from "../../store";

export default {
    components: {
        ProjectHeader,
        ContentSidebar,
    },

    mixins: [projectBreadcrumb],

    data() {
        return {
            project: useAdminStore().currentProject || {},
        };
    },

    computed: {
        collectionCount() {
            return this.project && this.project.collections
                ? this.project.collections.length
                : 0;
        },
    },
};
</script>