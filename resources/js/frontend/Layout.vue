<template>
    <div class="flex min-h-screen flex-col bg-gradient-to-b from-slate-50 to-gray-100 text-gray-900">
        <!-- Site header -->
        <header class="sticky top-0 z-40 border-b border-gray-200/80 bg-white/80 backdrop-blur-lg shadow-sm">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-4 px-4 sm:gap-6 sm:px-6">
                <router-link to="/" class="flex shrink-0 items-center gap-2.5 font-bold text-gray-900 transition hover:opacity-80">
                    <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-md shadow-indigo-500/20">
                        <img src="/images/logo-32x32-dark.svg" alt="Logo" class="h-6 w-6" />
                    </div>
                    <span class="truncate text-lg tracking-tight">{{ siteName }}</span>
                </router-link>

                <nav v-show="navMeasured && !navOverflow" class="flex flex-1 items-center gap-0.5 overflow-x-auto text-sm scrollbar-none">
                    <router-link
                        to="/"
                        class="group relative whitespace-nowrap rounded-lg px-3.5 py-2 font-medium text-gray-600 transition hover:text-indigo-600"
                        :class="{ 'text-indigo-600': isActive('home') }"
                    >
                        <span class="relative z-10">Home</span>
                        <span
                            class="absolute inset-x-2 -bottom-0.5 h-0.5 rounded-full bg-indigo-600 transition-all duration-300"
                            :class="isActive('home') ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                        ></span>
                    </router-link>
                    <router-link
                        to="/content"
                        class="group relative whitespace-nowrap rounded-lg px-3.5 py-2 font-medium text-gray-600 transition hover:text-indigo-600"
                        :class="{ 'text-indigo-600': isActive('content') }"
                    >
                        <span class="relative z-10">Content</span>
                        <span
                            class="absolute inset-x-2 -bottom-0.5 h-0.5 rounded-full bg-indigo-600 transition-all duration-300"
                            :class="isActive('content') ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                        ></span>
                    </router-link>
                    <router-link
                        to="/directory"
                        class="group relative whitespace-nowrap rounded-lg px-3.5 py-2 font-medium text-gray-600 transition hover:text-indigo-600"
                        :class="{ 'text-indigo-600': isActive('directory') }"
                    >
                        <span class="relative z-10">Directory</span>
                        <span
                            class="absolute inset-x-2 -bottom-0.5 h-0.5 rounded-full bg-indigo-600 transition-all duration-300"
                            :class="isActive('directory') ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                        ></span>
                    </router-link>
                    <router-link
                        to="/note"
                        class="group relative whitespace-nowrap rounded-lg px-3.5 py-2 font-medium text-gray-600 transition hover:text-indigo-600"
                        :class="{ 'text-indigo-600': isActive('note') }"
                    >
                        <span class="relative z-10">Note</span>
                        <span
                            class="absolute inset-x-2 -bottom-0.5 h-0.5 rounded-full bg-indigo-600 transition-all duration-300"
                            :class="isActive('note') ? 'opacity-100' : 'opacity-0 group-hover:opacity-40'"
                        ></span>
                    </router-link>
                </nav>

                <!-- Hamburger menu (shown when nav overflows) -->
                <div v-show="navMeasured && navOverflow" class="relative shrink-0" ref="mobileNavWrap">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100 hover:text-indigo-600"
                        @click.stop="toggleMobileNav"
                    >
                        <svg v-if="!mobileNavOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <div
                        v-if="mobileNavOpen"
                        class="fixed left-16 top-16 z-[100] w-44 overflow-hidden rounded-xl border border-gray-100 bg-white py-1.5 shadow-xl shadow-gray-300/30"
                    >
                        <router-link to="/" class="block px-4 py-2.5 text-sm transition hover:bg-indigo-50" :class="isActive('home') ? 'font-semibold text-indigo-600' : 'text-gray-700'" @click="mobileNavOpen = false">Home</router-link>
                        <router-link to="/content" class="block px-4 py-2.5 text-sm transition hover:bg-indigo-50" :class="isActive('content') ? 'font-semibold text-indigo-600' : 'text-gray-700'" @click="mobileNavOpen = false">Content</router-link>
                        <router-link to="/directory" class="block px-4 py-2.5 text-sm transition hover:bg-indigo-50" :class="isActive('directory') ? 'font-semibold text-indigo-600' : 'text-gray-700'" @click="mobileNavOpen = false">Directory</router-link>
                        <router-link to="/note" class="block px-4 py-2.5 text-sm transition hover:bg-indigo-50" :class="isActive('note') ? 'font-semibold text-indigo-600' : 'text-gray-700'" @click="mobileNavOpen = false">Note</router-link>
                    </div>
                </div>

                <!-- Right side elements (fixed at right) -->
                <div class="ml-auto flex items-center gap-3">
                <!-- Global search -->
                <form
                    class="relative hidden shrink-0 sm:block"
                    @submit.prevent="submitSearch"
                >
                    <svg
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 transition group-focus-within:text-indigo-500"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search…"
                        class="w-44 rounded-full border border-gray-200 bg-gray-50/80 py-2 pl-9 pr-3 text-sm text-gray-700 placeholder-gray-400 transition focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 lg:w-60"
                    />
                </form>

                <!-- Language switcher (project locales) -->
                <div v-if="store.cmsProjectLocales.length > 1" class="relative shrink-0" ref="langWrap">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 transition hover:border-indigo-300 hover:shadow-sm"
                        @click.stop="toggleLang"
                    >
                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>
                        </svg>
                        <span>{{ labelOf(store.locale) }}</span>
                        <svg class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="langOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div
                            v-if="langOpen"
                            class="fixed z-[100] w-44 overflow-hidden rounded-xl border border-gray-100 bg-white py-1.5 shadow-xl shadow-gray-300/30"
                            :style="langMenuStyle"
                        >
                            <button
                                v-for="l in store.cmsProjectLocales"
                                :key="l"
                                type="button"
                                class="flex w-full items-center justify-between px-3.5 py-2 text-left text-sm transition hover:bg-indigo-50"
                                :class="l === store.locale ? 'font-semibold text-indigo-600' : 'text-gray-700'"
                                @click="switchLocale(l)"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="text-base">{{ localeFlag(l) }}</span>
                                    {{ labelOf(l) }}
                                </span>
                                <svg v-if="l === store.locale" class="h-4 w-4 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    </transition>
                </div>

                <!-- User menu -->
                <div v-if="store.user" class="relative shrink-0" ref="userWrap">
                    <button
                        type="button"
                        class="flex max-w-[180px] items-center gap-2 rounded-full border border-gray-200 bg-white py-1.5 pl-1.5 pr-3 text-sm text-gray-700 transition hover:border-indigo-300 hover:shadow-sm"
                        @click.stop="toggleUser"
                    >
                        <img
                            v-if="store.user.avatar"
                            :src="store.user.avatar"
                            alt="Avatar"
                            class="h-7 w-7 shrink-0 rounded-full object-cover ring-2 ring-indigo-100"
                        />
                        <span
                            v-else
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-[11px] font-bold text-white"
                        >
                            {{ initials() }}
                        </span>
                        <span class="truncate font-medium">{{ store.user.name }}</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200" :class="userOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div
                            v-if="userOpen"
                            class="absolute right-0 z-[100] mt-2 w-48 overflow-hidden rounded-xl border border-gray-100 bg-white py-1.5 shadow-xl shadow-gray-300/30"
                        >
                            <router-link
                                to="/profile"
                                class="flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-600"
                                @click="userOpen = false"
                            >
                                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                My Profile
                            </router-link>
                            <div class="my-1 border-t border-gray-100"></div>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50"
                                @click="logout"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Log out
                            </button>
                        </div>
                    </transition>
                </div>
                <a
                    v-else
                    href="/login"
                    class="shrink-0 rounded-full bg-gradient-to-r from-indigo-600 to-indigo-500 px-5 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:shadow-lg hover:shadow-indigo-500/30 hover:brightness-110"
                >
                    Log in
                </a>
            </div>
                </div>
        </header>

        <!-- Page content (remounts when the language changes so views re-fetch) -->
        <main class="flex-1">
            <!-- Lightweight loading indicator while the router guard resolves
                 settings + projects. The page views render their own skeleton
                 once mounted, so this must not look like a page skeleton. -->
            <div
                v-if="!store.localeReady"
                class="flex items-center justify-center py-24"
            >
                <div class="relative h-10 w-10">
                    <div class="absolute inset-0 rounded-full border-2 border-gray-200"></div>
                    <div class="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-t-indigo-500"></div>
                </div>
            </div>
            <router-view v-else :key="store.locale || 'default'"></router-view>
        </main>

        <!-- Site footer -->
        <footer class="border-t border-gray-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <div class="flex items-center gap-2.5 text-sm text-gray-500">
                        <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600">
                            <img src="/images/logo-32x32-dark.svg" alt="Logo" class="h-5 w-5" />
                        </div>
                        <span class="font-semibold text-gray-900">{{ siteName }}</span>
                        <span v-if="siteDescription" class="text-gray-400">— {{ siteDescription }}</span>
                    </div>
                    <div class="text-sm text-gray-400">
                        © {{ year }} {{ siteName }} · Powered by Aine
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<script>
import { useFrontendStore } from "./store";
import http from "./http";

export default {
    name: "FrontendLayout",
    data() {
        return {
            year: new Date().getFullYear(),
            langOpen: false,
            userOpen: false,
            mobileNavOpen: false,
            navOverflow: true,
            navItemsWidth: 0,
            navMeasured: false,
            langMenuStyle: { left: "0px", top: "0px" },
            searchQuery: "",
        };
    },
    computed: {
        store() {
            return useFrontendStore();
        },
        siteName() {
            return this.store.settings.name || "Aine";
        },
        siteDescription() {
            return this.store.settings.description || "";
        },
    },
    async mounted() {
        document.addEventListener("click", this.onDocumentClick);
        window.addEventListener("resize", this.onResize);
        window.addEventListener("scroll", this.onLangViewportChange, true);
        this.store.loadMe();
        this.$nextTick(() => {
            this.measureNavWidth();
            this.checkNavOverflow();
        });
        setTimeout(() => {
            this.measureNavWidth();
            this.checkNavOverflow();
        }, 300);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => {
                this.measureNavWidth();
                this.checkNavOverflow();
            });
        }
    },
    beforeUnmount() {
        document.removeEventListener("click", this.onDocumentClick);
        window.removeEventListener("resize", this.onResize);
        window.removeEventListener("scroll", this.onLangViewportChange, true);
    },
    methods: {
        measureNavWidth() {
            const nav = this.$el.querySelector('nav');
            if (!nav) return;
            // Temporarily show nav to measure (it's hidden by v-show during initial check)
            const wasDisplay = nav.style.display;
            nav.style.display = 'flex';
            const items = nav.querySelectorAll('a');
            let totalWidth = 0;
            items.forEach(item => {
                totalWidth += item.offsetWidth + 4;
            });
            nav.style.display = wasDisplay;
            this.navItemsWidth = totalWidth;
        },
        checkNavOverflow() {
            const nav = this.$el.querySelector('nav');
            const header = document.querySelector('header > div');
            if (!nav || !header) return;

            // Temporarily show nav to measure (it may be hidden)
            const wasDisplay = nav.style.display;
            nav.style.display = 'flex';

            // Measure nav items total width
            const items = nav.querySelectorAll('a');
            let navWidth = 0;
            items.forEach(item => {
                navWidth += item.offsetWidth + 4;
            });

            // Measure other elements width
            const otherElements = header.querySelectorAll(':scope > *:not(nav)');
            let otherWidth = 0;
            otherElements.forEach(el => {
                // Skip hamburger button wrapper
                if (el === this.$refs.mobileNavWrap) return;
                if (el.offsetWidth > 0 && el.offsetParent !== null) {
                    otherWidth += el.offsetWidth + 16;
                }
            });

            // Restore nav display
            nav.style.display = wasDisplay;

            const availableWidth = header.clientWidth - otherWidth - 32;
            this.navOverflow = navWidth > availableWidth + 2;
            this.navMeasured = true;
        },
        toggleMobileNav() {
            this.mobileNavOpen = !this.mobileNavOpen;
            if (this.mobileNavOpen) {
                this.langOpen = false;
                this.userOpen = false;
            }
        },
        submitSearch() {
            const q = (this.searchQuery || "").trim();
            if (q.length < 2) {
                return;
            }
            if (this.$route.name === "search" && this.$route.query.q === q) {
                return;
            }
            this.$router.push({ path: "/search", query: { q } });
        },
        isActive(name) {
            if (name === "home") return this.$route.name === "home";
            if (name === "content") return this.$route.name === "content.index";
            if (name === "directory") return this.$route.name === "directory.index";
            if (name === "note") return this.$route.name === "note.index";
            return false;
        },
        initials() {
            const user = this.store.user;
            if (!user || !user.name) return "";
            return user.name
                .split(/\s+/)
                .map((part) => part[0])
                .filter(Boolean)
                .slice(0, 2)
                .join("")
                .toUpperCase();
        },
        async logout() {
            this.userOpen = false;
            try {
                await http.post("/logout");
            } catch (e) {
                /* even if the CSRF call fails we still end the session locally */
            }
            this.store.setUser(null);
            window.location.href = "/";
        },
        labelOf(l) {
            if (l === "en") return "English";
            if (l === "zh") return "中文";
            return l.toUpperCase();
        },
        localeFlag(l) {
            if (l === "en") return "🇬🇧";
            if (l === "zh") return "🇨🇳";
            return "🌐";
        },
        toggleUser() {
            this.userOpen = !this.userOpen;
            if (this.userOpen) {
                this.langOpen = false;
            }
        },
        toggleLang() {
            this.langOpen = !this.langOpen;
            if (this.langOpen) {
                this.userOpen = false;
                this.updateLangPosition();
            }
        },
        switchLocale(l) {
            this.langOpen = false;
            if (l !== this.store.locale) {
                this.store.setLocale(l);
            }
        },
        updateLangPosition() {
            const wrap = this.$refs.langWrap;
            if (!wrap) return;

            const rect = wrap.getBoundingClientRect();
            const menuWidth = 176;
            const menuHeight = Math.min(this.store.cmsProjectLocales.length * 36 + 16, 320);
            const vw = window.innerWidth || document.documentElement.clientWidth;
            const vh = window.innerHeight || document.documentElement.clientHeight;
            const margin = 8;
            const gap = 6;

            let left;
            if (rect.left + menuWidth <= vw - margin) {
                left = rect.left;
            } else {
                left = Math.max(margin, rect.right - menuWidth);
            }

            let top;
            if (rect.bottom + menuHeight <= vh - margin) {
                top = rect.bottom + gap;
            } else {
                top = Math.max(margin, rect.top - menuHeight - gap);
            }

            this.langMenuStyle = { left: left + "px", top: top + "px" };
        },
        onDocumentClick(event) {
            const wrap = this.$refs.langWrap;
            if (wrap && !wrap.contains(event.target)) {
                this.langOpen = false;
            }
            const userWrap = this.$refs.userWrap;
            if (userWrap && !userWrap.contains(event.target)) {
                this.userOpen = false;
            }
            const navWrap = this.$refs.mobileNavWrap;
            if (navWrap && !navWrap.contains(event.target)) {
                this.mobileNavOpen = false;
            }
        },
        onResize() {
            this.checkNavOverflow();
            this.onLangViewportChange();
        },
        onLangViewportChange() {
            if (this.langOpen) {
                this.updateLangPosition();
            }
        },
    },
};
</script>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>
