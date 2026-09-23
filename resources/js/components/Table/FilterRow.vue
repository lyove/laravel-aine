<template>
<tr v-if="hasFilterRow">
  <th v-if="expandRowsEnabled"></th>
  <th v-if="lineNumbers"></th>
  <th v-if="selectable"></th>
  <template v-for="(column, index) in columns" :key="index">
    <th v-if="!column.hidden" class="px-4 py-2 bg-gray-50">
      <slot name="column-filter" :column="column" :updateFilters="updateSlotFilter">
        <div v-if="isFilterable(column)">
          <!-- Text input filter -->
          <input
            v-if="!isDropdown(column)"
            :name="getName(column)"
            type="text"
            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
            :placeholder="getPlaceholder(column)"
            :value="columnFilters[fieldKey(column.field)]"
            @keyup.enter="updateFiltersOnEnter(column, $event.target.value)"
            @input="updateFiltersOnKeyup(column, $event.target.value)"
          />

          <!-- Dropdown filter (array of primitives) -->
          <select
            v-if="isDropdownArray(column)"
            :name="getName(column)"
            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
            :value="columnFilters[fieldKey(column.field)]"
            @change="updateFiltersImmediately(column.field, $event.target.value)"
          >
            <option value="" key="-1">{{ getPlaceholder(column) }}</option>
            <option
              v-for="(option, i) in column.filterOptions.filterDropdownItems"
              :key="i"
              :value="option"
            >
              {{ option }}
            </option>
          </select>

          <!-- Dropdown filter (array of objects) -->
          <select
            v-if="isDropdownObjects(column)"
            :name="getName(column)"
            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
            :value="columnFilters[fieldKey(column.field)]"
            @change="updateFiltersImmediately(column.field, $event.target.value)"
          >
            <option value="" key="-1">{{ getPlaceholder(column) }}</option>
            <option
              v-for="(option, i) in column.filterOptions.filterDropdownItems"
              :key="i"
              :value="option.value"
            >
              {{ option.text }}
            </option>
          </select>
        </div>
      </slot>
    </th>
  </template>
</tr>
</template>

<script>
export default {
  name: 'FilterRow',

  props: [
    'lineNumbers',
    'columns',
    'expandRowsEnabled',
    'typedColumns',
    'globalSearchEnabled',
    'selectable',
    'mode',
  ],

  emits: ['filter-changed'],

  watch: {
    columns: {
      handler(newValue, oldValue) {
        this.populateInitialFilters();
      },
      deep: true,
      immediate: true,
    },
  },

  data() {
    return {
      columnFilters: {},
      timer: null,
    };
  },

  computed: {
    hasFilterRow() {
      for (let i = 0; i < this.columns.length; i++) {
        const col = this.columns[i];
        if (col.filterOptions && col.filterOptions.enabled) {
          return true;
        }
      }
      return false;
    },
  },

  methods: {
    fieldKey(field) {
      if (typeof field === 'function' && field.name) {
        return field.name;
      }
      return field;
    },

    reset(emitEvent = false) {
      this.columnFilters = {};
      if (emitEvent) {
        this.$emit('filter-changed', this.columnFilters);
      }
    },

    isFilterable(column) {
      return column.filterOptions && column.filterOptions.enabled;
    },

    isDropdown(column) {
      return (
        this.isFilterable(column) &&
        column.filterOptions.filterDropdownItems &&
        column.filterOptions.filterDropdownItems.length
      );
    },

    isDropdownObjects(column) {
      return (
        this.isDropdown(column) &&
        typeof column.filterOptions.filterDropdownItems[0] === 'object'
      );
    },

    isDropdownArray(column) {
      return (
        this.isDropdown(column) &&
        typeof column.filterOptions.filterDropdownItems[0] !== 'object'
      );
    },

    getPlaceholder(column) {
      return (
        (this.isFilterable(column) && column.filterOptions.placeholder) ||
        `Filter ${column.label}`
      );
    },

    getName(column) {
      return `filter-${this.fieldKey(column.field)}`;
    },

    updateFiltersOnEnter(column, value) {
      if (this.timer) clearTimeout(this.timer);
      this.updateFiltersImmediately(column.field, value);
    },

    updateFiltersOnKeyup(column, value) {
      if (column.filterOptions.trigger === 'enter') return;
      this.updateFilters(column, value);
    },

    updateSlotFilter(column, value) {
      let fieldToFilter = column.filterOptions.slotFilterField || column.field;
      if (typeof column.filterOptions.formatValue === 'function') {
        value = column.filterOptions.formatValue(value);
      }
      this.updateFiltersImmediately(fieldToFilter, value);
    },

    updateFilters(column, value) {
      if (this.timer) clearTimeout(this.timer);
      this.timer = setTimeout(() => {
        this.updateFiltersImmediately(column.field, value);
      }, 400);
    },

    updateFiltersImmediately(field, value) {
      this.columnFilters[this.fieldKey(field)] = value;
      this.$emit('filter-changed', this.columnFilters);
    },

    populateInitialFilters() {
      for (let i = 0; i < this.columns.length; i++) {
        const col = this.columns[i];
        if (
          this.isFilterable(col) &&
          typeof col.filterOptions.filterValue !== 'undefined' &&
          col.filterOptions.filterValue !== null
        ) {
          this.columnFilters[this.fieldKey(col.field)] = col.filterOptions.filterValue;
        }
      }
      this.$emit('filter-changed', this.columnFilters);
    },
  },
};
</script>
