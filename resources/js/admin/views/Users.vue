<template>
    <div class="admin__users relative h-full flex flex-col overflow-y-auto">
        <div class="w-full p-4 border-b bg-white">
            <div class="text-xl font-bold">{{ __('Users') }}</div>
        </div>

        <div class="w-full flex flex-col flex-1 m-auto p-4 overflow-y-auto">
            <!-- Role tabs -->
            <div class="w-full flex flex-wrap items-center gap-2 mb-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    class="px-4 py-2 rounded-md text-sm font-semibold border transition-colors"
                    :class="role === tab.key
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                >
                    {{ tab.label }}
                    <span
                        class="ml-1 text-xs"
                        :class="role === tab.key ? 'text-indigo-200' : 'text-gray-400'"
                    >({{ counts[tab.key] ?? 0 }})</span>
                </button>

                <div class="flex-1"></div>

                <form class="w-full max-w-xs" @submit.prevent="getUsers(1)">
                    <div class="relative">
                        <input
                            type="text"
                            v-model="search"
                            @input="getUsers(1)"
                            :placeholder="__('Search by name or email…')"
                            class="w-full px-3 py-2 pl-10 placeholder-gray-400 text-gray-700 bg-white rounded-md text-sm border border-gray-200"
                        />
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                    </div>
                </form>

                <ui-button color="indigo-600" hover="indigo-700" @click="openCreate">
                    <i class="fa fa-user-plus"></i> {{ __('Create User') }}
                </ui-button>
            </div>

            <!-- Bulk action bar -->
            <div
                v-if="selected.length > 0"
                class="w-full flex flex-wrap items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-md px-4 py-2 mb-4"
            >
                <span class="text-sm font-semibold text-indigo-800">
                    {{ selected.length }} {{ __('selected') }}
                </span>
                <select
                    v-model="bulkRole"
                    class="px-2 py-1 text-sm text-gray-700 bg-white border border-gray-200 rounded-md"
                >
                    <option value="user">{{ __('Change role to User') }}</option>
                    <option value="super_admin">{{ __('Change role to Super Admin') }}</option>
                </select>
                <ui-button color="indigo-600" hover="indigo-700" size="sm" @click="bulkChangeRole">
                    <i class="fa fa-user-tag"></i> {{ __('Apply') }}
                </ui-button>
                <ui-button color="red-600" hover="red-700" size="sm" @click="bulkDelete">
                    <i class="fa fa-trash-alt"></i> {{ __('Delete Selected') }}
                </ui-button>
                <ui-button color="gray-200" hover="gray-300" size="sm" @click="selected = []">
                    <span class="text-gray-700">{{ __('Clear') }}</span>
                </ui-button>
            </div>

            <div class="w-full bg-white rounded-md shadow-sm p-4">
                <div class="w-full border rounded-md overflow-x-auto clear-both">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-4 py-3 w-10">
                                    <input
                                        type="checkbox"
                                        class="w-4 h-4"
                                        :checked="allSelected"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('User') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Global Role') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Project Memberships') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Created') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="user in users" :key="user.id" :class="{ 'bg-indigo-50/50': selected.includes(user.id) }">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="w-4 h-4"
                                        :value="user.id"
                                        v-model="selected"
                                        :disabled="user.id === currentUserId"
                                    />
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <div class="flex items-center">
                                        <div class="flex bg-indigo-500 text-white p-2 text-md rounded-full text-center mr-3 w-9 h-9 items-center justify-center">
                                            <span class="text-xs font-semibold">{{ initials(user.name) }}</span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800">
                                                {{ user.name }}
                                                <span v-if="user.id === currentUserId" class="text-xs text-gray-400">({{ __('you') }})</span>
                                            </div>
                                            <div class="text-xs text-gray-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <select
                                        class="rounded-full px-2 py-1 text-xs font-semibold border cursor-pointer outline-none w-32"
                                        :class="user.global_role === 'super_admin'
                                            ? 'bg-amber-100 text-amber-700 border-amber-200'
                                            : 'bg-gray-100 text-gray-600 border-gray-200'"
                                        :value="user.global_role"
                                        :disabled="user.id === currentUserId"
                                        @change="changeRoleInline(user, $event.target.value)"
                                    >
                                        <option value="super_admin" class="bg-white text-gray-700">{{ __('Super Admin') }}</option>
                                        <option value="user" class="bg-white text-gray-700">{{ __('User') }}</option>
                                    </select>
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <div v-if="user.memberships && user.memberships.length" class="flex flex-wrap gap-1">
                                        <span
                                            v-for="m in user.memberships"
                                            :key="m.id"
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700"
                                        >
                                            {{ m.name }}
                                            <span class="text-blue-400">·</span>
                                            <span class="capitalize">{{ m.role }}</span>
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">{{ __('No project memberships') }}</span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    {{ $filters.date(user.created_at, "D MMM YYYY") }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                                    <a class="inline-block text-indigo-500 p-2 px-3 mr-1 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50" @click="openEdit(user)">
                                        <i class="fa fa-pencil-alt"></i>
                                    </a>
                                    <a class="inline-block text-red-500 p-2 px-3 rounded-md hover:bg-gray-100 cursor-pointer bg-gray-50" @click="removeUser(user)">
                                        <i class="fa fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="users.length === 0">
                                <td colspan="6" class="text-center text-sm text-gray-500 p-5">{{ __('No users found') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end items-center mt-2">
                    <pagination :data="pagination" size="small" :limit="3" @pagination-change-page="getUsers"></pagination>
                </div>
            </div>
        </div>

        <!-- Edit / Create modal (Profile-style content) -->
        <ui-modal maxWidth="lg" :show="showModal" @close="showModal = false">
            <template #title>
                {{ modalMode === 'create' ? __('Create User') : __('Edit User') }}
            </template>
            <template #content>
                <div class="space-y-5">
                    <div class="block">
                        <div class="space-y-4">
                            <div class="form-item">
                                <label v-formlabel>{{ __('Name') }}</label>
                                <input type="text" v-model="form.name" v-forminput :placeholder="__('Full name')" />
                                <p class="text-sm text-red-600 mt-1" v-if="errors.name">{{ errors.name[0] }}</p>
                            </div>
                            <div class="form-item">
                                <label v-formlabel>{{ __('E-mail') }}</label>
                                <input type="email" v-model="form.email" v-forminput :placeholder="__('Email address')" />
                                <p class="text-sm text-red-600 mt-1" v-if="errors.email">{{ errors.email[0] }}</p>
                            </div>
                            <div class="form-item">
                                <label v-formlabel>{{ __('Password') }}</label>
                                <input type="password" v-model="form.password" v-forminput :placeholder="modalMode === 'edit' ? __('Leave blank to keep current') : __('At least 8 characters')" />
                                <p class="text-sm text-red-600 mt-1" v-if="errors.password">{{ errors.password[0] }}</p>
                            </div>
                            <div class="form-item">
                                <label v-formlabel>{{ __('Confirm Password') }}</label>
                                <input type="password" v-model="form.password_confirmation" v-forminput :placeholder="__('Confirm password')" />
                            </div>
                            <div class="form-item">
                                <label v-formlabel>{{ __('Global Role') }}</label>
                                <select v-model="form.role" v-formselect :disabled="canEditRole" class="w-full px-3 py-2 text-gray-700 bg-white border-gray-200 rounded-md">
                                    <option value="user">{{ __('User') }}</option>
                                    <option value="super_admin">{{ __('Super Admin') }}</option>
                                </select>
                                <p class="text-sm text-red-600 mt-1" v-if="errors.role">{{ errors.role[0] }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="modalMode === 'edit'" class="block">
                        <div class="text-sm font-bold text-gray-800 mb-3 border-b pb-2">{{ __('Two-Factor Authentication') }}</div>
                        <div class="bg-gray-50 border rounded-md p-4">
                            <div v-if="!twoFactorEnabled">
                                <p class="text-sm text-gray-600 mb-3">
                                    {{ __('Add an extra layer of security to this account by requiring an authentication code in addition to the password.') }}
                                </p>
                                <ui-button color="indigo-600" hover="indigo-700" size="sm" @click="enableTwoFactor()" :loading="twoFactorBusy">
                                    <i class="fa fa-shield-alt"></i> {{ __('Enable 2FA') }}
                                </ui-button>
                                <p class="text-sm text-red-600 mt-2" v-if="twoFactorError">{{ twoFactorError }}</p>
                            </div>
                            <div v-else>
                                <div class="flex items-center text-sm text-gray-700 mb-3">
                                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 mr-2"></span>
                                    {{ __('Two-factor authentication is enabled.') }}
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <ui-button color="gray-200" hover="gray-300" size="sm" @click="showRecoveryCodes()">
                                        <i class="fa fa-key"></i> {{ __('Recovery Codes') }}
                                    </ui-button>
                                    <ui-button color="red-500" hover="red-600" size="sm" @click="openDisableModal()">
                                        <i class="fa fa-ban"></i> {{ __('Disable 2FA') }}
                                    </ui-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <ui-button color="gray-200" hover="gray-300" @click="showModal = false">
                    <span class="text-gray-800">{{ __('Cancel') }}</span>
                </ui-button>
                <ui-button color="indigo-600" hover="indigo-700" @click="saveUser">
                    {{ modalMode === 'create' ? __('Create') : __('Save Changes') }}
                </ui-button>
            </template>
        </ui-modal>

        <!-- 2FA setup modal -->
        <ui-modal maxWidth="md" :show="enableModal" @close="enableModal = false">
            <template #title>
                {{ __('Two-Factor Authentication Setup') }}
            </template>
            <template #content>
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('Scan the QR code with your authenticator app (e.g. Google Authenticator, Authy) or enter the secret manually.') }}
                </p>
                <div class="flex flex-col items-center mb-4">
                    <img v-if="qrDataUrl" :src="qrDataUrl" class="w-52 h-52 border rounded-md" :alt="__('QR Code')" />
                    <div v-else class="w-52 h-52 bg-gray-100 border rounded-md"></div>
                    <div class="mt-3 flex items-center gap-2 bg-gray-100 rounded-md px-3 py-1.5">
                        <code class="text-xs text-gray-700">{{ twoFactorSecret }}</code>
                        <button type="button" class="text-indigo-500 text-xs font-semibold" @click="copySecret">{{ __('Copy') }}</button>
                    </div>
                </div>
                <label class="text-sm text-gray-700">{{ __('Enter the 6-digit code from your app') }}</label>
                <input
                    type="text"
                    v-model="confirmCode"
                    v-forminput
                    class="mt-1 w-full px-3 py-2 text-gray-700 bg-white border border-gray-200 rounded-md"
                    :placeholder="__('123456')"
                />
                <p class="text-sm text-red-600 mt-2" v-if="twoFactorError">{{ twoFactorError }}</p>
            </template>
            <template #footer>
                <ui-button color="gray-200" hover="gray-300" @click="enableModal = false">
                    <span class="text-gray-800">{{ __('Cancel') }}</span>
                </ui-button>
                <ui-button color="indigo-500" @click="confirmTwoFactor()" :loading="twoFactorBusy">
                    {{ __('Confirm') }}
                </ui-button>
            </template>
        </ui-modal>

        <!-- Recovery codes modal -->
        <ui-modal maxWidth="md" :show="recoveryModal" @close="recoveryModal = false">
            <template #title>
                {{ __('Recovery Codes') }}
            </template>
            <template #content>
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('Store these codes somewhere safe. Each code can only be used once.') }}
                </p>
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div
                        v-for="(code, index) in recoveryCodes"
                        :key="index"
                        class="bg-gray-50 border rounded-md px-3 py-2 text-center font-mono text-sm text-gray-700"
                    >
                        {{ code }}
                    </div>
                </div>
            </template>
            <template #footer>
                <ui-button color="gray-200" hover="gray-300" @click="recoveryModal = false">
                    <span class="text-gray-800">{{ __('Close') }}</span>
                </ui-button>
                <ui-button color="indigo-500" @click="regenerateRecoveryCodes()">
                    {{ __('Regenerate') }}
                </ui-button>
            </template>
        </ui-modal>

        <!-- Disable 2FA modal -->
        <ui-modal maxWidth="sm" :show="disableModal" @close="disableModal = false">
            <template #title>
                {{ __('Disable Two-Factor Authentication') }}
            </template>
            <template #content>
                <p class="text-sm text-gray-600 mb-3">
                    {{ __('This will remove the extra security layer from the account.') }}
                </p>
                <p class="text-sm text-red-600" v-if="twoFactorError">{{ twoFactorError }}</p>
            </template>
            <template #footer>
                <ui-button color="gray-200" hover="gray-300" @click="disableModal = false">
                    <span class="text-gray-800">{{ __('Cancel') }}</span>
                </ui-button>
                <ui-button color="red-500" @click="disableTwoFactor()" :loading="twoFactorBusy">
                    {{ __('Disable') }}
                </ui-button>
            </template>
        </ui-modal>
    </div>
</template>

<script>
import axios from "axios";
import QRCode from "qrcode";
import { __ } from "../translations/engine";
import UiButton from "../../components/Button.vue";
import UiModal from "../../components/Modal.vue";
import { useAdminStore } from "../store";

export default {
    components: {
        UiButton,
        UiModal,
    },

    data() {
        return {
            users: [],
            pagination: {},
            counts: { all: 0, super_admin: 0, user: 0 },
            role: "all",
            search: "",
            selected: [],
            bulkRole: "user",
            showModal: false,
            modalMode: "create",
            editingId: null,
            currentUserId: null,
            form: {
                name: "",
                email: "",
                password: "",
                password_confirmation: "",
                role: "user",
            },
            errors: {},
            // 2FA state
            twoFactorEnabled: false,
            enableModal: false,
            twoFactorBusy: false,
            twoFactorError: "",
            qrDataUrl: "",
            twoFactorSecret: "",
            confirmCode: "",
            recoveryModal: false,
            recoveryCodes: [],
            disableModal: false,
        };
    },

    computed: {
        tabs() {
            return [
                { key: "all", label: __("All") },
                { key: "super_admin", label: __("Super Admin") },
                { key: "user", label: __("User") },
            ];
        },
        allSelected() {
            const selectable = this.users.filter((u) => u.id !== this.currentUserId);
            return selectable.length > 0 && selectable.every((u) => this.selected.includes(u.id));
        },
        canEditRole() {
            return this.modalMode === "create" || this.editingId !== this.currentUserId;
        },
    },

    mounted() {
        const store = useAdminStore();
        this.currentUserId = store.user ? store.user.id : null;
        this.getUsers(1);
    },

    methods: {
        getUsers(page) {
            axios
                .get("users", {
                    params: { search: this.search, role: this.role, page, each: 15 },
                })
                .then((response) => {
                    const data = response.data.data;
                    this.users = data.data || [];
                    this.pagination = data;
                    if (data.counts) {
                        this.counts = data.counts;
                    }
                });
        },

        switchTab(key) {
            this.role = key;
            this.getUsers(1);
        },

        initials(name) {
            if (!name) return "";
            const parts = name.trim().split(/\s+/);
            return ((parts[0] || "")[0] || "").toUpperCase() + ((parts[1] || "")[0] || "").toUpperCase();
        },

        toggleAll() {
            const selectable = this.users.filter((u) => u.id !== this.currentUserId);
            if (this.allSelected) {
                this.selected = this.selected.filter((id) => !selectable.some((u) => u.id === id));
            } else {
                selectable.forEach((u) => {
                    if (!this.selected.includes(u.id)) this.selected.push(u.id);
                });
            }
        },

        changeRoleInline(user, role) {
            const previous = user.global_role;
            if (role === previous) return;

            user.global_role = role;
            axios
                .post(`users/${user.id}`, {
                    name: user.name,
                    email: user.email,
                    role,
                })
                .then((response) => {
                    if (response.data.data && response.data.data.global_role) {
                        user.global_role = response.data.data.global_role;
                    }
                    this.$toast.success(__("Role updated"));
                    this.refreshCounts();
                })
                .catch((error) => {
                    user.global_role = previous;
                    const message = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : __("Unable to update the role.");
                    this.$swal.fire({
                        icon: "error",
                        title: __("Unable to update role"),
                        text: message,
                    });
                });
        },

        refreshCounts() {
            axios.get("users", { params: { search: this.search, role: this.role, page: 1, each: 15 } })
                .then((response) => {
                    const data = response.data.data;
                    this.users = data.data || [];
                    this.pagination = data;
                    if (data.counts) this.counts = data.counts;
                });
        },

        bulkChangeRole() {
            if (this.selected.length === 0) return;
            const ids = [...this.selected];
            axios
                .post("users/bulk", {
                    ids,
                    action: "role",
                    role: this.bulkRole,
                })
                .then((response) => {
                    this.$toast.success(__("Roles updated"));
                    this.selected = [];
                    if (response.data.counts) this.counts = response.data.counts;
                    this.getUsers(this.pagination.current_page || 1);
                })
                .catch((error) => {
                    const message = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : __("Unable to update roles.");
                    this.$swal.fire({ icon: "error", title: __("Unable to update roles"), text: message });
                });
        },

        bulkDelete() {
            const ids = [...this.selected];
            this.$swal
                .fire({
                    title: __("Delete selected users?"),
                    text: __("The selected users will be removed permanently."),
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc2626",
                    confirmButtonText: __("Delete"),
                    cancelButtonText: __("Cancel"),
                })
                .then((result) => {
                    if (!result.isConfirmed) return;
                    axios
                        .post("users/bulk", { ids, action: "delete" })
                        .then((response) => {
                            this.$toast.success(__("Users deleted"));
                            this.selected = [];
                            if (response.data.counts) this.counts = response.data.counts;
                            this.getUsers(this.pagination.current_page || 1);
                        })
                        .catch((error) => {
                            const message = error.response && error.response.data && error.response.data.message
                                ? error.response.data.message
                                : __("Unable to delete users.");
                            this.$swal.fire({ icon: "error", title: __("Unable to delete users"), text: message });
                        });
                });
        },

        openCreate() {
            this.modalMode = "create";
            this.editingId = null;
            this.form = { name: "", email: "", password: "", password_confirmation: "", role: "user" };
            this.errors = {};
            this.resetTwoFactor();
            this.showModal = true;
        },

        openEdit(user) {
            this.modalMode = "edit";
            this.editingId = user.id;
            this.form = {
                name: user.name,
                email: user.email,
                password: "",
                password_confirmation: "",
                role: user.global_role || "user",
            };
            this.errors = {};
            this.resetTwoFactor();
            this.twoFactorEnabled = !!user.two_factor_enabled;
            this.showModal = true;
        },

        resetTwoFactor() {
            this.enableModal = false;
            this.twoFactorBusy = false;
            this.twoFactorError = "";
            this.qrDataUrl = "";
            this.twoFactorSecret = "";
            this.confirmCode = "";
            this.recoveryModal = false;
            this.recoveryCodes = [];
            this.disableModal = false;
        },

        saveUser() {
            const payload = {
                name: this.form.name,
                email: this.form.email,
                role: this.form.role,
            };
            if (this.form.password) {
                payload.password = this.form.password;
                payload.password_confirmation = this.form.password_confirmation;
            }

            const request =
                this.modalMode === "create"
                    ? axios.post("users", payload)
                    : axios.post(`users/${this.editingId}`, payload);

            request
                .then(() => {
                    this.showModal = false;
                    this.$toast.success(this.modalMode === "create" ? __("User created") : __("User updated"));
                    this.getUsers(this.pagination.current_page || 1);
                })
                .catch((error) => {
                    if (error.response && error.response.data && error.response.data.errors) {
                        this.errors = error.response.data.errors;
                    } else if (error.response && error.response.data && error.response.data.message) {
                        this.$swal.fire({
                            icon: "error",
                            title: __("Unable to save user"),
                            text: error.response.data.message,
                        });
                    }
                });
        },

        removeUser(user) {
            this.$swal
                .fire({
                    title: __("Delete user?"),
                    text: __("The user will be removed permanently."),
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc2626",
                    confirmButtonText: __("Delete"),
                    cancelButtonText: __("Cancel"),
                })
                .then((result) => {
                    if (!result.isConfirmed) return;
                    axios
                        .delete(`users/${user.id}`)
                        .then(() => {
                            this.$toast.success(__("User deleted"));
                            this.getUsers(this.pagination.current_page || 1);
                        })
                        .catch((error) => {
                            const message = error.response && error.response.data && error.response.data.message
                                ? error.response.data.message
                                : __("Unable to delete the user.");
                            this.$swal.fire({
                                icon: "error",
                                title: __("Unable to delete user"),
                                text: message,
                            });
                        });
                });
        },

        // ---- 2FA (admin acting on the edited user) ----
        async enableTwoFactor() {
            this.twoFactorBusy = true;
            this.twoFactorError = "";
            try {
                const { data } = await axios.post(`users/${this.editingId}/2fa/enable`);
                this.twoFactorSecret = data.secret;
                this.qrDataUrl = await QRCode.toDataURL(data.provisioning_uri, {
                    width: 400,
                    margin: 1,
                });
                this.confirmCode = "";
                this.enableModal = true;
            } catch (error) {
                this.twoFactorError =
                    (error.response && error.response.data && error.response.data.message) ||
                    __("Failed to enable two factor authentication.");
            } finally {
                this.twoFactorBusy = false;
            }
        },

        async confirmTwoFactor() {
            if (!this.confirmCode) return;
            this.twoFactorBusy = true;
            this.twoFactorError = "";
            try {
                const { data } = await axios.post(`users/${this.editingId}/2fa/confirm`, {
                    code: this.confirmCode,
                });
                this.enableModal = false;
                this.recoveryCodes = data.recovery_codes;
                this.recoveryModal = true;
                this.twoFactorEnabled = true;
                this.$toast.success(__("Two factor authentication enabled."));
            } catch (error) {
                this.twoFactorError =
                    (error.response && error.response.data && error.response.data.message) ||
                    __("The provided code was invalid.");
            } finally {
                this.twoFactorBusy = false;
            }
        },

        async showRecoveryCodes() {
            this.twoFactorError = "";
            try {
                const { data } = await axios.post(`users/${this.editingId}/2fa/recovery-codes`);
                this.recoveryCodes = data.recovery_codes;
                this.recoveryModal = true;
            } catch (error) {
                this.$toast.error(
                    (error.response && error.response.data && error.response.data.message) ||
                        __("Failed to load recovery codes.")
                );
            }
        },

        async regenerateRecoveryCodes() {
            try {
                const { data } = await axios.post(`users/${this.editingId}/2fa/recovery-codes`);
                this.recoveryCodes = data.recovery_codes;
                this.$toast.success(__("Recovery codes regenerated."));
            } catch (error) {
                this.$toast.error(__("Failed to regenerate recovery codes."));
            }
        },

        openDisableModal() {
            this.twoFactorError = "";
            this.disableModal = true;
        },

        async disableTwoFactor() {
            this.twoFactorBusy = true;
            this.twoFactorError = "";
            try {
                await axios.post(`users/${this.editingId}/2fa/disable`);
                this.disableModal = false;
                this.twoFactorEnabled = false;
                this.$toast.success(__("Two factor authentication disabled."));
            } catch (error) {
                this.twoFactorError =
                    (error.response && error.response.data && error.response.data.message) ||
                    __("Failed to disable two factor authentication.");
            } finally {
                this.twoFactorBusy = false;
            }
        },

        async copySecret() {
            try {
                await navigator.clipboard.writeText(this.twoFactorSecret);
                this.$toast.success(__("Secret copied."));
            } catch (e) {
                this.$toast.error(__("Could not copy the secret."));
            }
        },
    },
};
</script>
