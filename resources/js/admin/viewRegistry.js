/**
 * View Registry - Maps route names to view components with theme support.
 *
 * Theme packages: resources/js/admin/themes/{slug}/
 * Resolution priority: runtime (manifest.json) > legacy (config.js) > default views
 */

// Default fallback views
const defaultViews = {
    'projects.index':               () => import('./views/Project.Index/Index.vue'),
    'projects.collections':         () => import('./views/Project.Collection/Index.vue'),
    'projects.collections.list':    () => import('./views/Project.Collection/List.vue'),
    'projects.content':             () => import('./views/Project.Content/Index/index.vue'),
    'projects.content.list':        () => import('./views/Project.Content/List/index.vue'),
    'projects.content.new':         () => import('./views/Project.Content/New/index.vue'),
    'projects.content.edit':        () => import('./views/Project.Content/Edit/index.vue'),
    'projects.content.forms':       () => import('./views/Project.Content/Forms/index.vue'),
    'projects.content.forms.detail':() => import('./views/Project.Content/FormsDetail/index.vue'),
    'projects.media_library':       () => import('./views/Project.Content/Media/index.vue'),
    'projects.settings':            () => import('./views/Project.Settings/Index.vue'),
    'projects.settings.preferences':() => import('./views/Project.Settings/Preferences.vue'),
    'projects.settings.locales':    () => import('./views/Project.Settings/Locales.vue'),
    'projects.settings.users':      () => import('./views/Project.Settings/Users.vue'),
    'projects.settings.api':        () => import('./views/Project.Settings/API.vue'),
    'projects.settings.webhooks':   () => import('./views/Project.Settings/Webhooks.vue'),
    'projects.settings.webhooks.logs': () => import('./views/Project.Settings/WebhookLogs.vue'),
    'projects.settings.translations':  () => import('./views/Project.Settings/Translations.vue'),
    'projects.settings.language':      () => import('./views/Project.Settings/Language.vue'),
    'projects.settings.audit-logs':    () => import('./views/Project.Settings/AuditLogs.vue'),
};

// Theme registries
const legacyRegistry = {};   // From config.js (legacy)
const runtimeRegistry = {};  // From manifest.json via themeEngine

// Pre-resolved theme components via Vite glob
const themeModuleMap = import.meta.glob('./themes/**/*.vue');

/**
 * Register a legacy theme (from config.js).
 */
export function registerLegacyTheme(slug, config) {
    legacyRegistry[slug] = {
        name: config.name || slug,
        description: config.description || '',
        views: config.views || {},
    };
}

/**
 * Register a theme at runtime (from manifest.json via themeEngine).
 * Takes priority over legacy config.js registrations.
 */
export function registerTheme(slug, themeData) {
    const views = {};

    if (themeData.view_overrides) {
        for (const [routeName, componentPath] of Object.entries(themeData.view_overrides)) {
            const globKey = `./themes/${slug}/${componentPath.replace(/^\.\//, '')}`;
            if (themeModuleMap[globKey]) {
                views[routeName] = themeModuleMap[globKey];
            } else {
                console.warn(`[viewRegistry] Theme component not found: ${globKey}`);
            }
        }
    }

    runtimeRegistry[slug] = {
        name: themeData.name || slug,
        version: themeData.version || '1.0.0',
        assetPath: themeData.asset_path || `themes/${slug}`,
        views,
        designTokens: themeData.design_tokens || {},
    };
}

export function unregisterTheme(slug) {
    delete runtimeRegistry[slug];
}

export function isThemeRegistered(slug) {
    return slug in runtimeRegistry || slug in legacyRegistry;
}

/**
 * Get the view loader for a route.
 * Priority: runtime > legacy > default
 */
export function resolveView(routeName, uiSlug = null) {
    if (uiSlug && runtimeRegistry[uiSlug]?.views[routeName]) {
        return runtimeRegistry[uiSlug].views[routeName];
    }
    if (uiSlug && legacyRegistry[uiSlug]?.views[routeName]) {
        return legacyRegistry[uiSlug].views[routeName];
    }
    return defaultViews[routeName] || null;
}

/**
 * Get the view loader for a content route (collection-aware).
 * Priority: per-collection > generic > default
 */
export function resolveContentView(routeName, uiSlug = null, collectionSlug = null) {
    if (collectionSlug) {
        const collectionKey = `${routeName}.${collectionSlug}`;

        if (uiSlug && runtimeRegistry[uiSlug]?.views[collectionKey]) {
            return runtimeRegistry[uiSlug].views[collectionKey];
        }
        if (uiSlug && legacyRegistry[uiSlug]?.views[collectionKey]) {
            return legacyRegistry[uiSlug].views[collectionKey];
        }
        if (uiSlug && runtimeRegistry[uiSlug]?.views[routeName]) {
            return runtimeRegistry[uiSlug].views[routeName];
        }
        if (uiSlug && legacyRegistry[uiSlug]?.views[routeName]) {
            return legacyRegistry[uiSlug].views[routeName];
        }
    }
    return resolveView(routeName, uiSlug);
}

export function getThemeDesignTokens(uiSlug) {
    if (uiSlug && runtimeRegistry[uiSlug]) {
        return { ...runtimeRegistry[uiSlug].designTokens };
    }
    return {};
}

export function getAllThemes() {
    const themes = {};

    for (const [slug, config] of Object.entries(legacyRegistry)) {
        themes[slug] = { slug, name: config.name, description: config.description, source: 'legacy' };
    }
    for (const [slug, config] of Object.entries(runtimeRegistry)) {
        themes[slug] = { slug, name: config.name, version: config.version, source: 'runtime', designTokens: config.designTokens };
    }

    return themes;
}

export function getTheme(slug) {
    return runtimeRegistry[slug] || legacyRegistry[slug] || null;
}

/**
 * Preload legacy theme configs (config.js) via Vite glob import.
 */
export async function preloadProjectUis() {
    const uiModules = import.meta.glob('./themes/*/config.js', { eager: true });

    for (const [path, module] of Object.entries(uiModules)) {
        const match = path.match(/\.\/themes\/(.+)\/config\.js/);
        if (match) {
            registerLegacyTheme(match[1], module.default || module);
        }
    }
}

export function getAvailableProjectUis() {
    return [...new Set([...Object.keys(legacyRegistry), ...Object.keys(runtimeRegistry)])];
}

// Deprecated aliases
export function registerProjectUi(slug, config) { registerLegacyTheme(slug, config); }
export function getProjectUi(slug) { return getTheme(slug); }
export function getProjectUis() {
    const result = {};
    for (const slug of getAvailableProjectUis()) result[slug] = getTheme(slug);
    return result;
}
