<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Aine\TwoFactor;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function isSuperAdmin(){
        return $this->hasRole('super_admin');
    }

    /**
     * Projects the user owns (projects.owner_id is the source of truth).
     */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /**
     * Projects the user is a member of (through project_user), with the
     * membership role available on the pivot.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The user's role inside a project (owner/admin/editor/viewer) or null
     * when the user has no membership.
     */
    public function projectRole(Project $project): ?string
    {
        $membership = $this->projects()->where('projects.id', $project->id)->first();

        return $membership?->pivot->role;
    }

    public function isProjectMember(Project $project): bool
    {
        return $this->projectRole($project) !== null;
    }

    public function isProjectOwner(Project $project): bool
    {
        return $this->projectRole($project) === ProjectUser::ROLE_OWNER;
    }

    /**
     * Whether the user holds one of the given roles inside the project.
     *
     * @param  array<int, string>  $roles
     */
    public function hasProjectRole(Project $project, array $roles): bool
    {
        return in_array($this->projectRole($project), $roles, true);
    }

    /**
     * Whether two-factor authentication is fully enabled (confirmed).
     */
    public function twoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret)
            && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Whether a two-factor secret has been generated but not yet confirmed.
     */
    public function twoFactorPending(): bool
    {
        return ! is_null($this->two_factor_secret)
            && is_null($this->two_factor_confirmed_at);
    }

    public function twoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function twoFactorRecoveryCodes(): array
    {
        if (! $this->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
    }

    public function setTwoFactorRecoveryCodes(array $codes): void
    {
        $this->two_factor_recovery_codes = encrypt(json_encode($codes));
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->twoFactorEnabled()) {
            return false;
        }

        return TwoFactor::verify($this->two_factor_secret, $code);
    }

    public function verifyTwoFactorRecoveryCode(string $code): bool
    {
        $result = TwoFactor::consumeRecoveryCode($this->twoFactorRecoveryCodes(), $code);

        if ($result['valid']) {
            $this->setTwoFactorRecoveryCodes($result['remaining_codes']);
            $this->save();

            return true;
        }

        return false;
    }

    public function disableTwoFactor(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->two_factor_confirmed_at = null;
        $this->save();
    }
}
