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

const NoteArchive = () => import("./views/note/Archive.vue");
const NoteCategoryPage = () => import("./views/note/CategoryPage.vue");
const NoteTagPage = () => import("./views/note/TagPage.vue");
const NoteSectionPage = () => import("./views/note/SectionPage.vue");
const NotePostDetail = () => import("./views/note/PostDetail.vue");
const NotePageDetail = () => import("./views/note/PageDetail.vue");
const NotePagesList = () => import("./views/note/PagesList.vue");

const SearchPage = () => import("./views/SearchPage.vue");
const Profile = () => import("./views/Profile.vue");

/**
 * Routes
 *
 * The site is split into three independent systems:
 *   /content/*    — the CMS project (articles, categories, tags, pages)
 *   /directory/*  — the Business Directory project (listings, categories,
 *                   tags, locations)
 *   /note/*     — the Note project (posts, categories, tags, pages)
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

    // ---- Note system (/note) ----
    { path: "/note", name: "note.index", component: NoteArchive, props: { project: "note", mode: "all" } },
    { path: "/note/pages", name: "note.pages", component: NotePagesList },
    { path: "/note/slider", name: "note.slider", component: NoteSectionPage, props: { project: "note", mode: "slider" } },
    { path: "/note/featured", name: "note.featured", component: NoteSectionPage, props: { project: "note", mode: "featured" } },
    { path: "/note/recommended", name: "note.recommended", component: NoteSectionPage, props: { project: "note", mode: "recommended" } },
    { path: "/note/category/:slug", name: "note.category", component: NoteCategoryPage, props: { project: "note" } },
    { path: "/note/tag/:slug", name: "note.tag", component: NoteTagPage, props: { project: "note" } },
    { path: "/note/:category/:post", name: "note.post", component: NotePostDetail },
    { path: "/note/:slug", name: "note.page", component: NotePageDetail },

    // ---- Global search ----
    { path: "/search", name: "search", component: SearchPage },

    // ---- User profile (requires login) ----
    { path: "/profile", name: "profile", component: Profile, meta: { requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes: routes,
});

// Route guard: settings + both projects (languages) once on first visit;
// auth-required routes redirect guests to the login page.
router.beforeEach(async (to, from, next) => {
    const store = useFrontendStore();
    await Promise.all([store.loadSettings(), store.initLocale()]);

    if (to.meta.requiresAuth) {
        const user = await store.loadMe();
        if (!user) {
            window.location.assign("/login");
            return false;
        }
    }

    next();
});

export default router;
