/**
 * CMS Project UI Configuration
 * 
 * This theme is used for projects created with the CMS Template.
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
    name: 'CMS',
    description: 'Content Management System theme with articles, pages, categories, and tags',
    icon: 'fa-newspaper',
    
    // Project-specific view overrides
    // Leave empty to use the shared default views
    views: {
        'projects.index': () => import('./Index/index.vue'),
        // Generic content pages (collections without a dedicated folder: comments, globals, new collections)
        'projects.content.list': () => import('./Content/List/index.vue'),
        'projects.content.new': () => import('./Content/New/index.vue'),
        'projects.content.edit': () => import('./Content/Edit/index.vue'),
        // Per-collection content pages — each content type has its own isolated UI
        'projects.content.list.pages':      () => import('./Content/Pages/List/index.vue'),
        'projects.content.list.articles':   () => import('./Content/Articles/List/index.vue'),
        'projects.content.list.categories': () => import('./Content/Categories/List/index.vue'),
        'projects.content.list.tags':       () => import('./Content/Tags/List/index.vue'),
        'projects.content.new.pages':       () => import('./Content/Pages/New/index.vue'),
        'projects.content.new.articles':    () => import('./Content/Articles/New/index.vue'),
        'projects.content.new.categories':  () => import('./Content/Categories/New/index.vue'),
        'projects.content.new.tags':        () => import('./Content/Tags/New/index.vue'),
        'projects.content.edit.pages':      () => import('./Content/Pages/Edit/index.vue'),
        'projects.content.edit.articles':   () => import('./Content/Articles/Edit/index.vue'),
        'projects.content.edit.categories': () => import('./Content/Categories/Edit/index.vue'),
        'projects.content.edit.tags':       () => import('./Content/Tags/Edit/index.vue'),
    },
};
