import { createRouter, createWebHistory } from 'vue-router';
import { defineComponent, ref, watch, h, onMounted } from 'vue';
import { useAdminStore } from './store';
import { resolveView, preloadThemes } from './viewRegistry';
import Swal from 'sweetalert2';

/**
 * Create a theme-aware route component.
 */
function themedView(routeName) {
    return defineComponent({
        name: `ThemedView_${routeName.replace(/\./g, '_')}`,
        setup() {
            const store = useAdminStore();
            const Comp = ref(null);
            const error = ref(null);
            let loadSeq = 0;

            const load = async () => {
                const seq = ++loadSeq;
                error.value = null;
                try {
                    const theme = store.currentProject?.admin_theme || null;
                    const loader = resolveView(routeName, theme);
                    if (!loader) {
                        if (seq === loadSeq) {
                            Comp.value = null;
                            error.value = `No view registered for route "${routeName}" (theme: ${theme || 'default'})`;
                        }
                        return;
                    }
                    const mod = await loader();
                    if (seq === loadSeq) {
                        Comp.value = mod.default || mod;
                    }
                } catch (e) {
                    console.error(`[themedView] failed to load view for "${routeName}":`, e);
                    if (seq === loadSeq) {
                        Comp.value = null;
                        error.value = (e && (e.message || String(e))) || 'Failed to load view';
                    }
                }
            };

            onMounted(load);

            // Re-load when the project theme changes (e.g. user changes theme in settings)
            watch(() => store.currentProject?.admin_theme, load);

            return () => {
                if (error.value) {
                    return h('div', {
                        class: 'flex flex-col items-center justify-center h-full text-red-500 text-sm p-6 text-center',
                    }, [
                        h('i', { class: 'fas fa-exclamation-triangle text-2xl mb-3' }),
                        h('div', {}, error.value),
                    ]);
                }
                return Comp.value
                    ? h(Comp.value)
                    : h('div', {
                        class: 'flex items-center justify-center h-full text-gray-400 text-sm',
                    }, [h('i', { class: 'fas fa-spinner fa-spin mr-2' }), 'Loading...']);
            };
        },
    });
}

// Preload all theme configurations at startup
preloadThemes();

/**
 * Route Guard Function
 */
const checkPermission = async (to, from, next, options = {}) => {
    const store = useAdminStore();

    if (!store.user.roles || store.user.roles.length === 0) {
        await store.getUser();
    }

    const roles = store.user.roles;
    const { requireSuperAdmin = false, requiredRoles = [], requiredProjectRoles = [], redirectTo = null } = options;

    const pass = () => {
        if (redirectTo) {
            return next({ name: redirectTo, params: { project_id: to.params.project_id } });
        }
        return next();
    };

    if (roles.includes("super_admin")) {
        return pass();
    }

    if (requireSuperAdmin) {
        return next("/");
    }

    if (requiredRoles.length > 0) {
        const hasRole = requiredRoles.some(role => roles.includes(role));
        if (!hasRole) {
            return next("/");
        }
    }

    if (requiredProjectRoles.length > 0) {
        const myRole = store.currentProject && store.currentProject.my_role;
        if (!requiredProjectRoles.includes(myRole)) {
            return next("/");
        }
    }

    return pass();
};

// Non-project routes (global, not theme-aware)
const Dashboard = () => import("./views/Dashboard.vue");
const Settings = () => import("./views/Settings.vue");
const Users = () => import("./views/Users.vue");
const Language = () => import("./views/Language.vue");
const Translations = () => import("./views/Translations.vue");
const Localization = () => import("./views/Localization.vue");
const Profile = () => import("./views/Profile.vue");
const Projects = () => import("./views/Projects.vue");

/**
 * Routes
 */
const routes = [
  { path: "/", name: "dashboard", component: Dashboard },
  { path: "/settings", name: "settings", component: Settings },
  { path: "/users", name: "users", component: Users },
  { path: "/language", name: "language", component: Language },
  { path: "/localization", name: "localization", component: Localization },
  { path: "/translations", name: "translations", component: Translations },
  { path: "/profile", name: "profile", component: Profile },
  { path: "/projects/", name: "projects", component: Projects },
  {
    path: "/project/:project_id",
    name: "projects.index",
    component: themedView('projects.index'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor", "viewer"],
        }),
  },
  {
    path: "/project/:project_id/collections",
    name: "projects.collections",
    component: themedView('projects.collections'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/collections/:col_id",
    name: "projects.collections.list",
    component: themedView('projects.collections.list'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/content",
    name: "projects.content",
    component: themedView('projects.content'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/comments",
    name: "projects.comments",
    component: themedView('projects.comments'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/content/:col_id",
    name: "projects.content.list",
    component: themedView('projects.content.list'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/content/:col_id/new",
    name: "projects.content.new",
    component: themedView('projects.content.new'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/content/:col_id/edit/:content_id",
    name: "projects.content.edit",
    component: themedView('projects.content.edit'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/content/:col_id/forms",
    name: "projects.content.forms",
    component: themedView('projects.content.forms'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
  {
    path: "/project/:project_id/content/:col_id/forms/:form_id",
    name: "projects.content.forms.detail",
    component: themedView('projects.content.forms.detail'),
    beforeEnter: async (to, from, next) =>
      checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
      }),
  },
  {
    path: "/project/:project_id/settings",
    name: "projects.settings",
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
            redirectTo: 'projects.settings.preferences',
        }),
  },
  {
    path: "/project/:project_id/settings/preferences",
    name: "projects.settings.preferences",
    component: themedView('projects.settings.preferences'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/locales",
    name: "projects.settings.locales",
    component: themedView('projects.settings.locales'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/users",
    name: "projects.settings.users",
    component: themedView('projects.settings.users'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner"],
        }),
  },
  {
    path: "/project/:project_id/settings/api",
    name: "projects.settings.api",
    component: themedView('projects.settings.api'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/webhooks",
    name: "projects.settings.webhooks",
    component: themedView('projects.settings.webhooks'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/translations",
    name: "projects.settings.translations",
    component: themedView('projects.settings.translations'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/language",
    name: "projects.settings.language",
    component: themedView('projects.settings.language'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/webhooks/:webhook_id/logs",
    name: "projects.settings.webhooks.logs",
    component: themedView('projects.settings.webhooks.logs'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/settings/audit-logs",
    name: "projects.settings.audit-logs",
    component: themedView('projects.settings.audit-logs'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin"],
        }),
  },
  {
    path: "/project/:project_id/media_library",
    name: "projects.media_library",
    component: themedView('projects.media_library'),
    beforeEnter: async (to, from, next) =>
        checkPermission(to, from, next, {
            requiredProjectRoles: ["owner", "admin", "editor"],
        }),
  },
];

const router = createRouter({
    history: createWebHistory(window.ADMIN_BASE || '/admin'),
    routes: routes,
});

router.afterEach(() => {
    const store = useAdminStore();
    store.routeLoading = false;
});

router.beforeEach(async (to, from, next) => {
    if (Swal.isVisible()) {
        await new Promise((resolve) => {
            Swal.close();
            setTimeout(resolve, 350);
        });
    }

    const store = useAdminStore();
    store.routeLoading = true;
    const hasRoles = store.user.roles && store.user.roles.length > 0;

    if (!hasRoles) {
        await store.getUser();
    }

    // Check if entering a project page
    const isProjectPage = to.path.includes('/project/') && to.params.project_id;
    const wasProjectPage = from && from.path && from.path.includes('/project/') && from.params && from.params.project_id;

    // If it's a project page, ensure project data is loaded
    if (isProjectPage) {
        const currentProjectId = store.currentProject?.id;
        const projectChanged = !currentProjectId || currentProjectId != to.params.project_id;

        if (projectChanged) {
            store.currentProject = null;
            store.currentCollection = null;
            await store.setCurrentProject(to.params.project_id);
        } else {
            if (Date.now() - (store.currentProjectLoadedAt || 0) > 30000) {
                store.setCurrentProject(to.params.project_id, { silent: true });
            }
        }

        if (to.params.col_id) {
            const currentCollectionId = store.currentCollection?.id;
            if (!currentCollectionId || currentCollectionId != to.params.col_id) {
                await store.setCurrentCollection({
                    projectId: to.params.project_id,
                    colId: to.params.col_id,
                });
            }
        } else {
            store.currentCollection = null;
        }
    } else if (wasProjectPage && !isProjectPage) {
        store.setCurrentProject(null);
        store.currentCollection = null;
    }

    next();
});

export default router;
