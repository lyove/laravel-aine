<template>
    <article class="group flex gap-3.5 rounded-xl border border-gray-200/80 bg-white p-3 shadow-sm transition-all duration-300 hover:shadow-md hover:border-indigo-200">
        <!-- Small image on the left -->
        <router-link
            v-if="item['featured-image'] && item['featured-image'].full_url"
            :to="itemLink"
            class="block h-20 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100"
        >
            <img
                :src="item['featured-image'].full_url"
                :alt="item.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                loading="lazy"
            />
        </router-link>
        <router-link
            v-else
            :to="itemLink"
            class="flex h-20 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-indigo-400 to-purple-500 text-lg font-bold text-white"
        >
            {{ initials }}
        </router-link>

        <!-- Title + meta stacked on the right -->
        <div class="flex min-w-0 flex-1 flex-col">
            <h3 class="mb-1.5 line-clamp-2 text-sm font-semibold leading-snug text-gray-900 transition group-hover:text-indigo-600">
                <router-link :to="itemLink">{{ item.title }}</router-link>
            </h3>

            <div class="mb-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-400">
                <router-link
                    v-if="item.category"
                    :to="`${pathPrefix}/category/${item.category.slug}`"
                    class="font-medium text-indigo-600 transition hover:text-indigo-700"
                >
                    {{ item.category.title }}
                </router-link>
                <span v-if="item.category && formattedDate">·</span>
                <span v-if="formattedDate">{{ formattedDate }}</span>
            </div>

            <div v-if="item.tags && item.tags.length" class="mt-auto flex flex-wrap gap-1">
                <router-link
                    v-for="tag in item.tags.slice(0, 3)"
                    :key="tag.id"
                    :to="`${pathPrefix}/tag/${(tag.tag || '').toLowerCase().replace(/\s+/g, '-')}`"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500 transition hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>
        </div>
    </article>
</template>

<script>
import { formatDate } from "../../utils/filters";

export default {
    name: "FeaturedItem",
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
        initials() {
            const title = this.item.title || "?";
            return title
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join("")
                .toUpperCase();
        },
    },
};
</script>
