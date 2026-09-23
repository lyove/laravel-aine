<template>
    <div class="admin__profile relative h-full flex flex-col overflow-y-auto">
        <div class="w-full p-4 border-b bg-white">
            <div class="text-xl font-bold">{{ __('Settings') }}</div>
        </div>

        <div class="w-full flex flex-col flex-1 m-auto p-4 overflow-y-auto">
            <!-- Tab switcher -->
            <div class="flex border-b border-slate-200 mb-4">
                <button
                    type="button"
                    @click="activeTab = 'settings'"
                    :class="activeTab === 'settings' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                >
                    {{ __('Settings') }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'media'"
                    :class="activeTab === 'media' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                >
                    {{ __('Media Upload') }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'smtp'"
                    :class="activeTab === 'smtp' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                >
                    {{ __('SMTP') }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'security'"
                    :class="activeTab === 'security' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                >
                    {{ __('Security') }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'system'"
                    :class="activeTab === 'system' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                >
                    {{ __('System Tools') }}
                </button>
            </div>

            <!-- Tab 1: Settings -->
            <div v-if="activeTab === 'settings'" class="w-full bg-white rounded-md shadow-sm p-4">
                <form class="space-y-6" @submit.prevent="updateSettings()">
                    <div class="form-item">
                        <label v-formlabel>{{ __('App Name') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.name" autofocus v-forminput :placeholder="__('App name')" />
                            <p class="text-sm text-red-600 mt-1" v-if="settings.errors && settings.errors.name">{{ settings.errors.name }}</p>
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('Description') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.description" autofocus v-forminput :placeholder="__('App description')" />
                            <p class="text-sm text-red-600 mt-1" v-if="settings.errors && settings.errors.description">{{ settings.errors.description }}</p>
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('Version') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.version" v-forminput :placeholder="__('Version')" />
                            <p class="text-sm text-red-600 mt-1" v-if="settings.errors && settings.errors.version">{{ settings.errors.version }}</p>
                        </div>
                    </div>
                    <div class="form-button">
                        <label v-formlabel></label>
                        <div class="mt-1 relative">
                            <ui-button :color="'indigo-500'" @click="updateSettings()">{{ __('Update Settings') }}</ui-button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Media Upload -->
            <div v-if="activeTab === 'media'" class="w-full bg-white rounded-md shadow-sm p-4">
                <form class="space-y-6" @submit.prevent="updateSettings()">
                    <div class="form-item">
                        <label v-formlabel>{{ __('Max upload filesize (MB)') }}</label>
                        <div class="mt-1 relative">
                            <input type="number" min="1" max="2048" v-model.number="settings.media_max_upload_size" v-forminput :placeholder="__('e.g. 8')" />
                            <p class="text-sm text-red-600 mt-1" v-if="settings.errors && settings.errors.media_max_upload_size">{{ settings.errors.media_max_upload_size }}</p>
                        </div>
                    </div>

                    <div class="form-item">
                        <label v-formlabel>{{ __('Enable chunk size upload?') }}</label>
                        <div class="mt-1 relative flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" v-model="settings.media_enable_chunk_upload" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-slate-700">{{ __('Chunked upload') }}</span>
                            </label>
                            <p class="text-xs text-slate-400">{{ __('Splits large files into 2 MB chunks before uploading.') }}</p>
                        </div>
                    </div>

                    <div class="form-item">
                        <label v-formlabel>{{ __('Media thumbnails sizes') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.media_thumbnail_sizes" v-forminput placeholder="600" />
                            <p class="text-xs text-slate-400 mt-1">{{ __('Comma separated heights, e.g. 300,600. First value is used as the main thumbnail.') }}</p>
                            <p class="text-sm text-red-600 mt-1" v-if="settings.errors && settings.errors.media_thumbnail_sizes">{{ settings.errors.media_thumbnail_sizes }}</p>
                        </div>
                    </div>

                    <div class="form-item">
                        <label v-formlabel>{{ __('Image processing library') }}</label>
                        <div class="mt-1 relative flex items-center gap-6">
                            <label v-for="(d, dkey) in mediaStorageDrivers" :key="dkey" class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="radio" :value="dkey" v-model="settings.media_storage_driver" class="text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-slate-700">{{ __(d.label) }}</span>
                            </label>
                        </div>
                        <p v-if="settings.media_storage_driver !== 'local' && !mediaStorageConfigured" class="mt-1 text-xs text-amber-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>{{ __('Storage credentials are not configured yet. Uploads will fall back to local storage until you save valid credentials.') }}
                        </p>
                    </div>

                    <!-- Driver-specific configuration (schema-driven) -->
                    <div v-if="settings.media_storage_driver !== 'local' && ossFields.length" class="border-t border-slate-200 pt-4">
                        <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Storage Configuration') }}</h3>
                        <div class="space-y-4">
                            <div v-for="field in ossFields" :key="field.key" class="form-item">
                                <label v-formlabel>{{ __(field.label) }}</label>
                                <div class="mt-1 relative">
                                    <input
                                        v-if="field.type === 'checkbox'"
                                        type="checkbox"
                                        v-model="mediaStorageConfig[field.key]"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <input
                                        v-else
                                        :type="field.secret ? 'password' : 'text'"
                                        v-model="mediaStorageConfig[field.key]"
                                        :placeholder="field.placeholder || field.default || ''"
                                        v-forminput
                                    />
                                    <p class="text-xs text-slate-400 mt-1" v-if="field.hint">{{ __(field.hint) }}</p>
                                </div>
                            </div>

                            <div class="form-item">
                                <label v-formlabel></label>
                                <div class="mt-1 relative flex items-center gap-3">
                                    <ui-button :color="'slate-500'" :disabled="ossTestStatus && ossTestStatus.loading" @click="testStorageConnection()">
                                        <i class="fas fa-plug mr-2"></i>{{ __('Test Connection') }}
                                    </ui-button>
                                    <span v-if="ossTestStatus && ossTestStatus.loading" class="text-sm text-slate-400"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('Testing...') }}</span>
                                    <span v-else-if="ossTestStatus" :class="ossTestStatus.success ? 'text-green-600' : 'text-red-600'" class="text-sm">
                                        <i :class="ossTestStatus.success ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-1"></i>{{ ossTestStatus.message }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-button">
                        <label v-formlabel></label>
                        <div class="mt-1 relative">
                            <ui-button :color="'indigo-500'" @click="updateSettings()">{{ __('Update Media Settings') }}</ui-button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab 3: System Tools -->
            <div v-if="activeTab === 'system'" class="space-y-6">
                <!-- Cache Management -->
                <div class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Cache Management') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button @click="runSystemTool('cache/clear-all', __('Clear All Caches') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-broom text-indigo-500"></i>{{ __('Clear All Caches') }}
                        </button>
                        <button @click="runSystemTool('cache/clear-route', __('Clear Route Cache') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-route text-indigo-500"></i>{{ __('Clear Route Cache') }}
                        </button>
                        <button @click="runSystemTool('cache/clear-config', __('Clear Config Cache') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-cog text-indigo-500"></i>{{ __('Clear Config Cache') }}
                        </button>
                        <button @click="runSystemTool('cache/clear-view', __('Clear View Cache') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-eye text-indigo-500"></i>{{ __('Clear View Cache') }}
                        </button>
                        <button @click="runSystemTool('cache/clear-app', __('Clear App Cache') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-database text-indigo-500"></i>{{ __('Clear App Cache') }}
                        </button>
                        <button @click="runSystemTool('cache/rebuild', __('Rebuild Cache') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-sync text-green-500"></i>{{ __('Rebuild Cache') }}
                        </button>
                    </div>
                </div>

                <!-- Storage & Logs -->
                <div class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Storage & Logs') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button @click="runSystemTool('storage/link', __('Recreate Storage Link') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-link text-indigo-500"></i>{{ __('Recreate Storage Link') }}
                        </button>
                        <button @click="runSystemTool('logs/clear', __('Clear Old Logs') + '?')" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-file-excel text-indigo-500"></i>{{ __('Clear Old Logs') }}
                        </button>
                    </div>
                </div>

                <!-- Common Artisan Commands Reference -->
                <div class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Artisan Commands') }}</h3>
                    <p class="text-xs text-slate-500 mb-4">{{ __('Run these on the server via SSH when needed. Cache & storage tools above cover the most common ones from the UI.') }}</p>
                    <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan migrate</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Run pending database migrations') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan db:seed</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Run database seeders') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan optimize</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Cache config, routes & views (recommended in production)') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan config:clear</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Clear cached configuration') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan route:clear</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Clear cached routes') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan view:clear</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Clear compiled view cache') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan cache:clear</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Clear application cache') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan storage:link</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Recreate the public/storage symlink') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan queue:restart</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Restart queue workers (required after deploy)') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-amber-300 whitespace-nowrap">php artisan down / up</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Toggle maintenance mode') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan tinker</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('Interactive REPL for inspection & debugging') }}</span>
                        </div>
                        <div class="flex items-start gap-3">
                            <code class="shrink-0 rounded bg-slate-900 px-2 py-1 text-xs font-mono text-emerald-300 whitespace-nowrap">php artisan list</code>
                            <span class="text-xs text-slate-600 pt-1">{{ __('List all available Artisan commands') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Mode -->                <!-- Maintenance Mode -->
                <!-- <div class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Maintenance Mode') }}</h3>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2" :class="{ 'opacity-50 cursor-not-allowed': toolLoading, 'cursor-pointer': !toolLoading }">
                            <input type="radio" value="up" v-model="maintenanceMode" @change="toggleMaintenance(false)" :disabled="toolLoading" class="text-green-600 focus:ring-green-500" />
                            <span class="text-sm font-semibold" :class="maintenanceMode === 'up' ? 'text-green-700' : 'text-slate-500'">{{ __('Normal') }}</span>
                        </label>
                        <label class="flex items-center gap-2" :class="{ 'opacity-50 cursor-not-allowed': toolLoading, 'cursor-pointer': !toolLoading }">
                            <input type="radio" value="down" v-model="maintenanceMode" @change="toggleMaintenance(true)" :disabled="toolLoading" class="text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm font-semibold" :class="maintenanceMode === 'down' ? 'text-amber-700' : 'text-slate-500'">{{ __('Maintenance') }}</span>
                        </label>
                    </div>
                    <p v-if="status && status.maintenance" class="mt-2 text-xs text-amber-600">
                        <i class="fas fa-pause-circle mr-1"></i>{{ __('Site is currently in maintenance mode.') }}
                    </p>
                </div> -->

                <!-- Database -->
                <!-- <div class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">{{ __('Database') }}</h3>
                    <div class="mb-4">
                        <button @click="loadMigrateStatus" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-list text-indigo-500"></i>{{ __('Check Migration Status') }}
                        </button>
                    </div>
                    <div v-if="migrateStatus" class="mb-4 rounded-lg bg-slate-900 p-4 overflow-auto max-h-60">
                        <pre class="text-xs text-slate-100 whitespace-pre-wrap">{{ migrateStatus.output }}</pre>
                    </div>
                    <div class="flex gap-3">
                        <button @click="runSystemTool('migrate', __('Run pending migrations') + '?', true)" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-play text-indigo-500"></i>{{ __('Run Migrations') }}
                        </button>
                        <button v-if="status && status.environment !== 'production'" @click="runSystemTool('fresh-seed', __('Reset database and seed') + '? This will DELETE ALL DATA!', true)" :disabled="toolLoading" class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>{{ __('Reset & Seed') }}
                        </button>
                    </div>
                    <p v-if="status && status.environment === 'production'" class="mt-3 text-xs text-red-500">
                        <i class="fas fa-lock mr-1"></i>{{ __('Reset & Seed is disabled in production.') }}
                    </p>
                </div> -->

                <!-- Tool Output -->
                <div v-if="toolOutput" class="w-full bg-white rounded-md shadow-sm p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-2 uppercase tracking-wide">{{ __('Output') }}</h3>
                    <div class="rounded-lg bg-slate-900 p-4 overflow-auto max-h-60">
                        <pre class="text-xs text-slate-100 whitespace-pre-wrap">{{ toolOutput }}</pre>
                    </div>
                </div>
            </div>

            <!-- Tab 4: SMTP -->
            <div v-if="activeTab === 'smtp'" class="w-full bg-white rounded-md shadow-sm p-4">
                <form class="space-y-6" @submit.prevent="updateSettings()">
                    <div class="form-item">
                        <label v-formlabel>{{ __('Mail Driver') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.mail_driver" v-forminput :placeholder="__('smtp')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('SMTP Host') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.mail_host" v-forminput :placeholder="__('e.g. smtp.qq.com')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('SMTP Port') }}</label>
                        <div class="mt-1 relative">
                            <input type="number" v-model.number="settings.mail_port" v-forminput :placeholder="__('e.g. 465')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('Username') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.mail_username" v-forminput :placeholder="__('Email address')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('Password') }}</label>
                        <div class="mt-1 relative">
                            <input type="password" v-model="settings.mail_password" v-forminput :placeholder="__('SMTP password / app password')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('Encryption') }}</label>
                        <div class="mt-1 relative">
                            <select v-model="settings.mail_encryption" v-forminput>
                                <option value="" disabled selected>{{ __('Select encryption') }}</option>
                                <option value="ssl">{{ __('SSL') }}</option>
                                <option value="tls">{{ __('TLS') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('From Address') }}</label>
                        <div class="mt-1 relative">
                            <input type="email" v-model="settings.mail_from_address" v-forminput :placeholder="__('noreply@example.com')" />
                        </div>
                    </div>
                    <div class="form-item">
                        <label v-formlabel>{{ __('From Name') }}</label>
                        <div class="mt-1 relative">
                            <input type="text" v-model="settings.mail_from_name" v-forminput :placeholder="__('e.g. Aine')" />
                        </div>
                    </div>
                    <div class="form-button">
                        <label v-formlabel></label>
                        <div class="mt-1 relative">
                            <ui-button :color="'indigo-500'" @click="updateSettings()">{{ __('Save SMTP Settings') }}</ui-button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab 5: Security -->
            <div v-if="activeTab === 'security'" class="w-full bg-white rounded-md shadow-sm p-4 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-1 uppercase tracking-wide">{{ __('Admin Login Path') }}</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        {{ __('Hide the admin login from scanners by moving it to a custom URL. Leave empty to use the default "admin".') }}
                    </p>

                    <label class="block text-xs font-semibold text-slate-600 mb-1">{{ __('Path slug') }}</label>
                    <input
                        type="text"
                        v-model="settings.admin_path"
                        v-forminput
                        :placeholder="__('admin')"
                        class="w-full"
                    />
                    <p class="text-xs text-slate-400 mt-2">
                        {{ __('Example:') }} <code class="text-slate-500">mx9k2</code> &rarr; {{ __('login URL becomes') }} <code class="text-slate-500">/mx9k2/login</code>
                    </p>

                    <div v-if="settings.errors && settings.errors.admin_path" class="mt-2 text-xs text-red-600">
                        {{ settings.errors.admin_path[0] }}
                    </div>

                    <div class="mt-4">
                        <UiButton :color="'indigo-500'" @click="updateSettings" :loading="toolLoading">
                            {{ __('Save') }}
                        </UiButton>
                    </div>

                    <div v-if="newAdminUrl" class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        {{ __('Admin path changed. New login URL:') }}
                        <code class="font-semibold">{{ newAdminUrl }}</code>
                        <p class="mt-1 text-amber-700">{{ __('Bookmark it, then open it in a new tab to verify you can log in before closing this page.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import UiButton from "@/components/Button.vue";
import { useAdminStore } from '@/admin/store';
import { __ } from '@/admin/translations/engine';

export default {
    components: { UiButton },

    data() {
        return {
            activeTab: 'settings',
            settings: { name: null, description: null, version: null, media_max_upload_size: 8, media_enable_chunk_upload: false, media_thumbnail_sizes: '600', media_storage_driver: 'local', mail_driver: 'smtp', mail_host: '', mail_port: 465, mail_username: '', mail_password: '', mail_encryption: 'ssl', mail_from_address: '', mail_from_name: '', admin_path: '', errors: {} },
            mediaStorageConfig: {},
            mediaStorageDrivers: {},
            mediaStorageConfigured: true,
            newAdminUrl: null,
            ossTestStatus: null,
            status: null,
            toolLoading: false,
            toolOutput: null,
            migrateStatus: null,
            maintenanceMode: 'up',
        };
    },

    computed: {
        ossFields() {
            const driver = this.settings.media_storage_driver;
            const schema = (this.mediaStorageDrivers && this.mediaStorageDrivers[driver]) || {};
            const fields = schema.fields || {};
            return Object.keys(fields).map((key) => ({
                key,
                label: fields[key].label || key,
                required: !!fields[key].required,
                secret: !!fields[key].secret,
                type: fields[key].type || '',
                placeholder: fields[key].placeholder || '',
                hint: fields[key].hint || '',
                default: fields[key].default || '',
            }));
        },
    },

    methods: {
        getSettings() {
            axios.get("settings").then(
                (response) => {
                    this.settings = response.data;
                    this.mediaStorageConfig = response.data.media_storage_config || {};
                    this.mediaStorageDrivers = response.data.media_storage_drivers || {};
                    this.mediaStorageConfigured = response.data.media_storage_configured !== false;
                },
                (error) => { console.warn(error); }
            );
        },

        updateSettings() {
            const payload = Object.assign({}, this.settings, {
                media_storage_config: this.prepareStorageConfigForSubmit(),
            });
            delete payload.errors;
            delete payload.media_storage_drivers;
            delete payload.media_storage_configured;

            axios.post("settings/update", payload).then(
                (response) => {
                    this.$toast.success(__('Saved.'));
                    this.settings.errors = {};
                    this.ossTestStatus = null;
                    if (response.data && response.data.admin_path_changed) {
                        this.newAdminUrl = response.data.admin_url;
                    }
                    // Refresh to re-mask secrets and refresh the configured flag.
                    this.getSettings();
                },
                (error) => {
                    if (error.response && error.response.status == 422) {
                        this.settings.errors = error.response.data.errors;
                    }
                }
            );
        },

        prepareStorageConfigForSubmit() {
            const config = Object.assign({}, this.mediaStorageConfig || {});
            // Masked secrets must be submitted empty so the backend keeps the saved value.
            Object.keys(config).forEach((key) => {
                if (config[key] === '••••••••') {
                    config[key] = '';
                }
            });

            return config;
        },

        testStorageConnection() {
            this.ossTestStatus = { loading: true, success: false, message: '' };

            axios.post("settings/test-media-storage", {
                driver: this.settings.media_storage_driver,
                config: this.mediaStorageConfig,
            }).then((response) => {
                this.ossTestStatus = { loading: false, success: true, message: response.data.message || __('Connection successful.') };
            }).catch((error) => {
                const message = (error.response && error.response.data && error.response.data.message) || __('Connection failed.');
                this.ossTestStatus = { loading: false, success: false, message };
            });
        },

        loadStatus() {
            axios.get("system/status").then(
                (response) => {
                    this.status = response.data;
                    this.maintenanceMode = response.data.maintenance ? 'down' : 'up';
                },
                (error) => { console.warn(error); }
            );
        },

        toggleMaintenance(enable) {
            const action = enable ? 'down' : 'up';
            const msg = enable
                ? __('Enable maintenance mode') + '?'
                : __('Disable maintenance mode') + '?';
            this.$swal.fire({
                title: __('Are you sure'),
                text: msg,
                icon: 'warning',
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: enable ? '#dc3545' : '#3085d6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: __('Yes, I am sure!'),
            }).then((result) => {
                if (!result.isConfirmed) {
                    // Revert radio to actual state
                    this.maintenanceMode = this.status && this.status.maintenance ? 'down' : 'up';
                    return;
                }
                this.executeTool('maintenance/' + action);
            });
        },

        loadMigrateStatus() {
            this.toolLoading = true;
            this.toolOutput = null;
            axios.get("system/migrate-status").then(
                (response) => {
                    this.migrateStatus = response.data;
                    this.toolOutput = response.data.output;
                    this.$toast.success(response.data.message);
                    this.toolLoading = false;
                },
                (error) => {
                    this.$toast.error(__('Failed to load migration status.'));
                    this.toolLoading = false;
                }
            );
        },

        runSystemTool(endpoint, confirmMessage, isDangerous = false) {
            this.$swal.fire({
                title: __('Are you sure'),
                text: confirmMessage,
                icon: 'warning',
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: isDangerous ? '#dc3545' : '#3085d6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: __('Yes, I am sure!'),
            }).then((result) => {
                if (!result.isConfirmed) return;
                this.executeTool(endpoint);
            });
        },

        executeTool(endpoint) {
            this.toolLoading = true;
            this.toolOutput = null;
            axios.post("system/" + endpoint).then(
                (response) => {
                    this.toolOutput = response.data.output || response.data.message;
                    this.$toast.success(response.data.message);
                    this.toolLoading = false;
                    // Refresh status after any system operation
                    this.loadStatus();
                },
                (error) => {
                    const msg = error.response?.data?.message || __('Operation failed.');
                    this.$toast.error(msg);
                    this.toolOutput = msg;
                    this.toolLoading = false;
                }
            );
        },
    },

    watch: {
        activeTab(val) {
            if (val === 'system' && !this.status) {
                this.loadStatus();
            }
        }
    },

    created() {
        useAdminStore().setTopbarContent({
            page: 'settings',
            type: 'settings',
            title: this.__('Settings'),
            breadcrumb: [
                { name: 'Dashboard', url: '/', icon: 'fa fa-tachometer-alt' },
                { name: 'Settings', icon: 'fa fa-cog' },
            ],
        });
    },

    mounted() {
        this.getSettings();
    },

    unmounted() {
        if (this.$swal && typeof this.$swal.close === 'function') {
            this.$swal.close();
        }
    },
};
</script>
