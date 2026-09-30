<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <router-link to="/" class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:gap-2 hover:text-indigo-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to home
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
            <h2 class="text-xl font-bold text-gray-900">Article not found</h2>
            <p class="mt-2 text-sm text-gray-500">This article may have been unpublished or removed.</p>
            <router-link to="/" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70">
                Back to home
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <template v-else>
            <!-- Category + Date meta -->
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <router-link
                    v-if="item.category"
                    :to="`/content/category/${item.category.slug}`"
                    class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-600 transition hover:bg-indigo-100"
                >
                    {{ item.category.title }}
                </router-link>
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

            <!-- Tags -->
            <div v-if="item.tags && item.tags.length" class="mt-8 flex flex-wrap gap-2 border-t border-gray-200 pt-6">
                <span class="mr-1 flex items-center text-sm font-medium text-gray-400">Tags:</span>
                <router-link
                    v-for="tag in item.tags"
                    :key="tag.id"
                    :to="`/content/tag/${(tag.tag || '').toLowerCase().replace(/\s+/g, '-')}`"
                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>

            <!-- Favorite / Like -->
            <ContentActions :project="PROJECTS.cms.identifier" :content-id="item.id" class="mt-6" />

            <!-- Comments -->
            <div v-if="item['comments_moderation'] !== 'disabled'" class="mt-10 border-t border-gray-200 pt-8">
                <h2 class="mb-5 flex items-center gap-2 text-xl font-bold text-gray-900">
                    <svg class="h-5 w-5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Comments
                </h2>

                <div v-if="comments.length" class="space-y-4">
                    <div v-for="comment in comments" :key="comment.id" class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-xs font-bold text-white">
                                {{ (comment.name || "?").charAt(0).toUpperCase() }}
                            </div>
                            <span class="font-semibold text-gray-800">{{ comment.name }}</span>
                            <span class="text-xs text-gray-400">{{ formatCommentDate(comment.created_at) }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-gray-700">{{ comment.comment }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">No comments yet. Be the first to share your thoughts!</p>

                <div class="mt-6">
                    <div v-if="!currentUser" class="flex items-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-4 text-sm text-gray-600">
                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><a href="/login" class="font-semibold text-indigo-600 transition hover:text-indigo-700">Log in</a> to leave a comment.</span>
                    </div>
                    <form v-else @submit.prevent="submitComment" class="rounded-xl border border-gray-200/80 bg-white p-5 shadow-sm">
                        <div class="mb-3 flex items-center gap-2 text-sm text-gray-600">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-xs font-bold text-white">
                                {{ (currentUser.name || "?").charAt(0).toUpperCase() }}
                            </div>
                            Commenting as <span class="font-semibold text-gray-800">{{ currentUser.name }}</span>
                        </div>
                        <textarea
                            v-model="commentText"
                            rows="3"
                            required
                            maxlength="2000"
                            placeholder="Share your thoughts…"
                            class="w-full rounded-xl border border-gray-200 p-3.5 text-sm text-gray-800 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        ></textarea>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span v-if="submitMessage" class="flex items-center gap-1.5 text-sm" :class="submitError ? 'text-red-600' : 'text-green-600'">
                                <svg v-if="!submitError" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ submitMessage }}
                            </span>
                            <button
                                type="submit"
                                :disabled="submitting || !commentText.trim()"
                                class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:bg-indigo-500 disabled:opacity-50"
                            >
                                <svg v-if="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 12a9 9 0 1 1-6.219-8.56" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ submitting ? "Submitting…" : "Post Comment" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
    name: "ArticleDetail",
    components: {
        SsmlEditor,
        ContentActions,
    },
    data() {
        return {
            PROJECTS,
            item: null,
            loading: true,
            comments: [],
            currentUser: null,
            commentText: "",
            submitting: false,
            submitMessage: "",
            submitError: false,
        };
    },
    computed: {
        formattedDate() {
            if (!this.item || !this.item.published_at) return null;
            return formatDate(this.item.published_at, "MMM D, YYYY");
        },
        params() {
            return `${this.$route.params.category}/${this.$route.params.article}`;
        },
    },
    watch: {
        params() {
            this.loadItem();
        },
    },
    async mounted() {
        await this.loadItem();
        this.loadComments();
        this.loadMe();
    },
    methods: {
        async loadItem() {
            this.loading = true;
            this.item = null;
            const articleUrl = this.$route.params.article;

            try {
                this.item = await api.getArticleBySlug(articleUrl, { timestamps: true });
            } catch (error) {
                console.error("Failed to load article:", error);
                this.item = null;
            } finally {
                this.loading = false;
            }
        },

        async loadComments() {
            if (!this.item || this.item["comments_moderation"] === "disabled") {
                this.comments = [];
                return;
            }
            try {
                this.comments = await api.getComments(PROJECTS.cms.identifier, this.item.id);
            } catch (error) {
                console.error("Failed to load comments:", error);
                this.comments = [];
            }
        },

        async loadMe() {
            try {
                this.currentUser = await api.me();
            } catch (error) {
                this.currentUser = null;
            }
        },

        async submitComment() {
            this.submitting = true;
            this.submitMessage = "";
            this.submitError = false;

            try {
                const result = await api.submitComment(PROJECTS.cms.identifier, this.item.id, this.commentText);
                this.commentText = "";

                if (result && result.status === "pending") {
                    this.submitMessage = "Comment submitted — it will appear once approved.";
                } else {
                    await this.loadComments();
                }
            } catch (error) {
                this.submitError = true;
                this.submitMessage =
                    (error && error.response && error.response.data && error.response.data.message) ||
                    "Unable to post the comment. Please try again.";
            } finally {
                this.submitting = false;
            }
        },

        formatCommentDate(value) {
            return value ? formatDate(value, "MMM D, YYYY") : "";
        },
    },
};
</script>
