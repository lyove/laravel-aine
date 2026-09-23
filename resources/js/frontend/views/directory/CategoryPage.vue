<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6">
        <img
            v-if="entity && entity.image && entity.image.full_url"
            :src="entity.image.full_url"
            :alt="heading || entity.title"
            class="mb-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-lg shadow-gray-300/30"
        />

        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="transition hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span class="text-gray-300">/</span>
            <router-link :to="projectConfig.path" class="transition hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <span class="text-gray-300">/</span>
            <span>Categories</span>
            <span class="text-gray-300">/</span>
            <span v-if="heading" class="font-medium text-gray-900">{{ heading }}</span>
            <span v-else class="text-gray-400">{{ slug }}</span>
        </nav>

        <h1 class="mb-2 flex items-center gap-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            <span class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
            {{ heading || projectConfig.label }}
        </h1>
        <p class="mb-8 text-gray-500">
            {{ entity && entity.description ? entity.description : subtitle }}
        </p>

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
            <p class="mt-2 text-sm text-gray-500">Nothing has been published in this category.</p>
            <router-link :to="projectConfig.path" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Browse all
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
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
            this.heading = null;
            this.entity = null;

            try {
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

        async fetchPage() {
            const data = await api.getCategoryListingsBySlug(this.slug, {
                offset: this.offset,
                limit: ARCHIVE_PAGE_SIZE,
                sort: "published_at:desc",
                timestamps: true,
                state: "only_published",
            });

            this.items.push(...(data || []));

            if (!this.heading && this.items.length) {
                const category = this.items[0].category;
                if (category) {
                    this.heading = category.title || category.name || null;
                    this.entity = category;
                }
            }

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
