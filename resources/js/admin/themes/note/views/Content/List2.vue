<template>
    <div class="admin__project-content-list relative h-full flex flex-col">
        <project-header :project="project"></project-header>

        <div class="flex flex-1 overflow-y-auto">
            <div class="w-3/12 bg-white overflow-x-hidden">
                <content-sidebar :project="project"></content-sidebar>
            </div>

            <div class="w-9/12 p-4 overflow-x-auto">
                <div v-if="collection_id !== undefined" class="admin__project-content-table">
                    <h4 class="h-10 flex justify-end items-center font-bold text-lg mb-2">
                        <div class="flex-1">
                            {{ __(collection.name) }}
                            <small class="text-gray-400 ml-1">#{{ collection.slug }}</small>
                        </div>

                        <router-link
                            v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
                            :to="{
                                name: 'projects.content.forms',
                                params: {
                                    project_id: $route.params.project_id,
                                    col_id: $route.params.col_id,
                                },
                            }"
                            class="bg-green-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150 mr-2"
                        >
                            <i class="fab fa-wpforms"></i> {{ __('Forms') }} ({{ form_count }})
                        </router-link>

                        <router-link
                            :to="{
                                name: 'projects.content.new',
                                params: {
                                    project_id: $route.params.project_id,
                                    col_id: collection_id,
                                },
                            }"
                            class="bg-indigo-500 items-center px-4 py-2 border border-transparent rounded-md text-sm text-white focus:outline-none transition ease-in-out duration-150"
                            v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
                        >
                            {{ __('+ Create New') }}
                        </router-link>

                        <button
                            type="button"
                            class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
                            @click="exportContent()"
                        >
                            <i class="fa fa-download"></i> {{ __('Export') }}
                        </button>

                        <input v-if="!isReadonly && canProject(['owner', 'admin'])" ref="importFile" type="file" accept=".json,.csv" class="hidden" @change="importContent($event)" />

                        <button
                            v-if="!isReadonly && canProject(['owner', 'admin'])"
                            type="button"
                            class="bg-white items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150 ml-2"
                            @click="$refs.importFile.click()"
                        >
                            <i class="fa fa-upload"></i> {{ __('Import') }}
                        </button>
                    </h4>

                    <div class="flex space-between mb-2">
                        <div class="relative flex w-full flex-wrap items-stretch">
                            <span class="h-full leading-snug font-normal absolute text-center text-gray-400 absolute bg-transparent rounded-md text-base items-center justify-center w-8 pl-3 py-2">
                                <i class="fas fa-search"></i>
                            </span>
                            <form class="w-full" @submit.prevent="getContent()">
                                <input
                                    type="text"
                                    v-model="search"
                                    @input="getContent()"
                                    :placeholder="__('Type something and press enter')"
                                    class="px-3 py-2 placeholder-gray-400 text-gray-700 bg-white rounded-md text-sm w-full pl-10 border-gray-200 focus:border-gray-300"
                                />
                            </form>
                            <span
                                v-show="search != ''"
                                class="h-full leading-snug font-normal absolute text-center text-gray-400 absolute bg-transparent rounded-md text-base items-center justify-center w-8 py-2 right-0 pr-3 cursor-pointer"
                                @click="(search = ''), getContent()"
                            >
                                <i class="fas fa-times-circle"></i>
                            </span>
                        </div>

                        <div class="w-auto h-auto ml-2">
                            <select
                                v-model="localeFilter"
                                @change="changeLocale()"
                                v-formselect
                                class="px-3 pr-8 pl-3 text-gray-700 bg-white focus:border-gray-300 cursor-pointer"
                                :title="__('Language')"
                            >
                                <option v-for="opt in localeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])" class="w-full flex justify-between text-sm text-gray-700 mb-2 pl-1">
                        <div class="flex">
                            <div class="py-1">{{ selected.length }} {{ __('items selected') }}</div>
                            <template v-if="isComments">
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
                                    class="ml-2 cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="commentBulk('approve')"
                                >
                                    <i class="fa fa-check"></i> {{ __('approve') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
                                    class="ml-2 cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="commentBulk('spam')"
                                >
                                    <i class="fa fa-bug"></i> {{ __('mark as spam') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trash'"
                                    class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="commentBulk('trash')"
                                >
                                    <i class="fa fa-trash-restore"></i> {{ __('move to trash') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems === 'trash'"
                                    class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="commentBulk('restore')"
                                >
                                    <i class="fa fa-recycle"></i> {{ __('restore') }}
                                </div>
                                <div v-if="selected.length !== 0 && canProject(['owner', 'admin'])" class="ml-2 cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100" @click="deleteSelected">
                                    <i class="fa fa-trash-alt"></i> {{ __('delete') }}
                                </div>
                            </template>
                            <template v-else>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trashed' && canProject(['owner', 'admin'])"
                                    class="ml-2 cursor-pointer text-green-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="publishSelected"
                                >
                                    <i class="fa fa-cloud-upload-alt"></i> {{ __('publish') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
                                    class="ml-2 cursor-pointer text-gray-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="unPublishSelected"
                                >
                                    <i class="fa fa-cloud-download-alt"></i> {{ __('unpublish') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems !== 'trashed'"
                                    class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="moveToTrashSelected"
                                >
                                    <i class="fa fa-trash-restore"></i> {{ __('move to trash') }}
                                </div>
                                <div
                                    v-if="selected.length !== 0 && listOptions.getItems === 'trashed'"
                                    class="ml-2 cursor-pointer text-orange-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100"
                                    @click="restoreSelected"
                                >
                                    <i class="fa fa-recycle"></i> {{ __('restore') }}
                                </div>
                                <div v-if="selected.length !== 0 && canProject(['owner', 'admin'])" class="ml-2 cursor-pointer text-red-500 font-bold py-1 px-3 rounded-md hover:bg-gray-100" @click="deleteSelected">
                                    <i class="fa fa-trash-alt"></i> {{ __('delete') }}
                                </div>
                            </template>
                        </div>

                        <div class="flex">
                            <template v-if="isComments">
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('all')" :class="{ 'bg-gray-200': listOptions.getItems == 'all' }">
                                    {{ __('All') }}({{ totalCount }})
                                </div>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('approved')" :class="{ 'bg-gray-200': listOptions.getItems == 'approved' }">
                                    {{ __('Approved') }}({{ approvedCount }})
                                </div>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('pending')" :class="{ 'bg-gray-200': listOptions.getItems == 'pending' }">
                                    {{ __('Pending') }}({{ pendingCount }})
                                </div>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('spam')" :class="{ 'bg-gray-200': listOptions.getItems == 'spam' }">
                                    {{ __('Spam') }}({{ spamCount }})
                                </div>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('trash')" :class="{ 'bg-gray-200': listOptions.getItems == 'trash' }">
                                    {{ __('Trash') }}({{ trashCount }})
                                </div>
                            </template>
                            <template v-else>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('all')" :class="{ 'bg-gray-200': listOptions.getItems == 'all' }">
                                    {{ __('All') }}({{ totalCount }})
                                </div>
                                <div
                                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                                    @click="changeGetItems('published')"
                                    :class="{ 'bg-gray-200': listOptions.getItems == 'published' }"
                                >
                                    {{ __('Published') }}({{ publishedCount }})
                                </div>
                                <div class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100" @click="changeGetItems('draft')" :class="{ 'bg-gray-200': listOptions.getItems == 'draft' }">
                                    {{ __('Draft') }}({{ draftCount }})
                                </div>
                                <div
                                    class="ml-1 cursor-pointer text-blue-500 py-1 px-1 rounded-md hover:bg-gray-100"
                                    @click="changeGetItems('trashed')"
                                    :class="{ 'bg-gray-200': listOptions.getItems == 'trashed' }"
                                >
                                    {{ __('Trashed') }}({{ trashedCount }})
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Note timeline: notes ordered by update time (differs from the default table view) -->
                    <div v-if="content.data && content.data.length" class="max-w-3xl border border-gray-200 rounded-md bg-white divide-y divide-gray-100">
                        <div v-for="item in content.data" :key="item.id" class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition group">
                            <span
                                class="mt-1.5 w-2.5 h-2.5 rounded-full shrink-0"
                                :class="item.published_at !== null ? 'bg-green-400' : 'bg-gray-300'"
                            ></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <h5 class="font-bold text-gray-800 truncate" :title="primaryValue(item)">{{ primaryValue(item) }}</h5>
                                    <span class="text-xs text-gray-400 whitespace-nowrap mt-0.5" v-tooltip="__('Updated at {date}', { date: dateFormat(item.updated_at) })">
                                        {{ $filters.date(item.updated_at, 'YYYY-MM-DD HH:mm') }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 leading-relaxed" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    {{ summaryValue(item) }}
                                </p>
                                <div class="mt-1 text-xs text-gray-400 flex items-center gap-3">
                                    <span>#{{ item.id }}</span>
                                    <span v-if="item.published_at !== null" class="text-green-600">{{ __('published') }}</span>
                                    <span v-else class="text-gray-500">{{ __('draft') }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <router-link
                                    v-if="!isReadonly && canProject(['owner', 'admin', 'editor'])"
                                    :to="{ name: 'projects.content.edit', params: { project_id: $route.params.project_id, col_id: collection_id, content_id: item.id } }"
                                    class="text-indigo-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer"
                                    :title="__('Edit')"
                                >
                                    <i class="fa fa-pencil-alt"></i>
                                </router-link>
                                <router-link
                                    v-if="isReadonly || !canProject(['owner', 'admin', 'editor'])"
                                    :to="{ name: 'projects.content.edit', params: { project_id: $route.params.project_id, col_id: collection_id, content_id: item.id } }"
                                    class="text-gray-400 p-2 px-3 rounded-md cursor-default"
                                    :title="isReadonly ? __('Read-only') : __('View only')"
                                >
                                    <i class="fa fa-eye"></i>
                                </router-link>
                                <a
                                    v-if="!isReadonly && !isComments && canProject(['owner', 'admin'])"
                                    class="text-orange-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer"
                                    @click="moveToTrashContent(item)"
                                    :title="__('Move to trash')"
                                >
                                    <i class="fa fa-trash-restore"></i>
                                </a>
                                <a
                                    v-if="!isReadonly && isComments && canProject(['owner', 'admin', 'editor'])"
                                    class="text-orange-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer"
                                    @click="commentTrash(item)"
                                    :title="__('Move to trash')"
                                >
                                    <i class="fa fa-trash-restore"></i>
                                </a>
                                <input
                                    type="checkbox"
                                    :checked="checkIfSelected(item.id)"
                                    @click="selectRecord(item.id)"
                                    v-formcheckbox
                                    class="cursor-pointer ml-2"
                                    :title="__('Select')"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-else-if="content.data !== undefined && content.data.length === 0" class="text-center text-sm text-gray-500 p-10">
                        {{ __('No data found') }}
                    </div>

                    <div class="flex justify-between items-center mt-2">
                        <div class="block">
                            <pagination :data="content" size="small" :limit="3" @pagination-change-page="getContent"></pagination>
                        </div>

                        <div class="text-sm italic text-gray-500">{{ paginationInfo }}</div>

                        <div class="text-sm italic text-gray-500">
                            <select v-model="each" @change="getContent()" v-formselect>
                                <option value="5">5</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { localeDisplayName } from '@/admin/utils/locales';
import { formatDate } from '@/utils/filters';
import { __ } from '@/admin/translations/engine';
import axios from 'axios';
import { ref, computed, watch, onMounted, getCurrentInstance, useAttrs } from 'vue';
import { useRoute } from 'vue-router';
import { useAdminStore } from '@/admin/store';

import ProjectHeader from '@/admin/components/ProjectHeader.vue';
import ContentSidebar from '@/admin/components/ContentSidebar.vue';
import projectBreadcrumb from '@/admin/mixins/projectBreadcrumb';

/**
 * Normalize a project's `locales` attribute (comma-separated string or
 * array) into a clean array of locale codes.
 */
function parseLocales(value) {
    if (Array.isArray(value)) return value.filter((l) => typeof l === 'string' && l !== '');
    if (typeof value === 'string') {
        return value
            .split(',')
            .map((l) => l.trim())
            .filter((l) => l !== '');
    }
    return [];
}

/**
 * @param {Object} options
 * @param {Function} [options.collectionId] getter returning the current collection id (number|undefined)
 * @param {number} [options.eachProp] items per page (default 15)
 * @param {Function} [options.relationSelect] getter indicating relation-select mode
 * @param {Function} [options.relationType] getter for the relation type (==1 makes row selection single-choice)
 * @param {Function} [options.onAddSelected] callback when relation selection completes (replaces $emit('addSelected'))
 * @param {boolean} [options.enableCollectionWatch] whether to auto-refresh the list when the collection changes
 * @param {Function} [options.initialProject] getter for the initial project value
 */
function useContentList(options = {}) {
    const {
        collectionId = () => undefined,
        eachProp = 15,
        relationSelect = () => false,
        relationType = () => undefined,
        onAddSelected = null,
        enableCollectionWatch = false,
        initialProject = () => ({}),
    } = options;

    const route = useRoute();
    const store = useAdminStore();
    const instance = getCurrentInstance();
    const attrs = useAttrs();

    /* ---------------- Locale filter init (mirrors the original data()) ---------------- */
    const projectId = store.currentProject?.id;
    let localeStorageKeyInit = null;
    let localeFilterInit = '';
    let projectLocalesInit = [];

    if (projectId) {
        localeStorageKeyInit = 'aine_admin_content_locale_' + projectId;
        projectLocalesInit = parseLocales(store.currentProject.locales);
        const defaultLocale = store.currentProject.default_locale || projectLocalesInit[0] || 'en';

        let saved = null;
        try {
            saved = localStorage.getItem(localeStorageKeyInit);
        } catch (error) {
            saved = null;
        }
        localeFilterInit =
            saved !== null && (saved === 'all' || projectLocalesInit.includes(saved)) ? saved : defaultLocale;
    }

    /* ---------------- State ---------------- */
    const project = ref(initialProject());
    const collection = ref({});
    const content = ref({});
    const totalCount = ref(0);
    const publishedCount = ref(0);
    const draftCount = ref(0);
    const trashedCount = ref(0);
    const approvedCount = ref(0);
    const pendingCount = ref(0);
    const spamCount = ref(0);
    const trashCount = ref(0);
    const search = ref('');
    const localeFilter = ref(localeFilterInit);
    const projectLocales = ref(projectLocalesInit);
    const localeStorageKey = ref(localeStorageKeyInit);
    const columns = ref({});
    const listOptions = ref({
        orderBy: 'created_at',
        criteria: 'ASC',
        sortByMeta: 0,
        getItems: 'all',
    });
    const openTextModal = ref(false);
    const textRecord = ref(null);
    const textModalFieldName = ref(null);
    const openMediaModal = ref(false);
    const mediaRecords = ref({});
    const mediaModalFieldName = ref(null);
    const selected = ref([]);
    const selectAll = ref(false);
    const openRelationModal = ref(false);
    const relationRecords = ref({
        collection: {
            fields: {},
        },
    });
    const relationModalFieldName = ref(null);
    const each = ref(eachProp);
    const form_count = ref(0);

    // collection_id exposed to templates (unwrapped to its value)
    const collection_id = computed(() => collectionId());

    /* ---------------- Computed ---------------- */
    const isReadonly = computed(() => {
        const p = store.currentProject || project.value;
        return p && (p.is_readonly || !p.status);
    });

    const isComments = computed(() => collection.value && collection.value.kind === 'comment');

    const localeOptions = computed(() => {
        const options = projectLocales.value.map((l) => {
            const name = localeDisplayName(l) || l.toUpperCase();
            return { value: l, label: name + ' (' + l + ')' };
        });
        options.push({ value: 'all', label: __('All Languages') });
        return options;
    });

    const paginationInfo = computed(() =>
        __('{total} records, {from} - {to} showing', {
            total: content.value.total,
            from: content.value.from,
            to: content.value.to,
        })
    );

    const hasAddSelectedListener = computed(() => attrs && attrs.onAddSelected);

    /* ---------------- Methods ---------------- */
    function safeOptions(field) {
        if (!field.options) return {};
        if (typeof field.options === 'string') {
            try {
                return JSON.parse(field.options);
            } catch (e) {
                return {};
            }
        }
        return field.options;
    }

    function sanitizeHtml(html) {
        if (!html) return '';
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const dangerous = doc.querySelectorAll('script, iframe, object, embed, link, meta');
        dangerous.forEach((el) => el.remove());
        doc.querySelectorAll('*').forEach((el) => {
            for (const attr of Array.from(el.attributes)) {
                if (attr.name.startsWith('on') || attr.name === 'srcdoc') {
                    el.removeAttribute(attr.name);
                }
            }
        });
        return doc.body.innerHTML;
    }

    function canProject(roles) {
        const p = store.currentProject || project.value;
        return Array.isArray(roles) && roles.includes(p && p.my_role);
    }

    function getContent(page) {
        if (typeof page === 'undefined') {
            page = 1;
        }

        const cid = collectionId();

        if (cid === undefined || route.params.project_id === undefined) {
            return;
        }

        axios
            .get(
                'content/' +
                    route.params.project_id +
                    '/' +
                    cid +
                    '?page=' +
                    page +
                    '&search=' +
                    search.value +
                    '&orderBy=' +
                    listOptions.value.orderBy +
                    '&cr=' +
                    listOptions.value.criteria +
                    '&sbm=' +
                    listOptions.value.sortByMeta +
                    '&each=' +
                    each.value +
                    '&getItems=' +
                    listOptions.value.getItems +
                    '&locale=' +
                    localeFilter.value
            )
            .then((response) => {
                project.value = response.data.project;
                collection.value = response.data.collection;
                content.value = response.data.content;
                form_count.value = response.data.forms;

                const locales = parseLocales(project.value.locales);
                projectLocales.value = locales;
                if (!localeStorageKey.value) {
                    localeStorageKey.value = 'aine_admin_content_locale_' + route.params.project_id;
                }
                if (localeFilter.value !== 'all' && !locales.includes(localeFilter.value)) {
                    localeFilter.value = project.value.default_locale || locales[0] || 'en';
                    saveLocalePreference();
                    getContent(page);
                    return;
                }

                totalCount.value = response.data.totalCount;
                if (isComments.value) {
                    approvedCount.value = response.data.approved;
                    pendingCount.value = response.data.pending;
                    spamCount.value = response.data.spam;
                    trashCount.value = response.data.trash;
                } else {
                    publishedCount.value = response.data.published;
                    draftCount.value = response.data.draft;
                    trashedCount.value = response.data.trashed;
                }

                if (content.value.data == 0) selectAll.value = false;

                if (store.columnSettings.length == 0) {
                    setInitialColumns();
                    store.setColumns({
                        project_id: route.params.project_id,
                        collection_id: cid,
                        columns: columns.value,
                    });
                } else {
                    let storeHasSettings = store.columnSettings.some(
                        (o) => o.project_id == route.params.project_id && o.collection_id == cid
                    );

                    if (!storeHasSettings) {
                        setInitialColumns();
                        store.setColumns({
                            project_id: route.params.project_id,
                            collection_id: cid,
                            columns: columns.value,
                        });
                    } else {
                        columns.value = store.columnSettings.find(
                            (o) => o.project_id == route.params.project_id && o.collection_id == cid
                        ).columns;
                    }
                }

                selectAll.value = false;
            });
    }

    function setInitialColumns() {
        columns.value = {
            created_at: true,
            updated_at: true,
            published_at: false,
            created_by: false,
            updated_by: false,
            published_by: false,
        };

        (collection.value.fields || []).forEach((field) => {
            if (field.options.hideInContentList) columns.value[field.name] = false;
            else columns.value[field.name] = true;
        });
    }

    function changeColumnSettings() {
        store.updateColumn({
            project_id: route.params.project_id,
            collection_id: collectionId(),
            columns: columns.value,
        });
    }

    function changeLocale() {
        saveLocalePreference();
        getContent();
    }

    function saveLocalePreference() {
        if (!localeStorageKey.value) return;
        try {
            localStorage.setItem(localeStorageKey.value, localeFilter.value);
        } catch (error) {
            // Storage unavailable: the preference is a nicety, never fatal.
        }
    }

    function sortBy(field, meta = 0) {
        if (listOptions.value.orderBy != field) {
            listOptions.value.criteria = 'ASC';
        } else {
            if (listOptions.value.criteria == null || listOptions.value.criteria == 'DESC') {
                listOptions.value.criteria = 'ASC';
            } else {
                listOptions.value.criteria = 'DESC';
            }
        }
        listOptions.value.orderBy = field;

        listOptions.value.sortByMeta = meta;
        getContent(1);
    }

    function selectRecord(id) {
        if (relationType() === 1) {
            if (selected.value.includes(id)) {
                selected.value.splice(
                    selected.value.findIndex((v) => v === id),
                    1
                );
            } else {
                selected.value = [];
                selected.value.push(id);
            }
        } else {
            if (selected.value.includes(id)) {
                selected.value.splice(
                    selected.value.findIndex((v) => v === id),
                    1
                );
            } else {
                selected.value.push(id);
            }
        }
        selectAll.value = false;
    }

    function checkIfSelected(id) {
        return selected.value.includes(id);
    }

    function addSelected() {
        if (onAddSelected) {
            onAddSelected({
                selected: selected.value.slice(),
                collection_id: collectionId(),
            });
        }
        selected.value = [];
    }

    function exportContent() {
        axios({
            url: 'content/export/' + route.params.project_id + '/' + collectionId() + '?format=json',
            method: 'GET',
            responseType: 'blob',
        }).then((response) => {
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'content-export.json');
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    }

    function importContent(event) {
        const file = event.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        axios
            .post('content/import/' + route.params.project_id + '/' + collectionId(), formData)
            .then((response) => {
                instance?.proxy?.$toast.success(response.data.message || __('Content imported.'));
                getContent();
            })
            .catch((error) => {
                if (error.response && error.response.data && error.response.data.message) {
                    instance?.proxy?.$toast.error(error.response.data.message);
                }
            })
            .finally(() => {
                event.target.value = '';
            });
    }

    function selectAllFn() {
        if (!selectAll.value) {
            const data = content.value.data || [];
            for (let i = 0; i < data.length; i++) {
                if (!selected.value.includes(data[i].id)) {
                    selected.value.push(data[i].id);
                }
            }
        } else {
            selected.value = [];
        }
    }

    function closeTextModal() {
        openTextModal.value = false;
    }

    function showText(field, value) {
        openTextModal.value = true;
        textRecord.value = sanitizeHtml(value);
        textModalFieldName.value = __(field.label);
    }

    function closeMediaModal() {
        openMediaModal.value = false;
    }

    async function showMedia(field, files) {
        await axios
            .post('content/get-selected-files/' + route.params.project_id, { data: files.split(',') })
            .then((response) => {
                openMediaModal.value = true;
                mediaRecords.value = response.data;
                mediaModalFieldName.value = field.label;
                instance?.proxy?.$forceUpdate();
            });
    }

    function closeRelationModal() {
        openRelationModal.value = false;
    }

    async function showRelationlist(field, value) {
        let options = typeof field.options === 'string' ? JSON.parse(field.options) : field.options;
        if (options.relation === undefined) return;

        let data = {
            selected: value.split(','),
            collection_id: options.relation.collection,
        };

        await axios
            .post('content/get-selected-records/' + route.params.project_id, { data: data })
            .then((response) => {
                openRelationModal.value = true;
                relationRecords.value = response.data.content;
                relationRecords.value.collection = response.data.collection;
                relationModalFieldName.value = __(field.label);
                instance?.proxy?.$forceUpdate();
            });
    }

    function changeGetItems(status) {
        listOptions.value.getItems = status;
        getContent();
        selected.value = [];
        selectAll.value = false;
    }

    function publishSelected() {
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to publish all selected items?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(
                            'content/publish-selected/' + route.params.project_id + '/' + route.params.col_id,
                            { selected: selected.value }
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Selected items has been published'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function unPublishSelected() {
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to unpublish all selected items?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(
                            'content/unpublish-selected/' + route.params.project_id + '/' + route.params.col_id,
                            { selected: selected.value }
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Selected items has been unpublished'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function commentBulk(action) {
        const labels = {
            approve: __('approve all selected comments?'),
            spam: __('mark all selected comments as spam?'),
            trash: __('move all selected comments to the trash?'),
            restore: __('restore all selected comments?'),
            delete: __('delete all selected comments permanently?'),
        };

        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: labels[action] || __('continue?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post('content/comments/bulk/' + route.params.project_id, {
                            action,
                            ids: selected.value,
                        })
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Comments updated'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function commentTrash(item) {
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to move this comment to the trash?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post('content/comments/reject/' + route.params.project_id + '/' + item.id)
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Comment moved to the trash.'));
                            getContent();
                        });
                }
            });
    }

    function moveToTrashContent(item) {
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to move this item to the trash?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .delete(
                            'content/move-to-trash/' + route.params.project_id + '/' + route.params.col_id + '/' + item.id
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Content moved to the trash.'));
                            getContent();
                        });
                }
            });
    }

    function moveToTrashSelected() {
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to move all selected items to the trash?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(
                            'content/move-to-trash-selected/' + route.params.project_id + '/' + route.params.col_id,
                            { selected: selected.value }
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Selected items has been moved to the trash'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function deleteSelected() {
        if (isComments.value) {
            commentBulk('delete');
            return;
        }
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to delete all selected items permanently?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(
                            'content/delete-selected/' + route.params.project_id + '/' + route.params.col_id,
                            { selected: selected.value }
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Selected items has been deleted'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function restoreSelected() {
        if (isComments.value) {
            commentBulk('restore');
            return;
        }
        instance?.proxy?.$swal
            .fire({
                title: __('Are you sure'),
                text: __('you want to restore all selected items?'),
            })
            .then((result) => {
                if (result.isConfirmed) {
                    axios
                        .post(
                            'content/restore-selected/' + route.params.project_id + '/' + route.params.col_id,
                            { selected: selected.value }
                        )
                        .then((response) => {
                            instance?.proxy?.$toast.success(__('Selected items has been restored'));
                            getContent();
                            selected.value = [];
                            selectAll.value = false;
                        });
                }
            });
    }

    function getUserNameInitials(name) {
        let initials = name.split(' ');

        if (initials.length > 1) {
            initials = initials.shift().charAt(0) + initials.pop().charAt(0);
        } else {
            initials = name.substring(0, 2);
        }

        return initials.toUpperCase();
    }

    function dateFormat(date) {
        return formatDate(date, 'D MMM YYYY, H:mm');
    }

    /* ---------------- Lifecycle ---------------- */
    onMounted(() => {
        const cid = collectionId();
        if (cid !== undefined) {
            const filter = route.query.filter;
            if (filter && ['all', 'published', 'draft', 'trashed'].includes(filter)) {
                listOptions.value.getItems = filter;
            }
            getContent();
        }
    });

    if (enableCollectionWatch) {
        watch(collectionId, (newVal) => {
            search.value = '';
            selected.value = [];
            selectAll.value = false;
            if (newVal !== undefined && route.params.project_id !== undefined) {
                getContent();
            }
        });
    }

    /* ---------------- Exposed API ---------------- */
    return {
        // state
        project,
        collection,
        content,
        totalCount,
        publishedCount,
        draftCount,
        trashedCount,
        approvedCount,
        pendingCount,
        spamCount,
        trashCount,
        search,
        localeFilter,
        projectLocales,
        localeStorageKey,
        columns,
        listOptions,
        openTextModal,
        textRecord,
        textModalFieldName,
        openMediaModal,
        mediaRecords,
        mediaModalFieldName,
        selected,
        selectAll,
        openRelationModal,
        relationRecords,
        relationModalFieldName,
        each,
        form_count,
        collection_id,
        // computed
        isReadonly,
        isComments,
        localeOptions,
        paginationInfo,
        hasAddSelectedListener,
        // methods
        safeOptions,
        sanitizeHtml,
        canProject,
        getContent,
        setInitialColumns,
        changeColumnSettings,
        changeLocale,
        saveLocalePreference,
        sortBy,
        selectRecord,
        checkIfSelected,
        addSelected,
        exportContent,
        importContent,
        selectAllFn,
        closeTextModal,
        showText,
        closeMediaModal,
        showMedia,
        closeRelationModal,
        showRelationlist,
        changeGetItems,
        publishSelected,
        unPublishSelected,
        commentBulk,
        commentTrash,
        moveToTrashContent,
        moveToTrashSelected,
        deleteSelected,
        restoreSelected,
        getUserNameInitials,
        dateFormat,
    };
}

export default {
    components: {
        ProjectHeader,
        ContentSidebar,
    },

    mixins: [projectBreadcrumb],

    setup() {
        const route = useRoute();
        const cl = useContentList({
            collectionId: () => (route.params.col_id !== undefined ? parseInt(route.params.col_id) : undefined),
            eachProp: 15,
            enableCollectionWatch: true,
            initialProject: () => useAdminStore().currentProject || {},
        });

        // Note timeline-view render helpers (UI-layer private, no effect on shared logic)
        function visibleFields() {
            const fields = cl.collection.value.fields || [];
            return fields.filter((f) => f.type !== 'password' && f.type !== 'json' && f.type !== 'block');
        }
        function metaValue(item, fieldName) {
            const metas = (item.meta || []).filter((m) => m.field_name === fieldName && m.value !== null && m.value !== '');
            return metas.length ? metas[0].value : null;
        }
        function stripHtml(html) {
            if (!html) return '';
            const doc = new DOMParser().parseFromString(String(html), 'text/html');
            return (doc.body.textContent || '').trim();
        }
        function primaryValue(item) {
            const fields = visibleFields();
            const target = fields.find((f) => f.type === 'text' || f.type === 'slug' || f.type === 'number') || fields[0];
            if (!target) return '#' + item.id;
            return metaValue(item, target.name) || __('Untitled');
        }
        function summaryValue(item) {
            const fields = visibleFields();
            const target = fields.find((f) => f.type === 'longtext' || f.type === 'richtext' || f.type === 'textarea');
            const raw = target ? metaValue(item, target.name) : null;
            const text = target && target.type === 'richtext' ? stripHtml(raw) : raw || '';
            return text.length > 120 ? text.substring(0, 120) + '…' : text;
        }

        return { ...cl, visibleFields, metaValue, primaryValue, summaryValue };
    },
};
</script>
