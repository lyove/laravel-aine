<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CommentsController;
use App\Http\Controllers\API\InteractionsController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\ContentController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ProjectsController;
use App\Http\Controllers\API\FaviconController;
use App\Http\Controllers\API\FeedController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// UUID pattern: only match actual UUIDs, not paths like /getFavicon
Route::pattern('uuid', '[0-9a-fA-F-]{36}');

Route::middleware(['web'])->get('/auth/me', [AuthController::class, 'me']);

// Login API
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

// Frontend user profile
Route::middleware(['web', 'auth:web,sanctum'])->group(function () {
    Route::get('/me/profile', [ProfileController::class, 'profile']);
    Route::post('/me/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:api-write');
    Route::post('/me/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:api-write');
    Route::get('/me/favorites', [ProfileController::class, 'favorites']);
    Route::get('/me/likes', [ProfileController::class, 'likes']);
    Route::get('/me/comments', [ProfileController::class, 'comments']);
    Route::get('/me/tokens', [ProfileController::class, 'tokens']);
    Route::post('/me/tokens/revoke-others', [ProfileController::class, 'revokeOtherTokens'])->middleware('throttle:api-write');
    Route::delete('/me/tokens/{token}', [ProfileController::class, 'revokeToken'])->middleware('throttle:api-write');
});

// ============================================
// Method 1: Explicit project identifier API
// Use case: Multi-project frontend applications (single domain accessing multiple backend projects)
// Requires explicit project identifier (UUID or slug) in URL
// Validates project access via domain whitelist
// ============================================
Route::middleware(['verify.domain.whitelist'])->prefix('project')->group(function () {
    Route::get('/{project_identifier}/portal', [ContentController::class, 'getPortalContent']);

    Route::middleware(['web', 'auth:web,sanctum', 'throttle:api-write'])->group(function () {
        Route::post('/{project_identifier}/favorites', [InteractionsController::class, 'addFavorite']);
        Route::delete('/{project_identifier}/favorites/{content_id}', [InteractionsController::class, 'removeFavorite']);
        Route::post('/{project_identifier}/likes', [InteractionsController::class, 'addLike']);
        Route::delete('/{project_identifier}/likes/{content_id}', [InteractionsController::class, 'removeLike']);
    });
    Route::middleware(['web'])->get('/{project_identifier}/interactions/{content_id}', [InteractionsController::class, 'state']);
    Route::get('/{project_identifier}/sitemap.xml', [FeedController::class, 'sitemap']);
    Route::get('/{project_identifier}/feed.xml', [FeedController::class, 'rss']);
    Route::get('/{project_identifier}/media/name/{media_name}', [MediaController::class, 'getMediaByName']);
    Route::get('/{project_identifier}/media/{media_id}', [MediaController::class, 'getMediaByID']);
    Route::get('/{project_identifier}/media', [MediaController::class, 'getMediaList']);
    Route::delete('/{project_identifier}/media/{media_id}', [MediaController::class, 'deleteMedia'])->middleware(['auth:sanctum', 'throttle:api-write', 'abilities:delete']);
    Route::post('/{project_identifier}/media/upload', [MediaController::class, 'uploadMedia'])->middleware(['auth:sanctum', 'throttle:api-write', 'abilities:create']);
    Route::get('/{project_identifier}/{slug}/slug/{slug_value}', [ContentController::class, 'getProjectContentBySlug']);
    Route::get('/{project_identifier}/{slug}/slug/{slug_value}/{related_slug}', [ContentController::class, 'getProjectContentBySlugRelation']);
    Route::get('/{project_identifier}/{slug}/{slug_id}/{related_slug}', [ContentController::class, 'getProjectContentByRelation']);
    Route::get('/{project_identifier}/{slug}/search', [ContentController::class, 'searchContent'])->middleware('throttle:api-search');
    Route::get('/{project_identifier}/comments/{article_id}', [CommentsController::class, 'index']);
    Route::post('/{project_identifier}/comments', [CommentsController::class, 'store'])
        ->middleware(['web', 'auth:web', 'throttle:api-write']);
    Route::get('/{project_identifier}/{slug}/{slug_id}', [ContentController::class, 'getProjectContentByID']);
    Route::get('/{project_identifier}/{slug}', [ContentController::class, 'getContentList']);
    Route::get('/{project_identifier}', [ProjectsController::class, 'getProject']);
    Route::post('/{project_identifier}/{slug}', [ContentController::class, 'createContent'])->middleware(['auth:sanctum', 'throttle:api-write', 'abilities:create']);
    Route::post('/{project_identifier}/{slug}/update/{slug_id}', [ContentController::class, 'updateContent'])->middleware(['auth:sanctum', 'throttle:api-write', 'abilities:update']);
    Route::delete('/{project_identifier}/{slug}/{slug_id}', [ContentController::class, 'deleteContent'])->middleware(['auth:sanctum', 'throttle:api-write', 'abilities:delete']);
});

// ============================================
// Method 2: UUID + Token API (backend-to-backend)
// Use case: Laravel frontend projects, backend server calls
// Security: All operations require UUID validation + Token authentication
//           Prevents unauthorized cross-domain access from any website
// ============================================
Route::middleware(['validate.project.access', 'auth:sanctum'])->group(function () {
    Route::middleware('abilities:read')->group(function () {
        Route::get('/{uuid}/project-media/name/{media_name}', [MediaController::class, 'getMediaByName']);
        Route::get('/{uuid}/project-media/{media_id}', [MediaController::class, 'getMediaByID']);
        Route::get('/{uuid}/project-media', [MediaController::class, 'getMediaList']);
        Route::get('/{uuid}/{slug}/slug/{slug_value}', [ContentController::class, 'getProjectContentBySlug']);
        Route::get('/{uuid}/{slug}/slug/{slug_value}/{related_slug}', [ContentController::class, 'getProjectContentBySlugRelation']);
        Route::get('/{uuid}/{slug}/{slug_id}/{related_slug}', [ContentController::class, 'getProjectContentByRelation']);
        Route::get('/{uuid}/{slug}/search', [ContentController::class, 'searchContent'])->middleware('throttle:api-search');
        Route::get('/{uuid}/{slug}/{slug_id}', [ContentController::class, 'getProjectContentByID']);
        Route::get('/{uuid}/{slug}', [ContentController::class, 'getContentList']);
        Route::get('/{uuid}', [ProjectsController::class, 'getProject']);
    });

    Route::post('/{uuid}/{slug}', [ContentController::class, 'createContent'])->middleware(['throttle:api-write', 'abilities:create']);
    Route::post('/{uuid}/{slug}/update/{slug_id}', [ContentController::class, 'updateContent'])->middleware(['throttle:api-write', 'abilities:update']);
    Route::delete('/{uuid}/{slug}/{slug_id}', [ContentController::class, 'deleteContent'])->middleware(['throttle:api-write', 'abilities:delete']);
    Route::post('/{uuid}/project-media/upload', [MediaController::class, 'uploadMedia'])->middleware(['throttle:api-write', 'abilities:create']);
    Route::delete('/{uuid}/project-media/{media_id}', [MediaController::class, 'deleteMedia'])->middleware(['throttle:api-write', 'abilities:delete']);
});

// Favicon Fetch API
Route::get('/getFavicon', [\App\Http\Controllers\API\FaviconController::class, 'show'])->middleware('throttle:api-favicon');

Route::options('{any}', function () {
    return response('', 204);
})->where('any', '.*');