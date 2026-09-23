<template>
  <div class="flex items-center justify-between mt-4">
    <!-- Per page dropdown -->
    <div v-if="perPageDropdownEnabled" class="flex items-center">
      <label :for="id" class="mr-2 text-sm text-gray-700">{{ rowsPerPageText }}:</label>
      <select
        :id="id"
        autocomplete="off"
        name="perPageSelect"
        class="text-sm border border-gray-300 rounded px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500"
        v-model="currentPerPage"
        @change="perPageChanged"
      >
        <option v-for="(option, idx) in rowsPerPageOptions" :key="idx" :value="option">
          {{ option }}
        </option>
        <option v-if="paginateDropdownAllowAll" :value="total">{{ allText }}</option>
      </select>
    </div>

    <!-- Page info (inline, merged from PaginationPageInfo) -->
    <div class="flex-1 flex justify-center text-sm text-gray-700">
      <div v-if="infoFn">
        {{ infoFn(pageInfoParams) }}
      </div>
      <form v-else-if="mode === 'pages'" @submit.prevent>
        <label :for="pageInfoId" class="flex items-center">
          <span>{{ pageText }}</span>
          <input
            :id="pageInfoId"
            aria-describedby="change-page-hint"
            aria-controls="table"
            class="mx-2 w-16 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
            type="text"
            @keyup.enter.stop="changePageFromInput"
            :value="currentPage"
          />
          <span>{{ pageInfoText }}</span>
        </label>
        <span id="change-page-hint" style="display: none;">
          Type a page number and press Enter to change the page.
        </span>
      </form>
      <div v-else>
        {{ recordInfo }}
      </div>
    </div>

    <!-- Page navigation -->
    <nav class="flex items-center space-x-1">
      <!-- Previous button -->
      <button
        type="button"
        class="px-3 py-1 text-sm border border-gray-300 rounded-l-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="!prevIsPossible"
        @click.prevent.stop="previousPage"
      >
        <span aria-hidden="true" :class="{ 'rotate-180': rtl }">&#8592;</span>
        <span class="ml-1">{{ prevText }}</span>
      </button>

      <!-- Page numbers -->
      <template v-for="page in visiblePages" :key="page">
        <span v-if="page === '...'" class="px-2 py-1 text-sm text-gray-500">...</span>
        <button
          v-else
          type="button"
          class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50"
          :class="{ 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700': page === currentPage }"
          @click.prevent="changePage(page)"
        >
          {{ page }}
        </button>
      </template>

      <!-- Next button -->
      <button
        type="button"
        class="px-3 py-1 text-sm border border-gray-300 rounded-r-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="!nextIsPossible"
        @click.prevent.stop="nextPage"
      >
        <span>{{ nextText }}</span>
        <span class="ml-1" aria-hidden="true" :class="{ 'rotate-180': rtl }">&#8594;</span>
      </button>
    </nav>
  </div>
</template>

<script>
import { PAGINATION_MODES, DEFAULT_ROWS_PER_PAGE_DROPDOWN } from './utils/constants';

export default {
  name: 'Pagination',

  props: {
    styleClass: { default: '' },
    total: { default: null },
    perPage: {},
    rtl: { default: false },
    perPageDropdownEnabled: { default: true },
    customRowsPerPageDropdown: { default: () => [] },
    paginateDropdownAllowAll: { default: true },
    mode: { default: PAGINATION_MODES.Records },
    nextText: { default: 'Next' },
    prevText: { default: 'Prev' },
    rowsPerPageText: { default: 'Rows per page' },
    ofText: { default: 'of' },
    pageText: { default: 'page' },
    allText: { default: 'All' },
    infoFn: { default: null },
    // Number of page buttons to show on each side of current page
    pageRange: { default: 2, type: Number },
  },

  data() {
    return {
      id: this.getId(),
      pageInfoId: this.getPageInfoId(),
      currentPage: 1,
      prevPage: 0,
      currentPerPage: 10,
      rowsPerPageOptions: [],
    };
  },

  watch: {
    perPage: {
      handler(newValue, oldValue) {
        this.handlePerPage();
        this.perPageChanged(oldValue);
      },
      immediate: true,
    },
    customRowsPerPageDropdown: {
      handler() {
        this.handlePerPage();
      },
      deep: true,
    },
    total: {
      handler(newValue, oldValue) {
        if (this.rowsPerPageOptions.indexOf(this.currentPerPage) === -1) {
          this.currentPerPage = newValue;
        }
      },
    },
  },

  computed: {
    pagesCount() {
      const total = this.total || 0;
      if (total === 0 || this.currentPerPage <= 0) return 1;
      const quotient = Math.floor(total / this.currentPerPage);
      const remainder = total % this.currentPerPage;
      return remainder === 0 ? quotient : quotient + 1;
    },

    nextIsPossible() {
      return this.currentPage < this.pagesCount;
    },

    prevIsPossible() {
      return this.currentPage > 1;
    },

    // Calculate visible page numbers with ellipsis
    visiblePages() {
      const pages = [];
      const total = this.pagesCount;
      const current = this.currentPage;
      const range = this.pageRange;

      if (total <= 1) return pages;

      // Always show first page
      pages.push(1);

      // Calculate start and end of visible range
      let start = Math.max(2, current - range);
      let end = Math.min(total - 1, current + range);

      // Adjust range if near edges
      if (current - range <= 2) {
        end = Math.min(total - 1, range * 2 + 1);
      }
      if (current + range >= total - 1) {
        start = Math.max(2, total - range * 2 - 1);
      }

      // Add ellipsis before range
      if (start > 2) {
        pages.push('...');
      }

      // Add pages in range
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      // Add ellipsis after range
      if (end < total - 1) {
        pages.push('...');
      }

      // Always show last page (if more than 1 page)
      if (total > 1) {
        pages.push(total);
      }

      return pages;
    },

    // Page info computed properties (merged from PaginationPageInfo)
    firstRecordOnPage() {
      return ((this.currentPage - 1) * this.currentPerPage) + 1;
    },

    lastRecordOnPage() {
      return Math.min(this.total || 0, this.currentPage * this.currentPerPage);
    },

    recordInfo() {
      let first = this.firstRecordOnPage;
      const last = this.lastRecordOnPage;
      if (last === 0) {
        first = 0;
      }
      return `${first} - ${last} ${this.ofText} ${this.total || 0}`;
    },

    pageInfoText() {
      return `${this.ofText} ${this.pagesCount}`;
    },

    pageInfoParams() {
      let first = this.firstRecordOnPage;
      const last = this.lastRecordOnPage;
      if (last === 0) {
        first = 0;
      }
      return {
        firstRecordOnPage: first,
        lastRecordOnPage: last,
        totalRecords: this.total || 0,
        currentPage: this.currentPage,
        totalPages: this.pagesCount,
      };
    },
  },

  methods: {
    getId() {
      return `pagination-select-${Math.floor(Math.random() * Date.now())}`;
    },

    getPageInfoId() {
      return `page-input-${Math.floor(Math.random() * Date.now())}`;
    },

    changePage(pageNumber, emit = true) {
      const total = this.total || 0;
      if (pageNumber > 0 && pageNumber <= this.pagesCount) {
        this.prevPage = this.currentPage;
        this.currentPage = pageNumber;
        this.pageChanged(emit);
      }
    },

    changePageFromInput(event) {
      const value = parseInt(event.target.value, 10);

      if (Number.isNaN(value) || value > this.pagesCount || value < 1) {
        event.target.value = this.currentPage;
        return false;
      }

      event.target.value = value;
      this.changePage(value);
    },

    nextPage() {
      if (this.nextIsPossible) {
        this.prevPage = this.currentPage;
        ++this.currentPage;
        this.pageChanged();
      }
    },

    previousPage() {
      if (this.prevIsPossible) {
        this.prevPage = this.currentPage;
        --this.currentPage;
        this.pageChanged();
      }
    },

    pageChanged(emit = true) {
      const payload = {
        currentPage: this.currentPage,
        prevPage: this.prevPage,
      };
      if (!emit) payload.noEmit = true;
      this.$emit('page-changed', payload);
    },

    perPageChanged(oldValue) {
      if (oldValue) {
        this.$emit('per-page-changed', { currentPerPage: this.currentPerPage });
      }
      this.changePage(1, false);
    },

    handlePerPage() {
      if (
        this.customRowsPerPageDropdown !== null &&
        Array.isArray(this.customRowsPerPageDropdown) &&
        this.customRowsPerPageDropdown.length !== 0
      ) {
        this.rowsPerPageOptions = JSON.parse(JSON.stringify(this.customRowsPerPageDropdown));
      } else {
        this.rowsPerPageOptions = JSON.parse(JSON.stringify(DEFAULT_ROWS_PER_PAGE_DROPDOWN));
      }

      if (this.perPage) {
        this.currentPerPage = this.perPage;
        let found = false;
        for (let i = 0; i < this.rowsPerPageOptions.length; i++) {
          if (this.rowsPerPageOptions[i] === this.perPage) {
            found = true;
          }
        }
        if (!found && this.perPage !== -1) {
          this.rowsPerPageOptions.unshift(this.perPage);
        }
      } else {
        this.currentPerPage = 10;
      }
    },
  },
};
</script>
