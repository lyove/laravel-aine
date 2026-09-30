/**
 * Frontend app entry
 */

import { createApp } from "vue";
import { createPinia } from "pinia";

// Google Fonts
import "../css/fonts.css";
// Font Awesome
import "./vendor/fontawesome-pro/css/all.min.css";

// Routes
import router from "./frontend/routes";

// Frontend Layout
import FrontendLayout from "./frontend/Layout.vue";

/**
 * We'll load the axios HTTP library
 */
import axios from "axios";
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

/**
 * Create Vue app
 */
const app = createApp(FrontendLayout);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Mount
app.mount("#app");
