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
 *       'projects.collections.content.list': () => import('./Content/List.vue'),
 *       'projects.collections.content.edit': () => import('./Content/Edit.vue'),
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
        'projects.collections.content.list': () => import('./Content/List/index.vue'),
        'projects.collections.content.new': () => import('./Content/New/index.vue'),
        'projects.collections.content.edit': () => import('./Content/Edit/index.vue'),
        'projects.collections.content.list.pages':      () => import('./Content/Pages/List/index.vue'),
        'projects.collections.content.list.articles':   () => import('./Content/Articles/List/index.vue'),
        'projects.collections.content.list.categories': () => import('./Content/Categories/List/index.vue'),
        'projects.collections.content.list.tags':       () => import('./Content/Tags/List/index.vue'),
        'projects.collections.content.new.pages':       () => import('./Content/Pages/New/index.vue'),
        'projects.collections.content.new.articles':    () => import('./Content/Articles/New/index.vue'),
        'projects.collections.content.new.categories':  () => import('./Content/Categories/New/index.vue'),
        'projects.collections.content.new.tags':        () => import('./Content/Tags/New/index.vue'),
        'projects.collections.content.edit.pages':      () => import('./Content/Pages/Edit/index.vue'),
        'projects.collections.content.edit.articles':   () => import('./Content/Articles/Edit/index.vue'),
        'projects.collections.content.edit.categories': () => import('./Content/Categories/Edit/index.vue'),
        'projects.collections.content.edit.tags':       () => import('./Content/Tags/Edit/index.vue'),
    },
};
