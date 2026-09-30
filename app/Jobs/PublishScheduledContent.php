<?php

namespace App\Jobs;

use App\Events\ContentPublished;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\ContentRevision;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Delete the job if the content is no longer in the database.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public Content $content)
    {
    }

    /**
     * Publish the content if its scheduled time has arrived.
     */
    public function handle(): void
    {
        // The job may be picked up later than expected: only publish when the
        // content is still scheduled and the time has actually arrived.
        if ($this->content->scheduled_at === null) {
            return;
        }

        $scheduledAt = Carbon::parse($this->content->scheduled_at);

        if ($scheduledAt->greaterThan(now())) {
            return;
        }

        $publisherId = Project::whereKey($this->content->project_id)->value('owner_id');

        $this->content->published_at = $scheduledAt;
        $this->content->published_by = $publisherId;
        $this->content->scheduled_at = null;
        $this->content->save();

        $this->createPublishedRevision($publisherId);

        ContentPublished::dispatch([
            'source' => 'Schedule',
            'content' => $this->content,
        ]);
    }

    /**
     * Append a "published" revision snapshot for the content.
     *
     * Best-effort: a revision failure must never fail the publish itself.
     */
    private function createPublishedRevision(?int $publisherId): void
    {
        try {
            $data = [];
            foreach (ContentMeta::where('content_id', $this->content->id)->get() as $meta) {
                $data[$meta->field_name] = $meta->value;
            }

            $parent = ContentRevision::where('content_id', $this->content->id)
                ->orderByDesc('id')
                ->first();

            ContentRevision::create([
                'project_id'    => $this->content->project_id,
                'collection_id' => $this->content->collection_id,
                'content_id'    => $this->content->id,
                'locale'        => $this->content->locale,
                'data'          => $data,
                'note'          => 'Published by scheduled job',
                'action'        => 'published',
                'parent_id'     => $parent?->id,
                'created_by'    => $publisherId,
            ]);
        } catch (\Throwable) {
            // Never let a best-effort revision break the publish.
        }
    }
}
