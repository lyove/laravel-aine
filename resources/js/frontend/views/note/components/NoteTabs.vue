<template>
    <div class="mb-8 flex items-center gap-1 border-b border-gray-200">
        <router-link
            :to="{ name: 'note.index' }"
            class="group -mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-semibold transition"
            :class="active === 'posts'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-900'"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Posts
        </router-link>
        <router-link
            :to="{ name: 'note.pages' }"
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
    name: "NoteTabs",
    props: {
        active: {
            type: String,
            default: "posts", // "posts" | "pages"
        },
    },
};
</script>
