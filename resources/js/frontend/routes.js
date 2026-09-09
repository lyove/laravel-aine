import { createRouter, createWebHistory } from "vue-router";
import { useFrontendStore } from "./store";

const Home = () => import("./views/Home.vue");

const CMSArchive = () => import("./views/cms/Archive.vue");
const CMSCategoryPage = () => import("./views/cms/CategoryPage.vue");
const CMSTagPage = () => import("./views/cms/TagPage.vue");
const CMSSectionPage = () => import("./views/cms/SectionPage.vue");
const CMSArticleDetail = () => import("./views/cms/ArticleDetail.vue");
const CMSPageDetail = () => import("./views/cms/PageDetail.vue");
const CMSPagesList = () => import("./views/cms/PagesList.vue");

const DirectoryArchive = () => import("./views/directory/Archive.vue");
const DirectoryCategoryPage = () => import("./views/directory/CategoryPage.vue");
const DirectoryTagPage = () => import("./views/directory/TagPage.vue");
const DirectorySectionPage = () => import("./views/directory/SectionPage.vue");
const DirectoryListingDetail = () => import("./views/directory/ListingDetail.vue");

/**
 * Routes
 *
 * The site is split into two systems:
 *   /content/*    — the CMS project (articles, categories, tags, pages)
 *   /directory/*  — the Business Directory project (listings, categories,
 *                   tags, locations)
 */
const routes = [
    { path: "/", name: "home", component: Home },

    // ---- CMS system (/content) ----
    { path: "/content", name: "content.index", component: CMSArchive, props: { project: "cms", mode: "all" } },
    { path: "/content/pages", name: "content.pages", component: CMSPagesList },
    { path: "/content/slider", name: "content.slider", component: CMSSectionPage, props: { project: "cms", mode: "slider" } },
    { path: "/content/featured", name: "content.featured", component: CMSSectionPage, props: { project: "cms", mode: "featured" } },
    { path: "/content/recommended", name: "content.recommended", component: CMSSectionPage, props: { project: "cms", mode: "recommended" } },
    { path: "/content/category/:slug", name: "content.category", component: CMSCategoryPage, props: { project: "cms" } },
    { path: "/content/tag/:slug", name: "content.tag", component: CMSTagPage, props: { project: "cms" } },
    { path: "/content/:category/:article", name: "content.article", component: CMSArticleDetail },
    { path: "/content/:slug", name: "content.page", component: CMSPageDetail },

    // ---- Directory system (/directory) ----
    { path: "/directory", name: "directory.index", component: DirectoryArchive, props: { project: "directory", mode: "all" } },
    { path: "/directory/featured", name: "directory.featured", component: DirectorySectionPage, props: { project: "directory", mode: "featured" } },
    { path: "/directory/category/:slug", name: "directory.category", component: DirectoryCategoryPage, props: { project: "directory" } },
    { path: "/directory/tag/:slug", name: "directory.tag", component: DirectoryTagPage, props: { project: "directory" } },
    { path: "/directory/location/:slug", name: "directory.location", component: DirectoryArchive, props: { project: "directory", mode: "location" } },
    { path: "/directory/:category/:listing", name: "directory.listing", component: DirectoryListingDetail },
];

const router = createRouter({
    history: createWebHistory(),
    routes: routes,
});

// Route guard: settings + both projects (languages) once on first visit.
router.beforeEach(async (to, from, next) => {
    const store = useFrontendStore();
    await Promise.all([store.loadSettings(), store.initLocale()]);
    next();
});

export default router;
