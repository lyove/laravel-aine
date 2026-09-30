<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectUser extends Pivot
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    protected $table = 'project_user';


    public $timestamps = true;

    protected $fillable = ['project_id', 'user_id', 'role'];

    /**
     * Higher weight = more privileged role (used when merging memberships).
     */
    public static function roleWeight(string $role): int
    {
        return match ($role) {
            self::ROLE_OWNER => 4,
            self::ROLE_ADMIN => 3,
            self::ROLE_EDITOR => 2,
            self::ROLE_VIEWER => 1,
            default => 0,
        };
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
