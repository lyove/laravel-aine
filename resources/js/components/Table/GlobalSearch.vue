<template>
  <div v-if="showControlBar" class="flex items-center justify-between mb-4">
    <div v-if="searchEnabled" class="relative flex-1 max-w-md">
      <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
        <i class="fas fa-search text-gray-400"></i>
      </div>
      <input
        type="text"
        class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
        :placeholder="globalSearchPlaceholder"
        :value="value"
        @input="updateValue($event.target.value)"
        @keyup.enter="entered($event.target.value)"
      />
    </div>
    <div v-if="$slots['internal-table-actions']">
      <slot name="internal-table-actions"></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GlobalSearch',

  props: ['value', 'searchEnabled', 'globalSearchPlaceholder'],

  emits: ['input', 'keyup', 'enter'],

  data() {
    return {
      globalSearchTerm: null,
    };
  },

  computed: {
    showControlBar() {
      if (this.searchEnabled) return true;
      if (this.$slots && this.$slots['internal-table-actions']) return true;
      return false;
    },
  },

  methods: {
    updateValue(value) {
      this.$emit('input', value);
      this.$emit('keyup', value);
    },
    entered(value) {
      this.$emit('enter', value);
    },
  },
};
</script>
