<template>
<thead class="bg-gray-100">
  <tr>
    <th scope="col" v-if="lineNumbers" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">
      #
    </th>
    <th scope="col" v-if="selectable" class="px-4 py-3 text-center w-10">
      <input
        type="checkbox"
        :checked="allSelected"
        :indeterminate.prop="allSelectedIndeterminate"
        @change="toggleSelectAll"
        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
      />
    </th>
    <th scope="col" v-if="expandRowsEnabled" class="px-4 py-3 text-center w-10">
      <a href="" @click.prevent="toggleExpandRowsAll" class="text-gray-500 hover:text-gray-700">
        (+)
      </a>
    </th>
    <template v-for="(column, index) in columns" :key="index">
      <th
        v-if="!column.hidden"
        scope="col"
        :title="column.tooltip"
        :class="getHeaderClasses(column, index)"
        :style="columnStyles[index]"
        :aria-sort="getColumnSortLong(column)"
        :aria-controls="`col-${index}`"
        @click="onHeaderClick($event, column)"
      >
        <div class="flex items-center justify-between">
          <slot name="table-column" :column="column">
            <span>{{ column.label }}</span>
          </slot>
          <!-- Sort icons -->
          <span v-if="isSortableColumn(column)" class="ml-2">
            <i v-if="getColumnSort(column) === 'asc'" class="fa fa-sort-amount-up-alt text-indigo-500"></i>
            <i v-else-if="getColumnSort(column) === 'desc'" class="fa fa-sort-amount-down-alt text-indigo-500"></i>
            <i v-else class="fa fa-sort text-gray-400"></i>
          </span>
        </div>
      </th>
    </template>
  </tr>
  <ui-filter-row
    ref="filter-row"
    @filter-changed="filterRows"
    :global-search-enabled="searchEnabled"
    :line-numbers="lineNumbers"
    :expand-rows-enabled="expandRowsEnabled"
    :selectable="selectable"
    :columns="columns"
    :mode="mode"
    :typed-columns="typedColumns"
  >
    <template #column-filter="slotProps">
      <slot
        name="column-filter"
        :column="slotProps.column"
        :updateFilters="slotProps.updateFilters"
      ></slot>
    </template>
  </ui-filter-row>
</thead>
</template>

<script>
import FilterRow from './FilterRow.vue';
import { primarySort, secondarySort } from './utils/sort';

export default {
  name: 'TableHeader',

  components: {
    'ui-filter-row': FilterRow,
  },

  props: {
    lineNumbers: { default: false, type: Boolean },
    selectable: { default: false, type: Boolean },
    allSelected: { default: false, type: Boolean },
    allSelectedIndeterminate: { default: false, type: Boolean },
    columns: { type: Array },
    expandRowsEnabled: { default: false, type: Boolean },
    mode: { type: String },
    typedColumns: {},
    sortable: { type: Boolean },
    multipleColumnSort: { type: Boolean, default: true },
    getClasses: { type: Function },
    searchEnabled: { type: Boolean },
    tableRef: {},
    paginated: {},
  },

  emits: [
    'toggle-select-all',
    'toggle-expand-rows-all',
    'sort-change',
    'filter-changed',
  ],

  watch: {
    columns: {
      handler() {
        this.setColumnStyles();
      },
      immediate: true,
      deep: true,
    },
    tableRef: {
      handler() {
        this.setColumnStyles();
      },
      immediate: true,
    },
    paginated: {
      handler() {
        if (this.tableRef) {
          this.setColumnStyles();
        }
      },
      deep: true,
    },
  },

  data() {
    return {
      columnStyles: [],
      sorts: [],
      ro: null,
    };
  },

  methods: {
    reset() {
      this.$refs['filter-row'].reset(true);
    },

    toggleExpandRowsAll() {
      this.$emit('toggle-expand-rows-all');
    },

    toggleSelectAll() {
      this.$emit('toggle-select-all');
    },

    isSortableColumn(column) {
      const { sortable } = column;
      const isSortable = typeof sortable === 'boolean' ? sortable : this.sortable;
      return isSortable;
    },

    onHeaderClick(e, column) {
      this.sort(e, column);
    },

    sort(e, column) {
      if (!this.isSortableColumn(column)) return;

      if (e.shiftKey && this.multipleColumnSort) {
        this.sorts = secondarySort(this.sorts, column);
      } else {
        this.sorts = primarySort(this.sorts, column);
      }
      this.$emit('sort-change', this.sorts);
    },

    setInitialSort(sorts) {
      this.sorts = sorts;
      this.$emit('sort-change', this.sorts);
    },

    getColumnSort(column) {
      for (let i = 0; i < this.sorts.length; i += 1) {
        if (this.sorts[i].field === column.field) {
          return this.sorts[i].type || 'asc';
        }
      }
      return null;
    },

    getColumnSortLong(column) {
      return this.getColumnSort(column) === 'asc' ? 'ascending' : 'descending';
    },

    getHeaderClasses(column, index) {
      const classes = {
        'px-6': true,
        'py-3': true,
        'text-left': true,
        'text-xs': true,
        'font-medium': true,
        'text-gray-500': true,
        'uppercase': true,
        'whitespace-nowrap': true,
      };

      // Sortable cursor
      if (this.isSortableColumn(column)) {
        classes['cursor-pointer'] = true;
        classes['hover:bg-gray-200'] = true;
      }

      // Sticky column support
      if (column.sticky) {
        classes['sticky'] = true;
        classes['right-0'] = true;
        classes['bg-gray-100'] = true;
        classes['shadow-[-2px_0_5px_rgba(0,0,0,0.1)]'] = true;
        classes['z-10'] = true;
      }

      // Custom thClass
      if (typeof column.thClass === 'function') {
        const customClass = column.thClass(index);
        if (customClass) classes[customClass] = true;
      } else if (typeof column.thClass === 'string') {
        classes[column.thClass] = true;
      }

      // Sort state classes
      const sortState = this.getColumnSort(column);
      if (sortState === 'asc') {
        classes['text-indigo-600'] = true;
      } else if (sortState === 'desc') {
        classes['text-indigo-600'] = true;
      }

      return classes;
    },

    filterRows(columnFilters) {
      this.$emit('filter-changed', columnFilters);
    },

    getWidthStyle(dom) {
      if (window && window.getComputedStyle && dom) {
        const cellStyle = window.getComputedStyle(dom, null);
        return { width: cellStyle.width };
      }
      return { width: 'auto' };
    },

    setColumnStyles() {
      const colStyles = [];
      for (let i = 0; i < this.columns.length; i++) {
        if (this.tableRef) {
          let skip = 0;
          if (this.lineNumbers) skip++;
          if (this.selectable) skip++;
          if (this.expandRowsEnabled) skip++;
          const cell = this.tableRef.rows[0]?.cells[i + skip];
          if (cell) {
            colStyles.push(this.getWidthStyle(cell));
          } else {
            colStyles.push({
              width: this.columns[i].width || 'auto',
            });
          }
        } else {
          colStyles.push({
            minWidth: this.columns[i].width || 'auto',
            maxWidth: this.columns[i].width || 'auto',
            width: this.columns[i].width || 'auto',
          });
        }
      }
      this.columnStyles = colStyles;
    },
  },

  mounted() {
    this.$nextTick(() => {
      if ('ResizeObserver' in window) {
        this.ro = new ResizeObserver(() => {
          this.setColumnStyles();
        });
        this.ro.observe(this.$parent.$el);

        if (this.tableRef) {
          const headerEl = this.$parent.$refs['table-header-primary']?.$el;
          if (headerEl) {
            Array.from(headerEl.children[0]?.children || []).forEach((header) => {
              this.ro.observe(header);
            });
          }
        }
      }
    });
  },

  beforeUnmount() {
    if (this.ro) {
      this.ro.disconnect();
    }
  },
};
</script>
