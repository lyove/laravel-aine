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
 *     <template #table-row="props">
 *       <span v-if="props.column.field === 'name'">{{ props.row.name }}</span>
 *     </template>
 *   </ui-table>
 */

import Table from './Table.vue';

export default Table;

// Named export for ES modules
export { Table };
