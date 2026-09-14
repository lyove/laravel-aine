<template>
    <div class="admin__project-content-comments relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex flex-1 overflow-y-auto">
            <div class="w-96 bg-white overflow-x-hidden">
                <content-sidebar :project="project"></content-sidebar>
            </div>

            <div class="flex-1 overflow-x-hidden">
                <div class="p-4">
                    <div class="flex justify-between p-2 items-center">
                        <h4 class="mb-2 font-bold text-xl">
                            {{ __('Comments') }}
                        </h4>

                        <div class="flex space-x-2">
                            <ui-button
                                v-for="tab in tabs"
                                :key="tab.value"
                                :color="filter === tab.value ? 'indigo-600' : 'gray-100'"
                                :hover="filter === tab.value ? 'indigo-700' : 'gray-200'"
                                @click="switchFilter(tab.value)"
                            >
                                {{ tab.label }}
                                <span v-if="counts[tab.value] > 0" class="ml-1 px-1.5 py-0.5 rounded-full bg-white text-indigo-600 text-xs font-semibold">
                                    {{ counts[tab.value] }}
                                </span>
                            </ui-button>
                            <ui-button color="gray-200" hover="gray-300" @click="getComments">
                                <i class="fa fa-sync-alt"></i> {{ __('Refresh') }}
                            </ui-button>
                        </div>
                    </div>

                    <div v-if="loading" class="space-y-3 p-4">
                        <div class="h-16 animate-pulse rounded-lg bg-gray-100"></div>
                        <div class="h-16 animate-pulse rounded-lg bg-gray-100"></div>
                    </div>

                    <div v-else-if="grouped.length === 0" class="mt-4 rounded-md bg-white p-10 text-center text-sm text-gray-500">
                        {{ __('No comments in this view.') }}
                    </div>

                    <div v-else class="mt-4 space-y-6">
                        <div v-for="group in grouped" :key="group.article_id" class="rounded-md border border-gray-200 bg-white p-4">
                            <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2">
                                <h5 class="font-semibold text-gray-800">
                                    {{ group.article_title || __('(deleted article)') }}
                                </h5>
                                <span class="text-xs text-gray-400">{{ group.comments.length }} {{ __('comment(s)') }}</span>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="comment in group.comments"
                                    :key="comment.id"
                                    class="flex items-start justify-between rounded-md border border-gray-100 bg-gray-50 p-3"
                                >
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-sm text-gray-800">{{ comment.name }}</span>
                                            <span class="text-xs text-gray-400">{{ comment.email }}</span>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                :class="statusClass(comment.status)"
                                            >
                                                {{ statusLabel(comment.status) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm leading-relaxed text-gray-700">{{ comment.comment }}</p>
                                        <p class="mt-1 text-xs text-gray-400">{{ comment.created_at }}</p>
                                    </div>

                                    <div class="ml-4 flex shrink-0 items-center gap-2">
                                        <ui-button
                                            v-if="comment.status === 'pending' || comment.status === 'spam' || comment.status === 'trash'"
                                            color="green-600"
                                            hover="green-700"
                                            size="sm"
                                            @click="approve(comment)"
                                        >
                                            <i class="fa fa-check"></i> {{ __('Approve') }}
                                        </ui-button>
                                        <ui-button
                                            v-if="comment.status !== 'spam'"
                                            color="gray-200"
                                            hover="gray-300"
                                            size="sm"
                                            @click="markSpam(comment)"
                                        >
                                            <i class="fa fa-bug"></i> {{ __('Spam') }}
                                        </ui-button>
                                        <ui-button
                                            v-if="comment.status !== 'trash'"
                                            color="red-100"
                                            hover="red-200"
                                            size="sm"
                                            @click="trashComment(comment)"
                                        >
                                            <i class="fa fa-trash-restore"></i> {{ __('Trash') }}
                                        </ui-button>
                                        <ui-button
                                            v-if="comment.status === 'trash'"
                                            color="orange-200"
                                            hover="orange-300"
                                            size="sm"
                                            @click="restore(comment)"
                                        >
                                            <i class="fa fa-recycle"></i> {{ __('Restore') }}
                                        </ui-button>
                                        <ui-button
                                            v-if="comment.status === 'trash' || comment.status === 'spam'"
                                            color="red-200"
                                            hover="red-300"
                                            size="sm"
                                            @click="remove(comment)"
                                        >
                                            <i class="fa fa-trash-alt"></i> {{ __('Delete') }}
                                        </ui-button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { __ } from "../../translations/engine";
import UiButton from "../../../components/Button.vue";
import ProjectHeader from "../components/ProjectHeader.vue";
import ContentSidebar from "./sections/ContentSidebar.vue";
import projectBreadcrumb from "../../mixins/projectBreadcrumb";
import { useAdminStore } from "../../store";

export default {
    components: {
        ProjectHeader,
        ContentSidebar,
        UiButton,
    },

    mixins: [projectBreadcrumb],

    data() {
        return {
            project: useAdminStore().currentProject || {},
            filter: "pending",
            comments: [],
            counts: { pending: 0, approved: 0, spam: 0, trash: 0 },
            loading: false,
            tabs: [
                { value: "pending", label: __("Pending") },
                { value: "approved", label: __("Approved") },
                { value: "spam", label: __("Spam") },
                { value: "trash", label: __("Trash") },
                { value: "all", label: __("All") },
            ],
        };
    },

    computed: {
        grouped() {
            const map = new Map();
            for (const comment of this.comments) {
                const key = comment.article_id ?? "none";
                if (!map.has(key)) {
                    map.set(key, {
                        article_id: comment.article_id,
                        article_title: comment.article_title,
                        comments: [],
                    });
                }
                map.get(key).comments.push(comment);
            }
            return Array.from(map.values());
        },
    },

    mounted() {
        this.getComments();
    },

    methods: {
        getComments() {
            this.loading = true;
            axios
                .get(`/admin-api/content/comments/${this.project.id}`, {
                    params: { filter: this.filter },
                })
                .then((response) => {
                    this.comments = response.data.rows || [];
                    this.counts = response.data.counts || this.counts;
                })
                .catch(() => {
                    this.$swal.fire({
                        icon: "error",
                        title: __("Something went wrong"),
                        text: __("Unable to load comments."),
                    });
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        switchFilter(filter) {
            this.filter = filter;
            this.getComments();
        },

        statusLabel(status) {
            return {
                approved: __("Approved"),
                pending: __("Pending"),
                spam: __("Spam"),
                trash: __("Trash"),
            }[status] || status;
        },

        statusClass(status) {
            return {
                approved: "bg-green-100 text-green-700",
                pending: "bg-amber-100 text-amber-700",
                spam: "bg-gray-200 text-gray-600",
                trash: "bg-red-100 text-red-700",
            }[status] || "bg-gray-100 text-gray-600";
        },

        approve(comment) {
            this.transition(comment, "approve", __("The comment is now visible on the blog."));
        },

        markSpam(comment) {
            this.transition(comment, "spam", __("The comment has been marked as spam."));
        },

        trashComment(comment) {
            this.$swal
                .fire({
                    title: __("Move comment to trash?"),
                    text: __("The comment will disappear from the blog and the queue."),
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc2626",
                    confirmButtonText: __("Move to trash"),
                    cancelButtonText: __("Cancel"),
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.transition(comment, "reject", __("The comment has been moved to the trash."));
                    }
                });
        },

        restore(comment) {
            this.transition(comment, "restore", __("The comment has been restored to pending."));
        },

        transition(comment, action, successText) {
            axios
                .post(`/admin-api/content/comments/${action}/${this.project.id}/${comment.id}`)
                .then((response) => {
                    const status = response.data && response.data.data ? response.data.data.status : null;
                    if (status) {
                        comment.status = status;
                    }
                    this.$swal.fire({
                        icon: "success",
                        title: __("Done"),
                        text: successText,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    this.getComments();
                })
                .catch(() => {
                    this.$swal.fire({
                        icon: "error",
                        title: __("Something went wrong"),
                        text: __("Unable to update the comment."),
                    });
                });
        },

        remove(comment) {
            this.$swal
                .fire({
                    title: __("Delete comment permanently?"),
                    text: __("This cannot be undone."),
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc2626",
                    confirmButtonText: __("Delete"),
                    cancelButtonText: __("Cancel"),
                })
                .then((result) => {
                    if (!result.isConfirmed) return;
                    axios
                        .post(`/admin-api/content/comments/bulk/${this.project.id}`, {
                            action: "delete",
                            ids: [comment.id],
                        })
                        .then(() => {
                            this.comments = this.comments.filter((c) => c.id !== comment.id);
                            this.getComments();
                        })
                        .catch(() => {
                            this.$swal.fire({
                                icon: "error",
                                title: __("Something went wrong"),
                                text: __("Unable to delete the comment."),
                            });
                        });
                });
        },
    },
};
</script>
