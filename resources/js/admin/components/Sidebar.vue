<template>
    <div class="flex flex-col h-full overflow-y-auto">
        <div class="admin__brand text-center border-b border-gray-800">
            <brand size="md" mode="light" imgclass="w-20"></brand>
        </div>
        <nav class="admin__main-menu flex flex-col flex-1 overflow-y-auto">
            <router-link
                :to="{ name: 'dashboard' }"
                :exact-active-class="'bg-blue-500'"
                class="admin__main-menu-item flex flex-nowrap items-center px-8 py-4 hover:bg-blue-500"
            >
                <i class="admin__menu-item-icon pr-4 fas fa-tv"></i>
                <span class="text-xs">{{ __('Dashboard') }}</span>
            </router-link>
            <div class="admin__menu-group">
                <router-link
                    :to="{ name: 'projects' }"
                    :class="['admin__main-menu-item flex flex-nowrap items-center px-8 py-4 hover:bg-blue-400', { 'bg-blue-500': isProjectsActive && !isProjectPage }]"
                >
                    <i class="admin__menu-item-icon pr-4 fas fa-list"></i>
                    <span class="text-xs">{{ __('Projects') }}</span>
                </router-link>
                <div v-if="isProjectPage" class="admin__project-group bg-gray-800/50">
                    <div class="admin__project-name-row flex items-center px-8 py-3 border-b border-gray-700/50">
                        <router-link
                            :to="{
                                name: 'projects.index',
                                params: { project_id: $route.params.project_id },
                            }"
                            :active-class="'text-blue-400'"
                            class="admin__project-name flex items-center flex-1 min-w-0 hover:text-blue-400 transition-colors"
                        >
                            <i class="admin__menu-item-icon pr-3 fas fa-cubes text-gray-400"></i>
                            <span class="text-sm font-semibold truncate" :title="currentProjectName">
                                {{ currentProjectName }}
                            </span>
                        </router-link>
                        <button
                            @click="projectExpanded = !projectExpanded"
                            class="flex-shrink-0 ml-2 w-8 h-8 flex items-center justify-center rounded text-gray-400 transition-colors"
                            :title="projectExpanded ? __('Collapse') : __('Expand')"
                        >
                            <i :class="projectExpanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-xs"></i>
                        </button>
                    </div>
                    <transition
                        enter-active-class="transition-all duration-200 ease-out"
                        leave-active-class="transition-all duration-150 ease-in"
                        enter-from-class="opacity-0 max-h-0"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-show="projectExpanded" class="admin__project-sub-menu overflow-hidden">
                            <router-link
                                v-if="canProject(['owner', 'admin'])"
                                :to="{
                                    name: 'projects.collections',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex flex-nowrap items-center pl-12 pr-6 py-3 text-gray-400 hover:text-white hover:bg-gray-700/50 transition-colors', { 'text-blue-400 bg-gray-700/30': isCollectionsActive }]"
                            >
                                <i class="admin__menu-item-icon pr-3 fas fa-table text-sm"></i>
                                <span class="text-xs">{{ __('Collections') }}</span>
                            </router-link>
                            <router-link
                                :to="{
                                    name: 'projects.content',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex flex-nowrap items-center pl-12 pr-6 py-3 text-gray-400 hover:text-white hover:bg-gray-700/50 transition-colors', { 'text-blue-400 bg-gray-700/30': isContentActive }]"
                            >
                                <i class="admin__menu-item-icon pr-3 fas fa-edit text-sm"></i>
                                <span class="text-xs">{{ __('Content') }}</span>
                            </router-link>
                            <router-link
                                v-if="canProject(['owner', 'admin'])"
                                :to="{
                                    name: 'projects.settings',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex flex-nowrap items-center pl-12 pr-6 py-3 text-gray-400 hover:text-white hover:bg-gray-700/50 transition-colors', { 'text-blue-400 bg-gray-700/30': isSettingsActive }]"
                            >
                                <i class="admin__menu-item-icon pr-3 fas fa-cog text-sm"></i>
                                <span class="text-xs">{{ __('Settings') }}</span>
                            </router-link>
                        </div>
                    </transition>
                </div>
            </div>
        </nav>
        <nav class="admin__footer-menu border-t border-gray-700">
            <router-link
                v-if="checkRole(['super_admin'])"
                :to="{ name: 'settings' }"
                :active-class="'bg-blue-500'"
                class="admin__footer-menu-item flex flex-nowrap items-center px-8 py-4 hover:bg-blue-500 cursor-pointer"
            >
                <i class="admin__menu-item-icon pr-4 fas fa-cogs"></i>
                <span class="text-xs">{{ __('Setting') }}</span>
            </router-link>
            <router-link
                v-if="checkRole(['super_admin'])"
                :to="{ name: 'users' }"
                :active-class="'bg-blue-500'"
                class="admin__footer-menu-item flex flex-nowrap items-center px-8 py-4 hover:bg-blue-500 cursor-pointer"
            >
                <i class="admin__menu-item-icon pr-4 fas fa-users"></i>
                <span class="text-xs">{{ __('Users') }}</span>
            </router-link>
            <router-link
                :to="{ name: 'language' }"
                :class="['admin__footer-menu-item flex flex-nowrap items-center px-8 py-4 hover:bg-blue-500 cursor-pointer', { 'bg-blue-500': isLanguageActive }]"
            >
                <i class="admin__menu-item-icon pr-4 fas fa-language"></i>
                <span class="text-xs">{{ __('Language') }}</span>
            </router-link>
        </nav>
    </div>
</template>

<script>
import Brand from "./Brand.vue";
import UiDropdown from "../../components/Dropdown.vue";

import checkRole from "../../utils/checkrole";
import { useAdminStore } from "../store";

export default {
    components: {
        Brand,
        UiDropdown,
    },
    data() {
        return {
            sidebarOpen: false,
            projectExpanded: true,
        };
    },

    methods: {
        checkRole,

        canProject(roles) {
            return Array.isArray(roles) && roles.includes(this.currentProject && this.currentProject.my_role);
        },
    },

    computed: {
        currentProject() {
            return useAdminStore().currentProject;
        },

        isProjectsActive() {
            const name = this.$route.name;
            return name === 'projects' || (name && name.startsWith('projects.'));
        },

        isLanguageActive() {
            return this.$route.name === 'language';
        },

        isCollectionsActive() {
            const name = this.$route.name;
            return name === 'projects.collections' || (name && name.startsWith('projects.collections.'));
        },

        isContentActive() {
            const name = this.$route.name;
            return name === 'projects.content' || (name && name.startsWith('projects.content.'));
        },

        isSettingsActive() {
            const name = this.$route.name;
            return name === 'projects.settings' || (name && name.startsWith('projects.settings.'));
        },

        isProjectPage() {
            const fullPath = this.$route.fullPath;
            const projectPath = "/project/";
            if (
                fullPath.includes(projectPath) &&
                fullPath.length > projectPath.length
            ) {
                return true;
            }
            return false;
        },

        currentProjectName() {
            if (this.currentProject) {
                return this.currentProject.name;
            }
            return 'Loading...';
        },
    },

    watch: {
        '$route'(to) {
            const name = to.name;
            if (name && (name.startsWith('projects.collections') || name.startsWith('projects.content') || name.startsWith('projects.settings'))) {
                this.projectExpanded = true;
            }
        },
    },
};
</script>