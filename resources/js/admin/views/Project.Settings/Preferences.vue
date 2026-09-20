<template>
    <div class="relative h-full flex flex-col overflow-y-auto">
        <project-header :project="project"></project-header>
        <div class="flex">
            <div class="w-3/12 bg-white overflow-x-hidden">
                <settings-sidebar :project="project"></settings-sidebar>
            </div>
            <div class="w-9/12 overflow-x-hidden">
                <div class="p-4">
                    <!-- Tab switcher -->
                    <div class="flex border-b border-slate-200 mb-4">
                        <button v-for="tab in tabs" :key="tab.key" type="button" @click="activeTab = tab.key"
                            :class="activeTab === tab.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition whitespace-nowrap">
                            {{ __(tab.label) }}
                        </button>
                    </div>

                    <!-- Tab: General -->
                    <div v-if="activeTab === 'general'" class="w-full bg-white rounded-md p-4">
                        <h4 class="mb-2 font-bold text-lg">{{ __('Project Details') }}</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label v-formlabel>{{ __('Project Name') }}</label>
                                <div class="mt-1 relative">
                                    <input type="text" v-model="form.name" autofocus v-forminput :placeholder="__('Project Name')" @input="generateSlugFromName" />
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Project Slug') }}</label>
                                <div class="mt-1 relative">
                                    <input type="text" v-model="form.slug" v-forminput :placeholder="__('project-slug')" pattern="[a-z0-9\-]+" @blur="checkSlug" @input="onSlugInput" />
                                    <p class="text-sm text-gray-500 mt-1">{{ __('Only lowercase letters, numbers, and hyphens allowed') }}</p>
                                    <p v-if="form.slugExists" class="text-sm text-red-600 mt-2">{{ __('This slug is already in use by another project.') }}</p>
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Description') }}</label>
                                <div class="mt-1 relative">
                                    <input type="text" v-model="form.description" v-forminput :placeholder="__('Description')" />
                                </div>
                            </div>
                            <div v-if="project && project.s3">
                                <label v-formlabel>{{ __('Default Upload Disk') }}</label>
                                <div class="grid grid-cols-4 space-x-2">
                                    <div class="col-span-1">
                                        <label for="default_disk_local" class="p-5 border border-gray-300 rounded-md text-sm flex items-center space-x-2 cursor-pointer">
                                            <input name="default_disk" id="default_disk_local" type="radio" v-model="form.disk" value="local" />
                                            <span>{{ __('Local (storage folder)') }}</span>
                                        </label>
                                    </div>
                                    <div class="col-span-1">
                                        <label for="default_disk_s3" class="p-5 border border-gray-300 rounded-md text-sm flex items-center space-x-2 cursor-pointer">
                                            <input name="default_disk" id="default_disk_s3" type="radio" v-model="form.disk" value="s3" />
                                            <span>{{ __('AWS S3') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-2" />
                            <div>
                                <label v-formlabel>{{ __('Copyright') }}</label>
                                <div class="mt-1 relative">
                                    <input type="text" v-model="form.copyright" v-forminput :placeholder="__('© 2026 Your Company')" />
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Filing Info') }}</label>
                                <div class="mt-1 relative">
                                    <input type="text" v-model="form.filing_info" v-forminput :placeholder="__('ICP filing number, etc.')" />
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Timezone') }}</label>
                                <div class="mt-1 relative">
                                    <select v-model="form.timezone" v-forminput>
                                        <option value="">{{ __('Default (UTC)') }}</option>
                                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Language Direction') }}</label>
                                <div class="mt-1 relative flex gap-6">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="radio" v-model="form.language_direction" value="ltr" class="text-indigo-600" /> {{ __('LTR') }}
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="radio" v-model="form.language_direction" value="rtl" class="text-indigo-600" /> {{ __('RTL') }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label v-formlabel></label>
                                <div class="mt-1 relative">
                                    <ui-button :color="'green-500'" :disabled="isReadonly" @click="save()">{{ __('Save Settings') }}</ui-button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Logo -->
                    <div v-if="activeTab === 'logo'" class="w-full bg-white rounded-md p-4">
                        <h4 class="mb-2 font-bold text-lg">{{ __('Logo & Favicon') }}</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label v-formlabel>{{ __('Logo URL') }}</label>
                                <div class="mt-1 relative"><input type="text" v-model="form.logo_url" v-forminput :placeholder="__('/storage/logo.png')" /></div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Favicon URL') }}</label>
                                <div class="mt-1 relative"><input type="text" v-model="form.favicon_url" v-forminput :placeholder="__('/storage/favicon.ico')" /></div>
                            </div>
                            <div>
                                <label v-formlabel></label>
                                <div class="mt-1 relative"><ui-button :color="'green-500'" :disabled="isReadonly" @click="save()">{{ __('Save Settings') }}</ui-button></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Custom Code -->
                    <div v-if="activeTab === 'custom'" class="w-full bg-white rounded-md p-4">
                        <h4 class="mb-2 font-bold text-lg">{{ __('Custom Code') }}</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label v-formlabel>{{ __('Custom Header (CSS/JS)') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.custom_header" v-forminput rows="6" class="font-mono text-xs" :placeholder="__('<style>...</style> or <script>...</script>')"></textarea></div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Injected into the <head> section.') }}</p>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Custom Footer (HTML/CSS/JS)') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.custom_footer" v-forminput rows="6" class="font-mono text-xs" :placeholder="__('<div>...</div> or <script>...</script>')"></textarea></div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Injected before </body>.') }}</p>
                            </div>
                            <div>
                                <label v-formlabel></label>
                                <div class="mt-1 relative"><ui-button :color="'green-500'" :disabled="isReadonly" @click="save()">{{ __('Save Settings') }}</ui-button></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: SEO -->
                    <div v-if="activeTab === 'seo'" class="w-full bg-white rounded-md p-4">
                        <h4 class="mb-2 font-bold text-lg">{{ __('SEO Settings') }}</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label v-formlabel>{{ __('Meta Title') }}</label>
                                <div class="mt-1 relative"><input type="text" v-model="form.seo_title" v-forminput :placeholder="__('Page title for SEO')" /></div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Meta Description') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.seo_description" v-forminput rows="2" :placeholder="__('Brief description for search engines')"></textarea></div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Meta Keywords') }}</label>
                                <div class="mt-1 relative"><input type="text" v-model="form.seo_keywords" v-forminput :placeholder="__('comma, separated, keywords')" /></div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Analytics Script') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.analytics_script" v-forminput rows="4" class="font-mono text-xs" :placeholder="__('<script>...</script> (Google Analytics, etc.)')"></textarea></div>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Injected into the <head> section.') }}</p>
                            </div>
                            <div>
                                <label v-formlabel></label>
                                <div class="mt-1 relative"><ui-button :color="'green-500'" :disabled="isReadonly" @click="save()">{{ __('Save Settings') }}</ui-button></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Contact -->
                    <div v-if="activeTab === 'contact'" class="w-full bg-white rounded-md p-4">
                        <h4 class="mb-2 font-bold text-lg">{{ __('Contact Information') }}</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label v-formlabel>{{ __('Address') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.contact_address" v-forminput rows="1" :placeholder="__('Street, City, Country')"></textarea></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label v-formlabel>{{ __('Email') }}</label>
                                    <div class="mt-1 relative"><input type="email" v-model="form.contact_email" v-forminput :placeholder="__('contact@example.com')" /></div>
                                </div>
                                <div>
                                    <label v-formlabel>{{ __('Phone') }}</label>
                                    <div class="mt-1 relative"><input type="text" v-model="form.contact_phone" v-forminput :placeholder="__('+1 234 567 890')" /></div>
                                </div>
                            </div>
                            <div>
                                <label v-formlabel>{{ __('Contact Text') }}</label>
                                <div class="mt-1 relative"><textarea v-model="form.contact_text" v-forminput rows="3" :placeholder="__('Rich text contact information')"></textarea></div>
                            </div>
                            <div>
                                <label v-formlabel></label>
                                <div class="mt-1 relative"><ui-button :color="'green-500'" :disabled="isReadonly" @click="save()">{{ __('Save Settings') }}</ui-button></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { __ } from '../../translations/engine';
import UiButton from "../../../components/Button.vue";
import ProjectHeader from "../components/ProjectHeader.vue";
import SettingsSidebar from "./sections/SettingsSidebar.vue";
import projectBreadcrumb from '../../mixins/projectBreadcrumb';
import { useAdminStore } from '../../store';

function detectBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch (e) {
        return 'UTC';
    }
}

export default {
    components: { UiButton, ProjectHeader, SettingsSidebar },
    mixins: [projectBreadcrumb],
    data() {
        const p = useAdminStore().currentProject || {};
        return {
            project: p,
            activeTab: 'general',
            tabs: [
                { key: 'general', label: __('General') },
                { key: 'logo', label: __('Logo & Favicon') },
                { key: 'custom', label: __('Custom Code') },
                { key: 'seo', label: __('SEO') },
                { key: 'contact', label: __('Contact') },
            ],
            form: {
                ...p,
                timezone: p.timezone || detectBrowserTimezone(),
                errors: { name: [], slug: [], description: [] },
                slugExists: false,
                slugManuallyEdited: false,
            },
            browserTimezone: detectBrowserTimezone(),
            fallbackTimezones: [
                'UTC',
                'Asia/Shanghai','Asia/Hong_Kong','Asia/Taipei','Asia/Tokyo','Asia/Seoul','Asia/Singapore','Asia/Kuala_Lumpur','Asia/Jakarta','Asia/Manila','Asia/Bangkok','Asia/Ho_Chi_Minh','Asia/Kolkata','Asia/Karachi','Asia/Dhaka','Asia/Dubai','Asia/Riyadh','Asia/Jerusalem','Asia/Tehran','Asia/Tbilisi',
                'Europe/London','Europe/Dublin','Europe/Lisbon','Europe/Paris','Europe/Berlin','Europe/Madrid','Europe/Rome','Europe/Amsterdam','Europe/Vienna','Europe/Stockholm','Europe/Warsaw','Europe/Prague','Europe/Athens','Europe/Helsinki','Europe/Bucharest','Europe/Istanbul','Europe/Kyiv','Europe/Moscow',
                'America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Phoenix','America/Anchorage','America/Halifax','America/Toronto','America/Vancouver','America/Mexico_City','America/Bogota','America/Lima','America/Santiago','America/Sao_Paulo','America/Argentina/Buenos_Aires',
                'Australia/Sydney','Australia/Melbourne','Australia/Brisbane','Australia/Perth','Pacific/Auckland','Pacific/Honolulu',
            ],
        };
    },
    computed: {
        isReadonly() {
            return this.project && (this.project.is_readonly || !this.project.status);
        },
        timezones() {
            let list = null;
            if (typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function') {
                try {
                    list = Intl.supportedValuesOf('timeZone');
                } catch (e) {
                    list = null;
                }
            }
            if (!Array.isArray(list) || list.length === 0) {
                list = [...this.fallbackTimezones];
            }
            const bt = this.browserTimezone;
            if (bt && !list.includes(bt)) {
                list = [bt, ...list];
            }
            return list;
        },
    },
    methods: {
        generateSlugFromName() {
            if (this.form.slugManuallyEdited) return;
            this.form.slug = this.$slugify(this.form.name || '');
        },
        onSlugInput() {
            this.form.slugManuallyEdited = true;
            this.form.slugExists = false;
        },
        checkSlug() {
            let slug = this.form.slug; if (!slug) return;
            axios.get(`projects/check-slug/${slug}?exclude_id=${this.project.id}`).then(r => {
                this.form.slugExists = !r.data.available;
            });
        },
        save() {
            if (!this.form.timezone) {
                this.form.timezone = this.browserTimezone;
            }
            axios.post(`projects/update/${this.project.id}`, this.form).then(r => {
                this.$toast.success(__('Saved.'));
                this.form.slugExists = false;
                useAdminStore().setCurrentProject(r.data.id);
            }, e => {
                if (e.response?.status === 422) this.form.errors = e.response.data.errors;
            });
        },
    },
};
</script>
