<template>
    <div class="flex flex-col h-full overflow-y-auto bg-gray-900">
        <div class="admin__brand text-center border-b border-gray-800 py-6">
            <brand size="md" mode="light" imgclass="w-20"></brand>
        </div>
        <nav class="admin__main-menu flex flex-col flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <router-link
                :to="{ name: 'dashboard' }"
                :exact-active-class="'bg-indigo-600 text-white shadow-lg'"
                class="admin__main-menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-all duration-200 group"
            >
                <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center mr-3 group-hover:bg-indigo-500/30 transition-colors">
                    <i class="fas fa-tachometer-alt text-indigo-400 text-sm"></i>
                </div>
                <span class="text-sm font-medium">{{ __('Dashboard') }}</span>
            </router-link>
            <div class="admin__menu-group">
                <router-link
                    :to="{ name: 'projects' }"
                    :class="['admin__main-menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-all duration-200 group', { 'bg-indigo-600 text-white shadow-lg': isProjectsActive && !isProjectPage }]"
                >
                    <div class="w-9 h-9 rounded-lg bg-blue-500/20 flex items-center justify-center mr-3 group-hover:bg-blue-500/30 transition-colors">
                        <i class="fas fa-folder text-blue-400 text-sm"></i>
                    </div>
                    <span class="text-sm font-medium">{{ __('Projects') }}</span>
                </router-link>
                <div v-if="isProjectPage" class="admin__project-group mt-2 ml-4 rounded-lg bg-gray-800/50 border border-gray-700/50 overflow-hidden">
                    <div class="admin__project-name-row flex items-center px-4 py-3 border-b border-gray-700/50">
                        <router-link
                            :to="{
                                name: 'projects.index',
                                params: { project_id: $route.params.project_id },
                            }"
                            :active-class="'text-indigo-400'"
                            class="admin__project-name flex items-center flex-1 min-w-0 hover:text-indigo-400 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center mr-3">
                                <i class="fas fa-cubes text-purple-400 text-xs"></i>
                            </div>
                            <span class="text-sm font-semibold truncate" :title="currentProjectName">
                                {{ currentProjectName }}
                            </span>
                        </router-link>
                        <button
                            @click="projectExpanded = !projectExpanded"
                            class="flex-shrink-0 ml-2 w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-white hover:bg-gray-700 transition-colors"
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
                        <div v-show="projectExpanded" class="admin__project-sub-menu overflow-hidden py-2">
                            <router-link
                                v-if="canProject(['owner', 'admin'])"
                                :to="{
                                    name: 'projects.collections',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex items-center px-4 py-2.5 mx-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700/50 transition-all duration-200 group', { 'text-indigo-400 bg-indigo-500/10': isCollectionsActive }]"
                            >
                                <div class="w-7 h-7 rounded-md bg-emerald-500/20 flex items-center justify-center mr-3 group-hover:bg-emerald-500/30 transition-colors">
                                    <i class="fas fa-table text-emerald-400 text-xs"></i>
                                </div>
                                <span class="text-xs font-medium">{{ __('Collections') }}</span>
                            </router-link>
                            <router-link
                                :to="{
                                    name: 'projects.content',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex items-center px-4 py-2.5 mx-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700/50 transition-all duration-200 group', { 'text-indigo-400 bg-indigo-500/10': isContentActive }]"
                            >
                                <div class="w-7 h-7 rounded-md bg-amber-500/20 flex items-center justify-center mr-3 group-hover:bg-amber-500/30 transition-colors">
                                    <i class="fas fa-edit text-amber-400 text-xs"></i>
                                </div>
                                <span class="text-xs font-medium">{{ __('Content') }}</span>
                            </router-link>
                            <router-link
                                v-if="canProject(['owner', 'admin'])"
                                :to="{
                                    name: 'projects.settings',
                                    params: { project_id: $route.params.project_id },
                                }"
                                :class="['admin__sub-menu-item flex items-center px-4 py-2.5 mx-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700/50 transition-all duration-200 group', { 'text-indigo-400 bg-indigo-500/10': isSettingsActive }]"
                            >
                                <div class="w-7 h-7 rounded-md bg-gray-500/20 flex items-center justify-center mr-3 group-hover:bg-gray-500/30 transition-colors">
                                    <i class="fas fa-cog text-gray-400 text-xs"></i>
                                </div>
                                <span class="text-xs font-medium">{{ __('Settings') }}</span>
                            </router-link>
                        </div>
                    </transition>
                </div>
            </div>
            <router-link
                v-if="checkRole(['super_admin'])"
                :to="{ name: 'users' }"
                :active-class="'bg-indigo-600 text-white shadow-lg'"
                class="admin__main-menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-all duration-200 group cursor-pointer"
            >
                <div class="w-9 h-9 rounded-lg bg-pink-500/20 flex items-center justify-center mr-3 group-hover:bg-pink-500/30 transition-colors">
                    <i class="fas fa-users text-pink-400 text-sm"></i>
                </div>
                <span class="text-sm font-medium">{{ __('Users') }}</span>
            </router-link>
            <router-link
                :to="{ name: 'language' }"
                :class="['admin__main-menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-all duration-200 group cursor-pointer', { 'bg-indigo-600 text-white shadow-lg': isLanguageActive }]"
            >
                <div class="w-9 h-9 rounded-lg bg-teal-500/20 flex items-center justify-center mr-3 group-hover:bg-teal-500/30 transition-colors">
                    <i class="fas fa-language text-teal-400 text-sm"></i>
                </div>
                <span class="text-sm font-medium">{{ __('Language') }}</span>
            </router-link>
            <router-link
                v-if="checkRole(['super_admin'])"
                :to="{ name: 'settings' }"
                :active-class="'bg-indigo-600 text-white shadow-lg'"
                class="admin__main-menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-all duration-200 group cursor-pointer"
            >
                <div class="w-9 h-9 rounded-lg bg-gray-500/20 flex items-center justify-center mr-3 group-hover:bg-gray-500/30 transition-colors">
                    <i class="fas fa-cogs text-gray-400 text-sm"></i>
                </div>
                <span class="text-sm font-medium">{{ __('Setting') }}</span>
            </router-link>
        </nav>
        <div class="admin__footer flex-shrink-0 border-t border-gray-800 px-4 py-3">
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <p class="text-xs text-gray-500">{{ __('Copyright') }} &copy; {{ appName }}</p>
                    <p class="text-xs text-gray-600 mt-0.5">v{{ appVersion }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Brand from "@/admin/components/Brand.vue";

import checkRole from "@/utils/checkrole";
import { useAdminStore } from "@/admin/store";

export default {
    components: {
        Brand,
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

        settings() {
            return useAdminStore().settings;
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
            return this.__('Loading...');
        },

        appName() {
            return this.settings.name || 'Aine';
        },

        appVersion() {
            return this.settings.version || '0.0.1';
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