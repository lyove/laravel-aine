<template>
    <div class="mx-auto w-full max-w-6xl px-4 py-10">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span>›</span>
            <router-link :to="projectConfig.path" class="hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <template v-if="mode !== 'all'">
                <span>›</span>
                <span v-if="mode === 'category'">Categories</span>
                <span v-else-if="mode === 'tag'">Tags</span>
                <span v-else-if="mode === 'location'">Locations</span>
                <span v-else-if="mode === 'featured'">Featured</span>
                <span v-else-if="mode === 'recommended'">Recommended</span>
                <span>›</span>
                <span v-if="heading" class="text-gray-900">{{ heading }}</span>
                <span v-else class="text-gray-400">{{ slug }}</span>
            </template>
        </nav>

        <h1 class="mb-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            {{ heading || (mode === 'featured' ? 'Featured' : mode === 'recommended' ? 'Recommended' : projectConfig.label) }}
        </h1>
        <p class="mb-8 text-gray-500">
            {{ subtitle }}
        </p>

        <img
            v-if="mode === 'category' && entity && entity.image && entity.image.full_url"
            :src="entity.image.full_url"
            :alt="heading || entity.title"
            class="mb-8 aspect-[16/9] w-full rounded-xl object-cover"
        />

        <content-tabs v-if="project === 'cms'" active="articles" class="mb-8" />

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
                <component
                    :is="cardComponent"
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
import { api } from "../api";
import { PROJECTS, COLLECTIONS, ARCHIVE_PAGE_SIZE } from "../config";
import { useFrontendStore } from "../store";
import ArticleCard from "../components/ArticleCard.vue";
import ListingCard from "../components/ListingCard.vue";
import ContentTabs from "../components/ContentTabs.vue";

export default {
    name: "Archive",
    components: {
        ArticleCard,
        ListingCard,
        ContentTabs,
    },
    props: {
        project: {
            type: String,
            default: "cms", // "cms" | "directory"
        },
        mode: {
            type: String,
            default: "all", // "all" | "category" | "tag" | "location"
        },
    },
    data() {
        return {
            slug: null,
            heading: null,
            entityId: null,
            entity: null,
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
            return PROJECTS[this.project] || PROJECTS.cms;
        },
        cardComponent() {
            return this.project === "directory" ? ListingCard : ArticleCard;
        },
        param() {
            return this.$route.params.slug || null;
        },
        subtitle() {
            if (this.mode === "category") return `All ${this.projectConfig.contentCollection} in ${this.heading || "this category"}.`;
            if (this.mode === "tag") return `All ${this.projectConfig.contentCollection} tagged #${this.heading || this.slug}.`;
            if (this.mode === "location") return `All ${this.projectConfig.contentCollection} in ${this.heading || "this location"}.`;
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
        param() {
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
            this.slug = this.param;
            this.entity = null;

            try {
                if (this.mode === "category") {
                    await this.resolveEntity(COLLECTIONS.categories, "slug");
                } else if (this.mode === "tag") {
                    await this.resolveEntity(COLLECTIONS.tags, "tag");
                } else if (this.mode === "location") {
                    await this.resolveEntity("locations", "slug");
                }

                if (["category", "tag", "location"].includes(this.mode) && !this.entityId) {
                    this.hasMore = false;
                    return;
                }

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

        async resolveEntity(collectionSlug, matchField) {
            const list = await api.request({
                type: "get",
                project: this.projectConfig.identifier,
                collection: collectionSlug,
            });
            let match = null;

            if (this.mode === "tag") {
                match = (list || []).find((t) => (t.tag || "").toLowerCase().replace(/\s+/g, "-") === this.slug);
            } else {
                match = (list || []).find((c) => c[matchField] === this.slug);
            }

            if (match) {
                this.heading = this.mode === "tag" ? match.tag : (match.title || match.name);
                this.entityId = match.id;
                this.entity = match;
            }
        },

        async fetchPage() {
            const cfg = this.projectConfig;
            let data;

            if (this.mode === "all") {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    collection: cfg.contentCollection,
                    params: {
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                    },
                });
            } else if (this.mode === "category") {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    source: COLLECTIONS.categories,
                    id: this.entityId,
                    related: cfg.contentCollection,
                    params: {
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                        state: "only_published",
                    },
                });
            } else if (this.mode === "location") {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    source: "locations",
                    id: this.entityId,
                    related: cfg.contentCollection,
                    params: {
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                        state: "only_published",
                    },
                });
            } else if (this.mode === "featured") {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    collection: cfg.contentCollection,
                    params: {
                        filters: { featured: "1" },
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                    },
                });
            } else if (this.mode === "recommended") {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    collection: cfg.contentCollection,
                    params: {
                        filters: { recommended: "1" },
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                    },
                });
            } else {
                data = await api.request({
                    type: "get",
                    project: cfg.identifier,
                    collection: cfg.contentCollection,
                    params: {
                        filters: { tags: String(this.entityId) },
                        offset: this.offset,
                        limit: ARCHIVE_PAGE_SIZE,
                        sort: "published_at:desc",
                        timestamps: true,
                    },
                });
            }

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
