<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Like extends Model
{
    protected $table = 'likes';

    protected $fillable = [
        'user_id', 'project_id', 'content_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'project_id' => 'integer',
        'content_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
