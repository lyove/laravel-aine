<template>
    <div class="admin__project-centre relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-800">{{ project.name }}</h2>
                <p class="text-sm text-gray-500 mt-1" v-if="project.description">{{ project.description }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <router-link
                    v-for="col in collections"
                    :key="col.id"
                    :to="{ name: 'projects.content.list', params: { project_id: $route.params.project_id, col_id: col.id } }"
                    class="border border-gray-200 rounded-md bg-white p-4 hover:shadow-md hover:border-gray-300 transition"
                >
                    <div class="flex items-center justify-between">
                        <i class="fa fa-store text-2xl text-indigo-400"></i>
                        <span class="text-xs text-gray-500 bg-gray-100 rounded px-2 py-0.5">
                            {{ col.fields ? col.fields.length : 0 }} {{ __('fields') }}
                        </span>
                    </div>
                    <h4 class="font-bold text-gray-800 mt-3">{{ __(col.name) }}</h4>
                    <p class="text-sm text-gray-400 mt-0.5">#{{ col.slug }}</p>
                </router-link>
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
