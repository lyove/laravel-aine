<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentRevision extends Model
{
    protected $fillable = [
        'project_id',
        'collection_id',
        'content_id',
        'locale',
        'data',
        'note',
        'action',
        'label',
        'meta',
        'parent_id',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'meta' => 'array',
    ];

    /**
     * The user who created this revision.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The content this revision belongs to.
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    /**
     * The previous revision in the version chain.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The next revisions in the version chain.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Whether this revision is the current (latest) version of its content.
     */
    public function getIsCurrentAttribute(): bool
    {
        $latest = self::where('content_id', $this->content_id)
            ->orderByDesc('id')
            ->value('id');

        return $latest === $this->id;
    }

    /**
     * Human-readable change summary: list of field names that changed
     * compared to the parent revision.
     */
    public function getChangeSummaryAttribute(): ?array
    {
        if (! $this->parent_id) {
            return null;
        }

        $parent = self::find($this->parent_id);
        if (! $parent) {
            return null;
        }

        return $this->diffFields($parent->data ?? [], $this->data ?? []);
    }

    /**
     * Compute a field-level diff between two revision data arrays.
     * Returns an array of changed fields with old/new values.
     */
    public static function diffFields(array $oldData, array $newData): array
    {
        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
        $changes = [];

        foreach ($allKeys as $key) {
            $old = $oldData[$key] ?? null;
            $new = $newData[$key] ?? null;

            // Normalize: both null/empty string means unchanged
            $oldNorm = $old === '' ? null : $old;
            $newNorm = $new === '' ? null : $new;

            if ($oldNorm === $newNorm) {
                continue;
            }

            $changes[] = [
                'field' => $key,
                'old' => $old,
                'new' => $new,
                'type' => $oldNorm === null ? 'added' : ($newNorm === null ? 'removed' : 'modified'),
            ];
        }

        return $changes;
    }

    /**
     * Action label for display (translatable key).
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Created',
            'updated' => 'Updated',
            'published' => 'Published',
            'unpublished' => 'Unpublished',
            'draft_updated' => 'Draft updated',
            'restored' => 'Restored',
            'imported' => 'Imported',
            'deleted' => 'Deleted',
            default => ucfirst($this->action ?? 'updated'),
        };
    }
}
