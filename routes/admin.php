<?php

use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\UsersManagementController;
use App\Http\Controllers\Admin\UserTwoFactorController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ProjectsController;
use App\Http\Controllers\Admin\CollectionsController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\CollectionFieldsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TranslationsController;
use App\Http\Controllers\Admin\ProjectTranslationsController;
use App\Http\Controllers\Admin\LocalizationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\AdminPasswordResetLinkController;
use App\Http\Controllers\Auth\AdminNewPasswordController;
use App\Http\Controllers\Frontend\FormController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ThemesController;

// API Routes
Route::middleware(['auth:web', 'backend.user'])->prefix(\App\Support\AdminPath::apiSlug())->group(function(){
    Route::get('/user', function () {
        $user = Auth::user();
        return new UserResource($user);
    });

    // Project templates (for the create-project form)
    Route::get('/project-templates', [ProjectsController::class, 'templates']);

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/my-projects', [DashboardController::class, 'myProjects']);
        Route::get('/todos', [DashboardController::class, 'todos']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
    });
    Route::post('/user/update_name', [UsersController::class, 'updateName']);
    Route::post('/user/update_email', [UsersController::class, 'updateEmail']);
    Route::post('/user/update_password', [UsersController::class, 'updatePassword']);
    Route::post('/user/update_profile', [UsersController::class, 'updateProfile']);

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [UsersManagementController::class, 'index']);
        Route::post('/users', [UsersManagementController::class, 'store']);
        Route::post('/users/bulk', [UsersManagementController::class, 'bulk']);
        Route::post('/users/{id}/2fa/enable', [UserTwoFactorController::class, 'enable']);
        Route::post('/users/{id}/2fa/confirm', [UserTwoFactorController::class, 'confirm']);
        Route::post('/users/{id}/2fa/disable', [UserTwoFactorController::class, 'disable']);
        Route::post('/users/{id}/2fa/recovery-codes', [UserTwoFactorController::class, 'recoveryCodes']);
        Route::post('/users/{id}', [UsersManagementController::class, 'update']);
        Route::delete('/users/{id}', [UsersManagementController::class, 'destroy']);
    });

    Route::post('/user/2fa/enable', [TwoFactorController::class, 'enable']);
    Route::post('/user/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/user/2fa/disable', [TwoFactorController::class, 'disable']);
    Route::post('/user/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::post('/notifications/read', [NotificationsController::class, 'markRead']);

    Route::prefix('settings')->group(function(){
        Route::get('/', [SettingsController::class, 'index']);
        Route::post('/update', [SettingsController::class, 'update'])->middleware(['role:super_admin']);
        Route::post('/test-media-storage', [SettingsController::class, 'testMediaStorage'])->middleware(['role:super_admin']);
    });

    // System tools — super admin only
    Route::prefix('system')->middleware(['role:super_admin'])->group(function () {
        Route::get('/status', [SystemController::class, 'status']);

        Route::post('/cache/clear-all', [SystemController::class, 'clearAllCache']);
        Route::post('/cache/clear-route', [SystemController::class, 'clearRouteCache']);
        Route::post('/cache/clear-config', [SystemController::class, 'clearConfigCache']);
        Route::post('/cache/clear-view', [SystemController::class, 'clearViewCache']);
        Route::post('/cache/clear-app', [SystemController::class, 'clearAppCache']);
        Route::post('/cache/rebuild', [SystemController::class, 'rebuildCache']);

        // Storage & logs
        Route::post('/storage/link', [SystemController::class, 'storageLink']);
        Route::post('/logs/clear', [SystemController::class, 'clearLogs']);

        // Maintenance mode
        Route::post('/maintenance/down', [SystemController::class, 'maintenanceDown']);
        Route::post('/maintenance/up', [SystemController::class, 'maintenanceUp']);

        // Database
        Route::get('/migrate-status', [SystemController::class, 'migrateStatus']);
        Route::post('/migrate', [SystemController::class, 'runMigrations']);
        Route::post('/fresh-seed', [SystemController::class, 'freshSeed']);
    });

    Route::prefix('translations')->group(function(){
        Route::get('/', [TranslationsController::class, 'index']);
        Route::get('/locales', [TranslationsController::class, 'localesList']);
        Route::get('/dict', [TranslationsController::class, 'dict']);
        Route::post('/save', [TranslationsController::class, 'save'])->middleware(['role:super_admin']);
        Route::post('/add', [TranslationsController::class, 'addString'])->middleware(['role:super_admin']);
    });

    // Admin UI languages
    Route::prefix('localization')->group(function(){
        Route::get('/', [LocalizationController::class, 'index']);
        Route::post('/', [LocalizationController::class, 'store'])->middleware(['role:super_admin']);
        Route::post('/set-default', [LocalizationController::class, 'setDefault'])->middleware(['role:super_admin']);
        Route::delete('/{code}', [LocalizationController::class, 'destroy'])->middleware(['role:super_admin']);
    });

    // Theme management
    Route::prefix('themes')->group(function () {
        Route::get('/', [ThemesController::class, 'index']);
        Route::post('/sync', [ThemesController::class, 'sync'])->middleware(['role:super_admin']);
        Route::get('/{id}', [ThemesController::class, 'show']);
        Route::post('/{id}/toggle-status', [ThemesController::class, 'toggleStatus'])->middleware(['role:super_admin']);
    });

    Route::prefix('projects')->middleware('project.readonly')->group(function(){
        Route::get('/', [ProjectsController::class, 'index']);
        Route::post('/', [ProjectsController::class, 'store']);
        Route::get('/{id}', [ProjectsController::class, 'show']);
        Route::post('/update/{id}', [ProjectsController::class, 'update']);
        Route::post('/toggle-status/{id}', [ProjectsController::class, 'toggleStatus']);
        Route::delete('/delete/{id}', [ProjectsController::class, 'delete']);
        Route::get('/check-slug/{slug}', [ProjectsController::class, 'checkSlug']);

        // Project-level theme management
        Route::prefix('themes')->group(function () {
            Route::get('/{project_id}', [ThemesController::class, 'projectConfig']);
            Route::post('/{project_id}/apply', [ThemesController::class, 'apply']);
            Route::post('/{project_id}/config', [ThemesController::class, 'updateConfig']);
        });

        Route::prefix('settings')->group(function(){
            Route::get('/locales/{id}', [ProjectsController::class, 'locales']);
            Route::post('/locales/add/{id}', [ProjectsController::class, 'addLocale']);
            Route::post('/locales/change-default-locale/{id}', [ProjectsController::class, 'changeDefaultLocale']);
            Route::post('/locales/delete-locale/{id}', [ProjectsController::class, 'deleteLocale']);

            Route::prefix('translations')->group(function(){
                Route::get('/{id}', [ProjectTranslationsController::class, 'index']);
                Route::get('/{id}/dict', [ProjectTranslationsController::class, 'dict']);
                Route::post('/{id}/save', [ProjectTranslationsController::class, 'save']);
                Route::post('/{id}/add', [ProjectTranslationsController::class, 'addString']);
            });

            Route::get('/users/{id}', [ProjectsController::class, 'users']);
            Route::post('/users/assign/{id}', [ProjectsController::class, 'assignUser']);
            Route::post('/users/remove-user/{id}', [ProjectsController::class, 'removeUser']);
            Route::post('/users/new/{id}', [ProjectsController::class, 'newUser']);
            Route::post('/users/transfer-owner/{id}', [ProjectsController::class, 'transferOwnership']);

            Route::get('/api/{id}', [ProjectsController::class, 'api']);
            Route::post('/api/new-token/{id}', [ProjectsController::class, 'newToken']);
            Route::post('/api/update-token/{id}', [ProjectsController::class, 'updateToken']);
            Route::post('/api/delete-token/{id}', [ProjectsController::class, 'deleteToken']);
            Route::post('/api/enable_public_access/{id}', [ProjectsController::class, 'enablePublicAPIAccess']);
            Route::post('/api/disable_public_access/{id}', [ProjectsController::class, 'disablePublicAPIAccess']);
            Route::post('/api/update-domain-whitelist/{id}', [ProjectsController::class, 'updateDomainWhitelist']);

            Route::get('/webhooks/{project_id}', [ProjectsController::class, 'webhooks']);
            Route::get('/webhooks/{project_id}/logs/{webhook_id}', [ProjectsController::class, 'webhookLogs']);
            Route::delete('/webhooks/{project_id}/logs/{webhook_id}', [ProjectsController::class, 'deleteWebhookLogs']);
            Route::post('/webhooks/new/{project_id}', [ProjectsController::class, 'newWebhook']);
            Route::post('/webhooks/update/{project_id}', [ProjectsController::class, 'updateWebhook']);
            Route::post('/webhooks/delete/{project_id}', [ProjectsController::class, 'deleteWebhook']);
        });
    });

    // Collections
    Route::prefix('projects/{project_id}/collections')->middleware('project.readonly')->group(function(){
        Route::get('/', [CollectionsController::class, 'project']);
        Route::post('/', [CollectionsController::class, 'store']);
        Route::post('/update-order', [CollectionsController::class, 'updateOrder']);
        Route::get('/{collection_id}', [CollectionsController::class, 'show']);
        Route::post('/{collection_id}', [CollectionsController::class, 'update']);
        Route::delete('/{collection_id}', [CollectionsController::class, 'delete']);
        Route::get('/{collection_id}/export-schema', [CollectionsController::class, 'exportSchema']);
        Route::post('/import-schema', [CollectionsController::class, 'importSchema']);

        // Collection fields
        Route::post('/{collection_id}/fields', [CollectionFieldsController::class, 'store']);
        Route::post('/{collection_id}/fields/{field_id}', [CollectionFieldsController::class, 'update']);
        Route::post('/{collection_id}/fields/update-order', [CollectionFieldsController::class, 'updateOrder']);
        Route::delete('/{collection_id}/fields/{field_id}', [CollectionFieldsController::class, 'delete']);
    });

    // Content
    Route::prefix('projects/{project_id}/collections/{collection_id}')->middleware('project.readonly')->group(function(){
        // Content entries
        Route::get('/entries', [ContentController::class, 'index']);
        Route::get('/new', [ContentController::class, 'new']);
        Route::post('/entries', [ContentController::class, 'store']);
        Route::get('/{content_id}/edit', [ContentController::class, 'edit']);
        Route::post('/{content_id}', [ContentController::class, 'update']);
        Route::get('/export', [ContentController::class, 'exportContent']);
        Route::post('/import', [ContentController::class, 'importContent']);

        // Revisions
        Route::get('/{content_id}/revisions', [ContentController::class, 'revisions']);
        Route::get('/{content_id}/revisions/{revision_id}', [ContentController::class, 'showRevision']);
        Route::get('/{content_id}/revisions/diff/{from_id}/{to_id}', [ContentController::class, 'diffRevisions']);
        Route::patch('/{content_id}/revisions/{revision_id}/label', [ContentController::class, 'updateRevisionLabel']);
        Route::post('/{content_id}/revisions/{revision_id}/restore', [ContentController::class, 'restoreRevision']);

        // Single-record actions
        Route::post('/{content_id}/unpublish', [ContentController::class, 'unpublish']);
        Route::post('/{content_id}/publish-draft', [ContentController::class, 'publishDraft']);
        Route::delete('/{content_id}/discard-draft', [ContentController::class, 'discardDraft']);
        Route::delete('/{content_id}/move-to-trash', [ContentController::class, 'moveToTrash']);
        Route::delete('/{content_id}', [ContentController::class, 'delete']);

        // Preview token
        Route::post('/{content_id}/preview-token', [PreviewController::class, 'generate']);

        // Workflow
        Route::post('/{content_id}/workflow/submit-review', [WorkflowController::class, 'submitReview']);
        Route::post('/{content_id}/workflow/approve', [WorkflowController::class, 'approve']);
        Route::post('/{content_id}/workflow/reject', [WorkflowController::class, 'reject']);

        // Forms (nested under collection)
        Route::get('/forms', [FormController::class, 'forms']);
        Route::post('/forms', [FormController::class, 'store']);
        Route::post('/forms/{form_id}', [FormController::class, 'save']);
        Route::delete('/forms/{form_id}', [FormController::class, 'delete']);
    });

    // Content — project-level (bulk operations, project info)
    Route::prefix('projects/{project_id}')->middleware('project.readonly')->group(function(){
        Route::get('/content', [ContentController::class, 'project']);

        // Bulk operations (collection_id in body)
        Route::post('/collections/{collection_id}/entries/publish-selected', [ContentController::class, 'publishSelected']);
        Route::post('/collections/{collection_id}/entries/unpublish-selected', [ContentController::class, 'unPublishSelected']);
        Route::post('/collections/{collection_id}/entries/move-to-trash-selected', [ContentController::class, 'moveToTrashSelected']);
        Route::post('/collections/{collection_id}/entries/delete-selected', [ContentController::class, 'deleteSelected']);
        Route::post('/collections/{collection_id}/entries/restore-selected', [ContentController::class, 'restoreSelected']);

        Route::post('/entries/get-selected-records', [ContentController::class, 'getSelectedRecords']);
        Route::post('/entries/get-selected-files', [ContentController::class, 'getSelectedFiles']);
    });

    // Media — nested under projects
    Route::prefix('projects/{project_id}/media')->middleware('project.readonly')->group(function(){
        Route::get('/', [MediaLibraryController::class, 'getFiles']);
        Route::post('/', [MediaLibraryController::class, 'upload']);
        Route::post('/upload-chunk', [MediaLibraryController::class, 'uploadChunk']);
        Route::post('/upload-complete', [MediaLibraryController::class, 'uploadComplete']);
        Route::delete('/{file_id}', [MediaLibraryController::class, 'delete']);
        Route::post('/delete-selected', [MediaLibraryController::class, 'deleteSelected']);
        Route::post('/{file_id}', [MediaLibraryController::class, 'update']);
    });

    // Audit logs — nested under projects
    Route::prefix('projects/{project_id}/audit-logs')->group(function(){
        Route::get('/', [AuditLogController::class, 'index']);
    });

    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('admin.logout');
});

// Admin login
Route::middleware('guest')->prefix(\App\Support\AdminPath::slug())->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [AdminPasswordResetLinkController::class, 'create'])->name('admin.password.request');
    Route::post('/forgot-password', [AdminPasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('admin.password.email');
    Route::get('/reset-password/{token}', [AdminNewPasswordController::class, 'create'])->name('admin.password.reset');
    Route::post('/reset-password', [AdminNewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('admin.password.update');
});

// Vue Router SPA catch-all - MUST be last!
Route::middleware(['auth:web', 'backend.user'])->prefix(\App\Support\AdminPath::slug())->group(function(){
    Route::get('/{any?}', function () {
        return view('admin.admin');
    })->where('any', '.*');
});

