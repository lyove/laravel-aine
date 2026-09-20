<template>
    <section v-if="sections.length" class="flex h-full flex-col" :class="showTitle ? 'py-10' : ''">
        <!-- Section title (hidden when embedded under an outer heading) -->
        <h2 v-if="showTitle" class="mb-6 flex items-center gap-3 text-2xl font-bold tracking-tight text-gray-900">
            <span class="inline-block h-6 w-1.5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
            {{ title }}
        </h2>

        <!-- Category tabs -->
        <div class="mb-5 flex items-center border-b border-gray-200">
            <div class="flex flex-1 items-center gap-1 overflow-x-auto scrollbar-none">
                <button
                    v-for="section in sections"
                    :key="section.category.id"
                    type="button"
                    class="-mb-px flex-none whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                    :class="activeId === section.category.id
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-900'"
                    @click="activeId = section.category.id"
                >
                    {{ section.category.title }}
                </button>
            </div>
            <router-link
                :to="`${pathPrefix}/category/${activeSection.category.slug}`"
                class="ml-auto flex-none inline-flex items-center gap-1 whitespace-nowrap pl-3 text-sm font-medium text-indigo-600 transition hover:gap-1.5 hover:opacity-70"
            >
                More
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </router-link>
        </div>

        <!-- Active tab: tags (left) + More (right), both follow the tab -->
        <div v-if="activeSection.tags.length" class="mb-6 flex flex-wrap items-center gap-2">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <router-link
                    v-for="tag in activeSection.tags"
                    :key="tag.id"
                    :to="tagLink(tag)"
                    class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600"
                >
                    #{{ tag.tag }}
                </router-link>
            </div>
            <span class="flex-1"></span>
        </div>

        <!-- Cards of the active category -->
        <p v-if="!activeSection.items.length" class="py-12 text-center text-sm text-gray-400">
            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            No published items in this category yet.
        </p>
        <div v-else class="flex-1 auto-rows-fr" :class="gridClass">
            <template v-for="item in activeSection.items" :key="item.id">
                <slot name="card" :item="item"></slot>
            </template>
        </div>
    </section>
</template>

<script>
export default {
    name: "CategoryTabsSection",
    props: {
        title: {
            type: String,
            required: true,
        },
        pathPrefix: {
            type: String,
            required: true,
        },
        showTitle: {
            type: Boolean,
            default: true,
        },
        gridClass: {
            type: String,
            default: "grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4",
        },
        sections: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            activeId: null,
        };
    },
    computed: {
        activeSection() {
            if (!this.sections.length) {
                return { 
                    category: {}, 
                    items: [], 
                    tags: [] 
                };
            }
            const found = this.sections.find((s) => s.category.id === this.activeId);
            return found || this.sections[0];
        },
    },
    watch: {
        sections(list) {
            if (list.length && !list.some((s) => s.category.id === this.activeId)) {
                this.activeId = list[0].category.id;
            }
        },
    },
    mounted() {
        if (this.sections.length) {
            this.activeId = this.sections[0].category.id;
        }
    },
    methods: {
        tagLink(tag) {
            const slug = (tag.tag || "").toLowerCase().replace(/\s+/g, "-");
            return `${this.pathPrefix}/tag/${slug}`;
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
