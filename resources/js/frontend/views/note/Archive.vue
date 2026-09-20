<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="transition hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span class="text-gray-300">/</span>
            <router-link :to="projectConfig.path" class="transition hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <template v-if="mode !== 'all'">
                <span class="text-gray-300">/</span>
                <span v-if="mode === 'featured'">Featured</span>
                <span v-else-if="mode === 'recommended'">Recommended</span>
            </template>
        </nav>

        <h1 class="mb-2 flex items-center gap-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            <span v-if="mode === 'featured'" class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-amber-400 to-orange-500"></span>
            <span v-else-if="mode === 'recommended'" class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-emerald-400 to-teal-500"></span>
            <span v-else class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
            {{ mode === 'featured' ? 'Featured' : mode === 'recommended' ? 'Recommended' : projectConfig.label }}
        </h1>
        <p class="mb-8 text-gray-500">{{ subtitle }}</p>

        <note-tabs active="posts" class="mb-8" />

        <div v-if="loading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="n in 6" :key="n" class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="aspect-[16/9] animate-pulse bg-gray-100"></div>
                <div class="flex flex-1 flex-col p-5">
                    <div class="mb-3 h-3 w-24 animate-pulse rounded bg-gray-200"></div>
                    <div class="mb-3 h-5 w-4/5 animate-pulse rounded bg-gray-200"></div>
                    <div class="mb-2 h-4 w-full animate-pulse rounded bg-gray-100"></div>
                    <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                </div>
            </div>
        </div>

        <div v-else-if="!items.length" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">No items here yet</h2>
            <p class="mt-2 text-sm text-gray-500">Nothing has been published in this collection.</p>
            <router-link :to="projectConfig.path" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Browse all
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <article-card
                    v-for="item in items"
                    :key="item.id"
                    :item="item"
                    :path-prefix="projectConfig.path"
                />
            </div>

            <div class="mt-10 text-center">
                <button
                    v-if="hasMore"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600 disabled:opacity-50"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    <svg v-if="loadingMore" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ loadingMore ? "Loading…" : "Load more" }}
                </button>
                <p v-else class="text-sm text-gray-400">
                    You've reached the end — {{ items.length }} items in total.
                </p>
            </div>
        </template>
    </div>
</template>

<script>
import { api } from "../../api";
import { PROJECTS, ARCHIVE_PAGE_SIZE } from "../../config";
import { useFrontendStore } from "../../store";
import ArticleCard from "../../components/ArticleCard.vue";
import NoteTabs from "./components/NoteTabs.vue";

export default {
    name: "NoteArchive",
    components: {
        ArticleCard,
        NoteTabs,
    },
    props: {
        project: {
            type: String,
            default: "note",
        },
        mode: {
            type: String,
            default: "all", // "all" | "featured" | "recommended"
        },
    },
    data() {
        return {
            items: [],
            offset: 0,
            hasMore: true,
            loading: true,
            loadingMore: false,
            siteName: "Home",
        };
    },
    computed: {
        projectConfig() {
            return PROJECTS[this.project] || PROJECTS.note;
        },
        subtitle() {
            if (this.mode === "featured") return `All featured ${this.projectConfig.contentCollection}.`;
            if (this.mode === "recommended") return `All recommended ${this.projectConfig.contentCollection}.`;
            return `Browse all ${this.projectConfig.contentCollection}.`;
        },
    },
    watch: {
        project() {
            this.loadArchive();
        },
        mode() {
            this.loadArchive();
        },
    },
    async mounted() {
        const store = useFrontendStore();
        this.siteName = store.settings.name || "Home";
        this.loadArchive();
    },
    methods: {
        async loadArchive() {
            const seq = (this._loadSeq = (this._loadSeq || 0) + 1);

            this.loading = true;
            this.items = [];
            this.offset = 0;
            this.hasMore = true;

            try {
                await this.fetchPage();

                if (seq !== this._loadSeq) {
                    return;
                }
            } catch (error) {
                console.error("Failed to load archive:", error);
                this.hasMore = false;
            } finally {
                if (seq === this._loadSeq) {
                    this.loading = false;
                }
            }
        },

        async fetchPage() {
            const filters = this.mode === "all" ? undefined : { [this.mode]: "1" };

            const data = await api.getNotePosts({
                filters: filters,
                offset: this.offset,
                limit: ARCHIVE_PAGE_SIZE,
                sort: "published_at:desc",
                timestamps: true,
            });

            this.items.push(...(data || []));
            this.offset += ARCHIVE_PAGE_SIZE;
            this.hasMore = (data || []).length === ARCHIVE_PAGE_SIZE;
        },

        async loadMore() {
            this.loadingMore = true;
            const seq = this._loadSeq;
            try {
                await this.fetchPage();
                if (seq !== this._loadSeq) {
                    this.loadArchive();
                }
            } catch (error) {
                console.error("Failed to load more:", error);
                this.hasMore = false;
            } finally {
                this.loadingMore = false;
            }
        },
    },
};
</script>
