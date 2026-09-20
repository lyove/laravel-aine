<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <router-link to="/" class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:gap-2 hover:text-indigo-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to home
        </router-link>

        <div v-if="loading && !profile" class="py-20 text-center">
            <div class="relative mx-auto h-10 w-10">
                <div class="absolute inset-0 rounded-full border-2 border-gray-200"></div>
                <div class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-indigo-500"></div>
            </div>
            <p class="mt-4 text-sm text-gray-500">Loading your profile…</p>
        </div>

        <template v-else-if="profile">
            <!-- Profile header -->
            <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                <div class="h-40 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600"></div>
                <div class="px-6 pb-6 relative">
                    <div class="flex flex-wrap items-end gap-5 -mt-12">
                        <div class="shrink-0">
                            <img
                                v-if="profile.avatar"
                                :src="profile.avatar"
                                alt="Avatar"
                                class="h-24 w-24 rounded-full border-4 border-white object-cover shadow-lg"
                            />
                            <div
                                v-else
                                class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl font-bold text-white shadow-lg"
                            >
                                {{ initials }}
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-1 items-end justify-between gap-4 pb-1">
                            <div class="min-w-0">
                                <h1 class="text-2xl font-bold text-gray-100">{{ profile.name }}</h1>
                                <p class="mt-6 flex items-center gap-2 truncate text-sm text-gray-500">
                                    {{ profile.email }}
                                    <button type="button" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium underline" @click="showPwdModal = true">Change password</button>
                                    <span v-if="profile.email_verified_at" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                        Verified
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                        Unverified
                                    </span>
                                </p>
                                <p class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <path d="M16 2v4M8 2v4M3 10h18"/>
                                    </svg>
                                    Joined {{ joinedAt }}
                                </p>
                            </div>


                        </div>
                    </div>

                    <!-- Stats absolute -->
                    <div class="absolute right-6 top-[52px] flex gap-6 text-center">
                        <div>
                            <div class="text-2xl font-bold text-gray-900">{{ profile.favorites_count }}</div>
                            <div class="text-xs text-gray-400">Favorites</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">{{ profile.likes_count }}</div>
                            <div class="text-xs text-gray-400">Likes</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">{{ profile.comments_count }}</div>
                            <div class="text-xs text-gray-400">Comments</div>
                        </div>
                    </div>

                    <!-- Avatar upload -->
                    <div class="mt-5 flex items-center gap-3 border-t border-gray-100 pt-4 text-sm">
                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="onAvatarChange" />
                        <button
                            type="button"
                            :disabled="uploading"
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 font-medium text-gray-700 transition hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-600 disabled:opacity-50"
                            @click="$refs.avatarInput.click()"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                            {{ uploading ? "Uploading…" : "Change avatar" }}
                        </button>
                        <span v-if="avatarMessage" class="text-sm" :class="avatarError ? 'text-red-600' : 'text-green-600'">
                            {{ avatarMessage }}
                        </span>
                    </div>

                </div>
            </div>

            <!-- Tabs -->
            <div class="mt-8 flex gap-6 border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="-mb-px flex items-center gap-1.5 border-b-2 pb-2.5 text-sm font-semibold transition"
                    :class="activeTab === tab.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        v-if="counts[tab.key]"
                        class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] font-medium"
                        :class="activeTab === tab.key ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ counts[tab.key] }}
                    </span>
                </button>
            </div>

            <!-- Favorites -->
            <div v-if="activeTab === 'favorites'" class="mt-5">
                <p v-if="!favorites.length" class="py-12 text-center text-sm text-gray-500">
                    You haven't favorited anything yet.
                </p>
                <div v-else class="space-y-3">
                    <div v-for="f in favorites" :key="'f' + f.content_id" class="group flex items-start justify-between gap-4 rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm transition hover:shadow-md">
                        <div class="min-w-0">
                            <router-link
                                v-if="linkFor(f)"
                                :to="linkFor(f)"
                                class="font-semibold text-gray-900 transition hover:text-indigo-600"
                            >
                                {{ f.title || "Untitled" }}
                            </router-link>
                            <span v-else class="font-semibold text-gray-900">{{ f.title || "Untitled" }}</span>
                            <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-400">
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 font-medium text-indigo-600">{{ f.project_name || f.project_identifier || "Unknown" }}</span>
                                <template v-if="f.collection"> · {{ f.collection }}</template>
                                <template v-if="f.favorited_at"> · {{ formatDate(f.favorited_at) }}</template>
                                <span v-if="f.published === false" class="ml-1 text-amber-600">(unpublished)</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-xs text-gray-400 transition hover:text-rose-500"
                            @click="removeItem('favorite', f)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Likes -->
            <div v-if="activeTab === 'likes'" class="mt-5">
                <p v-if="!likes.length" class="py-12 text-center text-sm text-gray-500">
                    You haven't liked anything yet.
                </p>
                <div v-else class="space-y-3">
                    <div v-for="l in likes" :key="'l' + l.content_id" class="group flex items-start justify-between gap-4 rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm transition hover:shadow-md">
                        <div class="min-w-0">
                            <router-link
                                v-if="linkFor(l)"
                                :to="linkFor(l)"
                                class="font-semibold text-gray-900 transition hover:text-indigo-600"
                            >
                                {{ l.title || "Untitled" }}
                            </router-link>
                            <span v-else class="font-semibold text-gray-900">{{ l.title || "Untitled" }}</span>
                            <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-400">
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 font-medium text-rose-600">{{ l.project_name || l.project_identifier || "Unknown" }}</span>
                                <template v-if="l.collection"> · {{ l.collection }}</template>
                                <template v-if="l.liked_at"> · {{ formatDate(l.liked_at) }}</template>
                                <span v-if="l.published === false" class="ml-1 text-amber-600">(unpublished)</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-xs text-gray-400 transition hover:text-rose-500"
                            @click="removeItem('like', l)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Comments -->
            <div v-if="activeTab === 'comments'" class="mt-5">
                <p v-if="!comments.length" class="py-12 text-center text-sm text-gray-500">
                    You haven't commented yet.
                </p>
                <div v-else class="space-y-3">
                    <div v-for="c in comments" :key="'c' + c.id" class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <router-link
                                v-if="linkForComment(c)"
                                :to="linkForComment(c)"
                                class="font-medium text-indigo-600 transition hover:text-indigo-700"
                            >
                                {{ c.article_title || "Article" }}
                            </router-link>
                            <span v-else class="font-medium text-gray-700">{{ c.article_title || "Article" }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusClass(c.status)">
                                {{ c.status }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ c.project_name || c.project_identifier || "" }}
                                <template v-if="c.created_at"> · {{ formatDate(c.created_at) }}</template>
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-gray-700">{{ c.comment }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="py-20 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-8 w-8 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Sign in required</h2>
            <p class="mt-2 text-sm text-gray-500">Log in to view your profile.</p>
            <a href="/login" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:bg-indigo-500">
                Go to login
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>

            <!-- Change Password Modal -->
            <div v-if="showPwdModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPwdModal = false">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Password</h3>
                    <form class="space-y-3" @submit.prevent="changePassword">
                        <input v-model="pwd.current" type="password" placeholder="Current password" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none" />
                        <input v-model="pwd.new" type="password" placeholder="New password (min 8 chars)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none" />
                        <input v-model="pwd.confirm" type="password" placeholder="Confirm new password" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none" />
                        <div class="flex items-center justify-between gap-3">
                            <span v-if="pwdMsg" class="text-sm" :class="pwdError ? 'text-red-600' : 'text-green-600'">{{ pwdMsg }}</span>
                            <div class="flex gap-2 ml-auto">
                                <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showPwdModal = false">Cancel</button>
                                <button type="submit" :disabled="pwdLoading" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                    {{ pwdLoading ? "Saving…" : "Update Password" }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</template>

<script>
import { formatDate } from "../../utils/filters";
import { api } from "../api";

const PATH_PREFIX = {
    cms: "/content",
    directory: "/directory",
    note: "/note",
};

/** Maximum edge (px) for the avatar before upload; avatars render at ~80px. */
const AVATAR_MAX_DIMENSION = 512;

/**
 * Downscale JPEG/PNG/WebP avatars client-side so uploads stay well under the
 * server's 2 MB limit regardless of camera/photo size. GIFs and other types
 * are passed through unchanged.
 */
async function prepareAvatarFile(file) {
    if (!file || !["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        return file;
    }

    const bitmap = await loadAvatarImage(file);
    const scale = Math.min(
        1,
        AVATAR_MAX_DIMENSION / Math.max(bitmap.width, bitmap.height)
    );
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));

    const canvas = document.createElement("canvas");
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(bitmap, 0, 0, width, height);

    // JPEG stays JPEG; PNG/WebP export as PNG to preserve transparency.
    const outType = file.type === "image/jpeg" ? "image/jpeg" : "image/png";
    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob(
            (b) => (b ? resolve(b) : reject(new Error("Avatar compression failed."))),
            outType,
            0.9
        );
    });

    const ext = outType === "image/jpeg" ? "jpg" : "png";
    const base = (file.name || "avatar").replace(/\.[^.]+$/, "");
    return new File([blob], `${base}.${ext}`, { type: outType });
}

function loadAvatarImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error("The selected file is not a valid image."));
        };
        img.src = url;
    });
}

export default {
    name: "UserProfile",
    data() {
        return {
            profile: null,
            loading: true,
            activeTab: "favorites",
            favorites: [],
            likes: [],
            comments: [],
            tabs: [
                { key: "favorites", label: "Favorites" },
                { key: "likes", label: "Likes" },
                { key: "comments", label: "Comments" },
            ],
            uploading: false,
            avatarMessage: "",
            avatarError: false,
            showPwdModal: false,
            pwd: { current: "", new: "", confirm: "" },
            pwdLoading: false,
            pwdMsg: "",
            pwdError: false,
        };
    },
    computed: {
        initials() {
            const name = (this.profile && this.profile.name) || "?";
            return name
                .split(/\s+/)
                .map((part) => part[0])
                .filter(Boolean)
                .slice(0, 2)
                .join("")
                .toUpperCase();
        },
        joinedAt() {
            return this.profile && this.profile.created_at
                ? formatDate(this.profile.created_at, "MMM D, YYYY")
                : "";
        },
        counts() {
            return {
                favorites: this.favorites.length,
                likes: this.likes.length,
                comments: this.comments.length,
            };
        },
    },
    async mounted() {
        await this.loadAll();
    },
    methods: {
        formatDate,
        async changePassword() {
            if (this.pwd.new !== this.pwd.confirm) {
                this.pwdError = true;
                this.pwdMsg = "New passwords do not match.";
                return;
            }
            this.pwdLoading = true;
            this.pwdMsg = "";
            try {
                await axios.post("/api/me/password", {
                    current_password: this.pwd.current,
                    password: this.pwd.new,
                    password_confirmation: this.pwd.confirm,
                });
                this.pwdError = false;
                this.pwdMsg = "Password updated.";
                this.pwd = { current: "", new: "", confirm: "" };
            } catch (e) {
                this.pwdError = true;
                this.pwdMsg = e.response?.data?.message || "Failed to update password.";
            }
            this.pwdLoading = false;
        },
        async loadAll() {
            this.loading = true;
            try {
                const profile = await api.getMyProfile();
                if (!profile) {
                    this.profile = null;
                    return;
                }
                this.profile = profile;
                const [favorites, likes, comments] = await Promise.all([
                    api.getMyFavorites(),
                    api.getMyLikes(),
                    api.getMyComments(),
                ]);
                this.favorites = favorites || [];
                this.likes = likes || [];
                this.comments = comments || [];
            } catch (e) {
                this.profile = null;
            } finally {
                this.loading = false;
            }
        },
        switchTab(key) {
            this.activeTab = key;
        },
        linkFor(item) {
            if (!item.published || !item.slug || !item.project_identifier) return null;
            const prefix = PATH_PREFIX[item.project_identifier];
            if (!prefix) return null;
            if (item.category_slug) {
                return `${prefix}/${item.category_slug}/${item.slug}`;
            }
            return `${prefix}`;
        },
        linkForComment(item) {
            if (!item.article_slug || !item.project_identifier) return null;
            const prefix = PATH_PREFIX[item.project_identifier];
            if (!prefix) return null;
            if (item.article_category_slug) {
                return `${prefix}/${item.article_category_slug}/${item.article_slug}`;
            }
            return `${prefix}`;
        },
        statusClass(status) {
            const map = {
                approved: "bg-green-100 text-green-700",
                pending: "bg-amber-100 text-amber-700",
                spam: "bg-gray-100 text-gray-600",
                trash: "bg-red-100 text-red-600",
            };
            return map[status] || "bg-gray-100 text-gray-600";
        },
        async onAvatarChange(event) {
            let file = event.target.files && event.target.files[0];
            if (!file) return;
            this.uploading = true;
            this.avatarMessage = "";
            this.avatarError = false;
            try {
                file = await prepareAvatarFile(file);
                const formData = new FormData();
                formData.append("avatar", file);
                const data = await api.updateAvatar(formData);
                if (data && data.avatar) {
                    this.profile.avatar = data.avatar;
                    this.avatarMessage = "Avatar updated.";
                } else {
                    this.avatarError = true;
                    this.avatarMessage = "Unable to update the avatar. Please use a JPEG, PNG, WebP or GIF image under 2 MB.";
                }
            } catch (e) {
                this.avatarError = true;
                this.avatarMessage = "Unable to upload the avatar. Please use a JPEG, PNG, WebP or GIF image under 2 MB.";
            } finally {
                this.uploading = false;
                if (event.target) event.target.value = "";
            }
        },
        async removeItem(kind, item) {
            try {
                if (kind === "favorite") {
                    await api.removeFavorite(item.project_identifier, item.content_id);
                    this.favorites = this.favorites.filter((f) => f.content_id !== item.content_id);
                } else {
                    await api.removeLike(item.project_identifier, item.content_id);
                    this.likes = this.likes.filter((l) => l.content_id !== item.content_id);
                }
                this.profile = await api.getMyProfile();
            } catch (e) {
                /* shared toast shows the failure */
            }
        },
    },
};
</script>
