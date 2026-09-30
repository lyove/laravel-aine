<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="transition hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span class="text-gray-300">/</span>
            <router-link :to="projectConfig.path" class="transition hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <span class="text-gray-300">/</span>
            <span>Tags</span>
            <span class="text-gray-300">/</span>
            <span v-if="heading" class="font-medium text-gray-900">#{{ heading }}</span>
            <span v-else class="text-gray-400">#{{ slug }}</span>
        </nav>

        <h1 class="mb-2 flex items-center gap-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-600">#</span>
            {{ heading || projectConfig.label }}
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
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" stroke-linecap="round" stroke-linejoin="round"/>
                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">No items here yet</h2>
            <p class="mt-2 text-sm text-gray-500">Nothing has been published with this tag.</p>
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
    name: "NoteTagPage",
    components: {
        ArticleCard,
        NoteTabs,
    },
    props: {
        project: {
            type: String,
            default: "note",
        },
    },
    data() {
        return {
            slug: null,
            heading: null,
            entityId: null,
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
        param() {
            return this.$route.params.slug || null;
        },
        subtitle() {
            return `All ${this.projectConfig.contentCollection} tagged #${this.heading || this.slug}.`;
        },
    },
    watch: {
        project() {
            this.loadTag();
        },
        param() {
            this.loadTag();
        },
    },
    async mounted() {
        const store = useFrontendStore();
        this.siteName = store.settings.name || "Home";
        this.loadTag();
    },
    methods: {
        async loadTag() {
            const seq = (this._loadSeq = (this._loadSeq || 0) + 1);

            this.loading = true;
            this.items = [];
            this.offset = 0;
            this.hasMore = true;
            this.slug = this.param;
            this.heading = null;

            try {
                await this.resolveEntity();

                if (!this.entityId) {
                    this.hasMore = false;
                    return;
                }

                await this.fetchPage();

                if (seq !== this._loadSeq) {
                    return;
                }
            } catch (error) {
                console.error("Failed to load tag:", error);
                this.hasMore = false;
            } finally {
                if (seq === this._loadSeq) {
                    this.loading = false;
                }
            }
        },

        async resolveEntity() {
            const list = await api.getNoteTags({
                _skipLocale: true,
                filters: { locale: "all" },
            });
            const match = (list || []).find((t) => (t.tag || "").toLowerCase().replace(/\s+/g, "-") === this.slug);

            if (match) {
                this.heading = match.tag || match.name;
                this.entityId = match.id;
            }
        },

        async fetchPage() {
            const data = await api.getNoteTagPosts(this.entityId, {
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
                    this.loadTag();
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
