<template>
    <div v-if="slides.length" class="relative overflow-hidden rounded-2xl shadow-xl shadow-gray-300/30" style="aspect-ratio: 21 / 9;" @mouseenter="stop" @mouseleave="start">
        <div
            class="flex h-full transition-transform duration-700 ease-[cubic-bezier(0.25,0.46,0.45,0.94)]"
            :style="{ transform: `translateX(-${current * 100}%)` }"
        >
            <div v-for="slide in slides" :key="slide.id" class="relative h-full w-full shrink-0">
                <div
                    v-if="slide['featured-image'] && slide['featured-image'].full_url"
                    class="absolute inset-0 bg-cover bg-center"
                    :style="{ backgroundImage: `url('${slide['featured-image'].full_url}')` }"
                ></div>
                <div
                    v-else
                    class="absolute inset-0 bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800"
                ></div>
                <!-- Gradient overlay -->
                <div
                    class="absolute inset-0"
                    style="background: linear-gradient(135deg, rgba(0,0,0,0.75) 0%, rgba(0,0,0,0.3) 35%, rgba(0,0,0,0.05) 65%, transparent 100%);"
                ></div>

                <!-- Slide content -->
                <div class="absolute inset-0 z-10 flex items-end p-6 sm:p-10 lg:p-12">
                    <div class="max-w-xl text-white">
                        <router-link
                            v-if="slide.category"
                            :to="`${pathPrefix}/category/${slide.category.slug}`"
                            class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-widest backdrop-blur-sm transition hover:bg-white/20"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                            {{ slide.category.title }}
                        </router-link>
                        <h2 class="mb-3 text-2xl font-extrabold leading-tight drop-shadow-lg sm:text-3xl lg:text-4xl">
                            <router-link :to="slideLink(slide, pathPrefix)" class="transition hover:opacity-90">
                                {{ slide.title }}
                            </router-link>
                        </h2>
                        <p v-if="slide.excerpt" class="mb-5 line-clamp-2 text-sm text-white/80 sm:text-base">
                            {{ slide.excerpt }}
                        </p>
                        <router-link
                            :to="slideLink(slide, pathPrefix)"
                            class="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/30 transition hover:bg-indigo-500 hover:shadow-indigo-500/40"
                        >
                            Read more
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="slides.length > 1">
            <!-- Nav arrows -->
            <button
                type="button"
                aria-label="Previous slide"
                class="group absolute left-4 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition hover:bg-white/30"
                @click="prev"
            >
                <svg class="h-5 w-5 transition group-hover:scale-110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <button
                type="button"
                aria-label="Next slide"
                class="group absolute right-4 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition hover:bg-white/30"
                @click="next"
            >
                <svg class="h-5 w-5 transition group-hover:scale-110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <!-- Dots -->
            <div class="absolute bottom-5 right-6 z-20 flex gap-2">
                <button
                    v-for="(slide, index) in slides"
                    :key="index"
                    type="button"
                    :aria-label="`Go to slide ${index + 1}`"
                    class="h-2 rounded-full transition-all duration-300"
                    :class="index === current ? 'w-7 bg-white shadow-md' : 'w-2 bg-white/40 hover:bg-white/60'"
                    @click="go(index)"
                ></button>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    name: "BannerSlider",
    props: {
        slides: {
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
            current: 0,
            timer: null,
        };
    },
    methods: {
        slideLink(slide, pathPrefix) {
            const category = slide.category ? slide.category.slug : "articles";
            const url = slide.slug || slide.id;
            return `${pathPrefix || ''}/${category}/${url}`;
        },
        go(index) {
            this.current = (index + this.slides.length) % this.slides.length;
        },
        next() {
            this.go(this.current + 1);
        },
        prev() {
            this.go(this.current - 1);
        },
        start() {
            if (this.timer || this.slides.length <= 1) return;
            this.timer = setInterval(this.next, 5000);
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
    },
    mounted() {
        this.start();
    },
    beforeUnmount() {
        this.stop();
    },
};
</script>
