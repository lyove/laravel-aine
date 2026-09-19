<template>
    <!-- Full-page skeleton while the home data is loading -->
    <template v-if="loading">
        <!-- Banner slider placeholder -->
        <section v-if="showSliderSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6">
            <div class="pt-8">
                <div class="aspect-[21/9] animate-pulse rounded-2xl bg-gray-100"></div>
            </div>
        </section>

        <!-- Content area -->
        <section v-if="showCmsSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6">
            <div class="pt-10">
                <div class="mb-7 flex items-center gap-4">
                    <div class="h-8 w-28 shrink-0 animate-pulse rounded bg-gray-200"></div>
                    <div class="h-4 min-w-0 flex-1 animate-pulse rounded bg-gray-200"></div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs + cards -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 border-b border-gray-200">
                            <div class="mb-2.5 flex items-center gap-2">
                                <div v-for="n in 3" :key="n" class="h-7 w-16 animate-pulse rounded bg-gray-200"></div>
                            </div>
                        </div>
                        <div class="mb-6 flex flex-wrap items-center gap-2">
                            <div v-for="n in 3" :key="n" class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2">
                            <div v-for="n in 4" :key="n" class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                <div class="aspect-[16/9] animate-pulse bg-gray-100"></div>
                                <div class="flex flex-1 flex-col p-5">
                                    <div class="mb-3 h-3 w-24 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-3 h-5 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-2 h-4 w-full animate-pulse rounded bg-gray-100"></div>
                                    <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto pt-4">
                                        <div class="h-4 w-16 animate-pulse rounded bg-gray-200"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Featured | Recommended tabs -->
                    <div class="lg:col-span-1">
                        <div class="mb-7 flex items-center border-b border-gray-200">
                            <div class="h-7 w-20 animate-pulse rounded bg-gray-200"></div>
                            <div class="ml-auto h-7 w-12 animate-pulse rounded bg-gray-200"></div>
                        </div>
                        <div class="space-y-3">
                            <div v-for="n in 3" :key="n" class="flex gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                <div class="h-20 w-24 shrink-0 animate-pulse rounded-lg bg-gray-100"></div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="h-4 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="h-3 w-1/2 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto h-3 w-1/3 animate-pulse rounded bg-gray-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Directory area -->
        <section v-if="showDirectorySection" class="mx-auto w-full max-w-7xl px-4 sm:px-6 pb-16">
            <div class="pt-10">
                <div class="mb-7 h-8 w-36 animate-pulse rounded bg-gray-200"></div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs + cards -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 border-b border-gray-200">
                            <div class="mb-2.5 flex items-center gap-2">
                                <div v-for="n in 3" :key="n" class="h-7 w-16 animate-pulse rounded bg-gray-200"></div>
                            </div>
                        </div>
                        <div class="mb-6 flex flex-wrap items-center gap-2">
                            <div v-for="n in 3" :key="n" class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2">
                            <div v-for="n in 4" :key="n" class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                <div class="aspect-[16/9] animate-pulse bg-gray-100"></div>
                                <div class="flex flex-1 flex-col p-5">
                                    <div class="mb-3 h-3 w-24 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-3 h-5 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-2 h-4 w-full animate-pulse rounded bg-gray-100"></div>
                                    <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto pt-4">
                                        <div class="h-4 w-16 animate-pulse rounded bg-gray-200"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: featured listings -->
                    <div class="lg:col-span-1">
                        <div class="mb-4 flex items-center border-b border-gray-200">
                            <div class="h-7 w-20 animate-pulse rounded bg-gray-200"></div>
                            <div class="ml-auto h-7 w-12 animate-pulse rounded bg-gray-200"></div>
                        </div>
                        <div class="space-y-3">
                            <div v-for="n in 3" :key="n" class="flex gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                <div class="h-20 w-24 shrink-0 animate-pulse rounded-lg bg-gray-100"></div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="h-4 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="h-3 w-1/2 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto h-3 w-1/3 animate-pulse rounded bg-gray-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Note area -->
        <section v-if="showNoteSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6 pb-16">
            <div class="pt-10">
                <div class="mb-7 flex items-center gap-4">
                    <div class="h-8 w-24 shrink-0 animate-pulse rounded bg-gray-200"></div>
                    <div class="h-4 min-w-0 flex-1 animate-pulse rounded bg-gray-200"></div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs + cards -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 border-b border-gray-200">
                            <div class="mb-2.5 flex items-center gap-2">
                                <div v-for="n in 3" :key="n" class="h-7 w-16 animate-pulse rounded bg-gray-200"></div>
                            </div>
                        </div>
                        <div class="mb-6 flex flex-wrap items-center gap-2">
                            <div v-for="n in 3" :key="n" class="h-5 w-16 animate-pulse rounded-full bg-gray-200"></div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2">
                            <div v-for="n in 4" :key="n" class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                <div class="aspect-[16/9] animate-pulse bg-gray-100"></div>
                                <div class="flex flex-1 flex-col p-5">
                                    <div class="mb-3 h-3 w-24 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-3 h-5 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="mb-2 h-4 w-full animate-pulse rounded bg-gray-100"></div>
                                    <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto pt-4">
                                        <div class="h-4 w-16 animate-pulse rounded bg-gray-200"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Featured | Recommended tabs -->
                    <div class="lg:col-span-1">
                        <div class="mb-7 flex items-center border-b border-gray-200">
                            <div class="h-7 w-20 animate-pulse rounded bg-gray-200"></div>
                            <div class="ml-auto h-7 w-12 animate-pulse rounded bg-gray-200"></div>
                        </div>
                        <div class="space-y-3">
                            <div v-for="n in 3" :key="n" class="flex gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                <div class="h-20 w-24 shrink-0 animate-pulse rounded-lg bg-gray-100"></div>
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="h-4 w-4/5 animate-pulse rounded bg-gray-200"></div>
                                    <div class="h-3 w-1/2 animate-pulse rounded bg-gray-100"></div>
                                    <div class="mt-auto h-3 w-1/3 animate-pulse rounded bg-gray-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </template>

    <!-- Real content once the data is loaded -->
    <template v-else>
        <!-- Banner slider (CMS project slides) -->
        <section v-if="showSliderSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6">
            <div class="pt-8">
                <banner-slider :slides="cmsSlider" path-prefix="/content" />
            </div>
        </section>

        <!-- Content: full-width heading, then two columns
                (left: category tabs content / right: Featured | Recommended) -->
        <section v-if="showCmsSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6">
            <div class="pt-10">
                <div class="mb-7 flex items-center gap-4">
                    <h2 class="flex shrink-0 items-center gap-3 text-2xl font-bold tracking-tight text-gray-900">
                        <span class="inline-block h-6 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
                        Content
                    </h2>
                    <pages-ticker :pages="cmsPages" path-prefix="/content" class="min-w-0 flex-1" />
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs (the content) -->
                    <div class="lg:col-span-2">
                        <category-tabs-section
                            :show-title="false"
                            path-prefix="/content"
                            :sections="cmsSections"
                            grid-class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2"
                        >
                            <template #card="{ item }">
                                <article-card :item="item" path-prefix="/content" />
                            </template>
                        </category-tabs-section>
                    </div>

                    <!-- Right: Featured | Recommended tabs -->
                    <div class="lg:col-span-1">
                        <featured-sidebar
                            :featured="featuredArticles"
                            :recommended="recommendedArticles"
                            path-prefix="/content"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Directory: full-width heading, then two columns
                (left: category tabs / right: featured listings) -->
        <section v-if="showDirectorySection" class="mx-auto w-full max-w-7xl px-4 sm:px-6 pb-16">
            <div class="pt-10">
                <h2 class="mb-7 flex items-center gap-3 text-2xl font-bold tracking-tight text-gray-900">
                    <span class="inline-block h-6 w-1.5 rounded-full bg-gradient-to-b from-emerald-500 to-teal-500"></span>
                    Directory
                </h2>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs (the listings) -->
                    <div class="lg:col-span-2">
                        <category-tabs-section
                            :show-title="false"
                            path-prefix="/directory"
                            :sections="directorySections"
                            grid-class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2"
                        >
                            <template #card="{ item }">
                                <listing-card :item="item" path-prefix="/directory" />
                            </template>
                        </category-tabs-section>
                    </div>

                    <!-- Right: featured listings (no tabs — single dimension) -->
                    <div class="lg:col-span-1">
                        <featured-listings-sidebar
                            :items="featuredListings"
                            path-prefix="/directory"
                            more-link="/directory/featured"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Note: full-width heading, then two columns
                (left: category tabs / right: Featured | Recommended) -->
        <section v-if="showNoteSection" class="mx-auto w-full max-w-7xl px-4 sm:px-6 pb-16">
            <div class="pt-10">
                <div class="mb-7 flex items-center gap-4">
                    <h2 class="flex shrink-0 items-center gap-3 text-2xl font-bold tracking-tight text-gray-900">
                        <span class="inline-block h-6 w-1.5 rounded-full bg-gradient-to-b from-amber-500 to-orange-500"></span>
                        Note
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Left: category tabs (the posts) -->
                    <div class="lg:col-span-2">
                        <category-tabs-section
                            :show-title="false"
                            path-prefix="/note"
                            :sections="noteSections"
                            grid-class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-2"
                        >
                            <template #card="{ item }">
                                <article-card :item="item" path-prefix="/note" />
                            </template>
                        </category-tabs-section>
                    </div>

                    <!-- Right: Featured | Recommended tabs -->
                    <div class="lg:col-span-1">
                        <featured-sidebar
                            :featured="noteFeaturedPosts"
                            :recommended="noteRecommendedPosts"
                            path-prefix="/note"
                            more-prefix="/note"
                        />
                    </div>
                </div>
            </div>
        </section>
    </template>
</template>

<script>
import { api } from "../api";
import { PROJECTS, COLLECTIONS, DEFAULT_PORTAL } from "../config";
import CategoryTabsSection from "../components/CategoryTabsSection.vue";
import FeaturedSidebar from "../components/FeaturedSidebar.vue";
import FeaturedListingsSidebar from "../components/FeaturedListingsSidebar.vue";
import PagesTicker from "../components/PagesTicker.vue";
import BannerSlider from "../components/BannerSlider.vue";
import ArticleCard from "../components/ArticleCard.vue";
import ListingCard from "../components/ListingCard.vue";

export default {
    name: "Home",
    components: {
        CategoryTabsSection,
        FeaturedSidebar,
        FeaturedListingsSidebar,
        PagesTicker,
        BannerSlider,
        ArticleCard,
        ListingCard,
    },
    data() {
        return {
            cms: PROJECTS.cms,
            directory: PROJECTS.directory,
            note: PROJECTS.note,
            loading: true,
            featuredArticles: [],
            recommendedArticles: [],
            featuredListings: [],
            cmsSlider: [],
            cmsPages: [],
            cmsSections: [],
            directorySections: [],
            noteFeaturedPosts: [],
            noteRecommendedPosts: [],
            notePages: [],
            noteSections: [],
        };
    },
    computed: {
        showCmsSection() {
            return this.hasAnyBlock(this.cms, ["categoryTabs", "featured", "recommended", "pages"]);
        },
        showDirectorySection() {
            return this.hasAnyBlock(this.directory, ["categoryTabs", "featured"]);
        },
        showNoteSection() {
            return this.hasAnyBlock(this.note, ["categoryTabs", "featured", "recommended", "pages"]);
        },
        showSliderSection() {
            return this.hasBlock(this.cms, "slider");
        },
    },
    async mounted() {
        try {
            const cms = this.cms;
            const directory = this.directory;
            const note = this.note;

            const [cmsPortal, directoryPortal, notePortal] = await Promise.all([
                api.getCmsPortal({ collection: cms.contentCollection }).catch(() => null),
                api.getDirectoryPortal({ collection: directory.contentCollection }).catch(() => null),
                api.getNotePortal({ collection: note.contentCollection }).catch(() => null),
            ]);

            const cmsData = this.mapSections(cms, cmsPortal, true);
            this.featuredArticles = cmsData.featured;
            this.recommendedArticles = cmsData.recommended;
            this.cmsSlider = cmsData.slider;
            this.cmsPages = cmsData.pages;
            this.cmsSections = cmsData.sections;

            const directoryData = this.mapSections(directory, directoryPortal, false);
            this.featuredListings = directoryData.featured;
            this.directorySections = directoryData.sections;

            const noteData = this.mapSections(note, notePortal, true);
            this.noteFeaturedPosts = noteData.featured;
            this.noteRecommendedPosts = noteData.recommended;
            this.notePages = noteData.pages;
            this.noteSections = noteData.sections;
        } catch (error) {
            console.error("Failed to load home data:", error);
        } finally {
            this.loading = false;
        }
    },
    methods: {
        /**
         * Map the portal /portal skeleton onto the home page state.
         * Keeps the original fallback rules (featured/recommended/slider fall
         * back to latest when empty) and prunes the blocks the project does
         * not declare in its portal config.
         */
        mapSections(project, portal, fallbackToLatest = false) {
            if (!portal) {
                return { featured: [], recommended: [], slider: [], pages: [], sections: [] };
            }
            const blocks = project.portal || DEFAULT_PORTAL;

            const featuredRaw = Array.isArray(portal.featured) ? portal.featured : [];
            const recommendedRaw = Array.isArray(portal.recommended) ? portal.recommended : [];
            const sliderRaw = Array.isArray(portal.slider) ? portal.slider : [];
            const latest = Array.isArray(portal.latest) ? portal.latest : [];

            const featured = fallbackToLatest && !featuredRaw.length ? latest : featuredRaw;
            const recommended = fallbackToLatest && !recommendedRaw.length ? latest : recommendedRaw;
            const withImage = latest.filter((a) => a["featured-image"] && a["featured-image"].full_url);
            const slider =
                fallbackToLatest && !sliderRaw.length
                    ? (withImage.length ? withImage : latest).slice(0, 5)
                    : sliderRaw.slice(0, 5);

            return {
                featured: blocks.includes("featured") ? featured : [],
                recommended: blocks.includes("recommended") ? recommended : [],
                slider: blocks.includes("slider") ? slider : [],
                pages: blocks.includes("pages") && Array.isArray(portal.pages) ? portal.pages : [],
                sections: blocks.includes("categoryTabs") && Array.isArray(portal.categories) ? portal.categories : [],
            };
        },

        hasBlock(project, block) {
            return (project.portal || DEFAULT_PORTAL).includes(block);
        },

        hasAnyBlock(project, blocks) {
            return blocks.some((b) => this.hasBlock(project, b));
        },
    },
};
</script>
