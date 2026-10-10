/**
 * Note Theme Configuration
 * 
 * This theme is used for projects created with the Note Template.
 * It provides a content management focused admin interface.
 * 
 * To customize a specific page for this theme, add the view override below:
 * 
 * Example:
 *   views: {
 *       'projects.collections.content.list': () => import('./Content/List.vue'),
 *       'projects.collections.content.edit': () => import('./Content/Edit.vue'),
 *   }
 * 
 * Any route not listed in views will fall back to the default view.
 */
export default {
    name: 'Note',
    description: 'Note project UI with pages, posts, categories, and tags',
    icon: 'fa-sticky-note',
    
    // Project-specific view overrides
    // Leave empty to use the shared default views
    views: {
        'projects.index': () => import('./Index/index.vue'),
        // Generic content list (globals, new collections)
        'projects.collections.content.list': () => import('./Content/List.vue'),
        // Per-collection content lists — each content type has its own isolated UI
        'projects.collections.content.list.pages':      () => import('./Content/Pages/List.vue'),
        'projects.collections.content.list.posts':      () => import('./Content/Posts/List.vue'),
        'projects.collections.content.list.categories': () => import('./Content/Categories/List.vue'),
        'projects.collections.content.list.tags':       () => import('./Content/Tags/List.vue'),
        // Per-collection New / Edit
        'projects.collections.content.new.pages':      () => import('./Content/Pages/New/index.vue'),
        'projects.collections.content.new.posts':      () => import('./Content/Posts/New/index.vue'),
        'projects.collections.content.new.categories': () => import('./Content/Categories/New/index.vue'),
        'projects.collections.content.new.tags':       () => import('./Content/Tags/New/index.vue'),
        'projects.collections.content.edit.pages':     () => import('./Content/Pages/Edit/index.vue'),
        'projects.collections.content.edit.posts':     () => import('./Content/Posts/Edit/index.vue'),
        'projects.collections.content.edit.categories':() => import('./Content/Categories/Edit/index.vue'),
        'projects.collections.content.edit.tags':      () => import('./Content/Tags/Edit/index.vue'),
    },
};
