<template>
	<div class="relative" :class="{ 'rtl': rtl, [theme]: theme }">
		<!-- Loading overlay -->
		<div v-if="isLoading" class="absolute w-full z-10 flex items-center justify-center py-8">
			<slot name="loadingContent">
				<span class="rounded px-4 py-2 bg-blue-100 text-blue-700">Loading...</span>
			</slot>
		</div>

		<div class="relative" :class="{ 'opacity-50 pointer-events-none': isLoading }">
			<!-- Toolbar slot: for title, action buttons, etc. -->
			<slot name="table-toolbar"></slot>

			<!-- Global search -->
			<ui-global-search
				v-on:keyup="searchTableOnKeyUp"
				v-on:enter="searchTableOnEnter"
				:value="globalSearchTerm"
				@input="globalSearchTerm = $event"
				:search-enabled="searchEnabled && externalSearchQuery == null"
				:global-search-placeholder="searchPlaceholder"
			>
				<template #internal-table-actions v-if="$slots['table-actions']">
					<slot name="table-actions"></slot>
				</template>
			</ui-global-search>

			<!-- Filters slot: for status tabs, additional filters, etc. -->
			<slot name="table-filters"></slot>

			<div
				v-if="selectable"
				class="bg-blue-50 text-blue-700 px-4 py-2 text-sm flex items-center justify-between"
				:class="selectionInfoClass"
			>
				<span v-if="selectedRowCount && !disableSelectInfo">
					{{ selectionInfo }}
					<a href="" @click.prevent="unselectAllInternal(true)" class="ml-2 underline">
						{{ clearSelectionText }}
					</a>
				</span>
				<span v-else>&nbsp;</span>
				<div>
					<slot name="selected-row-actions"></slot>
				</div>
			</div>

			<!-- Fixed header table -->
			<div v-if="fixedHeader" class="absolute z-10 overflow-x-auto">
				<table v-if="fixedHeader" :class="tableStyleClasses">
					<colgroup>
						<col
							v-for="(column, index) in visibleColumns"
							:key="index"
							:id="`col-${index}`"
						/>
					</colgroup>
					<ui-table-header
						ref="table-header-secondary"
						v-on:toggle-select-all="toggleSelectAll"
						v-on:toggle-expand-rows-all="toggleExpandRowsAll"
						v-on:sort-change="changeSort"
						@filter-changed="filterRows"
						:columns="visibleColumns"
						:line-numbers="lineNumbers"
						:selectable="selectable"
						:all-selected="allSelected"
						:all-selected-indeterminate="allSelectedIndeterminate"
						:mode="mode"
						:sortable="sortable"
						:multiple-column-sort="multipleColumnSort"
						:typed-columns="typedColumns"
						:getClasses="getClasses"
						:searchEnabled="searchEnabled"
						:paginated="paginated"
						:table-ref="$refs.table"
						:expand-rows-enabled="expandRowsEnabled"
					>
						<template #table-column="slotProps">
							<slot name="table-column" :column="slotProps.column">
								<span>{{ slotProps.column.label }}</span>
							</slot>
						</template>
						<template #column-filter="slotProps">
							<slot
								name="column-filter"
								:column="slotProps.column"
								:updateFilters="slotProps.updateFilters"
							></slot>
						</template>
					</ui-table-header>
				</table>
			</div>

			<!-- Main table -->
			<div class="relative">
				<!-- Column toggle button -->
				<div v-if="showColumnToggle && toggleableColumns.length > 0" class="absolute top-2 right-2 z-20">
					<ui-dropdown align="right" :closeable="false">
						<template #trigger>
							<ui-button color="white" class="border border-gray-200">
								<i class="fa fa-cog text-gray-700"></i>
							</ui-button>
						</template>

						<template #content>
							<div class="border border-gray-200 p-3">
								<div v-for="col in toggleableColumns" :key="col.field" class="flex items-start m-2">
									<div class="flex items-center h-5">
										<input
											:id="'col-' + col.field"
											:checked="columnVisibility[col.field] !== false"
											type="checkbox"
											class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
											@click="onColumnToggle(col.field)"
										/>
									</div>
									<div class="ml-3 text-sm">
										<label :for="'col-' + col.field" class="font-medium text-gray-700 cursor-pointer">
											{{ col.label }}
										</label>
									</div>
								</div>
							</div>
						</template>
					</ui-dropdown>
				</div>

				<div :class="{ 'w-full overflow-x-auto': responsive }" :style="wrapperStyles">
					<table ref="table" :class="tableStyles">
					<colgroup>
						<col
							v-for="(column, index) in visibleColumns"
							:key="index"
							:id="`col-${index}`"
						/>
					</colgroup>

					<!-- Table header -->
					<ui-table-header
						ref="table-header-primary"
						v-on:toggle-select-all="toggleSelectAll"
						v-on:toggle-expand-rows-all="toggleExpandRowsAll"
						v-on:sort-change="changeSort"
						@filter-changed="filterRows"
						:columns="visibleColumns"
						:line-numbers="lineNumbers"
						:selectable="selectable"
						:all-selected="allSelected"
						:all-selected-indeterminate="allSelectedIndeterminate"
						:mode="mode"
						:sortable="sortable"
						:multiple-column-sort="multipleColumnSort"
						:typed-columns="typedColumns"
						:getClasses="getClasses"
						:searchEnabled="searchEnabled"
						:expand-rows-enabled="expandRowsEnabled"
					>
						<template #table-column="slotProps">
							<slot name="table-column" :column="slotProps.column">
								<span>{{ slotProps.column.label }}</span>
							</slot>
						</template>
						<template #column-filter="slotProps">
							<slot
								name="column-filter"
								:column="slotProps.column"
								:updateFilters="slotProps.updateFilters"
							></slot>
						</template>
					</ui-table-header>

					<!-- Table body -->
					<tbody>
						<template v-for="(headerRow, hIndex) in paginated" :key="hIndex">
							<!-- Group header top -->
							<ui-header-row
								v-if="groupHeaderOnTop"
								@expand="toggleExpand(headerRow[rowKeyField])"
								:header-row="headerRow"
								:columns="visibleColumns"
								:line-numbers="lineNumbers"
								:selectable="selectable"
								:select-all-by-group="selectAllByGroup"
								:collapsable="groupOptions.collapsable"
								:collect-formatted="collectFormatted"
								:formatted-row="formattedRow"
								:class="getRowStyleClass(headerRow)"
								:get-classes="getClasses"
								:full-colspan="fullColspan"
								:groupIndex="hIndex"
								v-on:select-group-change="toggleSelectGroup($event, headerRow)"
							>
								<template v-if="hasHeaderRowTemplate" #table-header-row="slotProps">
									<slot
										name="table-header-row"
										:column="slotProps.column"
										:formattedRow="slotProps.formattedRow"
										:row="slotProps.row"
									></slot>
								</template>
							</ui-header-row>

							<!-- Data rows -->
							<template v-for="(row, index) in headerRow.children">
								<tr
									v-if="groupOptions.collapsable ? headerRow.isExpanded : true"
									:key="row.originalIndex"
									:class="getRowStyleClass(row)"
									@mouseenter="onMouseenter(row, index)"
									@mouseleave="onMouseleave(row, index)"
									@dblclick="onRowDoubleClicked(row, index, $event)"
									@click="onRowClicked(row, index, $event)"
									@auxclick="onRowAuxClicked(row, index, $event)"
								>
									<!-- Line numbers -->
									<th v-if="lineNumbers" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
										{{ getCurrentIndex(row.originalIndex) }}
									</th>

									<!-- Checkbox column -->
									<th
										v-if="selectable"
										@click.stop="onCheckboxClicked(row, index, $event)"
										class="px-4 py-3 text-center w-10"
									>
										<input
											type="checkbox"
											:disabled="row.isDisabled"
											:checked="row.isSelected"
											class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
										/>
									</th>

									<!-- Data columns -->
									<template v-for="(column, i) in visibleColumns">
										<td
											:key="i"
											v-if="column.field"
											@click="onCellClicked(row, column, index, $event)"
											:class="getCellClasses(i, 'td', row, column)"
											v-bind:data-label="compactMode ? column.label : undefined"
										>
											<slot
												name="table-row"
												:row="row"
												:column="column"
												:formattedRow="formattedRow(row)"
												:index="index"
												:expandedRow="expandedRowIndex === index"
											>
												<span v-if="!column.html">
													{{ collectFormatted(row, column) }}
												</span>
												<span v-else v-html="collect(row, column.field)"></span>
											</slot>
										</td>
									</template>
								</tr>

								<!-- Expanded row details -->
								<tr
									v-if="expandedRowIndex === index"
									:class="expandedRowDetailClasses"
									:key="'expanded-' + row.originalIndex"
								>
									<td :colspan="fullColspan">
										<slot
											name="row-details"
											:row="row"
											:formattedRow="formattedRow(row)"
											:index="index"
										></slot>
									</td>
								</tr>
							</template>

							<!-- Group header bottom -->
							<ui-header-row
								v-if="groupHeaderOnBottom"
								:header-row="headerRow"
								:columns="visibleColumns"
								:line-numbers="lineNumbers"
								:selectable="selectable"
								:select-all-by-group="selectAllByGroup"
								:collect-formatted="collectFormatted"
								:formatted-row="formattedRow"
								:get-classes="getClasses"
								:full-colspan="fullColspan"
								:groupIndex="hIndex"
								v-on:select-group-change="toggleSelectGroup($event, headerRow)"
							>
								<template v-if="hasHeaderRowTemplate" #table-header-row="slotProps">
									<slot
										name="table-header-row"
										:column="slotProps.column"
										:formattedRow="slotProps.formattedRow"
										:row="slotProps.row"
									></slot>
								</template>
							</ui-header-row>
						</template>

						<!-- Empty state -->
						<tr v-if="showEmptySlot">
							<td :colspan="fullColspan" class="text-center text-sm text-gray-500 py-8">
								<slot name="emptystate">
									No data for table
								</slot>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			</div>

			<!-- Footer actions -->
			<div v-if="hasFooterSlot" class="border-t border-gray-200 px-4 py-3">
				<slot name="table-actions-bottom"></slot>
			</div>

			<!-- Bottom pagination -->
			<slot
				v-if="paginate && paginateOnBottom"
				name="pagination-bottom"
				:pageChanged="pageChanged"
				:perPageChanged="perPageChanged"
				:total="totalRows || totalRowCount"
			>
				<ui-pagination
					ref="paginationBottom"
					@page-changed="pageChanged"
					@per-page-changed="perPageChanged"
					:perPage="perPage"
					:rtl="rtl"
					:total="totalRows || totalRowCount"
					:mode="paginationMode"
					:nextText="nextText"
					:prevText="prevText"
					:rowsPerPageText="rowsPerPageText"
					:perPageDropdownEnabled="paginationOptions.perPageDropdownEnabled"
					:customRowsPerPageDropdown="customRowsPerPageDropdown"
					:paginateDropdownAllowAll="paginateDropdownAllowAll"
					:ofText="ofText"
					:pageText="pageText"
					:allText="allText"
					:info-fn="paginationInfoFn"
				></ui-pagination>
			</slot>
		</div>
	</div>
</template>

<script>
import { DEFAULT_SORT_TYPE, SORT_TYPES } from "./utils/constants";
import isEqual from "lodash.isequal";
import defaultType from "./types/default";
import Pagination from "./Pagination.vue";
import GlobalSearch from "./GlobalSearch.vue";
import TableHeader from "./TableHeader.vue";
import HeaderRow from "./HeaderRow.vue";
import UiButton from "../Button.vue";
import UiDropdown from "../Dropdown.vue";

// Load data type modules
import * as CoreDataTypes from "./types/index";

const dataTypes = {};
const coreDataTypes = CoreDataTypes.default;
Object.keys(coreDataTypes).forEach((key) => {
	const compName = key.replace(/^\.\//, "").replace(/\.js/, "");
	dataTypes[compName] = coreDataTypes[key].default;
});

export default {
	name: "Table",

	components: {
		"ui-pagination": Pagination,
		"ui-global-search": GlobalSearch,
		"ui-table-header": TableHeader,
		"ui-header-row": HeaderRow,
		"ui-button": UiButton,
		"ui-dropdown": UiDropdown,
	},

	props: {
		isLoading: { default: null, type: Boolean },
		maxHeight: { default: null, type: String },
		fixedHeader: Boolean,
		theme: { default: "" },
		mode: { default: "local" },
		totalRows: {},
		styleClass: { default: "" },
		columns: { default: () => [] },
		rows: { default: () => [] },
		lineNumbers: Boolean,
		responsive: { default: true, type: Boolean },
		rtl: Boolean,
		rowStyleClass: { default: null, type: [Function, String] },
		compactMode: Boolean,
		enableRowExpand: { default: false, type: Boolean },

		// Column toggle - NEW FEATURE
		showColumnToggle: { default: false, type: Boolean },

		expandRowsOptions: {
			default() {
				return { enabled: false };
			},
		},

		groupOptions: {
			default() {
				return {
					enabled: false,
					collapsable: false,
					rowKey: null,
				};
			},
		},

		selectOptions: {
			default() {
				return {
					enabled: false,
					selectionInfoClass: "",
					selectionText: n => n + ' row' + (n !== 1 ? 's' : '') + ' selected',
					clearSelectionText: "clear",
					disableSelectInfo: false,
					selectAllByGroup: false,
					alwaysShowSelectionInfo: false,
				};
			},
		},

		sortOptions: {
			default() {
				return {
					enabled: true,
					multipleColumns: true,
					initialSortBy: {},
				};
			},
		},

		paginationOptions: {
			default() {
				return {
					enabled: false,
					perPage: 10,
					perPageDropdown: null,
					perPageDropdownEnabled: true,
					dropdownAllowAll: true,
					mode: "records",
					infoFn: null,
				};
			},
		},

		searchOptions: {
			default() {
				return {
					enabled: false,
					trigger: "enter",
					externalQuery: null,
					searchFn: null,
					placeholder: "Search Table",
				};
			},
		},

		expandedRowClasses: { default: "", type: String },
		expandedRowDetailClasses: { default: "", type: String },
	},

	data: () => ({
		tableLoading: false,
		nextText: "Next",
		prevText: "Previous",
		rowsPerPageText: "Rows per page",
		ofText: "of",
		allText: "All",
		pageText: "page",

		selectable: false,
		selectAllByPage: true,
		disableSelectInfo: false,
		selectionInfoClass: "",
		selectionText: n => n + ' row' + (n !== 1 ? 's' : '') + ' selected',
		clearSelectionText: "clear",
		alwaysShowSelectionInfo: false,

		maintainExpanded: true,
		expandedRowKeys: new Set(),

		sortable: true,
		defaultSortBy: null,
		multipleColumnSort: true,

		searchEnabled: false,
		searchTrigger: null,
		externalSearchQuery: null,
		searchFn: null,
		searchPlaceholder: "Search Table",
		searchSkipDiacritics: false,

		perPage: null,
		paginate: false,
		paginateOnBottom: true,
		customRowsPerPageDropdown: [],
		paginateDropdownAllowAll: true,
		paginationMode: "records",
		paginationInfoFn: null,

		currentPage: 1,
		currentPerPage: 10,
		sorts: [],
		globalSearchTerm: "",
		filteredRows: [],
		columnFilters: {},
		forceSearch: false,
		sortChanged: false,
		dataTypes: dataTypes || {},

		expandedRowIndex: null,
		expandRowsEnabled: false,

		// Column visibility state - NEW FEATURE
		columnVisibility: {},
	}),

	emits: [
		"select-all",
		"selected-rows-change",
		"search",
		"per-page-change",
		"page-change",
		"update:isLoading",
		"sort-change",
		"row-click",
		"row-dblclick",
		"row-aux-click",
		"cell-click",
		"row-mouseenter",
		"row-mouseleave",
		"column-filter",
		"column-toggle",
	],

	watch: {
		rows: {
			handler() {
				this.$emit("update:isLoading", false);
				this.filterRows(this.columnFilters, false);
			},
			deep: true,
			immediate: true,
		},

		selectOptions: {
			handler() {
				this.initializeSelect();
			},
			deep: true,
			immediate: true,
		},

		paginationOptions: {
			handler(newValue, oldValue) {
				if (!isEqual(newValue, oldValue)) {
					this.initializePagination();
				}
			},
			deep: true,
			immediate: true,
		},

		expandRowsOptions: {
			handler(newValue, oldValue) {
				this.initializeExpandRows();
			},
			deep: true,
			immediate: true,
		},

		searchOptions: {
			handler() {
				if (
					this.searchOptions.externalQuery !== undefined &&
					this.searchOptions.externalQuery !== this.searchTerm
				) {
					this.externalSearchQuery = this.searchOptions.externalQuery;
					this.handleSearch();
				}
				this.initializeSearch();
			},
			deep: true,
			immediate: true,
		},

		sortOptions: {
			handler(newValue, oldValue) {
				if (!isEqual(newValue, oldValue)) {
					this.initializeSort();
				}
			},
			deep: true,
		},

		selectedRows(newValue, oldValue) {
			if (!isEqual(newValue, oldValue)) {
				this.$emit("selected-rows-change", {
					selectedRows: this.selectedRows,
				});
			}
		},

		columns: {
			handler(newColumns) {
				this.initializeColumnVisibility(newColumns);
			},
			deep: true,
			immediate: true,
		},
	},

	computed: {
		tableStyles() {
			let classes = "min-w-full divide-y divide-gray-200";
			if (this.compactMode) classes += " ui-table-compact";
			return classes;
		},

		hasFooterSlot() {
			return !!this.$slots["table-actions-bottom"];
		},

		wrapperStyles() {
			return {
				overflow: "scroll-y",
				maxHeight: this.maxHeight ? this.maxHeight : "auto",
			};
		},

		rowKeyField() {
			return this.groupOptions.rowKey || "headerId";
		},

		hasHeaderRowTemplate() {
			return !!this.$slots["table-header-row"];
		},

		showEmptySlot() {
			if (!this.paginated.length) return true;
			if (
				this.paginated[0].label === "no groups" &&
				!this.paginated[0].children.length
			) {
				return true;
			}
			return false;
		},

		allSelected() {
			return (
				this.selectedRowCount > 0 &&
				((this.selectAllByPage &&
					this.selectedPageRowsCount === this.totalPageRowCount) ||
					(!this.selectAllByPage &&
						this.selectedRowCount === this.totalRowCount))
			);
		},

		allSelectedIndeterminate() {
			return (
				!this.allSelected &&
				((this.selectAllByPage && this.selectedPageRowsCount > 0) ||
					(!this.selectAllByPage && this.selectedRowCount > 0))
			);
		},

		selectionInfo() {
			if (typeof this.selectionText == 'function') {
				return this.selectionText(this.selectedRowCount);
			} else {
				return `${this.selectedRowCount} ${this.selectionText}`;
			}
		},

		selectedRowCount() {
			return this.selectedRows.length;
		},

		selectedPageRowsCount() {
			return this.selectedPageRows.length;
		},

		selectedPageRows() {
			const selectedRows = [];
			this.paginated.forEach((headerRow) => {
				headerRow.children.forEach((row) => {
					if (row.isSelected) {
						selectedRows.push(row);
					}
				});
			});
			return selectedRows;
		},

		selectedRows() {
			const selectedRows = [];
			this.processedRows.forEach((headerRow) => {
				headerRow.children.forEach((row) => {
					if (row.isSelected) {
						selectedRows.push(row);
					}
				});
			});
			return selectedRows.sort((r1, r2) => r1.originalIndex - r2.originalIndex);
		},

		fullColspan() {
			let fullColspan = 0;
			for (let i = 0; i < this.visibleColumns.length; i += 1) {
				fullColspan += 1;
			}
			if (this.lineNumbers) fullColspan++;
			if (this.selectable) fullColspan++;
			return fullColspan;
		},

		groupHeaderOnTop() {
			if (
				this.groupOptions &&
				this.groupOptions.enabled &&
				this.groupOptions.headerPosition &&
				this.groupOptions.headerPosition === "bottom"
			) {
				return false;
			}
			if (this.groupOptions && this.groupOptions.enabled) return true;
			return false;
		},

		groupHeaderOnBottom() {
			if (
				this.groupOptions &&
				this.groupOptions.enabled &&
				this.groupOptions.headerPosition &&
				this.groupOptions.headerPosition === "bottom"
			) {
				return true;
			}
			return false;
		},

		totalRowCount() {
			const total = this.processedRows.reduce((total, headerRow) => {
				const childrenCount = headerRow.children ? headerRow.children.length : 0;
				return total + childrenCount;
			}, 0);
			return total;
		},

		totalPageRowCount() {
			const total = this.paginated.reduce((total, headerRow) => {
				const childrenCount = headerRow.children ? headerRow.children.length : 0;
				return total + childrenCount;
			}, 0);
			return total;
		},

		tableStyleClasses() {
			let classes = "min-w-full divide-y divide-gray-200";
			classes += ` ${this.theme}`;
			return classes;
		},

		searchTerm() {
			return this.externalSearchQuery != null
				? this.externalSearchQuery
				: this.globalSearchTerm;
		},

		globalSearchAllowed() {
			if (
				this.searchEnabled &&
				!!this.globalSearchTerm &&
				this.searchTrigger !== "enter"
			) {
				return true;
			}
			if (this.externalSearchQuery != null && this.searchTrigger !== "enter") {
				return true;
			}
			if (this.forceSearch) {
				this.forceSearch = false;
				return true;
			}
			return false;
		},

		// Visible columns (filtered by hidden state and column visibility)
		visibleColumns() {
			return this.columns.filter(col => {
				// Check if column has explicit hidden property
				if (col.hidden) return false;
				// Check column visibility state (for column toggle feature)
				if (this.columnVisibility[col.field] === false) return false;
				return true;
			});
		},

		// Toggleable columns (for column toggle dropdown)
		toggleableColumns() {
			return this.columns.filter(col => col.toggleable !== false);
		},

		processedRows() {
			let computedRows = this.filteredRows;
			if (this.mode === "remote") {
				return computedRows;
			}

			if (this.globalSearchAllowed) {
				const allRows = [];
				this.filteredRows.forEach((headerRow) => {
					allRows.push(...headerRow.children);
				});
				const filteredRows = [];
				allRows.forEach((row) => {
					for (let i = 0; i < this.visibleColumns.length; i += 1) {
						const col = this.visibleColumns[i];
						if (!col.globalSearchDisabled) {
							if (this.searchFn) {
								const foundMatch = this.searchFn(
									row,
									col,
									this.collectFormatted(row, col),
									this.searchTerm
								);
								if (foundMatch) {
									filteredRows.push(row);
									break;
								}
							} else {
								const matched = defaultType.filterPredicate(
									this.collectFormatted(row, col),
									this.searchTerm,
									this.searchSkipDiacritics
								);
								if (matched) {
									filteredRows.push(row);
									break;
								}
							}
						}
					}
				});

				this.$emit("search", {
					searchTerm: this.searchTerm,
					rowCount: filteredRows.length,
				});

				computedRows = [];
				this.filteredRows.forEach((headerRow) => {
					const i = headerRow.headerId;
					const children = filteredRows.filter((r) => r.groupId === i);
					if (children.length) {
						const newHeaderRow = JSON.parse(JSON.stringify(headerRow));
						newHeaderRow.children = children;
						computedRows.push(newHeaderRow);
					}
				});
			}

			if (this.sorts.length) {
				computedRows.forEach((cRows) => {
					cRows.children.sort((xRow, yRow) => {
						let sortValue;
						for (let i = 0; i < this.sorts.length; i += 1) {
							const srt = this.sorts[i];
							if (srt.type === SORT_TYPES.None) {
								sortValue = sortValue || xRow.originalIndex - yRow.originalIndex;
							} else {
								const column = this.getColumnForField(srt.field);
								const xvalue = this.collect(xRow, srt.field);
								const yvalue = this.collect(yRow, srt.field);
								const { sortFn } = column;
								if (sortFn && typeof sortFn === "function") {
									sortValue =
										sortValue ||
										sortFn(xvalue, yvalue, column, xRow, yRow) *
											(srt.type === SORT_TYPES.Descending ? -1 : 1);
								} else {
									sortValue =
										sortValue ||
										column.typeDef.compare(xvalue, yvalue, column) *
											(srt.type === SORT_TYPES.Descending ? -1 : 1);
								}
							}
						}
						return sortValue;
					});
				});
			}

			if (this.searchTrigger === "enter") {
				this.filteredRows = computedRows;
			}

			return computedRows;
		},

		paginated() {
			if (!this.processedRows.length) return [];
			if (this.mode === "remote") {
				return this.processedRows;
			}

			let paginatedRows = [];
			this.processedRows.forEach((childRows) => {
				if (this.groupOptions.enabled) {
					paginatedRows.push(childRows);
				}
				paginatedRows.push(...childRows.children);
			});

			if (this.paginate) {
				let pageStart = (this.currentPage - 1) * this.currentPerPage;
				if (pageStart >= paginatedRows.length || this.currentPerPage === -1) {
					this.currentPage = 1;
					pageStart = 0;
				}
				let pageEnd = paginatedRows.length + 1;
				if (this.currentPerPage !== -1) {
					pageEnd = this.currentPage * this.currentPerPage;
				}
				paginatedRows = paginatedRows.slice(pageStart, pageEnd);
			}

			const reconstructedRows = [];
			paginatedRows.forEach((flatRow) => {
				if (flatRow.headerId !== undefined) {
					this.handleExpanded(flatRow);
					const newHeaderRow = JSON.parse(JSON.stringify(flatRow));
					newHeaderRow.children = [];
					reconstructedRows.push(newHeaderRow);
				} else {
					let hRow = reconstructedRows.find(
						(r) => r.headerId === flatRow.groupId
					);
					if (!hRow) {
						hRow = this.processedRows.find(
							(r) => r.headerId === flatRow.groupId
						);
						if (hRow) {
							hRow = JSON.parse(JSON.stringify(hRow));
							hRow.children = [];
							reconstructedRows.push(hRow);
						}
					}
					hRow.children.push(flatRow);
				}
			});
			return reconstructedRows;
		},

		originalRows() {
			const rows = JSON.parse(JSON.stringify(this.rows));
			let nestedRows = [];
			if (!this.groupOptions.enabled) {
				nestedRows = this.handleGrouped([
					{
						label: "no groups",
						children: rows,
					},
				]);
			} else {
				nestedRows = this.handleGrouped(rows);
			}
			let index = 0;
			nestedRows.forEach((headerRow) => {
				headerRow.children.forEach((row) => {
					row.originalIndex = index++;
				});
			});
			return nestedRows;
		},

		typedColumns() {
			// Create copies to avoid mutating original props
			return this.visibleColumns.map(column => ({
				...column,
				typeDef: this.dataTypes[column.type] || defaultType,
			}));
		},
	},

	methods: {
		// Column toggle - NEW FEATURE
		initializeColumnVisibility(columns) {
			// Only initialize if not already set (preserve user toggles)
			if (Object.keys(this.columnVisibility).length > 0) {
				// Update only for new columns, preserve existing visibility state
				const visibility = { ...this.columnVisibility };
				columns.forEach(col => {
					if (visibility[col.field] === undefined) {
						visibility[col.field] = col.hidden !== true;
					}
				});
				// Remove visibility entries for columns that no longer exist
				const columnFields = new Set(columns.map(c => c.field));
				Object.keys(visibility).forEach(field => {
					if (!columnFields.has(field)) {
						delete visibility[field];
					}
				});
				this.columnVisibility = visibility;
			} else {
				// First time initialization
				const visibility = {};
				columns.forEach(col => {
					visibility[col.field] = col.hidden !== true;
				});
				this.columnVisibility = visibility;
			}
		},

		onColumnToggle(field) {
			const newValue = !this.columnVisibility[field];
			this.columnVisibility = {
				...this.columnVisibility,
				[field]: newValue,
			};
			this.$emit("column-toggle", field, newValue);
		},

		// Get cell classes with sticky support - NEW FEATURE
		getCellClasses(index, element, row, column) {
			const classes = {};
			const { typeDef } = this.typedColumns[index] || {};
			let { isRight } = typeDef || {};
			if (this.rtl) isRight = true;

			classes["text-right"] = isRight;
			classes["text-left"] = !isRight;

			// Base Tailwind classes
			classes["px-6"] = true;
			classes["py-4"] = true;
			classes["text-sm"] = true;
			classes["whitespace-nowrap"] = true;

			// Sticky column support - NEW FEATURE
			if (column.sticky) {
				classes["sticky"] = true;
				classes["right-0"] = true;
				classes["bg-white"] = true;
				classes["shadow-[-2px_0_5px_rgba(0,0,0,0.1)]"] = true;
				classes["z-10"] = true;
			}

			// Custom tdClass
			if (typeof column.tdClass === "function") {
				const customClass = column.tdClass(row, index);
				if (customClass) classes[customClass] = true;
			} else if (typeof column.tdClass === "string") {
				classes[column.tdClass] = true;
			}

			// Row style class
			if (typeof this.rowStyleClass === "function") {
				const rowClass = this.rowStyleClass(row);
				if (rowClass) classes[rowClass] = true;
			} else if (typeof this.rowStyleClass === "string") {
				classes[this.rowStyleClass] = true;
			}

			return classes;
		},

		getClasses(index, element, row) {
			const { typeDef, [`${element}Class`]: custom } = this.typedColumns[index] || {};
			let { isRight } = typeDef || {};
			if (this.rtl) isRight = true;

			const classes = {
				"text-right": isRight,
				"text-left": !isRight,
			};

			if (typeof custom === "function") {
				classes[custom(row)] = true;
			} else if (typeof custom === "string") {
				classes[custom] = true;
			}
			return classes;
		},

		handleExpanded(headerRow) {
			if (
				this.maintainExpanded &&
				this.expandedRowKeys.has(headerRow[this.rowKeyField])
			) {
				headerRow["isExpanded"] = true;
			} else {
				headerRow["isExpanded"] = false;
			}
		},

		toggleExpand(id) {
			const headerRow = this.filteredRows.find(
				(r) => r[this.rowKeyField] === id
			);
			if (headerRow) {
				headerRow["isExpanded"] = !headerRow.isExpanded;
			}
			if (this.maintainExpanded && headerRow.isExpanded) {
				this.expandedRowKeys.add(headerRow[this.rowKeyField]);
			} else {
				this.expandedRowKeys.delete(headerRow[this.rowKeyField]);
			}
		},

		expandAll() {
			this.filteredRows.forEach((row) => {
				row["isExpanded"] = true;
				if (this.maintainExpanded) {
					this.expandedRowKeys.add(row[this.rowKeyField]);
				}
			});
		},

		collapseAll() {
			this.filteredRows.forEach((row) => {
				row["isExpanded"] = false;
				this.expandedRowKeys.clear();
			});
		},

		getColumnForField(field) {
			for (let i = 0; i < this.typedColumns.length; i += 1) {
				if (this.typedColumns[i].field === field) return this.typedColumns[i];
			}
		},

		handleSearch() {
			this.resetTable();
			if (this.mode === "remote") {
				this.$emit("search", {
					searchTerm: this.searchTerm,
				});
			}
		},

		reset() {
			this.initializeSort();
			this.changePage(1);
			this.$refs["table-header-primary"].reset(true);
			if (this.$refs["table-header-secondary"]) {
				this.$refs["table-header-secondary"].reset(true);
			}
		},

		emitSelectedRows() {
			this.$emit("select-all", {
				selected: this.selectedRowCount === this.totalRowCount,
				selectedRows: this.selectedRows,
			});
		},

		unselectAllInternal(forceAll) {
			const rows =
				this.selectAllByPage && !forceAll ? this.paginated : this.filteredRows;
			rows.forEach((headerRow, i) => {
				headerRow.children.forEach((row, j) => {
					row["isSelected"] = false;
				});
			});
			this.emitSelectedRows();
		},

		toggleSelectAll() {
			if (this.allSelected) {
				this.unselectAllInternal();
				return;
			}
			const rows = this.selectAllByPage ? this.paginated : this.filteredRows;
			rows.forEach((headerRow) => {
				headerRow.children.forEach((row) => {
					row["isSelected"] = true;
				});
			});
			this.emitSelectedRows();
		},

		toggleExpandRowsAll() {
			for (let row of this.rows) {
				if (row["expandedRow"]) {
					row["expanded"] = !row["expanded"];
				} else {
					row["expanded"] = false;
				}
			}
		},

		toggleSelectGroup(event, headerRow) {
			headerRow.children.forEach((row) => {
				row["isSelected"] = event.checked;
			});
		},

		changePage(value) {
			const enabled = this.paginate;
			const { paginationBottom } = this.$refs;
			if (enabled) {
				if (this.paginateOnBottom && paginationBottom) {
					paginationBottom.currentPage = value;
				}
				this.currentPage = value;
			}
		},

		pageChangedEvent() {
			return {
				currentPage: this.currentPage,
				currentPerPage: this.currentPerPage,
				total: Math.floor(this.totalRowCount / this.currentPerPage),
			};
		},

		pageChanged(pagination) {
			this.currentPage = pagination.currentPage;
			if (!pagination.noEmit) {
				const pageChangedEvent = this.pageChangedEvent();
				pageChangedEvent.prevPage = pagination.prevPage;
				this.$emit("page-change", pageChangedEvent);
				if (this.mode === "remote") {
					this.$emit("update:isLoading", true);
				}
			}
		},

		perPageChanged(pagination) {
			this.currentPerPage = pagination.currentPerPage;
			if (this.$refs.paginationBottom) {
				this.$refs.paginationBottom.currentPerPage = this.currentPerPage;
			}
			const perPageChangedEvent = this.pageChangedEvent();
			this.$emit("per-page-change", perPageChangedEvent);
			if (this.mode === "remote") {
				this.$emit("update:isLoading", true);
			}
		},

		changeSort(sorts) {
			this.sorts = sorts;
			this.$emit("sort-change", sorts);
			this.changePage(1);
			if (this.mode === "remote") {
				this.$emit("update:isLoading", true);
				return;
			}
			this.sortChanged = true;
		},

		toggleRowExpand(row, index) {
			if (this.expandedRowIndex === index) {
				this.expandedRowIndex = null;
			} else {
				this.expandedRowIndex = index;
			}
		},

		onCheckboxClicked(row, index, event) {
			if (this.enableRowExpand) {
				this.toggleRowExpand(row, index);
			}
			row["isSelected"] = !row.isSelected;
			this.$emit("row-click", {
				row,
				pageIndex: index,
				selected: !!row.isSelected,
				event,
			});
		},

		toggleExpandRow(row) {
			row["expanded"] = !row["expanded"];
		},

		onRowDoubleClicked(row, index, event) {
			this.$emit("row-dblclick", {
				row,
				pageIndex: index,
				selected: !!row.isSelected,
				event,
			});
		},

		onRowClicked(row, index, event) {
			if (this.enableRowExpand) {
				this.toggleRowExpand(row, index);
			}
			this.$emit("row-click", {
				row,
				pageIndex: index,
				selected: !!row.isSelected,
				event,
			});
		},

		onRowAuxClicked(row, index, event) {
			this.$emit("row-aux-click", {
				row,
				pageIndex: index,
				selected: !!row.isSelected,
				event,
			});
		},

		onCellClicked(row, column, rowIndex, event) {
			this.$emit("cell-click", {
				row,
				column,
				rowIndex,
				event,
			});
		},

		onMouseenter(row, index) {
			this.$emit("row-mouseenter", {
				row,
				pageIndex: index,
			});
		},

		onMouseleave(row, index) {
			this.$emit("row-mouseleave", {
				row,
				pageIndex: index,
			});
		},

		searchTableOnEnter() {
			if (this.searchTrigger === "enter") {
				this.handleSearch();
				this.filteredRows = JSON.parse(JSON.stringify(this.originalRows));
				this.forceSearch = true;
				this.sortChanged = true;
			}
		},

		searchTableOnKeyUp() {
			if (this.searchTrigger !== "enter") {
				this.handleSearch();
			}
		},

		resetTable() {
			this.unselectAllInternal(true);
			this.changePage(1);
		},

		collect(obj, field) {
			function dig(obj, selector) {
				let result = obj;
				const splitter = selector.split(".");
				for (let i = 0; i < splitter.length; i++) {
					if (typeof result === "undefined" || result === null) {
						return undefined;
					}
					result = result[splitter[i]];
				}
				return result;
			}
			if (typeof field === "function") return field(obj);
			if (typeof field === "string") return dig(obj, field);
			return undefined;
		},

		collectFormatted(obj, column, headerRow = false) {
			let value;
			if (headerRow && column.headerField) {
				value = this.collect(obj, column.headerField);
			} else {
				value = this.collect(obj, column.field);
			}
			if (value === undefined) return "";
			if (column.formatFn && typeof column.formatFn === "function") {
				return column.formatFn(value, obj);
			}
			let type = column.typeDef;
			if (!type) {
				type = this.dataTypes[column.type] || defaultType;
			}
			let result = type.format(value, column);
			if (this.compactMode && (result == "" || result == null)) return "-";
			return result;
		},

		formattedRow(row, isHeaderRow = false) {
			const formattedRow = {};
			for (let i = 0; i < this.typedColumns.length; i++) {
				const col = this.typedColumns[i];
				if (col.field) {
					formattedRow[col.field] = this.collectFormatted(
						row,
						col,
						isHeaderRow
					);
				}
			}
			return formattedRow;
		},

		getCurrentIndex(rowId) {
			let index = 0;
			let found = false;
			for (let i = 0; i < this.paginated.length; i += 1) {
				const headerRow = this.paginated[i];
				const { children } = headerRow;
				if (children && children.length) {
					for (let j = 0; j < children.length; j++) {
						const c = children[j];
						if (c.originalIndex === rowId) {
							found = true;
							break;
						}
						index += 1;
					}
				}
				if (found) break;
			}
			return (this.currentPage - 1) * this.currentPerPage + index + 1;
		},

		getRowStyleClass(row) {
			let classes = "";
			let rowStyleClasses;
			if (typeof this.rowStyleClass === "function") {
				rowStyleClasses = this.rowStyleClass(row);
			} else {
				rowStyleClasses = this.rowStyleClass;
			}
			if (rowStyleClasses) {
				classes += ` ${rowStyleClasses}`;
			}
			if (this.expandedRowIndex === row.originalIndex) {
				classes += ` ${this.expandedRowClasses}`;
			}
			return classes;
		},

		handleGrouped(originalRows) {
			originalRows.forEach((headerRow, i) => {
				headerRow.headerId = i;
				if (
					this.groupOptions.maintainExpanded &&
					this.expandedRowKeys.has(headerRow[this.groupOptions.rowKey])
				) {
					headerRow["isExpanded"] = true;
				}
				headerRow.children.forEach((childRow) => {
					childRow.groupId = i;
				});
			});
			return originalRows;
		},

		initializePagination() {
			const {
				enabled,
				perPage,
				perPageDropdown,
				perPageDropdownEnabled,
				dropdownAllowAll,
				nextLabel,
				prevLabel,
				rowsPerPageLabel,
				ofLabel,
				pageLabel,
				allLabel,
				setCurrentPage,
				mode,
				infoFn,
			} = this.paginationOptions;

			if (typeof enabled === "boolean") this.paginate = enabled;
			if (typeof perPage === "number") this.perPage = perPage;
			if (Array.isArray(perPageDropdown) && perPageDropdown.length) {
				this.customRowsPerPageDropdown = perPageDropdown;
				if (!this.perPage) [this.perPage] = perPageDropdown;
			}
			if (typeof perPageDropdownEnabled === "boolean") {
				this.perPageDropdownEnabled = perPageDropdownEnabled;
			}
			if (typeof dropdownAllowAll === "boolean") {
				this.paginateDropdownAllowAll = dropdownAllowAll;
			}
			if (typeof mode === "string") this.paginationMode = mode;
			if (typeof nextLabel === "string") this.nextText = nextLabel;
			if (typeof prevLabel === "string") this.prevText = prevLabel;
			if (typeof rowsPerPageLabel === "string") this.rowsPerPageText = rowsPerPageLabel;
			if (typeof ofLabel === "string") this.ofText = ofLabel;
			if (typeof pageLabel === "string") this.pageText = pageLabel;
			if (typeof allLabel === "string") this.allText = allLabel;
			if (typeof setCurrentPage === "number") {
				setTimeout(() => this.changePage(setCurrentPage), 500);
			}
			if (typeof infoFn === "function") this.paginationInfoFn = infoFn;
		},

		initializeExpandRows() {
			const { enabled } = this.expandRowsOptions;
			if (typeof enabled === "boolean") this.expandRowsEnabled = enabled;
		},

		initializeSearch() {
			const {
				enabled,
				trigger,
				externalQuery,
				searchFn,
				placeholder,
				skipDiacritics,
			} = this.searchOptions;

			if (typeof enabled === "boolean") this.searchEnabled = enabled;
			if (trigger === "enter") this.searchTrigger = trigger;
			if (typeof externalQuery === "string") this.externalSearchQuery = externalQuery;
			if (typeof searchFn === "function") this.searchFn = searchFn;
			if (typeof placeholder === "string") this.searchPlaceholder = placeholder;
			if (typeof skipDiacritics === "boolean") this.searchSkipDiacritics = skipDiacritics;
		},

		initializeSort() {
			const { enabled, initialSortBy, multipleColumns } = this.sortOptions;
			const initSortBy = JSON.parse(JSON.stringify(initialSortBy || {}));

			if (typeof enabled === "boolean") this.sortable = enabled;
			if (typeof multipleColumns === "boolean") this.multipleColumnSort = multipleColumns;

			if (typeof initSortBy === "object") {
				const ref = this.fixedHeader
					? this.$refs["table-header-secondary"]
					: this.$refs["table-header-primary"];
				if (Array.isArray(initSortBy)) {
					ref.setInitialSort(initSortBy);
				} else {
					const hasField = Object.prototype.hasOwnProperty.call(initSortBy, "field");
					if (hasField) ref.setInitialSort([initSortBy]);
				}
			}
		},

		initializeSelect() {
			const {
				enabled,
				selectionInfoClass,
				selectionText,
				clearSelectionText,
				selectAllByPage,
				disableSelectInfo,
				selectAllByGroup,
				alwaysShowSelectionInfo,
			} = this.selectOptions;

			if (typeof enabled === "boolean") this.selectable = enabled;
			if (typeof selectAllByPage === "boolean") this.selectAllByPage = selectAllByPage;
			if (typeof selectAllByGroup === "boolean") this.selectAllByGroup = selectAllByGroup;
			if (typeof disableSelectInfo === "boolean") this.disableSelectInfo = disableSelectInfo;
			if (typeof selectionInfoClass === "string") this.selectionInfoClass = selectionInfoClass;
			if (typeof selectionText === "string" || typeof selectionText === "function") {
				this.selectionText = selectionText;
			}
			if (typeof alwaysShowSelectionInfo === "boolean") {
				this.alwaysShowSelectionInfo = alwaysShowSelectionInfo;
			}
			if (typeof clearSelectionText === "string") {
				this.clearSelectionText = clearSelectionText;
			}
		},

		filterRows(columnFilters, fromFilter = true) {
			this.columnFilters = columnFilters;
			let computedRows = JSON.parse(JSON.stringify(this.originalRows));
			let instancesOfFiltering = false;

			if (this.columnFilters && Object.keys(this.columnFilters).length) {
				if (this.mode !== "remote" || fromFilter) {
					this.changePage(1);
				}
				if (fromFilter) {
					this.$emit("column-filter", {
						columnFilters: this.columnFilters,
					});
				}
				if (this.mode === "remote") {
					if (fromFilter) {
						this.$emit("update:isLoading", true);
					} else {
						this.filteredRows = computedRows;
					}
					return;
				}

				const fieldKey = (field) => {
					if (typeof field === "function" && field.name) return field.name;
					return field;
				};

				for (let i = 0; i < this.typedColumns.length; i++) {
					const col = this.typedColumns[i];
					if (this.columnFilters[fieldKey(col.field)]) {
						instancesOfFiltering = true;
						computedRows.forEach((headerRow) => {
							const newChildren = headerRow.children.filter((row) => {
								if (
									col.filterOptions &&
									typeof col.filterOptions.filterFn === "function"
								) {
									return col.filterOptions.filterFn(
										this.collect(row, col.field),
										this.columnFilters[fieldKey(col.field)]
									);
								}
								const { typeDef } = col;
								return typeDef.filterPredicate(
									this.collect(row, col.field),
									this.columnFilters[fieldKey(col.field)],
									false,
									col.filterOptions &&
										typeof col.filterOptions.filterDropdownItems === "object"
								);
							});
							headerRow.children = newChildren;
						});
					}
				}
			}

			if (instancesOfFiltering) {
				this.filteredRows = computedRows.filter(
					(h) => h.children && h.children.length
				);
			} else {
				this.filteredRows = computedRows;
			}
		},
	},

	mounted() {
		if (this.perPage) {
			this.currentPerPage = this.perPage;
		}
		this.initializeSort();
	},
};
</script>

<style scoped>
/* Compact mode */
:deep(.ui-table-compact th),
:deep(.ui-table-compact td) {
	padding: 0.5rem 0.75rem;
}

/* RTL support */
.rtl {
	direction: rtl;
}
</style>
