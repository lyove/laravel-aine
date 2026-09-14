<template>
    <div class="mx-auto w-full max-w-6xl px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                {{ query ? `Search results for “${query}”` : "Search" }}
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ totalLabel }}
            </p>
        </div>

        <div v-if="loading" class="py-16 text-center text-sm text-gray-500">
            Searching…
        </div>

        <div v-else-if="error" class="py-16 text-center text-sm text-red-600">
            {{ error }}
        </div>

        <div v-else-if="!query" class="py-16 text-center text-sm text-gray-500">
            Type at least 2 characters to search across all projects.
        </div>

        <div v-else-if="groups.length === 0" class="py-16 text-center text-sm text-gray-500">
            No results found for “{{ query }}”.
        </div>

        <div v-else class="space-y-10">
            <section v-for="group in groups" :key="group.project">
                <template v-if="group.items.length > 0">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        <span>{{ group.label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ group.items.length }}</span>
                    </h2>
                    <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
                        <li v-for="item in group.items" :key="group.project + '-' + item.id" class="px-4 py-3">
                            <router-link :to="detailPath(group.project, item)" class="block group">
                                <h3 class="font-medium text-gray-900 transition group-hover:text-indigo-600">{{ item.title }}</h3>
                                <p v-if="summary(item)" class="mt-0.5 line-clamp-2 text-sm text-gray-500">{{ summary(item) }}</p>
                            </router-link>
                        </li>
                    </ul>
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
            if (project === "directory") return category ? `/directory/${category}/${item.slug}` : `/directory/${item.slug}`;
            return category ? `/note/${category}/${item.slug}` : `/note/${item.slug}`;
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
