<template>
    <div class="admin__project-header w-full border-b bg-white an__sticky top-0 z-1">
        <div v-if="isReadonly" class="bg-amber-50 border-b border-amber-200 px-4 py-2 flex items-center">
            <i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>
            <span class="text-sm text-amber-800 font-medium">
                {{ __('This project is inactive and in read-only mode. Content can be viewed but not modified.') }}
            </span>
        </div>
        <div class="px-4 py-2">
            <div class="text-xl font-bold">
                {{ __('Project:') }} {{ project.name }}
                <span v-if="isReadonly" class="text-xs font-semibold uppercase tracking-wide text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full ml-2">
                    {{ __('Read Only') }}
                </span>
            </div>
            <div class="text-sm">{{ project.description || __('Project description') }}</div>
        </div>
    </div>
</template>

<script>

export default {
    props: ["project"],
    computed: {
        isReadonly() {
            return this.project && (this.project.is_readonly || !this.project.status);
        },
    },
};
</script>
