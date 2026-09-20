<template>
    <div class="admin__project-collection-index relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex flex-1 overflow-y-auto">
            <div class="w-96 bg-white border-r border-gray-100 overflow-y-auto flex-shrink-0">
                <collection-sidebar :project="project" class="h-full"></collection-sidebar>
            </div>
            <div class="flex-1 flex items-center justify-center bg-gray-50">
                <div class="text-center max-w-md px-8">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-indigo-50 flex items-center justify-center">
                        <i class="fas fa-table text-3xl text-indigo-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Collections') }}</h3>
                    <p class="text-sm text-gray-500 mb-6">{{ __('Select a collection from the left to view and manage its fields, content, and settings.') }}</p>
                    <div v-if="collectionCount > 0" class="flex justify-center gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-800">{{ collectionCount }}</div>
                            <div class="text-xs text-gray-400">{{ __('Collections') }}</div>
                        </div>
                    </div>
                    <div v-if="!isReadonly" class="flex justify-center">
                        <button
                            @click="$root.$emit('open-new-collection')"
                            class="text-sm text-indigo-500 hover:text-indigo-600 font-semibold"
                        >
                            <i class="fas fa-plus-circle mr-1"></i>
                            {{ __('Create your first collection') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ProjectHeader from "../components/ProjectHeader.vue";
import CollectionSidebar from "./sections/CollectionSidebar.vue";
import projectBreadcrumb from "../../mixins/projectBreadcrumb";
import { useAdminStore } from "../../store";

export default {
    components: {
        CollectionSidebar,
        ProjectHeader,
    },

    mixins: [projectBreadcrumb],

    data() {
        return {
            project: useAdminStore().currentProject || {},
        };
    },

    computed: {
        isReadonly() {
            return this.project && (this.project.is_readonly || !this.project.status);
        },
        collectionCount() {
            return this.project && this.project.collections
                ? this.project.collections.length
                : 0;
        },
    },
};
</script>