<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <router-link :to="projectConfig.path" class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:gap-2 hover:text-indigo-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to {{ projectConfig.label.toLowerCase() }}
        </router-link>

        <div v-if="loading" class="space-y-4">
            <div class="h-8 w-3/4 animate-pulse rounded bg-gray-200"></div>
            <div class="h-64 animate-pulse rounded-2xl bg-gray-100"></div>
            <div class="h-4 w-full animate-pulse rounded bg-gray-200"></div>
            <div class="h-4 w-5/6 animate-pulse rounded bg-gray-200"></div>
        </div>

        <div v-else-if="!item" class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Post not found</h2>
            <p class="mt-2 text-sm text-gray-500">This post may have been unpublished or removed.</p>
            <router-link :to="projectConfig.path" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Back to {{ projectConfig.label.toLowerCase() }}
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <router-link
                    v-if="item.category"
                    :to="`${projectConfig.path}/category/${item.category.slug}`"
                    class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-600 transition hover:bg-indigo-100"
                >
                    {{ item.category.title }}
                </router-link>
                <span v-if="item.category && formattedDate" class="text-gray-400">·</span>
                <span v-if="formattedDate" class="flex items-center gap-1.5 text-gray-400">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    {{ formattedDate }}
                </span>
            </div>

            <h1 class="mb-4 text-3xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-4xl">
                {{ item.title }}
            </h1>

            <p v-if="item.excerpt" class="mb-6 text-lg leading-relaxed text-gray-500">
                {{ item.excerpt }}
            </p>

            <img
                v-if="item['featured-image'] && item['featured-image'].full_url"
                :src="item['featured-image'].full_url"
                :alt="item.title"
                class="mb-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-lg shadow-gray-300/30"
            />

            <SsmlEditor class="article-body text-base leading-relaxed text-gray-800" :model-value="item.content" read-only html />

            <div v-if="item.tags && item.tags.length" class="mt-8 flex flex-wrap gap-2 border-t border-gray-200 pt-6">
                <span class="mr-1 flex items-center text-sm font-medium text-gray-400">Tags:</span>
                <router-link
                    v-for="tag in item.tags"
                    :key="tag.id"
                    :to="`${projectConfig.path}/tag/${(tag.tag || '').toLowerCase().replace(/\s+/g, '-')}`"
                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>

            <!-- Favorite / Like -->
            <ContentActions :project="PROJECTS.note.identifier" :content-id="item.id" class="mt-6" />
        </template>
    </div>
</template>

<script>
import { formatDate } from "../../../utils/filters";
import { api } from "../../api";
import { PROJECTS } from "../../config";
import SsmlEditor from "../../../components/SsmlEditor.vue";
import ContentActions from "../../components/ContentActions.vue";

export default {
    name: "NotePostDetail",
    components: {
        SsmlEditor,
        ContentActions,
    },
    data() {
        return {
            PROJECTS,
            item: null,
            loading: true,
        };
    },
    computed: {
        projectConfig() {
            return PROJECTS.note;
        },
        formattedDate() {
            if (!this.item || !this.item.published_at) return null;
            return formatDate(this.item.published_at, "MMM D, YYYY");
        },
        params() {
            return `${this.$route.params.category}/${this.$route.params.post}`;
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
        async loadItem() {
            this.loading = true;
            this.item = null;
            const postUrl = this.$route.params.post;

            try {
                this.item = await api.getNotePostBySlug(postUrl, { timestamps: true, _skipLocale: true });
            } catch (error) {
                console.error("Failed to load post:", error);
                this.item = null;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
.article-body h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 2rem 0 1rem;
}
.article-body h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 1.5rem 0 0.75rem;
}
.article-body p {
    margin: 0 0 1rem;
}
.article-body img {
    max-width: 100%;
    border-radius: 0.5rem;
    margin: 1rem 0;
}
.article-body a {
    color: #4f46e5;
    text-decoration: underline;
}
.article-body ul,
.article-body ol {
    margin: 0 0 1rem 1.5rem;
}
.article-body li {
    margin-bottom: 0.25rem;
}
.article-body blockquote {
    border-left: 3px solid #4f46e5;
    padding-left: 1rem;
    margin: 1.5rem 0;
    color: #6b7280;
    font-style: italic;
}
.article-body pre {
    background: #f5f5f5;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1rem 0;
    font-size: 0.875rem;
}
.article-body code {
    background: #f5f5f5;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}
.article-body pre code {
    background: none;
    padding: 0;
}
</style>
