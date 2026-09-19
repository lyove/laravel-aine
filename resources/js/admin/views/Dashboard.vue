<template>
    <div class="admin__projects-dashboard m-3 p-3 overflow-y-auto">
        <!-- Welcome Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Welcome back') }}, {{ userName }}!</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Here\'s what\'s happening with your projects today.') }}</p>
        </div>

        <!-- Overview Statistics Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('My Projects') }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.my_projects || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-folder text-indigo-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Total Content') }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total_content || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Media Files') }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.media_files || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-image text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Pending Comments') }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.pending_comments || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-comments text-amber-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column: My Projects & Todos -->
            <div class="lg:col-span-2 space-y-6">
                <!-- My Projects -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('My Projects') }}</h2>
                        <router-link to="/projects" class="text-xs text-indigo-600 hover:text-indigo-700">
                            {{ __('View all') }} →
                        </router-link>
                    </div>
                    <div class="p-4">
                        <div v-if="myProjects.length === 0" class="text-center py-8 text-sm text-gray-500">
                            {{ __('No projects yet') }}
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="project in myProjects"
                                :key="project.id"
                                class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors"
                            >
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="w-10 h-10 rounded-md bg-indigo-50 flex items-center justify-center flex-shrink-0 border border-indigo-100 overflow-hidden">
                                        <img v-if="project.logo_url" :src="project.logo_url" class="w-full h-full object-cover" :alt="project.name" />
                                        <span v-else class="text-sm font-bold text-indigo-500 uppercase">{{ (project.name || '?').charAt(0) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm text-gray-900 truncate">{{ project.name }}</span>
                                            <span
                                                :class="[
                                                    'w-2 h-2 rounded-full flex-shrink-0',
                                                    project.status ? 'bg-green-400' : 'bg-gray-300'
                                                ]"
                                            ></span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                            <span>{{ project.content_count || 0 }} {{ __('contents') }}</span>
                                            <span>·</span>
                                            <span class="capitalize">{{ project.my_role }}</span>
                                        </div>
                                    </div>
                                </div>
                                <router-link
                                    :to="{ name: 'projects.index', params: { project_id: project.id } }"
                                    class="flex-shrink-0 text-xs text-indigo-600 hover:text-indigo-700 font-medium"
                                >
                                    {{ __('Enter') }} →
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- App Info -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('System Info') }}</h2>
                    </div>
                    <div class="p-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('Application') }}</span>
                            <span class="font-medium text-gray-900">{{ settings.name || 'Aine' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('Version') }}</span>
                            <span class="font-medium text-gray-900">{{ settings.version || '0.0.1' }}</span>
                        </div>
                    </div>
                </div>

                <!-- System Status (super admin only) -->
                <div v-if="isSuperAdmin" class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('System Status') }}</h2>
                    </div>
                    <div class="p-4">
                        <div class="grid gap-3 sm:grid-cols-2" v-if="status">
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('PHP Version') }}</p>
                                <p class="text-sm font-semibold text-slate-900 mt-1">{{ status.php_version }}</p>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('Laravel Version') }}</p>
                                <p class="text-sm font-semibold text-slate-900 mt-1">{{ status.laravel_version }}</p>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('Environment') }}</p>
                                <p class="text-sm font-semibold mt-1" :class="status.environment === 'production' ? 'text-red-600' : 'text-green-600'">{{ status.environment }}</p>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('Database') }}</p>
                                <p class="text-sm font-semibold mt-1" :class="status.database.connected ? 'text-green-600' : 'text-red-600'">
                                    {{ status.database.driver }} · {{ status.database.connected ? __('Connected') : __('Disconnected') }}
                                </p>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('Disk Space') }}</p>
                                <p class="text-sm font-semibold text-slate-900 mt-1">{{ status.disk.used }} / {{ status.disk.total }}</p>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-3">
                                <p class="text-xs text-slate-500">{{ __('Maintenance Mode') }}</p>
                                <p class="text-sm font-semibold mt-1" :class="status.maintenance ? 'text-red-600' : 'text-green-600'">
                                    {{ status.maintenance ? __('Active') : __('Inactive') }}
                                </p>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center text-sm text-slate-400">{{ __('Loading...') }}</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Todos & Quick Actions -->
            <div class="space-y-6">
                <!-- Todo Items -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('Todo') }}</h2>
                    </div>
                    <div class="p-4">
                        <div v-if="todos.length === 0" class="text-center py-8 text-sm text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p>{{ __('All caught up!') }}</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(todo, index) in todos"
                                :key="index"
                                class="flex items-center justify-between p-3 rounded-lg bg-amber-50 border border-amber-100"
                            >
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                        <i :class="getTodoIcon(todo.type)" class="text-amber-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ todo.message }}</p>
                                    </div>
                                </div>
                                <router-link
                                    :to="todo.action"
                                    class="flex-shrink-0 text-xs text-amber-700 hover:text-amber-800 font-medium"
                                >
                                    {{ __('Review') }}
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('Quick Actions') }}</h2>
                    </div>
                    <div class="p-4 space-y-2">
                        <router-link
                            to="/projects"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-plus text-indigo-600 text-xs"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ __('Create New Project') }}</span>
                        </router-link>

                        <router-link
                            v-if="myProjects.length > 0"
                            :to="{ name: 'projects.content', params: { project_id: myProjects[0].id } }"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-alt text-green-600 text-xs"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ __('Create New Content') }}</span>
                        </router-link>

                        <router-link
                            v-if="myProjects.length > 0"
                            :to="{ name: 'projects.media_library', params: { project_id: myProjects[0].id } }"
                            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-upload text-purple-600 text-xs"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ __('Upload Media') }}</span>
                        </router-link>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('Recent Activities') }}</h2>
                    </div>
                    <div class="p-4">
                        <div v-if="recentActivities.length === 0" class="text-center py-8 text-sm text-gray-500">
                            {{ __('No recent activities') }}
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="activity in recentActivities"
                                :key="activity.id + activity.timestamp"
                                class="flex items-start gap-3 text-sm"
                            >
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i :class="getActivityIcon(activity.action)" class="text-gray-600 text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-900">
                                        <span class="font-medium">{{ activity.user_name }}</span>
                                        {{ getActivityText(activity.action) }}
                                        <span class="font-medium">{{ activity.content_title }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ activity.collection_name }} · {{ activity.project_name }} · {{ activity.time }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { __ } from '../translations/engine';
    import { useAdminStore } from '../store';

    export default {
        data() {
            return {
                settings: {
                    name: null,
                    description: null,
                    version: null
                },
                status: null,
                stats: {},
                myProjects: [],
                todos: [],
                recentActivities: [],
            };
        },

        computed: {
            isSuperAdmin() {
                const store = useAdminStore();
                return (store.userRoles || []).includes('super_admin');
            },
            userName() {
                const store = useAdminStore();
                return store.user?.name || __('User');
            },
        },

        methods: {
            getSettings() {
                axios.get("settings").then(
                    (response) => { this.settings = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            loadStatus() {
                axios.get("system/status").then(
                    (response) => { this.status = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            loadStats() {
                axios.get("dashboard/stats").then(
                    (response) => { this.stats = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            loadMyProjects() {
                axios.get("dashboard/my-projects?limit=5").then(
                    (response) => { this.myProjects = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            loadTodos() {
                axios.get("dashboard/todos").then(
                    (response) => { this.todos = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            loadRecentActivities() {
                axios.get("dashboard/recent-activities?limit=8").then(
                    (response) => { this.recentActivities = response.data; },
                    (error) => { console.warn(error); }
                );
            },

            getTodoIcon(type) {
                const icons = {
                    'comments': 'fas fa-comments',
                    'drafts': 'fas fa-file-alt',
                    'trashed': 'fas fa-trash-alt',
                };
                return icons[type] || 'fas fa-bell';
            },

            getActivityIcon(action) {
                const icons = {
                    'published': 'fas fa-check-circle text-green-600',
                    'updated': 'fas fa-edit text-blue-600',
                    'deleted': 'fas fa-trash-alt text-red-600',
                };
                return icons[action] || 'fas fa-circle text-gray-600';
            },

            getActivityText(action) {
                const texts = {
                    'published': __('published'),
                    'updated': __('updated'),
                    'deleted': __('deleted'),
                };
                return texts[action] || action;
            },
        },

        created() {
            useAdminStore().setTopbarContent({
                page: 'dashboard',
                type: 'dashboard',
                title: __('Dashboard'),
                breadcrumb: [
                    { name: 'Dashboard', url: '/', icon: 'fa fa-tachometer-alt' },
                ],
            });
        },

        mounted() {
            this.getSettings();
            this.loadStats();
            this.loadMyProjects();
            this.loadTodos();
            this.loadRecentActivities();

            if (this.isSuperAdmin) {
                this.loadStatus();
            }
        },
    };
</script>
