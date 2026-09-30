<template>
    <div class="admin__project-centre relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="mb-5">
                <h2 class="text-xl font-bold text-gray-800">{{ project.name }}</h2>
                <p class="text-sm text-gray-500 mt-1" v-if="project.description">{{ project.description }}</p>
            </div>

            <!-- Notes workspace: collection entries (timeline layout) -->
            <div class="max-w-2xl border border-gray-200 rounded-md bg-white divide-y divide-gray-100">
                <router-link
                    v-for="col in collections"
                    :key="col.id"
                    :to="{ name: 'projects.content.list', params: { project_id: $route.params.project_id, col_id: col.id } }"
                    class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition group"
                >
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 shrink-0 group-hover:bg-indigo-500"></span>
                    <span class="flex-1 min-w-0">
                        <span class="font-bold text-gray-800 block truncate">{{ __(col.name) }}</span>
                        <span class="text-xs text-gray-400 block mt-0.5">#{{ col.slug }}</span>
                    </span>
                    <span class="text-xs text-gray-500 bg-gray-100 rounded px-2 py-0.5 whitespace-nowrap">
                        {{ col.fields ? col.fields.length : 0 }} {{ __('fields') }}
                    </span>
                    <i class="fa fa-chevron-right text-gray-300 group-hover:text-gray-500"></i>
                </router-link>
                <div v-if="!collections.length" class="px-5 py-8 text-center text-sm text-gray-500">{{ __('No collections yet') }}</div>
            </div>

            <div v-if="canProject(['owner', 'admin'])" class="mt-4">
                <router-link
                    :to="{ name: 'projects.collections', params: { project_id: $route.params.project_id } }"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 transition"
                >
                    <i class="fa fa-plus mr-2"></i> {{ __('Manage Collections') }}
                </router-link>
            </div>
        </div>
    </div>
</template>

<script>
import { useAdminStore } from '@/admin/store';
import ProjectHeader from '@/admin/components/ProjectHeader.vue';
import projectBreadcrumb from '@/admin/mixins/projectBreadcrumb';

export default {
    components: {
        ProjectHeader,
    },

    mixins: [projectBreadcrumb],

    setup() {
        const store = useAdminStore();
        const project = store.currentProject || {};

        return {
            project,
            collections: store.currentProject?.collections || [],
            canProject(roles) {
                const p = store.currentProject;
                return Array.isArray(roles) && roles.includes(p && p.my_role);
            },
        };
    },
};
</script>
