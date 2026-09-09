<template>
    <div class="mx-auto w-full max-w-6xl px-4 py-10">
        <img
            v-if="entity && entity.image && entity.image.full_url"
            :src="entity.image.full_url"
            :alt="heading || entity.title"
            class="mb-8 aspect-[16/9] w-full rounded-xl object-cover"
        />

        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span>›</span>
            <router-link :to="projectConfig.path" class="hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <span>›</span>
            <span>Categories</span>
            <span>›</span>
            <span v-if="heading" class="text-gray-900">{{ heading }}</span>
            <span v-else class="text-gray-400">{{ slug }}</span>
        </nav>

        <h1 class="mb-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            {{ heading || projectConfig.label }}
        </h1>
        <p class="mb-8 text-gray-500">
            {{ subtitle }}
        </p>

        <div v-if="loading" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="n in 6" :key="n" class="h-72 animate-pulse rounded-xl bg-gray-100"></div>
        </div>

        <div v-else-if="!items.length" class="py-16 text-center">
            <h2 class="text-xl font-bold text-gray-900">No items here yet</h2>
            <p class="mt-2 text-sm text-gray-500">Nothing has been published in this category.</p>
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
import { api } from "../../api";
import { PROJECTS, COLLECTIONS, ARCHIVE_PAGE_SIZE } from "../../config";
import { useFrontendStore } from "../../store";
import ArticleCard from "../../components/ArticleCard.vue";
import ListingCard from "../../components/ListingCard.vue";

export default {
    name: "CategoryPage",
    components: {
        ArticleCard,
        ListingCard,
    },
    props: {
        project: {
            type: String,
            default: "cms", // "cms" | "directory"
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
            return `All ${this.projectConfig.contentCollection} in ${this.heading || "this category"}.`;
        },
    },
    watch: {
        project() {
            this.loadCategory();
        },
        param() {
            this.loadCategory();
        },
    },
    async mounted() {
        const store = useFrontendStore();
        this.siteName = store.settings.name || "Home";
        this.loadCategory();
    },
    methods: {
        async loadCategory() {
            const seq = (this._loadSeq = (this._loadSeq || 0) + 1);

            this.loading = true;
            this.items = [];
            this.offset = 0;
            this.hasMore = true;
            this.slug = this.param;
            this.entity = null;

            try {
                await this.resolveEntity(COLLECTIONS.categories, "slug");

                if (!this.entityId) {
                    this.hasMore = false;
                    return;
                }

                await this.fetchPage();

                if (seq !== this._loadSeq) {
                    return;
                }
            } catch (error) {
                console.error("Failed to load category:", error);
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
            const match = (list || []).find((c) => c[matchField] === this.slug);

            if (match) {
                this.heading = match.title || match.name;
                this.entityId = match.id;
                this.entity = match;
            }
        },

        async fetchPage() {
            const cfg = this.projectConfig;
            const data = await api.request({
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
                    this.loadCategory();
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
