<template>
    <section v-if="featured.length || recommended.length" class="rounded-2xl border border-gray-200/80 bg-gradient-to-b from-white to-gray-50/50 p-5">
        <!-- Tabs + More (right-aligned in the same row) -->
        <div class="mb-5 flex items-center gap-1 border-b border-gray-200">
            <button
                v-if="featured.length"
                type="button"
                class="group -mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="active === 'featured'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-900'"
                @click="active = 'featured'"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2 15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Featured
                </span>
            </button>
            <button
                v-if="recommended.length"
                type="button"
                class="group -mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="active === 'recommended'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-900'"
                @click="active = 'recommended'"
            >
                <span class="flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Recommended
                </span>
            </button>
            <router-link
                :to="moreLink"
                class="-mb-px ml-auto py-2.5 text-sm font-medium text-indigo-600 transition hover:opacity-70"
            >
                More →
            </router-link>
        </div>

        <!-- Horizontal list items -->
        <transition name="fade" mode="out-in">
            <div v-if="active === 'featured'" key="featured" class="space-y-3">
                <featured-item
                    v-for="article in featured"
                    :key="article.id"
                    :item="article"
                    :path-prefix="pathPrefix"
                />
            </div>
            <div v-else key="recommended" class="space-y-3">
                <featured-item
                    v-for="article in recommended"
                    :key="article.id"
                    :item="article"
                    :path-prefix="pathPrefix"
                />
            </div>
        </transition>
    </section>
</template>

<script>
import FeaturedItem from "./FeaturedItem.vue";

export default {
    name: "FeaturedSidebar",
    components: {
        FeaturedItem,
    },
    props: {
        featured: {
            type: Array,
            default: () => [],
        },
        recommended: {
            type: Array,
            default: () => [],
        },
        pathPrefix: {
            type: String,
            default: "/content",
        },
        morePrefix: {
            type: String,
            default: "/content",
        },
    },
    data() {
        return {
            active: "featured",
        };
    },
    computed: {
        moreLink() {
            return this.active === "featured" ? `${this.morePrefix}/featured` : `${this.morePrefix}/recommended`;
        },
    },
    mounted() {
        if (!this.featured.length && this.recommended.length) {
            this.active = "recommended";
        }
    },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
