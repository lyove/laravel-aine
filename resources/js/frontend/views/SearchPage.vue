<template>
    <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
        <div class="mb-8">
            <h1 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                <span class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
                {{ query ? `Search results for "${query}"` : "Search" }}
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                {{ totalLabel }}
            </p>
        </div>

        <div v-if="loading" class="py-20 text-center">
            <div class="relative mx-auto h-10 w-10">
                <div class="absolute inset-0 rounded-full border-2 border-gray-200"></div>
                <div class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-indigo-500"></div>
            </div>
            <p class="mt-4 text-sm text-gray-500">Searching…</p>
        </div>

        <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {{ error }}
        </div>

        <div v-else-if="!query" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p class="text-sm text-gray-500">Type at least 2 characters to search across all projects.</p>
        </div>

        <div v-else-if="groups.length === 0" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35M8 11h6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">No results found</h2>
            <p class="mt-2 text-sm text-gray-500">We couldn't find anything for "{{ query }}". Try a different keyword.</p>
        </div>

        <div v-else class="space-y-10">
            <section v-for="group in groups" :key="group.project">
                <template v-if="group.items.length > 0">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                        <span class="inline-block h-4 w-1 rounded-full bg-indigo-500"></span>
                        <span>{{ group.label }}</span>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">{{ group.items.length }}</span>
                    </h2>
                    <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm">
                        <ul class="divide-y divide-gray-100">
                            <li v-for="item in group.items" :key="group.project + '-' + item.id" class="transition hover:bg-indigo-50/30">
                                <router-link :to="detailPath(group.project, item)" class="group block px-5 py-4">
                                    <h3 class="font-semibold text-gray-900 transition group-hover:text-indigo-600">{{ item.title }}</h3>
                                    <p v-if="summary(item)" class="mt-1 line-clamp-2 text-sm text-gray-500">{{ summary(item) }}</p>
                                </router-link>
                            </li>
                        </ul>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<script>
import api from "../api";
import { PROJECTS } from "../config";

export default {
    name: "SearchPage",
    data() {
        return {
            query: "",
            loading: false,
            error: "",
            results: {
                cms: [],
                directory: [],
                note: [],
            },
        };
    },
    computed: {
        queryParam() {
            return (this.$route.query.q || "").trim();
        },
        groups() {
            return [
                { project: "cms", label: PROJECTS.cms.label, items: this.results.cms },
                { project: "directory", label: PROJECTS.directory.label, items: this.results.directory },
                { project: "note", label: PROJECTS.note.label, items: this.results.note },
            ].filter((g) => g.items.length > 0);
        },
        totalLabel() {
            const total = this.groups.reduce((n, g) => n + g.items.length, 0);
            if (!this.query) return "";
            return `${total} result${total === 1 ? "" : "s"}`;
        },
    },
    watch: {
        queryParam(q) {
            this.search(q);
        },
    },
    async mounted() {
        this.search(this.queryParam);
    },
    methods: {
        summary(item) {
            return item.excerpt || item.description || "";
        },
        detailPath(project, item) {
            const category =
                item.category?.slug || item.categories?.[0]?.slug || item.location?.slug || "";
            if (project === "cms") return category ? `/content/${category}/${item.slug}` : `/content/${item.slug}`;
            if (project === "directory") return category ? `/directory/${category}/${item.slug}` : `/directory`;
            return category ? `/note/${category}/${item.slug}` : `/note`;
        },
        async search(q) {
            this.query = q;
            this.error = "";
            if (!q || q.length < 2) {
                this.results = { cms: [], directory: [], note: [] };
                return;
            }
            this.loading = true;
            const params = { query: q, limit: 20 };
            const calls = [
                api.searchArticles(params).catch(() => []),
                api.searchListings(params).catch(() => []),
                api.searchNotePosts(params).catch(() => []),
            ];
            const [cms, directory, note] = await Promise.all(calls);
            this.results = {
                cms: Array.isArray(cms) ? cms : [],
                directory: Array.isArray(directory) ? directory : [],
                note: Array.isArray(note) ? note : [],
            };
            this.loading = false;
        },
    },
};
</script>
