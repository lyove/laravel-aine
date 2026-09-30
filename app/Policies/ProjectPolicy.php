<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;

/**
 * Resource-level authorization for projects.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->isProjectMember($project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
        ]);
    }

    public function updateSettings(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
        ]);
    }

    public function manageCollections(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
        ]);
    }

    public function manageContent(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
            ProjectUser::ROLE_EDITOR,
        ]);
    }

    public function publishContent(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
        ]);
    }

    public function manageMedia(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
            ProjectUser::ROLE_EDITOR,
        ]);
    }

    public function viewAuditLogs(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
            ProjectUser::ROLE_ADMIN,
        ]);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
        ]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project, [
            ProjectUser::ROLE_OWNER,
        ]);
    }
}
