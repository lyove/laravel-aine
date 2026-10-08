<template>
    <div class="admin__project-content-media-library relative h-screen overflow-auto">
        <project-header :project="project"></project-header>

        <div class="flex flex-1 overflow-y-auto">
            <div class="w-3/12 bg-white overflow-x-hidden">
                <collection-sidebar :project="project"></collection-sidebar>
            </div>

            <div class="w-9/12 p-4 overflow-x-auto">
                <media-library :project="project"></media-library>
            </div>
        </div>
    </div>
</template>

<script>
import ProjectHeader from "@/admin/components/ProjectHeader.vue";
import MediaLibrary from "@/admin/components/MediaLibrary.vue";
import CollectionSidebar from "@/admin/components/CollectionSidebar.vue";
import projectBreadcrumb from "@/admin/mixins/projectBreadcrumb";
import { useAdminStore } from "@/admin/store";

export default {
    components: {
        ProjectHeader,
        CollectionSidebar,
        MediaLibrary,
    },

    mixins: [projectBreadcrumb],

    data() {
        return {
            // Project data comes from the store — the router guard loads it
            // before this page renders, so no extra request is needed.
            project: useAdminStore().currentProject || {},
        };
    },
};
</script>
