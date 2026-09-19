<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <router-link to="/" class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:gap-2 hover:text-indigo-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to home
        </router-link>

        <div v-if="loading" class="space-y-4">
            <div class="h-8 w-2/3 animate-pulse rounded bg-gray-200"></div>
            <div class="h-64 animate-pulse rounded-2xl bg-gray-100"></div>
            <div class="h-4 w-full animate-pulse rounded bg-gray-200"></div>
        </div>

        <div v-else-if="!item" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Listing not found</h2>
            <p class="mt-2 text-sm text-gray-500">This business listing may have been unpublished or removed.</p>
            <router-link to="/" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Back to home
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <router-link
                    v-if="item.category"
                    :to="`/directory/category/${item.category.slug}`"
                    class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-600 transition hover:bg-indigo-100"
                >
                    {{ item.category.title }}
                </router-link>
                <span v-if="item.location" class="flex items-center gap-1 text-gray-400">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <router-link
                        v-if="item.location"
                        :to="`/directory/location/${item.location.slug}`"
                        class="text-gray-500 transition hover:text-indigo-600"
                    >
                        {{ item.location.name }}
                    </router-link>
                </span>
                <span v-if="item['price-range']" class="ml-auto font-semibold text-emerald-600">
                    {{ item['price-range'] }}
                </span>
            </div>

            <div class="flex items-start gap-4">
                <img
                    v-if="item.logo && item.logo.full_url"
                    :src="item.logo.full_url"
                    :alt="item.title"
                    class="h-20 w-20 shrink-0 rounded-2xl border border-gray-200 object-cover shadow-sm"
                />
                <div
                    v-else
                    class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl font-bold text-white shadow-md"
                >
                    {{ initials }}
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-4xl">
                        {{ item.title }}
                    </h1>
                    <span
                        v-if="item.featured"
                        class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-xs font-bold text-amber-900 shadow-sm"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                        </svg>
                        Featured
                    </span>
                </div>
            </div>

            <p v-if="item.description" class="mt-6 text-base leading-relaxed text-gray-600">
                {{ item.description }}
            </p>

            <!-- Gallery -->
            <div v-if="gallery.length" class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <img
                    v-for="img in gallery"
                    :key="img.id"
                    :src="img.full_url"
                    :alt="item.title"
                    class="aspect-[16/10] w-full rounded-xl border border-gray-200 object-cover shadow-sm transition hover:shadow-md"
                />
            </div>

            <!-- Contact & details -->
            <div class="mt-8 grid grid-cols-1 gap-4 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm sm:grid-cols-2">
                <div v-if="item.phone" class="flex items-center gap-3 text-sm text-gray-700">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </div>
                    <a :href="`tel:${item.phone}`" class="transition hover:text-indigo-600">{{ item.phone }}</a>
                </div>
                <div v-if="item.email" class="flex items-center gap-3 text-sm text-gray-700">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <path d="m22 6-10 7L2 6"/>
                        </svg>
                    </div>
                    <a :href="`mailto:${item.email}`" class="transition hover:text-indigo-600">{{ item.email }}</a>
                </div>
                <div v-if="item.website" class="flex items-center gap-3 text-sm text-gray-700">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>
                        </svg>
                    </div>
                    <a :href="item.website" target="_blank" rel="noopener noreferrer" class="transition hover:text-indigo-600">
                        {{ item.website }}
                    </a>
                </div>
                <div v-if="item.address" class="flex items-start gap-3 text-sm text-gray-700">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <span class="pt-1.5">{{ item.address }}</span>
                </div>
                <div v-if="item['opening-hours']" class="flex items-start gap-3 text-sm text-gray-700 sm:col-span-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <span class="pt-1.5">{{ item['opening-hours'] }}</span>
                </div>
            </div>

            <!-- Tags -->
            <div v-if="item.tags && item.tags.length" class="mt-6 flex flex-wrap gap-2">
                <router-link
                    v-for="tag in item.tags"
                    :key="tag.id"
                    :to="`/directory/tag/${(tag.tag || '').toLowerCase().replace(/\s+/g, '-')}`"
                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>

            <!-- Favorite / Like -->
            <ContentActions :project="PROJECTS.directory.identifier" :content-id="item.id" class="mt-6" />

            <!-- Reviews -->
            <div v-if="reviews.length" class="mt-10">
                <h2 class="mb-5 flex items-center gap-2 text-xl font-bold text-gray-900">
                    <svg class="h-5 w-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2 15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Reviews ({{ reviews.length }})
                </h2>
                <div class="space-y-3">
                    <div
                        v-for="review in reviews"
                        :key="review.id"
                        class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-xs font-bold text-white">
                                {{ (review.name || "?").charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-sm font-semibold text-gray-800">{{ review.name }}</span>
                            <span class="text-sm text-amber-500">{{ stars(review.rating) }}</span>
                        </div>
                        <p v-if="review.review" class="text-sm leading-relaxed text-gray-600">{{ review.review }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { api } from "../../api";
import { PROJECTS } from "../../config";
import ContentActions from "../../components/ContentActions.vue";

export default {
    name: "ListingDetail",
    components: {
        ContentActions,
    },
    data() {
        return {
            PROJECTS,
            item: null,
            reviews: [],
            loading: true,
        };
    },
    computed: {
        gallery() {
            if (!this.item || !Array.isArray(this.item.gallery)) return [];
            return this.item.gallery;
        },
        initials() {
            return (this.item?.title || "?")
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join("")
                .toUpperCase();
        },
        params() {
            return `${this.$route.params.category}/${this.$route.params.listing}`;
        },
    },
    watch: {
        params() {
            this.loadItem();
        },
    },
    async mounted() {
        this.loadItem();
    },
    methods: {
        stars(rating) {
            const n = parseInt(rating, 10) || 0;
            return "★".repeat(Math.max(0, Math.min(5, n))) + "☆".repeat(Math.max(0, 5 - n));
        },
        async loadItem() {
            this.loading = true;
            this.item = null;
            this.reviews = [];
            const listingUrl = this.$route.params.listing;

            try {
                this.item = await api.getListingBySlug(listingUrl, { timestamps: true });

                if (this.item) {
                    this.reviews = (await api.getListingReviews(this.item.id, {
                        sort: "created_at:desc",
                        timestamps: true,
                        state: "only_published",
                    })) || [];
                }
            } catch (error) {
                console.error("Failed to load listing:", error);
                this.item = null;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
