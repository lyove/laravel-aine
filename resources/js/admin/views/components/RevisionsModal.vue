<template>
    <ui-modal :show="show" max-width="6xl" @close="$emit('close')">
        <template #title>
            <div class="flex items-center justify-between w-full">
                <span><i class="fa fa-history mr-2 text-indigo-500"></i>{{ __('Content History') }}</span>
                <span v-if="compareMode" class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded">
                    {{ __('Comparing') }}: #{{ compareFrom?.id }} → #{{ compareTo?.id }}
                </span>
            </div>
        </template>

        <template #content>
            <div v-if="loading" class="py-12 text-center text-gray-400">
                <i class="fa fa-spinner fa-spin text-2xl"></i>
                <p class="mt-2 text-sm">{{ __('Loading revisions...') }}</p>
            </div>

            <div v-else-if="revisions.length === 0" class="py-12 text-center text-gray-400">
                <i class="fa fa-clock-o text-3xl"></i>
                <p class="mt-2 text-sm">{{ __('No revisions found for this content.') }}</p>
            </div>

            <div v-else class="flex gap-4" style="min-height: 420px;">
                <!-- ── Left: revision list ─────────────────────────── -->
                <div class="w-72 flex-shrink-0 border-r border-gray-100 pr-3 overflow-y-auto max-h-[520px]">
                    <div
                        v-for="(revision, index) in revisions"
                        :key="revision.id"
                        class="revision-item group relative p-3 mb-2 rounded-lg border cursor-pointer transition-all"
                        :class="[
                            selectedId === revision.id
                                ? 'border-indigo-400 bg-indigo-50'
                                : 'border-gray-100 bg-white hover:border-gray-200 hover:bg-gray-50',
                            revision.is_current ? 'ring-1 ring-green-300' : ''
                        ]"
                        @click="selectRevision(revision)"
                    >
                        <!-- Current badge + action -->
                        <div class="flex items-center gap-2 mb-1">
                            <span
                                class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded"
                                :class="actionBadgeClass(revision.action)"
                            >{{ revision.action_label || revision.action }}</span>
                            <span v-if="revision.is_current" class="text-[10px] font-semibold text-green-600 bg-green-50 px-1.5 py-0.5 rounded">
                                {{ __('Current') }}
                            </span>
                        </div>

                        <!-- User label (editable) -->
                        <div v-if="revision.label || isEditingLabel === revision.id" class="mb-1">
                            <input
                                v-if="isEditingLabel === revision.id"
                                v-model="labelDraft"
                                class="w-full text-xs border border-indigo-300 rounded px-1.5 py-0.5 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                :placeholder="__('Label (e.g. v1.0 release)')"
                                @keyup.enter="saveLabel(revision)"
                                @keyup.esc="cancelLabel"
                                @blur="saveLabel(revision)"
                                ref="labelInput"
                            />
                            <button
                                v-else
                                class="text-xs font-medium text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded hover:bg-indigo-100"
                                @click.stop="startEditLabel(revision)"
                            >
                                <i class="fa fa-tag mr-1"></i>{{ revision.label }}
                            </button>
                        </div>

                        <!-- Meta -->
                        <div class="text-xs text-gray-500">
                            <span>{{ revision.user ? revision.user.name : __('Unknown') }}</span>
                            <span class="mx-1">·</span>
                            <span>{{ formatDate(revision.created_at) }}</span>
                        </div>

                        <!-- Change summary -->
                        <div v-if="revision.meta?.change_summary?.changed_count" class="mt-1.5">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="field in revision.meta.change_summary.changed_fields.slice(0, 3)"
                                    :key="field"
                                    class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"
                                >{{ field }}</span>
                                <span
                                    v-if="revision.meta.change_summary.changed_count > 3"
                                    class="text-[10px] text-gray-400 px-1"
                                >+{{ revision.meta.change_summary.changed_count - 3 }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                v-if="!revision.is_current"
                                class="text-[10px] text-indigo-600 hover:text-indigo-800 bg-white border border-indigo-200 rounded px-1.5 py-0.5"
                                @click.stop="confirmRestore(revision)"
                                title="{{ __('Restore this version') }}"
                            >
                                <i class="fa fa-undo mr-0.5"></i>{{ __('Restore') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Right: detail / diff / preview ──────────────── -->
                <div class="flex-1 min-w-0 overflow-y-auto max-h-[520px]">
                    <!-- Compare mode -->
                    <div v-if="compareMode && compareFrom && compareTo">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b">
                            <h4 class="text-sm font-semibold text-gray-700">
                                <i class="fa fa-exchange mr-1 text-indigo-500"></i>
                                {{ __('Field Comparison') }}
                            </h4>
                            <button class="text-xs text-gray-500 hover:text-gray-700" @click="exitCompareMode">
                                <i class="fa fa-times mr-1"></i>{{ __('Exit compare') }}
                            </button>
                        </div>

                        <div v-if="diffLoading" class="py-8 text-center text-gray-400 text-sm">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('Computing diff...') }}
                        </div>
                        <div v-else-if="diffResult && diffResult.changed_count === 0" class="py-8 text-center text-gray-400 text-sm">
                            <i class="fa fa-check-circle text-green-400"></i> {{ __('No differences between these versions.') }}
                        </div>
                        <div v-else-if="diffResult" class="space-y-2">
                            <div
                                v-for="change in diffResult.changes"
                                :key="change.field"
                                class="border rounded-lg overflow-hidden"
                            >
                                <div class="px-3 py-1.5 bg-gray-50 border-b flex items-center justify-between">
                                    <span class="text-xs font-semibold text-gray-700">{{ change.field }}</span>
                                    <span
                                        class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                                        :class="{
                                            'bg-green-100 text-green-700': change.type === 'added',
                                            'bg-red-100 text-red-700': change.type === 'removed',
                                            'bg-yellow-100 text-yellow-700': change.type === 'modified'
                                        }"
                                    >{{ change.type }}</span>
                                </div>
                                <div class="p-3 space-y-2 text-xs">
                                    <div v-if="change.type !== 'added'">
                                        <div class="text-[10px] text-gray-400 uppercase mb-0.5">{{ __('Before') }}</div>
                                        <div class="bg-red-50 text-red-800 p-2 rounded line-through whitespace-pre-wrap break-all">{{ formatValue(change.old) }}</div>
                                    </div>
                                    <div v-if="change.type !== 'removed'">
                                        <div class="text-[10px] text-gray-400 uppercase mb-0.5">{{ __('After') }}</div>
                                        <div class="bg-green-50 text-green-800 p-2 rounded whitespace-pre-wrap break-all">{{ formatValue(change.new) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview mode (single revision) -->
                    <div v-else-if="selectedRevision">
                        <div class="flex items-center justify-between mb-3 pb-2 border-b">
                            <h4 class="text-sm font-semibold text-gray-700">
                                <i class="fa fa-eye mr-1 text-indigo-500"></i>
                                {{ __('Version #') }}{{ selectedRevision.id }}
                                <span class="ml-2 text-xs font-normal text-gray-400">{{ selectedRevision.action_label || selectedRevision.action }}</span>
                            </h4>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="!selectedRevision.is_current && !restoring"
                                    class="text-xs text-white bg-indigo-600 hover:bg-indigo-700 px-2.5 py-1 rounded"
                                    @click="confirmRestore(selectedRevision)"
                                >
                                    <i class="fa fa-undo mr-1"></i>{{ __('Restore this version') }}
                                </button>
                                <button
                                    v-if="compareFrom && !compareTo && compareFrom.id !== selectedRevision.id"
                                    class="text-xs text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded"
                                    @click="startCompare(selectedRevision)"
                                >
                                    <i class="fa fa-exchange mr-1"></i>{{ __('Compare with #') }}{{ compareFrom.id }}
                                </button>
                                <button
                                    v-else-if="!compareFrom"
                                    class="text-xs text-gray-600 bg-gray-100 hover:bg-gray-200 px-2.5 py-1 rounded"
                                    @click="setCompareBase(selectedRevision)"
                                >
                                    <i class="fa fa-columns mr-1"></i>{{ __('Select for compare') }}
                                </button>
                            </div>
                        </div>

                        <!-- Meta info -->
                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                            <div class="bg-gray-50 rounded p-2">
                                <span class="text-gray-400">{{ __('Author') }}:</span>
                                <span class="ml-1 text-gray-700">{{ selectedRevision.user?.name || __('Unknown') }}</span>
                            </div>
                            <div class="bg-gray-50 rounded p-2">
                                <span class="text-gray-400">{{ __('Created') }}:</span>
                                <span class="ml-1 text-gray-700">{{ formatDate(selectedRevision.created_at) }}</span>
                            </div>
                            <div v-if="selectedRevision.parent_id" class="bg-gray-50 rounded p-2">
                                <span class="text-gray-400">{{ __('Parent version') }}:</span>
                                <span class="ml-1 text-gray-700">#{{ selectedRevision.parent_id }}</span>
                            </div>
                            <div v-if="selectedRevision.meta?.change_summary?.changed_count" class="bg-gray-50 rounded p-2">
                                <span class="text-gray-400">{{ __('Fields changed') }}:</span>
                                <span class="ml-1 text-gray-700">{{ selectedRevision.meta.change_summary.changed_count }}</span>
                            </div>
                        </div>

                        <!-- Full snapshot data -->
                        <div>
                            <div class="text-[10px] text-gray-400 uppercase mb-1.5">{{ __('Snapshot data') }}</div>
                            <div class="space-y-1.5">
                                <div
                                    v-for="(value, field) in selectedRevision.data"
                                    :key="field"
                                    class="border border-gray-100 rounded"
                                >
                                    <div class="px-2 py-1 bg-gray-50 border-b text-[10px] font-semibold text-gray-500 uppercase">{{ field }}</div>
                                    <div class="px-2 py-1.5 text-xs text-gray-700 whitespace-pre-wrap break-all">{{ formatValue(value) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty selection -->
                    <div v-else class="flex flex-col items-center justify-center h-full text-gray-300 py-16">
                        <i class="fa fa-arrow-left text-2xl mb-2"></i>
                        <p class="text-sm">{{ __('Select a version from the list to preview') }}</p>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="flex items-center justify-between w-full">
                <span v-if="revisions.length" class="text-xs text-gray-400">
                    {{ revisions.length }} {{ __('versions') }}
                </span>
                <div class="flex gap-2 ml-auto">
                    <ui-button color="white" hover="gray-200" @click="$emit('close')">
                        {{ __('Close') }}
                    </ui-button>
                </div>
            </div>
        </template>
    </ui-modal>

    <!-- Restore confirmation dialog -->
    <ui-modal :show="showRestoreConfirm" max-width="lg" @close="showRestoreConfirm = false">
        <template #title>
            <i class="fa fa-exclamation-triangle text-yellow-500 mr-2"></i>{{ __('Confirm Restore') }}
        </template>
        <template #content>
            <div v-if="restoreTarget">
                <p class="text-sm text-gray-600 mb-3">
                    {{ __('You are about to restore the content to version #') }}{{ restoreTarget.id }}
                    ({{ restoreTarget.action_label || restoreTarget.action }}, {{ formatDate(restoreTarget.created_at) }}).
                </p>
                <p class="text-sm text-gray-600 mb-3">
                    {{ __('The current content will be overwritten. A new revision will be created for this restore action.') }}
                </p>

                <div v-if="restoreDiff" class="border rounded-lg overflow-hidden max-h-60 overflow-y-auto">
                    <div class="px-3 py-1.5 bg-gray-50 border-b text-xs font-semibold text-gray-600">
                        {{ __('Changes that will be applied') }} ({{ restoreDiff.changed_count }})
                    </div>
                    <div class="divide-y">
                        <div v-for="change in restoreDiff.changes" :key="change.field" class="px-3 py-2 text-xs">
                            <span class="font-semibold text-gray-700">{{ change.field }}</span>
                            <span class="mx-1 text-gray-400">→</span>
                            <span class="text-green-700">{{ formatValue(change.new) || '(empty)' }}</span>
                        </div>
                    </div>
                </div>
                <div v-else-if="restoreDiffLoading" class="text-center text-gray-400 text-sm py-4">
                    <i class="fa fa-spinner fa-spin"></i> {{ __('Computing changes...') }}
                </div>
            </div>
        </template>
        <template #footer>
            <ui-button color="white" hover="gray-200" @click="showRestoreConfirm = false">
                {{ __('Cancel') }}
            </ui-button>
            <ui-button color="indigo-500" :disabled="restoring" @click="doRestore">
                <i v-if="restoring" class="fa fa-spinner fa-spin mr-1"></i>
                {{ __('Confirm Restore') }}
            </ui-button>
        </template>
    </ui-modal>
</template>

<script>
import UiButton from "../../../components/Button.vue";
import UiModal from "../../../components/Modal.vue";

export default {
    name: "RevisionsModal",
    components: {
        UiButton,
        UiModal,
    },
    props: {
        show: { type: Boolean, default: false },
        projectId: { type: [Number, String], required: true },
        collectionId: { type: [Number, String], required: true },
        contentId: { type: [Number, String], required: true },
    },
    data() {
        return {
            revisions: [],
            loading: false,
            selectedId: null,
            // Compare mode
            compareFrom: null,
            compareTo: null,
            compareMode: false,
            diffResult: null,
            diffLoading: false,
            // Label editing
            isEditingLabel: null,
            labelDraft: "",
            // Restore
            showRestoreConfirm: false,
            restoreTarget: null,
            restoreDiff: null,
            restoreDiffLoading: false,
            restoring: false,
        };
    },
    computed: {
        selectedRevision() {
            return this.revisions.find((r) => r.id === this.selectedId) || null;
        },
        baseUrl() {
            return `content/revisions/${this.projectId}/${this.collectionId}/${this.contentId}`;
        },
    },
    watch: {
        show(value) {
            if (value) {
                this.resetState();
                this.load();
            }
        },
    },
    methods: {
        resetState() {
            this.revisions = [];
            this.selectedId = null;
            this.compareFrom = null;
            this.compareTo = null;
            this.compareMode = false;
            this.diffResult = null;
            this.showRestoreConfirm = false;
            this.restoreTarget = null;
            this.restoreDiff = null;
        },
        load() {
            this.loading = true;
            axios
                .get(this.baseUrl)
                .then((response) => {
                    this.revisions = response.data || [];
                    if (this.revisions.length) {
                        this.selectedId = this.revisions[0].id;
                    }
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        selectRevision(revision) {
            if (this.compareMode) return;
            this.selectedId = revision.id;
        },
        setCompareBase(revision) {
            this.compareFrom = revision;
        },
        startCompare(revision) {
            if (!this.compareFrom) return;
            this.compareTo = revision;
            this.compareMode = true;
            this.loadDiff();
        },
        exitCompareMode() {
            this.compareMode = false;
            this.compareFrom = null;
            this.compareTo = null;
            this.diffResult = null;
        },
        loadDiff() {
            if (!this.compareFrom || !this.compareTo) return;
            this.diffLoading = true;
            axios
                .get(`${this.baseUrl}/diff/${this.compareFrom.id}/${this.compareTo.id}`)
                .then((response) => {
                    this.diffResult = response.data;
                })
                .finally(() => {
                    this.diffLoading = false;
                });
        },
        startEditLabel(revision) {
            this.isEditingLabel = revision.id;
            this.labelDraft = revision.label || "";
            this.$nextTick(() => {
                if (this.$refs.labelInput) {
                    this.$refs.labelInput.focus();
                }
            });
        },
        cancelLabel() {
            this.isEditingLabel = null;
            this.labelDraft = "";
        },
        saveLabel(revision) {
            if (this.isEditingLabel !== revision.id) return;
            const label = this.labelDraft.trim();
            axios
                .patch(`${this.baseUrl}/${revision.id}/label`, { label })
                .then((response) => {
                    const idx = this.revisions.findIndex((r) => r.id === revision.id);
                    if (idx !== -1) {
                        this.$set(this.revisions, idx, { ...this.revisions[idx], label });
                    }
                })
                .finally(() => {
                    this.isEditingLabel = null;
                    this.labelDraft = "";
                });
        },
        confirmRestore(revision) {
            this.restoreTarget = revision;
            this.restoreDiff = null;
            this.showRestoreConfirm = true;
            // Compute diff between target and current (latest)
            const current = this.revisions.find((r) => r.is_current);
            if (current && current.id !== revision.id) {
                this.restoreDiffLoading = true;
                axios
                    .get(`${this.baseUrl}/diff/${revision.id}/${current.id}`)
                    .then((response) => {
                        this.restoreDiff = response.data;
                    })
                    .finally(() => {
                        this.restoreDiffLoading = false;
                    });
            }
        },
        doRestore() {
            if (!this.restoreTarget) return;
            this.restoring = true;
            axios
                .post(`${this.baseUrl}/${this.restoreTarget.id}/restore`)
                .then(() => {
                    this.$toast?.success?.(this.__("Revision restored successfully."));
                    this.showRestoreConfirm = false;
                    this.$emit("restored");
                    this.load();
                })
                .finally(() => {
                    this.restoring = false;
                });
        },
        actionBadgeClass(action) {
            const map = {
                created: "bg-green-100 text-green-700",
                updated: "bg-blue-100 text-blue-700",
                published: "bg-purple-100 text-purple-700",
                unpublished: "bg-orange-100 text-orange-700",
                draft_updated: "bg-cyan-100 text-cyan-700",
                restored: "bg-indigo-100 text-indigo-700",
                imported: "bg-gray-100 text-gray-700",
                deleted: "bg-red-100 text-red-700",
            };
            return map[action] || "bg-gray-100 text-gray-600";
        },
        formatValue(value) {
            if (value === null || value === undefined || value === "") return "(empty)";
            if (typeof value === "object") return JSON.stringify(value, null, 2);
            if (typeof value === "string" && value.length > 500) return value.slice(0, 500) + "...";
            return String(value);
        },
        formatDate(value) {
            if (!value) return "";
            const d = new Date(value);
            return d.toLocaleString();
        },
        __(key) {
            return key;
        },
    },
};
</script>

<style scoped>
.revision-item {
    transition: all 0.15s ease;
}
</style>
