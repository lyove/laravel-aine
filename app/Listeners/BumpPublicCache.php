<?php

namespace App\Listeners;

use App\Aine\PublicCache;
use App\Models\Collection;
use App\Models\Content;

/**
 * Invalidate the public API cache after any content write.
 *
 * Bound to every content lifecycle event (created, updated, trashed,
 * deleted, published, unpublished, restored), so all write paths — the
 * admin panel and the public API — bump the project's cache version
 * consistently. Previously only the public API controller invalidated its
 * own cache, which left admin-panel changes serving stale data for up to
 * the 10-minute cache TTL.
 */
class BumpPublicCache
{
    /**
     * Handle the event.
     *
     * @param object $event any content lifecycle event
     * @return void
     */
    public function handle($event): void
    {
        $content = $event->getEventContent();

        // Most events carry a Content model; hard deletes may carry a plain
        // array (project_id, collection_id, item_id) instead.
        if ($content instanceof Content) {
            $projectId = $content->project_id;
            $collectionSlug = $content->collection?->slug;
        } else {
            $projectId = $content['project_id'] ?? null;
            $collectionSlug = null;
            $collectionId = $content['collection_id'] ?? null;
            if ($collectionId) {
                $collectionSlug = Collection::where('id', (int) $collectionId)->value('slug');
            }
        }

        if ($projectId) {
            PublicCache::bump((int) $projectId, $collectionSlug ?: null);
        }
    }
}
