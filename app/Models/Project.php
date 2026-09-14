<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $table = "projects";

    protected $fillable = ['owner_id', 'name', 'slug', 'description', 'default_locale', 'locales', 'disk', 'public_api', 'domain_whitelist', 'status', 'workflow_enabled'];

    protected $hidden = ['deleted_at'];

    protected $casts = [
        'public_api' => 'boolean',
        'domain_whitelist' => 'array',
        'status' => 'boolean',
        'workflow_enabled' => 'boolean',
    ];

    protected static function boot(){
        parent::boot();

        static::creating(function  ($model)  {
            $model->uuid = (string) Str::uuid()->getHex();
            
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * The user who created / owns this project.
     */
    public function owner(){
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Users with a membership in this project (project_user), each carrying
     * a role: owner | admin | editor | viewer.
     */
    public function members(){
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope to projects the given user owns or is a member of.
     * Row-level isolation: a user must never see projects they have no
     * relationship with.
     */
    public function scopeForUser($query, User $user){
        return $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
        });
    }

    /**
     * Whether the project is active (enabled).
     */
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    /**
     * Scope a query to active (enabled) projects only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to inactive (disabled) projects only.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    public function collections(){
        return $this->hasMany('App\Models\Collection')->orderBy('order', 'ASC');
    }

    public function fields(){
        return $this->hasMany('App\Models\CollectionField');
    }

    public function content(){
        return $this->hasMany('App\Models\Content');
    }

    public function meta(){
        return $this->hasMany('App\Models\ContentMeta');
    }

    public function media(){
        return $this->hasMany('App\Models\Media');
    }

    public function webhooks()
    {
        return $this->hasMany('App\Models\Webhook');
    }

    public function webhook_logs(){
        return $this->hasMany('App\Models\WebhookLog', 'project_uuid', 'uuid');
    }

    public function forms(){
        return $this->hasMany('App\Models\Form');
    }
}
