<template>
    <div class="mb-6 flex items-center gap-1 border-b border-gray-200">
        <router-link
            :to="{ name: 'note.index' }"
            class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition"
            :class="active === 'posts'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-900'"
        >
            Posts
        </router-link>
        <router-link
            :to="{ name: 'note.pages' }"
            class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition"
            :class="active === 'pages'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-900'"
        >
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
