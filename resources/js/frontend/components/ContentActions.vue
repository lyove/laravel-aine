<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            :disabled="busy || !currentUser"
            class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-medium transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50"
            :class="state.is_favorited
                ? 'border-amber-400 bg-amber-50 text-amber-700 shadow-sm shadow-amber-200/50'
                : 'border-gray-200 bg-white text-gray-600 hover:border-amber-300 hover:bg-amber-50/50 hover:text-amber-600'"
            @click="toggleFavorite"
        >
            <svg class="h-4 w-4 transition" :class="state.is_favorited ? 'scale-110' : ''" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
            <span>{{ state.is_favorited ? "Favorited" : "Favorite" }}</span>
            <span v-if="state.favorite_count" class="text-xs opacity-70">{{ state.favorite_count }}</span>
        </button>

        <button
            type="button"
            :disabled="busy || !currentUser"
            class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-medium transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50"
            :class="state.is_liked
                ? 'border-rose-400 bg-rose-50 text-rose-600 shadow-sm shadow-rose-200/50'
                : 'border-gray-200 bg-white text-gray-600 hover:border-rose-300 hover:bg-rose-50/50 hover:text-rose-500'"
            @click="toggleLike"
        >
            <svg class="h-4 w-4 transition" :class="state.is_liked ? 'scale-110' : ''" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
            <span>{{ state.is_liked ? "Liked" : "Like" }}</span>
            <span v-if="state.like_count" class="text-xs opacity-70">{{ state.like_count }}</span>
        </button>

        <a
            v-if="!currentUser"
            href="/login"
            class="inline-flex items-center gap-1 text-xs text-gray-400 transition hover:text-indigo-600"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Log in to interact
        </a>
    </div>
</template>

<script>
import { api } from "../api";

export default {
    name: "ContentActions",
    props: {
        project: { type: String, required: true },
        contentId: { type: [Number, String], required: true },
    },
    data() {
        return {
            state: { favorite_count: 0, like_count: 0, is_favorited: false, is_liked: false },
            busy: false,
            currentUser: null,
        };
    },
    async mounted() {
        this.loadState();
        try {
            this.currentUser = await api.me();
        } catch (e) {
            this.currentUser = null;
        }
    },
    methods: {
        async loadState() {
            try {
                const data = await api.getInteractionState(this.project, this.contentId);
                if (data) this.state = { ...this.state, ...data };
            } catch (e) {
                /* keep defaults */
            }
        },
        async toggleFavorite() {
            if (!this.currentUser || this.busy) return;
            this.busy = true;
            try {
                const data = this.state.is_favorited
                    ? await api.removeFavorite(this.project, this.contentId)
                    : await api.addFavorite(this.project, this.contentId);
                if (data) this.state = { ...this.state, ...data };
            } catch (e) {
                /* handled by the shared API error toast */
            } finally {
                this.busy = false;
            }
        },
        async toggleLike() {
            if (!this.currentUser || this.busy) return;
            this.busy = true;
            try {
                const data = this.state.is_liked
                    ? await api.removeLike(this.project, this.contentId)
                    : await api.addLike(this.project, this.contentId);
                if (data) this.state = { ...this.state, ...data };
            } catch (e) {
                /* handled by the shared API error toast */
            } finally {
                this.busy = false;
            }
        },
    },
};
</script>
