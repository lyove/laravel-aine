<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use App\Http\Controllers\Admin\LocalizationController;
use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $table = "projects";

    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'default_locale', 'locales', 
        'disk', 'public_api', 'domain_whitelist', 'status', 'workflow_enabled',
        'copyright', 'filing_info', 'timezone', 'language_direction',
        'logo_url', 'favicon_url',
        'custom_header', 'custom_footer',
        'seo_title', 'seo_description', 'seo_keywords', 'analytics_script',
        'contact_address', 'contact_email', 'contact_phone', 'contact_text',
    ];

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

    /**
     * Expose the acting user's role inside this project (owner / admin /
     * editor / viewer) as `my_role`, mirroring ProjectsController@show.
     * Super admins without a membership are treated as owners.
     */
    public function applyMyRole(AppUser $user): static
    {
        $this->my_role = $user->isSuperAdmin() && $user->projectRole($this) === null
            ? ProjectUser::ROLE_OWNER
            : $user->projectRole($this);

        return $this;
    }

    /**
     * Present the project for the acting user: inject `my_role` and resolve
     * the `description` shown in the admin UI (translated into the admin UI's
     * current base locale when a translation exists).
     */
    public function presentFor(AppUser $user): static
    {
        $this->applyMyRole($user);
        // Raw (source-language) values for edit forms; translated values for display.
        $this->raw_name = $this->name;
        $this->raw_description = $this->description;
        $this->name = $this->presentName();
        $this->description = $this->presentDescription();
        $this->is_readonly = !$this->isActive();

        return $this;
    }

    /**
     * Resolve the name that should be displayed, following the admin UI's
     * current base locale.
     */
    public function presentName(?string $locale = null): string
    {
        $base = (string) ($this->name ?? '');

        if ($base === '') {
            return '';
        }

        $locale = $locale ?: LocalizationController::baseLocale();

        $translated = ProjectTranslation::where('project_id', $this->id)
            ->where('source', $base)
            ->where('locale', $locale)
            ->value('value');

        return $translated !== null && $translated !== ''
            ? (string) $translated
            : $base;
    }

    /**
     * Resolve the description that should be displayed, following the admin
     * UI's current base locale.
     */
    public function presentDescription(?string $locale = null): string
    {
        $base = (string) ($this->description ?? '');

        if ($base === '') {
            return '';
        }

        $locale = $locale ?: LocalizationController::baseLocale();

        $translated = ProjectTranslation::where('project_id', $this->id)
            ->where('source', $base)
            ->where('locale', $locale)
            ->value('value');

        return $translated !== null && $translated !== ''
            ? (string) $translated
            : $base;
    }
}
