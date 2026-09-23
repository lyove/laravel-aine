/**
 * Frontend configuration.
 */

const meta = (name) => {
    if (typeof document === "undefined") {
        return null;
    }
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.content : null;
};

/**
 * Systems and their collection slugs.
 */
export const PROJECTS = {
    cms: {
        identifier: meta("cms-project-identifier") || "cms",
        contentCollection: "articles",
        path: "/content",
        label: "Content",
        portal: ["slider", "featured", "recommended", "categoryTabs", "pages"],
        fallbackToLatest: true,
    },
    directory: {
        identifier: meta("directory-project-identifier") || "directory",
        contentCollection: "listings",
        path: "/directory",
        label: "Directory",
        portal: ["featured", "categoryTabs"],
    },
    note: {
        identifier: meta("note-project-identifier") || "note",
        contentCollection: "posts",
        path: "/note",
        label: "Note",
        portal: ["slider", "featured", "recommended", "categoryTabs", "pages"],
        fallbackToLatest: true,
    },
};

/**
 * Blocks a page renders when a project does not declare its own portal.
 * Keeps the default layout identical to the pre-consolidation UI.
 */
export const DEFAULT_PORTAL = ["slider", "featured", "recommended", "categoryTabs", "pages"];

/**
 * Collection slugs shared by both systems.
 */
export const COLLECTIONS = {
    portal: "portal",
    categories: "categories",
    tags: "tags",
    pages: "pages",
};

/**
 * How many items to load per "Load more" batch on archive pages.
 */
export const ARCHIVE_PAGE_SIZE = 6;
