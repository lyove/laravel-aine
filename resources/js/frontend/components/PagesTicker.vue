<template>
    <div
        v-if="pages.length"
        class="group flex items-center gap-3"
        @mouseenter="stop"
        @mouseleave="start"
    >
        <div class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-gray-400">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="hidden sm:inline">Pages</span>
        </div>
        <!-- Vertical ticker: exactly one title visible at a time -->
        <div class="relative flex-1 overflow-hidden" style="height: 36px">
            <div
                class="flex flex-col transition-transform duration-500 ease-in-out"
                :style="{ transform: `translateY(-${index * 36}px)` }"
            >
                <router-link
                    v-for="page in pages"
                    :key="page.id"
                    :to="`${pathPrefix}/${page.slug}`"
                    class="flex h-9 items-center truncate text-sm text-gray-600 transition hover:text-indigo-600"
                >
                    {{ page.title }}
                </router-link>
            </div>
        </div>

        <!-- Prev / Next on the far right -->
        <div class="flex shrink-0 items-center gap-1">
            <button
                type="button"
                aria-label="Previous page"
                class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600"
                @click="step(-1)"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <button
                type="button"
                aria-label="Next page"
                class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600"
                @click="step(1)"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
const ROW_HEIGHT = 36; // must match h-9

export default {
    name: "PagesTicker",
    props: {
        pages: {
            type: Array,
            default: () => [],
        },
        pathPrefix: {
            type: String,
            default: "/content",
        },
    },
    data() {
        return {
            index: 0,
            timer: null,
        };
    },
    mounted() {
        this.start();
    },
    beforeUnmount() {
        this.stop();
    },
    methods: {
        step(dir) {
            const len = this.pages.length;
            if (!len) return;
            this.index = (this.index + dir + len) % len;
        },
        start() {
            if (this.timer || this.pages.length <= 1) return;
            this.timer = setInterval(() => this.step(1), 3000);
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
    },
};
</script>
