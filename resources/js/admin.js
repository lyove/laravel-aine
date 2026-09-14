/**
 * Admin entry
 */
import { createApp } from "vue";
import { createPinia } from "pinia";

import { createToastInterface, toastInjectionKey } from "vue-toastification";
import "vue-toastification/dist/index.css";

import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

import VueSweetalert2 from "vue-sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css";

import { TailwindPagination } from "laravel-vue-pagination";

import "./vendor/fontawesome-pro/css/all.min.css";
import "./vendor/nprogress/nprogress.css";

import Slugify from "./vendor/slugify";

// Tooltip/popover library (v-tooltip directive)
import FloatingVue from "floating-vue";
import "floating-vue/dist/style.css";

import { registerDirectives } from "./utils/directives";
import { registerFilters } from "./utils/filters";
import ColorpickerPlugin from "./vendor/colorpicker";

// Explicit translation helper (see admin/translations/engine.js)
import { __ } from "./admin/translations/engine";

// Routes
import router from "./admin/routes";

// Admin Layout
import AdminLayout from "./admin/Layout.vue";

/**
 * Axios HTTP library
 */
import axios from "axios";
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.baseURL = "/admin-api";

const csrfTokenMeta = document.head.querySelector('meta[name="csrf-token"]');
if (csrfTokenMeta) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfTokenMeta.content;
}

// NProgress — progress bar for real network requests only.
import NProgress from "./vendor/nprogress/nprogress";
NProgress.configure({
    showSpinner: false,
    trickle: false,
    speed: 150,
    minimum: 0.2,
});

let pendingRequests = 0;

window.axios.interceptors.request.use(
    (config) => {
        if (!config.silent) {
            pendingRequests++;
            NProgress.start();
        }
        return config;
    },
    (error) => {
        if (!error.config || !error.config.silent) {
            pendingRequests = Math.max(0, pendingRequests - 1);
            if (pendingRequests === 0) {
                NProgress.done();
            }
        }
        return Promise.reject(error);
    }
);

window.axios.interceptors.response.use(
    (response) => {
        if (!response.config || !response.config.silent) {
            pendingRequests = Math.max(0, pendingRequests - 1);
            if (pendingRequests === 0) {
                NProgress.done();
            }
        }
        return response;
    },
    (error) => {
        if (error.response && error.response.status === 401) {
            window.location.reload();
        } else if (error.response && error.response.status === 403) {
            console.warn(error.response.data?.message || "Forbidden");
        } else if (error.response && error.response.status === 404) {
            console.warn("Resource not found. Please check your connection and try again.");
        } else if (error.response && error.response.status === 419) {
            window.location.reload();
        } else if (error.response && error.response.status === 422) {
            // Validation errors - let components handle display
        } else if (error.request && !error.response) {
            console.warn("Cannot connect to server. Please check your network connection.");
        } else {
            console.warn("An unexpected error occurred. Please try again.");
        }

        if (!error.config || !error.config.silent) {
            pendingRequests = Math.max(0, pendingRequests - 1);
            if (pendingRequests === 0) {
                NProgress.done();
            }
        }
        return Promise.reject(error);
    }
);

/**
 * Create Vue app
 */
const app = createApp(AdminLayout);
const pinia = createPinia();

// Toast
const toast = createToastInterface({
    draggable: false,
    timeout: 2000,
    pauseOnFocusLoss: false,
    position: "top-center",
});

// vue-toastification
app.config.globalProperties.$toast = toast;
app.provide(toastInjectionKey, toast);

// Explicit translation helper — templates can write `{{ __('...') }}` and script code `this.__('...')`
app.config.globalProperties.__ = __;

// Register plugins
app.use(pinia);
app.use(router);
app.use(VueSweetalert2, {
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: __('Yes, I am sure!'),
    icon: "warning",
});
app.use(Slugify);
app.use(ColorpickerPlugin);

// Register directives and filters
registerDirectives(app);
registerFilters(app);

// v-tooltip — used by the topbar "open website" button and content tables
app.use(FloatingVue);

// Global components
app.component("pagination", TailwindPagination);
app.component("v-select", vSelect);

// Keep the admin shell hidden until the boot UI language is applied
const adminRoot = document.getElementById("admin");
if (adminRoot) {
    adminRoot.setAttribute("data-ui-pending", "");
    setTimeout(() => adminRoot.removeAttribute("data-ui-pending"), 5000);
}

// Mount
app.mount("#admin");

// Activate the saved admin UI language (translates the interface in place).
import { useAdminStore } from "./admin/store";
useAdminStore().initUiLocale();
