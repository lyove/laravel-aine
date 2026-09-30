<template>
    <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-300/40 hover:border-indigo-200">
        <router-link
            v-if="item['featured-image'] && item['featured-image'].full_url"
            :to="itemLink"
            class="relative block aspect-[16/9] overflow-hidden bg-gray-100"
        >
            <img
                :src="item['featured-image'].full_url"
                :alt="item.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                loading="lazy"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition group-hover:opacity-100"></div>
        </router-link>
        <div
            v-else
            class="relative block aspect-[16/9] overflow-hidden bg-gradient-to-br from-indigo-100 via-purple-50 to-indigo-50"
        >
            <div class="flex h-full items-center justify-center text-indigo-300">
                <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M4 16l4.586-4.586a2 2 0 0 1 2.828 0L16 16m-2-2 1.586-1.586a2 2 0 0 1 2.828 0L20 14m-6-6h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>

        <div class="flex flex-1 flex-col p-5">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                <router-link
                    v-if="item.category"
                    :to="`${pathPrefix}/category/${item.category.slug}`"
                    class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-600 transition hover:bg-indigo-100"
                >
                    {{ item.category.title }}
                </router-link>
                <span v-if="formattedDate" class="flex items-center gap-1 text-gray-400">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    {{ formattedDate }}
                </span>
            </div>

            <h3 class="mb-2 text-lg font-bold leading-snug text-gray-900 transition group-hover:text-indigo-600">
                <router-link :to="itemLink">{{ item.title }}</router-link>
            </h3>

            <p v-if="item.excerpt" class="mb-4 line-clamp-2 text-sm leading-relaxed text-gray-500">
                {{ item.excerpt }}
            </p>

            <div class="mt-auto flex items-center justify-between">
                <router-link :to="itemLink" class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 transition hover:gap-2 hover:text-indigo-700">
                    Read more
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </router-link>
            </div>
        </div>
    </article>
</template>

<script>
import { formatDate } from "../../utils/filters";

export default {
    name: "ArticleCard",
    props: {
        item: {
            type: Object,
            required: true,
        },
        pathPrefix: {
            type: String,
            default: "/content",
        },
    },
    computed: {
        itemLink() {
            const category = this.item.category ? this.item.category.slug : "articles";
            const url = this.item.slug || this.item.id;
            return `${this.pathPrefix}/${category}/${url}`;
        },
        formattedDate() {
            if (!this.item.published_at) return null;
            return formatDate(this.item.published_at, "MMM D, YYYY");
        },
    },
};
</script>
