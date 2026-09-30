/**
 * Directory Theme Configuration
 * 
 * This theme is used for projects created with the Directory Template.
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
    name: 'Directory',
    description: 'Business Directory project UI with listings, categories, locations, and reviews',
    icon: 'fa-store',
    
    // Project-specific view overrides
    // Leave empty to use the shared default views
    views: {
        'projects.index': () => import('./Index/index.vue'),
        // Generic content list (globals, new collections)
        'projects.content.list': () => import('./Content/List.vue'),
        // Per-collection content lists — each content type has its own isolated UI
        'projects.content.list.listings':   () => import('./Content/Listings/List.vue'),
        'projects.content.list.categories': () => import('./Content/Categories/List.vue'),
        'projects.content.list.tags':       () => import('./Content/Tags/List.vue'),
        'projects.content.list.locations':  () => import('./Content/Locations/List.vue'),
        'projects.content.list.reviews':    () => import('./Content/Reviews/List.vue'),
        // Per-collection New / Edit
        'projects.content.new.listings':       () => import('./Content/Listings/New/index.vue'),
        'projects.content.new.categories':     () => import('./Content/Categories/New/index.vue'),
        'projects.content.new.tags':           () => import('./Content/Tags/New/index.vue'),
        'projects.content.new.locations':      () => import('./Content/Locations/New/index.vue'),
        'projects.content.new.reviews':        () => import('./Content/Reviews/New/index.vue'),
        'projects.content.edit.listings':      () => import('./Content/Listings/Edit/index.vue'),
        'projects.content.edit.categories':    () => import('./Content/Categories/Edit/index.vue'),
        'projects.content.edit.tags':          () => import('./Content/Tags/Edit/index.vue'),
        'projects.content.edit.locations':     () => import('./Content/Locations/Edit/index.vue'),
        'projects.content.edit.reviews':       () => import('./Content/Reviews/Edit/index.vue'),
    },
};
