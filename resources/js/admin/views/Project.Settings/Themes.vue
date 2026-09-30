<template>
    <div class="relative h-full flex flex-col overflow-y-auto">
        <project-header :project="project"></project-header>
        <div class="flex">
            <div class="w-3/12 bg-white overflow-x-hidden">
                <settings-sidebar :project="project"></settings-sidebar>
            </div>
            <div class="w-9/12 overflow-x-hidden">
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800">{{ __('Themes') }}</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Choose a theme for your project admin interface.') }}</p>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading" class="flex items-center justify-center py-12">
                        <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                    </div>

                    <!-- Theme Grid -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div
                            v-for="theme in themes"
                            :key="theme.id"
                            :class="[
                                'relative rounded-xl border-2 p-5 transition-all cursor-pointer',
                                isActive(theme)
                                    ? 'border-indigo-500 bg-indigo-50 shadow-md'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'
                            ]"
                            @click="selectTheme(theme)"
                        >
                            <!-- Active Badge -->
                            <div v-if="isActive(theme)" class="absolute top-3 right-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    <i class="fas fa-check mr-1"></i> {{ __('Active') }}
                                </span>
                            </div>

                            <!-- Theme Icon & Name -->
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center text-white text-xl"
                                    :style="{ backgroundColor: getThemeColor(theme) }"
                                >
                                    <i :class="theme.icon || 'fa-palette'"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 truncate">{{ theme.name }}</h3>
                                    <p class="text-sm text-gray-500 mt-0.5">v{{ theme.version }} <span v-if="theme.author">by {{ theme.author }}</span></p>
                                </div>
                            </div>

                            <!-- Description -->
                            <p class="text-sm text-gray-600 mt-3 line-clamp-2">{{ theme.description }}</p>

                            <!-- Color Preview -->
                            <div class="flex items-center gap-2 mt-4">
                                <div
                                    v-for="(color, index) in getThemeColors(theme)"
                                    :key="index"
                                    class="w-6 h-6 rounded-full border border-gray-200"
                                    :style="{ backgroundColor: color }"
                                    :title="color"
                                ></div>
                            </div>

                            <!-- Projects Count -->
                            <div class="mt-4 pt-3 border-t border-gray-100">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-cube mr-1"></i>
                                    {{ theme.projects_count }} {{ __('project(s) using this theme') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!loading && themes.length === 0" class="text-center py-12">
                        <i class="fas fa-palette text-gray-300 text-4xl mb-4"></i>
                        <p class="text-gray-500">{{ __('No themes available.') }}</p>
                    </div>

                    <!-- Design Token Customization -->
                    <div v-if="selectedTheme && isActive(selectedTheme)" class="mt-8 bg-white rounded-xl border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ __('Customize Colors') }}</h3>
                        <p class="text-sm text-gray-500 mb-4">{{ __('Override the default design tokens for this project.') }}</p>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="(value, key) in colorTokens" :key="key" class="flex items-center gap-2">
                                <input
                                    type="color"
                                    :value="value"
                                    @input="updateToken(key, $event.target.value)"
                                    class="w-8 h-8 rounded border border-gray-300 cursor-pointer"
                                />
                                <div class="flex-1 min-w-0">
                                    <label class="text-xs text-gray-600 truncate block">{{ formatTokenName(key) }}</label>
                                    <span class="text-xs text-gray-400">{{ value }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-3">
                            <ui-button :color="'green-500'" :disabled="isReadonly || !hasTokenChanges" @click="saveTokens">
                                {{ __('Save Customizations') }}
                            </ui-button>
                            <button
                                v-if="hasTokenChanges"
                                @click="resetTokens"
                                class="text-sm text-gray-500 hover:text-gray-700"
                            >
                                {{ __('Reset to Defaults') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import UiButton from '@/components/Button.vue';
import ProjectHeader from '@/admin/components/ProjectHeader.vue';
import SettingsSidebar from '@/admin/components/SettingsSidebar.vue';
import projectBreadcrumb from '@/admin/mixins/projectBreadcrumb';
import { __ } from '@/admin/translations/engine';
import { useAdminStore } from '@/admin/store';
import { fetchAvailableThemes } from '@/admin/themeEngine';

export default {
    components: { UiButton, ProjectHeader, SettingsSidebar },
    mixins: [projectBreadcrumb],
    data() {
        const store = useAdminStore();
        return {
            project: store.currentProject || {},
            themes: [],
            loading: true,
            selectedTheme: null,
            editableTokens: {},
            originalTokens: {},
        };
    },
    computed: {
        isReadonly() {
            return this.project && (this.project.is_readonly || !this.project.status);
        },
        hasTokenChanges() {
            return JSON.stringify(this.editableTokens) !== JSON.stringify(this.originalTokens);
        },
        colorTokens() {
            const colors = {};
            for (const [key, value] of Object.entries(this.editableTokens)) {
                if (key.includes('color') && typeof value === 'string' && value.startsWith('#')) {
                    colors[key] = value;
                }
            }
            return colors;
        },
    },
    async mounted() {
        await this.loadThemes();
    },
    methods: {
        async loadThemes() {
            this.loading = true;
            try {
                this.themes = await fetchAvailableThemes();
                // Find the currently active theme
                const store = useAdminStore();
                const currentSlug = store.currentProject?.theme?.slug;
                this.selectedTheme = this.themes.find(t => t.slug === currentSlug) || null;

                // Load editable tokens for the active theme
                if (this.selectedTheme) {
                    await this.loadThemeTokens();
                }
            } catch (error) {
                console.error('Failed to load themes:', error);
                this.$toast.error(__('Failed to load themes.'));
            } finally {
                this.loading = false;
            }
        },

        async loadThemeTokens() {
            if (!this.selectedTheme) return;

            try {
                const store = useAdminStore();
                const { data } = await axios.get(`projects/themes/${store.currentProject.id}`);

                // Merge theme defaults with project overrides
                const defaults = this.selectedTheme.design_tokens || {};
                const overrides = data.project_theme_config || {};
                this.editableTokens = { ...defaults, ...overrides };
                this.originalTokens = { ...this.editableTokens };
            } catch (error) {
                console.warn('Failed to load theme tokens:', error);
            }
        },

        isActive(theme) {
            const store = useAdminStore();
            return store.currentProject?.theme?.slug === theme.slug;
        },

        getThemeColor(theme) {
            const tokens = theme.design_tokens || {};
            return tokens['--theme-color-primary'] || '#6366f1';
        },

        getThemeColors(theme) {
            const tokens = theme.design_tokens || {};
            return [
                tokens['--theme-color-primary'],
                tokens['--theme-color-secondary'],
                tokens['--theme-color-accent'],
                tokens['--theme-color-success'],
                tokens['--theme-color-warning'],
                tokens['--theme-color-danger'],
            ].filter(Boolean);
        },

        async selectTheme(theme) {
            if (this.isActive(theme) || this.isReadonly) return;

            // Confirm theme change
            const result = await this.$swal.fire({
                title: __('Switch Theme?'),
                text: __('Switch to') + ` "${theme.name}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: __('Switch'),
                cancelButtonText: __('Cancel'),
            });

            if (!result.isConfirmed) return;

            try {
                const store = useAdminStore();
                await store.applyTheme(theme.id);
                this.selectedTheme = theme;
                await this.loadThemeTokens();

                this.$toast.success(__('Theme switched successfully.'));

                // Reload the current route to apply new views
                // The watch on theme.slug in projectUiView will handle this
            } catch (error) {
                console.error('Failed to apply theme:', error);
                this.$toast.error(__('Failed to switch theme.'));
            }
        },

        updateToken(key, value) {
            this.editableTokens = { ...this.editableTokens, [key]: value };
        },

        async saveTokens() {
            try {
                const store = useAdminStore();
                await store.updateThemeConfig(this.editableTokens);
                this.originalTokens = { ...this.editableTokens };
                this.$toast.success(__('Customizations saved.'));
            } catch (error) {
                console.error('Failed to save tokens:', error);
                this.$toast.error(__('Failed to save customizations.'));
            }
        },

        resetTokens() {
            if (!this.selectedTheme) return;
            this.editableTokens = { ...(this.selectedTheme.design_tokens || {}) };
        },

        formatTokenName(key) {
            return key
                .replace(/^--theme-/, '')
                .replace(/-([a-z])/g, (_, c) => ' ' + c.toUpperCase())
                .replace(/^./, s => s.toUpperCase());
        },
    },
};
</script>
