<template>
<tr class="bg-gray-50">
  <th
    v-if="headerRow.mode === 'span'"
    class="px-6 py-3 text-left text-sm font-medium text-gray-700"
    :colspan="fullColspan"
  >
    <template v-if="selectAllByGroup">
      <slot name="table-header-group-select" :columns="columns" :row="headerRow">
        <input
          type="checkbox"
          :checked="allSelected"
          @change="toggleSelectGroup($event)"
          class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2"
        />
      </slot>
    </template>
    <span
      @click="collapsable ? $emit('expand', !headerRow.isExpanded) : () => {}"
      :class="{ 'cursor-pointer': collapsable }"
    >
      <span v-if="collapsable" class="inline-block mr-2 transition-transform" :class="{ 'rotate-90': headerRow.isExpanded }">
        &#9654;
      </span>
      <slot :row="headerRow" name="table-header-row">
        <span v-if="headerRow.html" v-html="headerRow.label"></span>
        <span v-else>{{ headerRow.label }}</span>
      </slot>
    </span>
  </th>

  <th v-if="headerRow.mode !== 'span' && lineNumbers" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"></th>
  <th v-if="headerRow.mode !== 'span' && selectable" class="px-4 py-2 text-center w-10">
    <template v-if="selectAllByGroup">
      <slot name="table-header-group-select" :columns="columns" :row="headerRow">
        <input
          type="checkbox"
          :checked="allSelected"
          @change="toggleSelectGroup($event)"
          class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
        />
      </slot>
    </template>
  </th>

  <template v-for="(column, i) in columns" :key="i">
    <th
      v-if="headerRow.mode !== 'span' && !column.hidden"
      class="px-6 py-3 text-left text-sm font-medium text-gray-700"
      :class="getClasses(i, 'td')"
      @click="columnCollapsable(i) ? $emit('expand', !headerRow.isExpanded) : () => {}"
    >
      <span
        v-if="columnCollapsable(i)"
        class="inline-block mr-2 transition-transform cursor-pointer"
        :class="{ 'rotate-90': headerRow.isExpanded }"
      >
        &#9654;
      </span>
      <slot name="table-header-row" :row="headerRow" :column="column" :formattedRow="formattedRow(headerRow, true)">
        <span v-if="!column.html">{{ collectFormatted(headerRow, column, true) }}</span>
        <span v-if="column.html" v-html="collectFormatted(headerRow, column, true)"></span>
      </slot>
    </th>
  </template>
</tr>
</template>

<script>
export default {
  name: 'HeaderRow',

  props: {
    headerRow: { type: Object },
    columns: { type: Array },
    lineNumbers: { type: Boolean },
    selectable: { type: Boolean },
    selectAllByGroup: { type: Boolean },
    collapsable: { type: [Boolean, Number], default: false },
    collectFormatted: { type: Function },
    formattedRow: { type: Function },
    getClasses: { type: Function },
    fullColspan: { type: Number },
    groupIndex: { type: Number },
  },

  emits: ['expand', 'select-group-change'],

  computed: {
    allSelected() {
      const { headerRow } = this;
      return headerRow.children.filter((row) => row.isSelected).length === headerRow.children.length;
    },
  },

  methods: {
    columnCollapsable(currentIndex) {
      if (this.collapsable === true) {
        return currentIndex === 0;
      }
      return currentIndex === this.collapsable;
    },

    toggleSelectGroup(event) {
      this.$emit('select-group-change', {
        groupIndex: this.groupIndex,
        checked: event.target.checked,
      });
    },
  },
};
</script>
