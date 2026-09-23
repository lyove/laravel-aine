<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <router-link to="/" class="transition hover:text-indigo-600">{{ siteName || "Home" }}</router-link>
            <span class="text-gray-300">/</span>
            <router-link :to="projectConfig.path" class="transition hover:text-indigo-600">{{ projectConfig.label }}</router-link>
            <span class="text-gray-300">/</span>
            <span class="font-medium text-gray-900">Pages</span>
        </nav>

        <h1 class="mb-2 flex items-center gap-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
            <span class="inline-block h-7 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
            Pages
        </h1>
        <p class="mb-8 text-gray-500">All static pages of the {{ projectConfig.label.toLowerCase() }} project.</p>

        <note-tabs active="pages" />

        <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="n in 6" :key="n" class="h-32 animate-pulse rounded-2xl bg-gray-100"></div>
        </div>

        <div v-else-if="!pages.length" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">No pages yet</h2>
            <p class="mt-2 text-sm text-gray-500">Publish some pages from the admin panel to see them here.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <router-link
                v-for="page in pages"
                :key="page.id"
                :to="`${projectConfig.path}/${page.slug}`"
                class="group rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-gray-300/30"
            >
                <div class="mb-3 flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500 transition group-hover:bg-indigo-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 transition group-hover:text-indigo-600">
                        {{ page.title }}
                    </h3>
                </div>
                <p v-if="snippet(page)" class="line-clamp-2 text-sm leading-relaxed text-gray-500">
                    {{ snippet(page) }}
                </p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 opacity-0 transition group-hover:opacity-100">
                    Open page
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </router-link>
        </div>
    </div>
</template>

<script>
import { api } from "../../api";
import { PROJECTS } from "../../config";
import { useFrontendStore } from "../../store";
import NoteTabs from "./components/NoteTabs.vue";

export default {
    name: "NotePagesList",
    components: {
        NoteTabs,
    },
    data() {
        return {
            pages: [],
            loading: true,
            siteName: "Home",
        };
    },
    computed: {
        projectConfig() {
            return PROJECTS.note;
        },
    },
    async mounted() {
        const store = useFrontendStore();
        this.siteName = store.settings.name || "Home";
        try {
            this.pages = await api.getNotePages({ timestamps: true });
        } catch (error) {
            console.error("Failed to load pages:", error);
        } finally {
            this.loading = false;
        }
    },
    methods: {
        snippet(page) {
            if (!page.content) return "";
            const text = page.content
                .replace(/<[^>]+>/g, " ")
                .replace(/&amp;/g, "&")
                .replace(/&nbsp;/g, " ")
                .replace(/\s+/g, " ")
                .trim();
            return text.length > 120 ? text.slice(0, 120) + "…" : text;
        },
    },
};
</script>
