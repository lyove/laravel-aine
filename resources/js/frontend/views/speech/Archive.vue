<template>
    <div class="mx-auto w-full max-w-6xl px-4 py-10">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span>›</span>
            <router-link :to="projectConfig.path" class="hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <template v-if="mode !== 'all'">
                <span>›</span>
                <span v-if="mode === 'featured'">Featured</span>
                <span v-else-if="mode === 'recommended'">Recommended</span>
            </template>
        </nav>

        <h1 class="mb-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            {{ mode === 'featured' ? 'Featured' : mode === 'recommended' ? 'Recommended' : projectConfig.label }}
        </h1>
        <p class="mb-8 text-gray-500">{{ subtitle }}</p>

        <speech-tabs active="posts" class="mb-8" />

        <div v-if="loading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="n in 6" :key="n" class="h-72 animate-pulse rounded-xl bg-gray-100"></div>
        </div>

        <div v-else-if="!items.length" class="py-16 text-center">
            <h2 class="text-xl font-bold text-gray-900">No items here yet</h2>
            <p class="mt-2 text-sm text-gray-500">Nothing has been published in this collection.</p>
            <router-link :to="projectConfig.path" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:opacity-80">
                Browse all →
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
                    class="rounded-lg border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 transition hover:border-indigo-600 hover:text-indigo-600 disabled:opacity-50"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
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
import SpeechTabs from "./components/SpeechTabs.vue";

export default {
    name: "SpeechArchive",
    components: {
        ArticleCard,
        SpeechTabs,
    },
    props: {
        project: {
            type: String,
            default: "speech",
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
            return PROJECTS[this.project] || PROJECTS.speech;
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

            const data = await api.getSpeechPosts({
                filters: filters,
                offset: this.offset,
                limit: ARCHIVE_PAGE_SIZE,
                sort: "published_at:desc",
                timestamps: true,
                _skipLocale: true,
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
