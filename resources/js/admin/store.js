import { defineStore } from 'pinia';
import axios from 'axios';
import {
    setLocale,
    setProjectDict,
    setBaseUiDict,
    BASE_LOCALE,
    loadCachedUiDict,
    saveCachedUiDict,
    loadCachedProjectDict,
    saveCachedProjectDict,
} from './translations/engine';

/**
 * Reveal the admin shell once the boot UI language has been applied. The
 * `data-ui-pending` attribute (set by admin.js before mount) keeps the app
 * hidden until then, so a page refresh never flashes the English UI before
 * the saved language's translations arrive.
 */
function markUiReady() {
    const el = document.getElementById('admin');
    if (el) {
        el.removeAttribute('data-ui-pending');
    }
}

/**
 * The globally configured default UI language (Settings → Localization →
 * "Set as default") is the ONLY way to change the admin's display language —
 * the per-user topbar switcher was removed. The last known default is cached
 * so a page refresh can apply its dictionary synchronously (no network) and
 * never flashes the base language before translations arrive.
 */
const UI_BASE_CACHE_KEY = 'aine_admin_ui_base';

function loadCachedUiBase() {
    try {
        return localStorage.getItem(UI_BASE_CACHE_KEY) || null;
    } catch (error) {
        return null;
    }
}

function saveCachedUiBase(baseLocale) {
    try {
        if (baseLocale) {
            localStorage.setItem(UI_BASE_CACHE_KEY, baseLocale);
        }
        else localStorage.removeItem(UI_BASE_CACHE_KEY);
    } catch (error) {
        // Storage unavailable: the cache is a nicety, never fatal.
    }
}

export const useAdminStore = defineStore('admin', {
    state: () => ({
        user: {},
        settings: {},
        currentProject: null,
        currentCollection: null,
        routeLoading: false,
        currentProjectLoadedAt: 0,
        topbarContent: null,
        columnSettings: [],
        uiLocale: BASE_LOCALE,
        uiBaseLocale: BASE_LOCALE,
        _localeSeq: 0,
        _projectSeq: 0,
    }),

    actions: {
        /**
         * Re-apply the current UI language's dictionary and refresh its
         * cache — used after translations are saved so open pages update
         * immediately. This never changes the UI language itself: the global
         * default (Localization → "Set as default") is the only way to do
         * that, via initUiLocale().
         */
        async refreshUiLocale() {
            const seq = ++this._localeSeq;
            const dict = await setLocale(this.uiLocale, BASE_LOCALE);
            if (seq !== this._localeSeq) {
                return;
            }
            if (dict && Object.keys(dict).length) {
                saveCachedUiDict(this.uiLocale, dict);
            }
            if (this.uiLocale === BASE_LOCALE) {
                await this.loadBaseUiDict();
            }
            await this.loadProjectTranslationsDict();
        },
        /**
         * Apply the global default UI language. `expectedBase` is optional:
         * callers that already know the new default (Localization → "Set as
         * default") pass it so a stale cached apply is skipped.
         */
        async initUiLocale(expectedBase = null) {
            const seq = ++this._localeSeq;

            const bootBase = expectedBase || loadCachedUiBase() || BASE_LOCALE;
            if (bootBase !== BASE_LOCALE) {
                const cached = loadCachedUiDict(bootBase);
                if (cached) {
                    this.uiLocale = bootBase;
                    await setLocale(this.uiLocale, BASE_LOCALE, cached);
                    markUiReady();
                }
            } else {
                const cached = loadCachedUiDict(BASE_LOCALE);
                if (cached) {
                    setBaseUiDict(cached);
                    markUiReady();
                }
            }

            await this.loadUiLocales();
            if (seq !== this._localeSeq) {
                return;
            }

            this.uiLocale = this.uiBaseLocale || BASE_LOCALE;
            const dict = await setLocale(this.uiLocale, BASE_LOCALE);
            if (seq !== this._localeSeq) {
                return;
            }
            if (dict && Object.keys(dict).length) {
                saveCachedUiDict(this.uiLocale, dict);
            }
            if (this.uiLocale === BASE_LOCALE) {
                await this.loadBaseUiDict();
                if (seq !== this._localeSeq) {
                    return;
                }
            }

            markUiReady();
            await this.loadProjectTranslationsDict();
        },
        /**
         * Fetch the base-language UI dictionary and apply it as an overlay
         * (admin UI in the base language). Base-language strings are edited
         * on the Translations page; saved values override the built-in
         * labels.
         */
        async loadBaseUiDict() {
            try {
                const { data } = await axios.get('translations/dict', { params: { locale: BASE_LOCALE } });
                const dict = (data && data.dict) || {};
                if (Object.keys(dict).length) {
                    saveCachedUiDict(BASE_LOCALE, dict);
                }
                setBaseUiDict(dict);
                return dict;
            } catch (error) {
                console.warn('Failed to load base UI translations:', error);
                return {};
            }
        },
        async loadUiLocales() {
            let locales = [];
            try {
                const { data } = await axios.get('translations/locales');
                if (data) {
                    this.uiBaseLocale = data.base_locale || BASE_LOCALE;
                    this.uiLocale = this.uiBaseLocale;
                    if (Array.isArray(data.locales) && data.locales.length) {
                        locales = data.locales;
                    }
                    saveCachedUiBase(this.uiBaseLocale);
                }
            } catch (error) {
                console.warn('Failed to load UI locales:', error);
            }
            return locales;
        },
        async loadProjectTranslationsDict() {
            const projectId = this.currentProject?.id;
            if (!projectId) {
                setProjectDict({});
                return {};
            }
            const seq = this._localeSeq;

            const cached = loadCachedProjectDict(projectId, this.uiLocale);
            if (cached) {
                setProjectDict(cached);
            }

            try {
                const { data } = await axios.get(
                    `projects/settings/translations/${projectId}/dict`,
                    { params: { locale: this.uiLocale } }
                );
                if (seq !== this._localeSeq) {
                    return {};
                }
                if (this.currentProject?.id !== projectId) {
                    return {};
                }
                const dict = (data && data.dict) || {};
                setProjectDict(dict);
                if (Object.keys(dict).length) {
                    saveCachedProjectDict(projectId, this.uiLocale, dict);
                }
                return dict;
            } catch (error) {
                if (seq !== this._localeSeq) {
                    return {};
                }
                if (this.currentProject?.id !== projectId) {
                    return {};
                }
                console.warn('Failed to load project translations:', error);
                if (!cached) {
                    setProjectDict({});
                }
                return {};
            }
        },
        async getUser() {
            return await axios
                .get('user')
                .then((response) => { this.user = response.data });
        },
        async setCurrentProject(projectId, options = {}) {
            const seq = ++this._projectSeq;

            if (!projectId) {
                this.currentProject = null;
                this.currentCollection = null;
                this.currentProjectLoadedAt = 0;
                setProjectDict({});
                return;
            }
            try {
                const response = await axios.get('projects/' + projectId, { silent: options.silent });
                if (seq !== this._projectSeq) {
                    return;
                }
                this.currentProject = response.data;
                this.currentProjectLoadedAt = Date.now();
                this.loadProjectTranslationsDict();
            } catch (error) {
                console.error('Failed to load project:', error);
                if (seq !== this._projectSeq) {
                    return;
                }
                this.currentProjectLoadedAt = 0;
                if (this.currentProject?.id !== projectId) {
                    this.currentProject = null;
                    this.currentCollection = null;
                    setProjectDict({});
                }
            }
        },
        async setCurrentCollection({ projectId, colId }) {
            if (!colId) {
                this.currentCollection = null;
                return;
            }
            try {
                const response = await axios.get('collections/show/' + projectId + '/' + colId);
                this.currentCollection = response.data.collection;
            } catch (error) {
                console.error('Failed to load collection:', error);
                this.currentCollection = null;
            }
        },
        setColumns(obj) {
            this.columnSettings.push(obj);
        },
        updateColumn(obj) {
            const found = this.columnSettings.find(
                (o) => o.project_id === obj.project_id && o.collection_id === obj.collection_id
            );
            if (found) {
                found.columns = obj.columns;
            }
        },
        setTopbarContent(component) {
            this.topbarContent = component;
        },
        clearTopbarContent() {
            this.topbarContent = null;
        },
        logout() {
            this.$reset();
        },
    },

    getters: {
        userRoles: (state) => state.user?.roles || [],
    },
});
