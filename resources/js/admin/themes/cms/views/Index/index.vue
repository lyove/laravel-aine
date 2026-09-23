<template>
    <div class="admin__project-centre relative h-full flex flex-col">
        <project-header :project="project"></project-header>
        <div class="grid grid-cols-1 sm:grid-cols-2 pt-4 overflow-y-auto">
            <div class="col p-4" v-if="canProject(['owner', 'admin'])">
                <div class="inline-flex mb-5">
                    <div class="mr-4 text-gray-100 bg-yellow-900 rounded-md text-xl p-4 h-full items-center content-center">
                        <i class="fas fa-table"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">{{ __('Set up Collections') }}</h3>
                        <div class="text-sm">
                            {{ __('Add new collections to your projects, edit fields to create your schema.') }}
                        </div>
                    </div>
                </div>
                <collection-sidebar :project="project" class="shadow-md rounded-md"></collection-sidebar>
            </div>
            <div class="col p-4" v-if="canProject(['owner', 'admin', 'editor'])">
                <div class="inline-flex mb-5">
                    <div class="mr-4 text-gray-100 bg-green-400 rounded-md text-xl p-4 h-full items-center content-center">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="block">
                        <h3 class="font-bold text-lg">{{ __('Create Content') }}</h3>
                        <div class="text-sm">
                            {{ __('Using the collection schema structure create content for your project.') }}
                        </div>
                    </div>
                </div>
                <content-sidebar :project="project" class="shadow-md rounded-md"></content-sidebar>
            </div>
            <div class="col p-4 sm:col-span-2" v-if="canProject(['viewer']) && !canProject(['owner', 'admin', 'editor'])">
                <div class="inline-flex mb-5">
                    <div class="mr-4 text-gray-100 bg-gray-500 rounded-md text-xl p-4 h-full items-center content-center">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="block">
                        <h3 class="font-bold text-lg">{{ __('Read-only access') }}</h3>
                        <div class="text-sm">
                            {{ __('You have viewer access to this project. Content and collections are managed by the owner and team members.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { useAdminStore } from '@/admin/store';
import ProjectHeader from "@/admin/components/ProjectHeader.vue";
import CollectionSidebar from "@/admin/components/CollectionSidebar.vue";
import ContentSidebar from "@/admin/components/ContentSidebar.vue";
import projectBreadcrumb from "@/admin/mixins/projectBreadcrumb";

export default {
    components: {
        ProjectHeader,
        CollectionSidebar,
        ContentSidebar,
    },

    mixins: [projectBreadcrumb],

    data() {
        return {
            project: useAdminStore().currentProject || {},
        };
    },

    methods: {
        canProject(roles) {
            return Array.isArray(roles) && roles.includes(this.project && this.project.my_role);
        },
    },
};
</script>
