/**
 * UiTable - A feature-rich data table component
 *
 * Features:
 * - Sortable columns with multi-column support
 * - Pagination with page numbers
 * - Global search
 * - Per-column filtering
 * - Row selection (checkbox)
 * - Row expansion
 * - Row grouping
 * - Column toggle (show/hide columns)
 * - Sticky columns
 * - Compact mode
 * - RTL support
 * - Custom cell rendering via slots
 * - Extensible toolbar and filter slots
 *
 * Available Slots:
 * - #table-row: Custom cell rendering
 * - #table-column: Custom column header
 * - #table-toolbar: Content above the search bar (title, action buttons, etc.)
 * - #table-filters: Content between search and table (status tabs, etc.)
 * - #table-actions: Actions inside the search bar area
 * - #selected-row-actions: Batch actions shown when rows are selected
 * - #row-details: Expanded row content
 * - #emptystate: Empty state content
 *
 * Usage:
 *   import UiTable from '@/components/Table';
 *
 *   <ui-table
 *     :columns="columns"
 *     :rows="rows"
 *     mode="remote"
 *     :total-rows="total"
 *     :pagination-options="{ enabled: true, perPage: 15 }"
 *     :sort-options="{ enabled: true }"
 *     :select-options="{ enabled: true }"
 *     :search-options="{ enabled: true }"
 *     :show-column-toggle="true"
 *     @sort-change="onSortChange"
 *     @page-change="onPageChange"
 *     @column-toggle="onColumnToggle"
 *   >
 *     <!-- Toolbar area: title, buttons -->
 *     <template #table-toolbar>
 *       <div class="flex justify-between mb-4">
 *         <h2>My Table</h2>
 *         <button>Add New</button>
 *       </div>
 *     </template>
 *
 *     <!-- Filters area: status tabs -->
 *     <template #table-filters>
 *       <div class="flex space-x-2 mb-2">
 *         <button>All (10)</button>
 *         <button>Active (8)</button>
 *       </div>
 *     </template>
 *
 *     <!-- Batch actions when rows selected -->
 *     <template #selected-row-actions>
 *       <button @click="deleteSelected">Delete</button>
 *     </template>
 *
 *     <!-- Cell rendering -->
 *     <template #table-row="props">
 *       <span v-if="props.column.field === 'name'">{{ props.row.name }}</span>
 *     </template>
 *   </ui-table>
 */

import Table from './Table.vue';

export default Table;

// Named export for ES modules
export { Table };
