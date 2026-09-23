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
 *       'projects.content.list': () => import('./views/Content/List.vue'),
 *       'projects.content.edit': () => import('./views/Content/Edit.vue'),
 *   }
 * 
 * Any route not listed in views will fall back to the default view.
 */
export default {
    name: 'Directory',
    description: 'Business Directory theme with listings, categories, locations, and reviews',
    icon: 'fa-store',
    
    // Override specific views for this theme
    // Leave empty to use all default views
    views: {
        'projects.index': () => import('./views/Index/index.vue'),
        'projects.content.list': () => import('./views/Content/List.vue'),
    },
};
