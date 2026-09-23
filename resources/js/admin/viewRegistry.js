/**
 * View Registry - Project Theme System
 * 
 * This module manages the mapping between route names and view components,
 * allowing different projects to use different UI themes.
 * 
 * Each theme lives in resources/js/admin/themes/{slug}/ and has a config.js
 * that declares which views it overrides.
 * 
 * Usage:
 *   - resolveView(routeName, themeSlug) -> returns the component loader
 *   - preloadThemes() -> scans themes/ directory and registers all configs
 */

// Default views (the existing views/ directory)
const defaultViews = {
    'projects.index':               () => import('./views/Project.Index/Index.vue'),
    'projects.collections':         () => import('./views/Project.Collection/CollectionIndex.vue'),
    'projects.collections.list':    () => import('./views/Project.Collection/CollectionList.vue'),
    'projects.content':             () => import('./views/Project.Content/ContentIndex.vue'),
    'projects.content.list':        () => import('./views/Project.Content/List.vue'),
    'projects.content.new':         () => import('./views/Project.Content/New.vue'),
    'projects.content.edit':        () => import('./views/Project.Content/Edit.vue'),
    'projects.comments':            () => import('./views/Project.Content/Comments.vue'),
    'projects.content.forms':       () => import('./views/Project.Content/Forms.vue'),
    'projects.content.forms.detail':() => import('./views/Project.Content/FormsDetail.vue'),
    'projects.media_library':       () => import('./views/Project.Content/Media.vue'),
    'projects.settings':            () => import('./views/Project.Settings/SettingsIndex.vue'),
    'projects.settings.preferences':() => import('./views/Project.Settings/Preferences.vue'),
    'projects.settings.locales':    () => import('./views/Project.Settings/Locales.vue'),
    'projects.settings.users':      () => import('./views/Project.Settings/Users.vue'),
    'projects.settings.api':        () => import('./views/Project.Settings/API.vue'),
    'projects.settings.webhooks':   () => import('./views/Project.Settings/Webhooks.vue'),
    'projects.settings.webhooks.logs': () => import('./views/Project.Settings/WebhookLogs.vue'),
    'projects.settings.translations':  () => import('./views/Project.Settings/ProjectTranslations.vue'),
    'projects.settings.language':      () => import('./views/Project.Settings/ProjectLanguage.vue'),
    'projects.settings.audit-logs':    () => import('./views/Project.Settings/AuditLogs.vue'),
};

// Theme registry: slug -> config
const themeRegistry = {};

/**
 * Register a theme configuration
 */
export function registerTheme(slug, config) {
    themeRegistry[slug] = config;
}

/**
 * Get the view loader for a route, considering the current theme
 * 
 * Priority: theme override > default view
 * 
 * @param {string} routeName - The Vue Router route name
 * @param {string|null} themeSlug - The project's admin_theme slug
 * @returns {Function|null} - A dynamic import function or null
 */
export function resolveView(routeName, themeSlug = null) {
    // Check if theme has an override for this route
    if (themeSlug && themeRegistry[themeSlug]) {
        const themeConfig = themeRegistry[themeSlug];
        if (themeConfig.views && themeConfig.views[routeName]) {
            return themeConfig.views[routeName];
        }
    }
    // Fallback to default view
    return defaultViews[routeName] || null;
}

/**
 * Get all registered themes
 */
export function getThemes() {
    return { ...themeRegistry };
}

/**
 * Get a specific theme config
 */
export function getTheme(slug) {
    return themeRegistry[slug] || null;
}

/**
 * Preload all theme configurations using Vite's glob import
 * This automatically discovers themes in the themes/ directory
 */
export async function preloadThemes() {
    // Vite glob import - automatically finds all theme config files
    const themeModules = import.meta.glob('./themes/*/config.js', { eager: true });
    
    for (const [path, module] of Object.entries(themeModules)) {
        // Extract theme slug from path: ./themes/cms/config.js -> cms
        const match = path.match(/\.\/themes\/(.+)\/config\.js/);
        if (match) {
            const slug = match[1];
            const config = module.default || module;
            registerTheme(slug, config);
        }
    }
}

/**
 * Get the list of available theme slugs
 */
export function getAvailableThemes() {
    return Object.keys(themeRegistry);
}
