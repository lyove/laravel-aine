<template>
    <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-300/40 hover:border-indigo-200">
        <router-link
            :to="itemLink"
            class="relative block aspect-[16/9] overflow-hidden bg-gray-100"
        >
            <img
                v-if="item.logo && item.logo.full_url"
                :src="item.logo.full_url"
                :alt="item.title"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-500 via-purple-500 to-indigo-600 text-3xl font-bold text-white shadow-inner"
            >
                {{ initials }}
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition group-hover:opacity-100"></div>
            <span
                v-if="item.featured"
                class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-xs font-bold text-amber-900 shadow-md shadow-amber-400/30"
            >
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                </svg>
                Featured
            </span>
        </router-link>

        <div class="flex flex-1 flex-col p-5">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                <router-link
                    v-if="item.category"
                    :to="`${pathPrefix}/category/${item.category.slug}`"
                    class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-600 transition hover:bg-indigo-100"
                >
                    {{ item.category.title }}
                </router-link>
                <span v-if="item.location" class="flex items-center gap-1 text-gray-400">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    {{ item.location.name }}
                </span>
                <span v-if="item['price-range']" class="ml-auto font-semibold text-emerald-600">
                    {{ item['price-range'] }}
                </span>
            </div>

            <h3 class="mb-2 text-lg font-bold leading-snug text-gray-900 transition group-hover:text-indigo-600">
                <router-link :to="itemLink">{{ item.title }}</router-link>
            </h3>

            <p v-if="item.description" class="mb-3 line-clamp-2 text-sm leading-relaxed text-gray-500">
                {{ item.description }}
            </p>

            <div v-if="item.phone || item.website" class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                <span v-if="item.phone" class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ item.phone }}
                </span>
                <span v-if="item.website" class="inline-flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>
                    </svg>
                    {{ shortWebsite }}
                </span>
            </div>

            <div v-if="(item.tags || []).length" class="mb-2 flex flex-wrap gap-1.5">
                <router-link
                    v-for="tag in (item.tags || []).slice(0, 3)"
                    :key="tag.id"
                    :to="`${pathPrefix}/tag/${(tag.tag || '').toLowerCase().replace(/\s+/g, '-')}`"
                    class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-500 transition hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>

            <div class="mt-auto flex items-center">
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
export default {
    name: "ListingCard",
    props: {
        item: {
            type: Object,
            required: true,
        },
        pathPrefix: {
            type: String,
            default: "/directory",
        },
    },
    computed: {
        itemLink() {
            const category = this.item.category ? this.item.category.slug : "listings";
            const url = this.item.slug || this.item.id;
            return `${this.pathPrefix}/${category}/${url}`;
        },
        initials() {
            return (this.item.title || "?")
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join("")
                .toUpperCase();
        },
        shortWebsite() {
            return (this.item.website || "").replace(/^https?:\/\//, "").replace(/\/$/, "");
        },
    },
};
</script>
