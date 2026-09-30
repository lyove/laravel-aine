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
 *       'projects.content.list': () => import('./Content/List.vue'),
 *       'projects.content.edit': () => import('./Content/Edit.vue'),
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
        'projects.content.list': () => import('./Content/List.vue'),
        // Per-collection content lists — each content type has its own isolated UI
        'projects.content.list.pages':      () => import('./Content/Pages/List.vue'),
        'projects.content.list.posts':      () => import('./Content/Posts/List.vue'),
        'projects.content.list.categories': () => import('./Content/Categories/List.vue'),
        'projects.content.list.tags':       () => import('./Content/Tags/List.vue'),
        // Per-collection New / Edit
        'projects.content.new.pages':      () => import('./Content/Pages/New/index.vue'),
        'projects.content.new.posts':      () => import('./Content/Posts/New/index.vue'),
        'projects.content.new.categories': () => import('./Content/Categories/New/index.vue'),
        'projects.content.new.tags':       () => import('./Content/Tags/New/index.vue'),
        'projects.content.edit.pages':     () => import('./Content/Pages/Edit/index.vue'),
        'projects.content.edit.posts':     () => import('./Content/Posts/Edit/index.vue'),
        'projects.content.edit.categories':() => import('./Content/Categories/Edit/index.vue'),
        'projects.content.edit.tags':      () => import('./Content/Tags/Edit/index.vue'),
    },
};
