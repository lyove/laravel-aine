<template>
    <div class="admin__projects-list flex flex-col m-3 p-3">
        <div class="admin__projects-search flex justify-between pb-4">
            <div class="search-input relative flex flex-wrap items-stretch flex-1 max-w-md">
                <span class="h-full leading-snug font-normal absolute text-center text-gray-400 absolute bg-transparent rounded-md text-base items-center justify-center w-8 pl-3 py-2">
                    <i class="fas fa-search"></i>
                </span>
                <input
                    type="text"
                    v-model="search"
                    @input="onSearchInput"
                    :placeholder="__('Search projects...')"
                    class="px-3 py-2 placeholder-gray-400 text-gray-700 bg-white rounded-md text-sm w-full pl-10 border-gray-200 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 transition"
                />
            </div>
            <button
                @click="openNewProjectModal = true"
                class="flex items-center gap-2 bg-indigo-500 hover:bg-indigo-600 text-white px-4 h-9 rounded-md cursor-pointer shadow-sm transition"
            >
                <i class="fas fa-plus"></i>
                <span class="text-sm font-medium hidden sm:inline">{{ __('New Project') }}</span>
            </button>
        </div>

        <div v-if="!loading && projects.length > 0" class="flex flex-wrap items-center justify-between gap-3 pb-4">
            <div class="flex items-center gap-2">
                <button
                    v-for="opt in filterOptions"
                    :key="opt.value"
                    @click="filterStatus = opt.value"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-full transition',
                        filterStatus === opt.value
                            ? 'bg-indigo-500 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    {{ __(opt.label) }}
                    <span class="ml-1 opacity-75">{{ opt.count }}</span>
                </button>
            </div>
            <select
                v-model="sortBy"
                class="text-xs text-gray-600 bg-white border border-gray-200 rounded-md px-3 pr-8 py-1.5 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%236b7280%22%3E%3Cpath%20fill-rule%3D%22evenodd%22%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:1.25rem] bg-[right_0.25rem_center] bg-no-repeat"
            >
                <option value="created_desc">{{ __('Newest first') }}</option>
                <option value="created_asc">{{ __('Oldest first') }}</option>
                <option value="name_asc">{{ __('Name (A-Z)') }}</option>
                <option value="name_desc">{{ __('Name (Z-A)') }}</option>
            </select>
        </div>

        <div v-if="loading" class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(300px,1fr))]">
            <div v-for="i in 6" :key="'skeleton-'+i" class="bg-white p-5 rounded-lg shadow-sm border border-gray-100 animate-pulse">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-gray-200 rounded-md"></div>
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                </div>
                <div class="h-3 bg-gray-200 rounded w-full mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-5/6 mb-4"></div>
                <div class="h-px bg-gray-100 my-3"></div>
                <div class="h-8 bg-gray-200 rounded w-full"></div>
            </div>
        </div>

        <div v-else-if="projects.length === 0 || filteredProjects.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
            <i :class="['fas', search || filterStatus !== 'all' ? 'fa-search' : 'fa-folder-open', 'text-6xl text-gray-300 mb-4']"></i>
            <p class="text-gray-500 text-lg mb-1">
                {{ search || filterStatus !== 'all' ? __('No projects found') : __('No projects yet') }}
            </p>
            <p class="text-gray-400 text-sm mb-6">
                {{ search || filterStatus !== 'all' ? __('Try a different search term or filter') : __('Create your first project to get started') }}
            </p>
            <button
                v-if="!search && filterStatus === 'all'"
                @click="openNewProjectModal = true"
                class="px-6 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition"
            >
                <i class="fas fa-plus mr-2"></i>{{ __('Create New Project') }}
            </button>
        </div>

        <!-- 项目卡片列表 -->
        <div v-else class="admin__projects-list grid gap-4 grid-cols-[repeat(auto-fill,minmax(300px,1fr))] overflow-y-auto">
            <div
                v-for="project in filteredProjects"
                :key="project.id"
                class="flex flex-col bg-white text-gray-900 p-5 rounded-lg shadow-sm border border-gray-100 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
                :class="project.status
                    ? 'border-l-4 border-l-green-400'
                    : 'border-l-4 border-l-gray-300 opacity-75'"
            >
                <div class="flex items-center justify-between shrink-0 mb-2">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-md bg-indigo-50 flex items-center justify-center flex-shrink-0 border border-indigo-100 overflow-hidden">
                            <img v-if="project.logo_url" :src="project.logo_url" class="w-full h-full object-cover" :alt="project.name" />
                            <span v-else class="text-sm font-bold text-indigo-500 uppercase">{{ (project.name || '?').charAt(0) }}</span>
                        </div>
                        <router-link
                            :to="{ name: 'projects.index', params: { project_id: project.id } }"
                            class="min-w-0 hover:text-indigo-600 transition-colors"
                        >
                            <span class="font-bold text-base truncate block">{{ project.name }}</span>
                            <span v-if="project.slug" class="text-xs text-gray-400 truncate block">{{ project.slug }}</span>
                        </router-link>
                    </div>
                    <span
                        class="inline-flex items-center justify-center p-1.5 -m-1.5 cursor-pointer"
                        v-tooltip="{
                            content: project.status
                                ? __('Active')
                                : __('Disabled projects only block external API calls (404). Admin editing remains available.'),
                            delay: 200
                        }"
                    >
                        <span
                            :class="[
                                'w-2.5 h-2.5 rounded-full flex-shrink-0',
                                project.status ? 'bg-green-400' : 'bg-gray-300'
                            ]"
                        ></span>
                    </span>
                </div>

                <div
                    class="flex-1 py-2 text-sm text-gray-500 leading-relaxed project-desc"
                    :title="project.description"
                >
                    {{ project.description || __('No description') }}
                </div>

                <div class="flex items-center gap-4 text-xs text-gray-400 py-2 border-t border-gray-50">
                    <span class="flex items-center gap-1" :title="__('Default locale')">
                        <i class="fas fa-globe"></i>
                        {{ project.default_locale || 'en' }}
                    </span>
                    <span v-if="project.collections_count !== undefined" class="flex items-center gap-1" :title="__('Collections')">
                        <i class="fas fa-folder"></i>
                        {{ project.collections_count }}
                    </span>
                    <span v-if="project.members_count !== undefined" class="flex items-center gap-1" :title="__('Members')">
                        <i class="fas fa-users"></i>
                        {{ project.members_count }}
                    </span>
                    <span class="flex items-center gap-1 ml-auto" :title="__('My role')">
                        <i class="fas fa-shield-alt"></i>
                        {{ project.my_role }}
                    </span>
                </div>

                <div class="flex items-center justify-between shrink-0 pt-2">
                    <div class="flex items-center gap-2">
                        <div v-if="canDeleteProject(project)" class="relative project-more-menu">
                            <button
                                @click="toggleMenu(project.id)"
                                class="inline-flex items-center px-2 py-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition"
                                :aria-label="__('More options')"
                            >
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div
                                v-if="openMenuId === project.id"
                                class="absolute left-0 top-full mt-1 w-40 bg-white rounded-md shadow-lg border border-gray-100 py-1 z-10"
                                @mouseleave="closeMenu"
                            >
                                <button
                                    @click="deleteProject(project); closeMenu()"
                                    :disabled="!project.status"
                                    :class="[
                                        'w-full text-left px-3 py-2 text-xs transition',
                                        project.status
                                            ? 'text-red-600 hover:bg-red-50'
                                            : 'text-gray-400 cursor-not-allowed'
                                    ]"
                                >
                                    <i class="fas fa-trash-alt mr-2"></i>{{ __('Delete Project') }}
                                </button>
                            </div>
                        </div>

                        <ui-switch
                            :model-value="Boolean(project.status)"
                            :label="__(project.status ? 'Active' : 'Inactive')"
                            :disabled="!canToggleStatus(project)"
                            @change="(value) => toggleStatus(project, value)"
                        />
                    </div>
                    <router-link
                        :to="{
                            name: 'projects.index',
                            params: { project_id: project.id },
                        }"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-md transition"
                    >
                        {{ __('Enter Project') }}
                        <i class="fas fa-arrow-right text-xs"></i>
                    </router-link>
                </div>
            </div>
        </div>

        <ui-modal :show="openNewProjectModal" @close="closeNewProjectModal">
            <template #title> {{ __('Create New Project') }} </template>

            <template #content>
                <div class="mt-4 pb-4">
                    <form @submit.prevent="handleNewProjectSubmit">
                        <div class="mt-2">
                            <label v-formlabel>{{ __('Project Name') }}</label>
                            <input
                                type="text"
                                v-model="new_project.name"
                                autofocus
                                v-forminput
                                :placeholder="__('Project name')"
                                @input="generateSlugFromName"
                            />
                            <p class="text-sm text-red-600 mt-2">
                                {{ new_project.errors.name[0] }}
                            </p>
                        </div>
                        <div class="mt-6">
                            <label v-formlabel>{{ __('Project Slug') }}</label>
                            <input
                                type="text"
                                v-model="new_project.slug"
                                v-forminput
                                :placeholder="__('project-slug')"
                                pattern="[a-z0-9\-]+"
                                @blur="checkSlug"
                                @input="onSlugInput"
                            />
                            <p class="text-sm text-gray-500 mt-1">
                                {{ __('Only lowercase letters, numbers, and hyphens allowed') }}
                            </p>
                            <p v-if="new_project.slugExists" class="text-sm text-red-600 mt-2">
                                {{ __('This slug is already in use by another project.') }}
                            </p>
                            <p v-else class="text-sm text-red-600 mt-2">
                                {{ new_project.errors.slug[0] }}
                            </p>
                        </div>
                        <div class="mt-6">
                            <label v-formlabel>{{ __('Description') }}</label>
                            <input
                                type="text"
                                v-model="new_project.description"
                                v-forminput
                                :placeholder="__('Project description')"
                            />
                        </div>
                        <div class="mt-6">
                            <label v-formlabel>{{ __('Default Locale') }}</label>
                            <v-select
                                :options="locales"
                                :get-option-key="localeKey"
                                :get-option-label="localeLabel"
                                :reduce="(o) => o.id"
                                :clearable="false"
                                class="v-select"
                                :placeholder="__('Select Locale')"
                                v-model="new_project.default_locale"
                            ></v-select>
                        </div>
                        <div class="mt-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-1">
                                    <div class="p-5 border border-gray-300 rounded-md text-sm space-x-2 h-32 relative">
                                        <label for="blank_project" class="absolute inset-0 w-full h-full cursor-pointer"></label>
                                        <div class="flex">
                                            <input
                                                type="radio"
                                                id="blank_project"
                                                v-model="new_project.type"
                                                value="1"
                                            />
                                            <div class="ml-2">{{ __('Blank') }}</div>
                                        </div>
                                        <div class="block mt-2 text-xs text-gray-500">
                                            {{ __('Start from scratch with an empty project') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-span-1">
                                    <div class="p-5 border border-gray-300 rounded-md text-sm space-x-2 h-32 relative">
                                        <label for="cms_template" class="absolute inset-0 w-full h-full cursor-pointer"></label>
                                        <div class="flex mb-2">
                                            <input
                                                type="radio"
                                                id="cms_template"
                                                v-model="new_project.type"
                                                value="2"
                                            />
                                            <div class="ml-2">
                                                {{ __('CMS Template') }}
                                            </div>
                                        </div>
                                        <div class="block">
                                            {{ __('Content Management System (Pages, Articles, Categories, Authors, Tags, Comments, Globals)') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-span-1">
                                    <div class="p-5 border border-gray-300 rounded-md text-sm space-x-2 h-32 relative">
                                        <label for="directory_template" class="absolute inset-0 w-full h-full cursor-pointer"></label>
                                        <div class="flex mb-2">
                                            <input
                                                type="radio"
                                                id="directory_template"
                                                v-model="new_project.type"
                                                value="3"
                                            />
                                            <div class="ml-2">
                                                {{ __('Business Directory Template') }}
                                            </div>
                                        </div>
                                        <div class="block">
                                            {{ __('Business Directory (Listings, Categories, Tags, Locations, Reviews, Globals)') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-span-1">
                                    <div class="p-5 border border-gray-300 rounded-md text-sm space-x-2 h-32 relative">
                                        <label for="note_template" class="absolute inset-0 w-full h-full cursor-pointer"></label>
                                        <div class="flex mb-2">
                                            <input
                                                type="radio"
                                                id="note_template"
                                                v-model="new_project.type"
                                                value="4"
                                            />
                                            <div class="ml-2">
                                                {{ __('Note Template') }}
                                            </div>
                                        </div>
                                        <div class="block">
                                            {{ __('Note (Pages, Posts, Categories, Tags, Globals)') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </template>

            <template #footer>
                <ui-button
                    color="gray-200"
                    hover="gray-300"
                    @click="closeNewProjectModal"
                >
                    <span class="text-gray-800">{{ __('Cancel') }}</span>
                </ui-button>

                <ui-button
                    color="indigo-500"
                    @click="handleNewProjectSubmit"
                    :class="{ 'opacity-25': processing }"
                    :disabled="processing"
                >
                    {{ __('Create New Project') }}
                </ui-button>
            </template>
        </ui-modal>
    </div>
</template>

<script>
import UiModal from "../../components/Modal.vue";
import UiButton from "../../components/Button.vue";
import UiSwitch from "../../components/UiSwitch.vue";

import localesJson from "../../locales.json";
import { useAdminStore } from '../store';
import { __ } from '../translations/engine';

function detectBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch (e) {
        return 'UTC';
    }
}

export default {
    components: {
        UiModal,
        UiButton,
        UiSwitch,
    },

    data() {
        return {
            openNewProjectModal: false,
            new_project: {
                default_locale: "en",
                type: 1,
                timezone: detectBrowserTimezone(),
                errors: {
                    name: [],
                    slug: [],
                },
                slugExists: false,
                slugManuallyEdited: false,
            },
            projects: [],
            processing: false,
            search: "",
            locales: [],
            loading: false,
            searchDebounce: null,
            filterStatus: 'all',
            sortBy: 'created_asc',
            openMenuId: null,
        };
    },

    computed: {
        filterOptions() {
            const all = this.projects.length;
            const active = this.projects.filter(p => p.status).length;
            const inactive = all - active;
            return [
                { label: __('All'), value: 'all', count: all },
                { label: __('Active'), value: 'active', count: active },
                { label: __('Inactive'), value: 'inactive', count: inactive },
            ];
        },
        filteredProjects() {
            let list = [...this.projects];
            if (this.filterStatus === 'active') {
                list = list.filter(p => p.status);
            }
            if (this.filterStatus === 'inactive') {
                list = list.filter(p => !p.status);
            }
            switch (this.sortBy) {
                case 'name_asc': {
                    list.sort((a, b) => (a.name || '').localeCompare(b.name || '')); 
                    break;
                }
                case 'name_desc': {
                    list.sort((a, b) => (b.name || '').localeCompare(a.name || '')); 
                    break;
                }
                case 'created_desc': {
                    list.sort((a, b) => b.id - a.id); 
                    break;
                }
                case 'created_asc': {
                    list.sort((a, b) => a.id - b.id); 
                    break;
                }
            }
            return list;
        }
    },

    methods: {
        generateSlugFromName() {
            if (this.new_project.slugManuallyEdited) {
                return;
            }

            this.new_project.slug = this.$slugify(this.new_project.name || '');
        },

        onSlugInput() {
            this.new_project.slugManuallyEdited = true;
            this.clearSlugError();
        },

        localeKey(option) {
            return typeof option === 'string' ? option : option.id;
        },

        localeLabel(option) {
            if (typeof option === 'string') {
                const found = this.locales.find((l) => l.id === option);
                return found ? found.id + ' - ' + found.name : option;
            }
            return option.id + ' - ' + option.name;
        },

        checkSlug() {
            let slug = this.new_project.slug;
            if (!slug || slug === '') return;

            axios.get("projects/check-slug/" + slug)
                .then((response) => {
                    if (!response.data.available) {
                        this.new_project.slugExists = true;
                    } else {
                        this.new_project.slugExists = false;
                    }
                });
        },

        clearSlugError() {
            this.new_project.slugExists = false;
        },

        handleNewProjectSubmit() {
            this.processing = true;

            axios.post("projects", this.new_project).then(
                (response) => {
                    this.$toast.success(this.__("New project created."));
                    this.closeNewProjectModal();
                    this.projects.unshift(response.data);
                },
                (error) => {
                    if (error.response && error.response.status == 422) {
                        this.new_project.errors = error.response.data.errors;
                        this.processing = false;
                    }
                }
            );
        },

        closeNewProjectModal() {
            this.openNewProjectModal = false;
            this.new_project = {
                default_locale: "en",
                type: 1,
                timezone: detectBrowserTimezone(),
                errors: {
                    name: [],
                    slug: [],
                },
                slugExists: false,
                slugManuallyEdited: false,
            };
            this.processing = false;
        },

        onSearchInput() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
                this.getProjects();
            }, 300);
        },

        getProjects() {
            this.loading = true;
            axios
                .get("projects", { params: { search: this.search } })
                .then((response) => {
                    this.projects = response.data;
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },

        closeMenu() {
            this.openMenuId = null;
        },

        canToggleStatus(project) {
            return project && ["owner", "admin"].includes(project.my_role);
        },

        canDeleteProject(project) {
            return project && project.my_role === 'owner';
        },

        deleteProject(project) {
            if (!project.status) {
                this.$toast.error(this.__("Please reactivate the project before deleting."));
                return;
            }
            this.$swal
                .fire({
                    title: this.__("Are you sure"),
                    text: this.__("you want to delete this project? All the collections and the content will be lost. You won't be able to revert this!"),
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        axios
                            .delete("projects/delete/" + project.id)
                            .then(() => {
                                this.$toast.success(this.__("Project deleted."));
                                this.projects = this.projects.filter(
                                    (p) => p.id !== project.id
                                );
                            });
                    }
                });
        },

        toggleStatus(project, value) {
            const applyToggle = () => {
                axios
                    .post(`projects/toggle-status/${project.id}`)
                    .then((response) => {
                        const updated = response.data;
                        const index = this.projects.findIndex(
                            (p) => p.id === updated.id
                        );
                        if (index !== -1) this.projects[index] = updated;
                        this.$toast.success(
                            updated.status
                                ? this.__("Project enabled.")
                                : this.__("Project disabled.")
                        );
                    })
                    .catch((error) => {
                        const message =
                            error.response?.data?.message ||
                            this.__("Failed to update project status.");
                        this.$toast.error(message);
                        this.getProjects();
                    });
            };

            if (value === false) {
                this.$swal
                    .fire({
                        title: this.__("Disable this project?"),
                        text: this.__("External API calls will return 404. Admin editing remains available."),
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: this.__("Disable"),
                        cancelButtonText: this.__("Cancel"),
                    })
                    .then((result) => {
                        if (result.isConfirmed) {
                            applyToggle();
                        }
                    });
                return;
            }

            applyToggle();
        },

        handleOutsideClick(e) {
            if (this.openMenuId !== null && !e.target.closest('.project-more-menu')) {
                this.closeMenu();
            }
        },
    },

    created() {
        useAdminStore().setTopbarContent({ 
            page: 'projects',
            type: 'projectList', 
            title: this.__('Project List'),
            breadcrumb: [
                { name: this.__('Dashboard'), url: '/', icon: 'fa fa-tachometer-alt' },
                { name: this.__('Project List'), icon: 'fas fa-list' },
            ],
        });
    },

    mounted() {
        this.getProjects();

        Object.entries(localesJson).forEach((item, key) => {
            this.locales.push({ id: item[0], name: item[1] });
        });

        document.addEventListener('click', this.handleOutsideClick);
    },

    beforeUnmount() {
        document.removeEventListener('click', this.handleOutsideClick);
    },


};
</script>

<style scoped>
.project-desc {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 2.5rem;
}
</style>