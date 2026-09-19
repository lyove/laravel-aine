<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <router-link :to="projectConfig.path" class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:gap-2 hover:text-indigo-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to {{ projectConfig.label.toLowerCase() }}
        </router-link>

        <div v-if="loading" class="space-y-4">
            <div class="h-8 w-1/2 animate-pulse rounded bg-gray-200"></div>
            <div class="h-4 w-full animate-pulse rounded bg-gray-200"></div>
            <div class="h-4 w-5/6 animate-pulse rounded bg-gray-200"></div>
        </div>

        <div v-else-if="!page" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 2v6h6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Page not found</h2>
            <p class="mt-2 text-sm text-gray-500">The page "{{ slug }}" does not exist.</p>
            <router-link :to="projectConfig.path" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Back to {{ projectConfig.label.toLowerCase() }}
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <template v-else>
            <h1 class="mb-4 text-3xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-4xl">{{ page.title }}</h1>

            <img
                v-if="page.image && page.image.full_url"
                :src="page.image.full_url"
                :alt="page.title"
                class="mb-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-lg shadow-gray-300/30"
            />

            <SsmlEditor class="page-body text-base leading-relaxed text-gray-800" :model-value="page.content" read-only html />
        </template>
    </div>
</template>

<script>
import { api } from "../../api";
import { PROJECTS } from "../../config";
import SsmlEditor from "../../../components/SsmlEditor.vue";

export default {
    name: "NotePageDetail",
    components: {
        SsmlEditor,
    },
    data() {
        return {
            page: null,
            loading: true,
        };
    },
    computed: {
        projectConfig() {
            return PROJECTS.note;
        },
        slug() {
            return this.$route.params.slug;
        },
    },
    watch: {
        slug() {
            this.loadPage();
        },
    },
    async mounted() {
        this.loadPage();
    },
    methods: {
        async loadPage() {
            this.loading = true;
            this.page = null;
            try {
                const pages = await api.getNotePages({ timestamps: true, _skipLocale: true });
                this.page = (pages || []).find((p) => p.slug === this.slug) || null;
            } catch (error) {
                console.error("Failed to load page:", error);
                this.page = null;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.page-body h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 2rem 0 1rem;
}
.page-body h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 1.5rem 0 0.75rem;
}
.page-body p {
    margin: 0 0 1rem;
}
.page-body img {
    max-width: 100%;
    border-radius: 0.5rem;
    margin: 1rem 0;
}
.page-body a {
    color: #4f46e5;
    text-decoration: underline;
}
.page-body ul,
.page-body ol {
    margin: 0 0 1rem 1.5rem;
}
.page-body li {
    margin-bottom: 0.25rem;
}
.page-body blockquote {
    border-left: 3px solid #4f46e5;
    padding-left: 1rem;
    margin: 1.5rem 0;
    color: #6b7280;
    font-style: italic;
}
.page-body pre {
    background: #f5f5f5;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1rem 0;
    font-size: 0.875rem;
}
</style>
