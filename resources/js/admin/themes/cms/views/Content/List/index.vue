<template>
  <div class="admin__project-content-list relative h-full flex flex-col">
    <project-header :project="project"></project-header>

    <div class="flex flex-1 overflow-y-auto">
      <div class="w-3/12 bg-white overflow-x-hidden">
        <content-sidebar :project="project"></content-sidebar>
      </div>

      <div class="w-9/12 p-4 overflow-x-auto">
        <div v-if="$route.params.col_id !== undefined" class="admin__project-content-table">
          <ContentTable
            :columns="tableColumns"
            :content="content"
            :collection="collection"
            :collection_id="collection_id"
            :is-readonly="isReadonly"
            :is-comments="isComments"
            :each="each"
            :can-project="canProject"
            :form_count="form_count"
            :locale-filter="localeFilter"
            :locale-options="localeOptions"
            :selected="selected"
            :list-options="listOptions"
            :total-count="totalCount"
            :published-count="publishedCount"
            :draft-count="draftCount"
            :trashed-count="trashedCount"
            :approved-count="approvedCount"
            :pending-count="pendingCount"
            :spam-count="spamCount"
            :trash-count="trashCount"
            @sort-change="onSortChange"
            @selected-rows-change="onSelectedRowsChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
            @column-toggle="onColumnToggle"
            @show-text="showText"
            @show-media="showMedia"
            @show-relationlist="showRelationlist"
            @move-to-trash-content="moveToTrashContent"
            @comment-trash="commentTrash"
            @export="exportContent"
            @import="importContent"
            @table-search="onTableSearch"
            @locale-change="onLocaleChange"
            @comment-bulk="commentBulk"
            @delete-selected="deleteSelected"
            @publish-selected="publishSelected"
            @unpublish-selected="unPublishSelected"
            @move-to-trash-selected="moveToTrashSelected"
            @restore-selected="restoreSelected"
            @change-get-items="changeGetItems"
          />

          <TextModal
            :show="openTextModal"
            :record="textRecord"
            :field-name="textModalFieldName"
            @close="closeTextModal"
          />

          <MediaModal
            :show="openMediaModal"
            :records="mediaRecords"
            :field-name="mediaModalFieldName"
            @close="closeMediaModal"
          />

          <RelationModal
            :show="openRelationModal"
            :records="relationRecords"
            :field-name="relationModalFieldName"
            @close="closeRelationModal"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";
import { ref, computed, watch, onMounted, getCurrentInstance, useAttrs } from "vue";
import { useRoute } from "vue-router";

import { formatDate } from "@/utils/filters";
import { useAdminStore } from "@/admin/store";
import { localeDisplayName } from "@/admin/utils/locales";
import { __ } from "@/admin/translations/engine";

import UiTable from "@/components/Table/index.js";
import UiModal from "@/components/Modal.vue";
import UiButton from "@/components/Button.vue";
import UiDropdown from "@/components/Dropdown.vue";

import ProjectHeader from "@/admin/components/ProjectHeader.vue";
import ContentSidebar from "@/admin/components/ContentSidebar.vue";
import projectBreadcrumb from "@/admin/mixins/projectBreadcrumb";

import ContentTable from "./ContentTable.vue";
import TextModal from "./TextModal.vue";
import MediaModal from "./MediaModal.vue";
import RelationModal from "./RelationModal.vue";

/**
 * Normalize a project's `locales` attribute (comma-separated string or
 * array) into a clean array of locale codes.
 */
function parseLocales(value) {
  if (Array.isArray(value)) return value.filter((l) => typeof l === "string" && l !== "");
  if (typeof value === "string") {
    return value
      .split(",")
      .map((l) => l.trim())
      .filter((l) => l !== "");
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
  let localeFilterInit = "";
  let projectLocalesInit = [];

  if (projectId) {
    localeStorageKeyInit = "aine_admin_content_locale_" + projectId;
    projectLocalesInit = parseLocales(store.currentProject.locales);
    const defaultLocale = store.currentProject.default_locale || projectLocalesInit[0] || "en";

    let saved = null;
    try {
      saved = localStorage.getItem(localeStorageKeyInit);
    } catch (error) {
      saved = null;
    }
    localeFilterInit =
      saved !== null && (saved === "all" || projectLocalesInit.includes(saved)) ? saved : defaultLocale;
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
  const search = ref("");
  const localeFilter = ref(localeFilterInit);
  const projectLocales = ref(projectLocalesInit);
  const localeStorageKey = ref(localeStorageKeyInit);
  const columns = ref({});
  const listOptions = ref({
    orderBy: "created_at",
    criteria: "ASC",
    sortByMeta: 0,
    getItems: "all",
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

  const isComments = computed(() => collection.value && collection.value.kind === "comment");

  const localeOptions = computed(() => {
    const options = projectLocales.value.map((l) => {
      const name = localeDisplayName(l) || l.toUpperCase();
      return { value: l, label: name + " (" + l + ")" };
    });
    options.push({ value: "all", label: __("All Languages") });
    return options;
  });

  const paginationInfo = computed(() =>
    __("{total} records, {from} - {to} showing", {
      total: content.value.total,
      from: content.value.from,
      to: content.value.to,
    }),
  );

  const hasAddSelectedListener = computed(() => attrs && attrs.onAddSelected);

  /* ---------------- Methods ---------------- */
  function safeOptions(field) {
    if (!field.options) return {};
    if (typeof field.options === "string") {
      try {
        return JSON.parse(field.options);
      } catch (e) {
        return {};
      }
    }
    return field.options;
  }

  function sanitizeHtml(html) {
    if (!html) return "";
    const doc = new DOMParser().parseFromString(html, "text/html");
    const dangerous = doc.querySelectorAll("script, iframe, object, embed, link, meta");
    dangerous.forEach((el) => el.remove());
    doc.querySelectorAll("*").forEach((el) => {
      for (const attr of Array.from(el.attributes)) {
        if (attr.name.startsWith("on") || attr.name === "srcdoc") {
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
    if (typeof page === "undefined") {
      page = 1;
    }

    const cid = collectionId();

    if (cid === undefined || route.params.project_id === undefined) {
      return;
    }

    axios
      .get(
        "content/" +
          route.params.project_id +
          "/" +
          cid +
          "?page=" +
          page +
          "&search=" +
          search.value +
          "&orderBy=" +
          listOptions.value.orderBy +
          "&cr=" +
          listOptions.value.criteria +
          "&sbm=" +
          listOptions.value.sortByMeta +
          "&each=" +
          each.value +
          "&getItems=" +
          listOptions.value.getItems +
          "&locale=" +
          localeFilter.value,
      )
      .then((response) => {
        project.value = response.data.project;
        collection.value = response.data.collection;
        content.value = response.data.content;
        form_count.value = response.data.forms;

        const locales = parseLocales(project.value.locales);
        projectLocales.value = locales;
        if (!localeStorageKey.value) {
          localeStorageKey.value = "aine_admin_content_locale_" + route.params.project_id;
        }
        if (localeFilter.value !== "all" && !locales.includes(localeFilter.value)) {
          localeFilter.value = project.value.default_locale || locales[0] || "en";
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
            (o) => o.project_id == route.params.project_id && o.collection_id == cid,
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
              (o) => o.project_id == route.params.project_id && o.collection_id == cid,
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
      listOptions.value.criteria = "ASC";
    } else {
      if (listOptions.value.criteria == null || listOptions.value.criteria == "DESC") {
        listOptions.value.criteria = "ASC";
      } else {
        listOptions.value.criteria = "DESC";
      }
    }
    listOptions.value.orderBy = field;

    listOptions.value.sortByMeta = meta;
    getContent(1);
  }

  function selectRecord(id) {
    if (relationType() === 1) {
      if (selected.value.includes(id)) {
        selected.value.splice(selected.value.findIndex((v) => v === id), 1);
      } else {
        selected.value = [];
        selected.value.push(id);
      }
    } else {
      if (selected.value.includes(id)) {
        selected.value.splice(selected.value.findIndex((v) => v === id), 1);
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
      url: "content/export/" + route.params.project_id + "/" + collectionId() + "?format=json",
      method: "GET",
      responseType: "blob",
    }).then((response) => {
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", "content-export.json");
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }

  function importContent(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("file", file);

    axios
      .post("content/import/" + route.params.project_id + "/" + collectionId(), formData)
      .then((response) => {
        instance?.proxy?.$toast.success(response.data.message || __("Content imported."));
        getContent();
      })
      .catch((error) => {
        if (error.response && error.response.data && error.response.data.message) {
          instance?.proxy?.$toast.error(error.response.data.message);
        }
      })
      .finally(() => {
        event.target.value = "";
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
      .post("content/get-selected-files/" + route.params.project_id, {
        data: files.split(","),
      })
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
    let options = typeof field.options === "string" ? JSON.parse(field.options) : field.options;
    if (options.relation === undefined) return;

    let data = {
      selected: value.split(","),
      collection_id: options.relation.collection,
    };

    await axios
      .post("content/get-selected-records/" + route.params.project_id, {
        data: data,
      })
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
        title: __("Are you sure"),
        text: __("you want to publish all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "content/publish-selected/" + route.params.project_id + "/" + route.params.col_id,
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Selected items has been published"));
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
        title: __("Are you sure"),
        text: __("you want to unpublish all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "content/unpublish-selected/" + route.params.project_id + "/" + route.params.col_id,
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Selected items has been unpublished"));
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function commentBulk(action) {
    const labels = {
      approve: __("approve all selected comments?"),
      spam: __("mark all selected comments as spam?"),
      trash: __("move all selected comments to the trash?"),
      restore: __("restore all selected comments?"),
      delete: __("delete all selected comments permanently?"),
    };

    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: labels[action] || __("continue?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post("content/comments/bulk/" + route.params.project_id, {
              action,
              ids: selected.value,
            })
            .then((response) => {
              instance?.proxy?.$toast.success(__("Comments updated"));
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
        title: __("Are you sure"),
        text: __("you want to move this comment to the trash?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post("content/comments/reject/" + route.params.project_id + "/" + item.id)
            .then((response) => {
              instance?.proxy?.$toast.success(__("Comment moved to the trash."));
              getContent();
            });
        }
      });
  }

  function moveToTrashContent(item) {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to move this item to the trash?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .delete(
              "content/move-to-trash/" +
                route.params.project_id +
                "/" +
                route.params.col_id +
                "/" +
                item.id,
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Content moved to the trash."));
              getContent();
            });
        }
      });
  }

  function moveToTrashSelected() {
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to move all selected items to the trash?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "content/move-to-trash-selected/" + route.params.project_id + "/" + route.params.col_id,
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Selected items has been moved to the trash"));
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function deleteSelected() {
    if (isComments.value) {
      commentBulk("delete");
      return;
    }
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to delete all selected items permanently?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "content/delete-selected/" + route.params.project_id + "/" + route.params.col_id,
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Selected items has been deleted"));
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function restoreSelected() {
    if (isComments.value) {
      commentBulk("restore");
      return;
    }
    instance?.proxy?.$swal
      .fire({
        title: __("Are you sure"),
        text: __("you want to restore all selected items?"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          axios
            .post(
              "content/restore-selected/" + route.params.project_id + "/" + route.params.col_id,
              { selected: selected.value },
            )
            .then((response) => {
              instance?.proxy?.$toast.success(__("Selected items has been restored"));
              getContent();
              selected.value = [];
              selectAll.value = false;
            });
        }
      });
  }

  function getUserNameInitials(name) {
    let initials = name.split(" ");

    if (initials.length > 1) {
      initials = initials.shift().charAt(0) + initials.pop().charAt(0);
    } else {
      initials = name.substring(0, 2);
    }

    return initials.toUpperCase();
  }

  function dateFormat(date) {
    return formatDate(date, "D MMM YYYY, H:mm");
  }

  /* ---------------- Lifecycle ---------------- */
  onMounted(() => {
    const cid = collectionId();
    if (cid !== undefined) {
      const filter = route.query.filter;
      if (filter && ["all", "published", "draft", "trashed"].includes(filter)) {
        listOptions.value.getItems = filter;
      }
      getContent();
    }
  });

  if (enableCollectionWatch) {
    watch(collectionId, (newVal) => {
      search.value = "";
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
    columns,
    listOptions,
    openTextModal,
    textRecord,
    textModalFieldName,
    openMediaModal,
    mediaRecords,
    mediaModalFieldName,
    selected,
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
    hasAddSelectedListener,
    // methods
    safeOptions,
    canProject,
    getContent,
    changeLocale,
    addSelected,
    exportContent,
    importContent,
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
    UiTable,
    UiModal,
    UiButton,
    UiDropdown,
    ContentTable,
    TextModal,
    MediaModal,
    RelationModal,
  },

  mixins: [projectBreadcrumb],

  setup() {
    const route = useRoute();
    const cl = useContentList({
      collectionId: () =>
        route.params.col_id !== undefined ? parseInt(route.params.col_id) : undefined,
      eachProp: 15,
      enableCollectionWatch: true,
      initialProject: () => useAdminStore().currentProject || {},
    });

    // Column definitions driving the shared UiTable (presentation layer only)
    const tableColumns = computed(() => {
      const cols = [];
      cols.push({
        field: "id",
        label: __("ID"),
        sortable: false,
        toggleable: false,
      });

      (cl.collection.value.fields || []).forEach((field) => {
        if (field.type === "password" || field.type === "json" || field.type === "block") {
          return;
        }
        cols.push({
          field: field.name,
          label: __(field.label),
          sortable: true,
          hidden: !cl.columns.value[field.name],
          toggleable: true,
        });
      });

      cols.push({
        field: "status",
        label: __("Status"),
        sortable: false,
        toggleable: false,
      });

      const timeCols = [
        { field: "created_at", label: __("Created At") },
        { field: "created_by", label: __("Created By") },
        { field: "updated_at", label: __("Updated At") },
        { field: "updated_by", label: __("Updated By") },
        { field: "published_at", label: __("Published At") },
        { field: "published_by", label: __("Published By") },
      ];
      timeCols.forEach((timeCol) => {
        cols.push({
          field: timeCol.field,
          label: timeCol.label,
          sortable: true,
          hidden: !cl.columns.value[timeCol.field],
          toggleable: true,
        });
      });

      if (cl.listOptions.value.getItems !== "trashed") {
        cols.push({
          field: "action",
          label: __("Action"),
          sortable: false,
          toggleable: false,
          sticky: true,
        });
      }
      return cols;
    });

    const onSortChange = (sorts) => {
      if (!sorts || !sorts.length) return;
      const sort = sorts[0];
      const field = sort.field;
      const criteria = sort.type === "desc" ? "DESC" : "ASC";

      const isMetaField =
        [
          "created_at",
          "created_by",
          "updated_at",
          "updated_by",
          "published_at",
          "published_by",
          "id",
          "status",
          "action",
        ].indexOf(field) === -1;
      const sortByMeta = isMetaField ? 1 : 0;

      if (cl.listOptions.value.orderBy !== field) {
        cl.listOptions.value.criteria = "ASC";
      } else {
        cl.listOptions.value.criteria = criteria;
      }
      cl.listOptions.value.orderBy = field;
      cl.listOptions.value.sortByMeta = sortByMeta;
      cl.getContent(1);
    };

    const onSelectedRowsChange = ({ selectedRows }) => {
      cl.selected.value = selectedRows.map((r) => r.id);
    };

    const onPageChange = ({ currentPage }) => {
      cl.getContent(currentPage);
    };

    const onPerPageChange = ({ currentPerPage }) => {
      cl.each.value = Number(currentPerPage);
      cl.getContent(1);
    };

    const onColumnToggle = (field, isVisible) => {
      cl.columns.value[field] = isVisible;
      cl.changeColumnSettings();
    };

    const onTableSearch = (searchTerm) => {
      cl.search.value = searchTerm;
      cl.getContent(1);
    };

    const onLocaleChange = (value) => {
      cl.localeFilter.value = value;
      cl.changeLocale();
    };

    return {
      ...cl,
      tableColumns,
      onSortChange,
      onSelectedRowsChange,
      onPageChange,
      onPerPageChange,
      onColumnToggle,
      onTableSearch,
      onLocaleChange,
    };
  },
};
</script>
