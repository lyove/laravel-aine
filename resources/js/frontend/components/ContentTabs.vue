<template>
    <div class="mb-8 flex items-center gap-1 border-b border-gray-200">
        <router-link
            :to="{ name: 'content.index' }"
            class="group -mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-semibold transition"
            :class="active === 'articles'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-900'"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Articles
        </router-link>
        <router-link
            :to="{ name: 'content.pages' }"
            class="group -mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-semibold transition"
            :class="active === 'pages'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-900'"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Pages
        </router-link>
    </div>
</template>

<script>
export default {
    name: "ContentTabs",
    props: {
        active: {
            type: String,
            default: "articles", // "articles" | "pages"
        },
    },
};
</script>
