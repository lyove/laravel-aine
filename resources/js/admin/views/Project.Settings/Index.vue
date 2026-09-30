<template>
    <div class="admin__project-settings-index relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex flex-1 overflow-y-auto">
            <div class="w-3/12 bg-white overflow-x-hidden">
                <settings-sidebar :project="project"></settings-sidebar>
            </div>
            <div class="w-9/12 overflow-x-hidden">
                <!-- Placeholder — settings content moved to dedicated pages:
                     General, Logo, CustomCode, SEO, Contact -->
            </div>
        </div>
    </div>
</template>

<script>
import ProjectHeader from "@/admin/components/ProjectHeader.vue";
import SettingsSidebar from "@/admin/components/SettingsSidebar.vue";
import projectBreadcrumb from "@/admin/mixins/projectBreadcrumb";
import { useAdminStore } from "@/admin/store";

export default {
    components: { ProjectHeader, SettingsSidebar },
    mixins: [projectBreadcrumb],
    data() {
        return {
            project: useAdminStore().currentProject || {},
        };
    },
};
</script>
