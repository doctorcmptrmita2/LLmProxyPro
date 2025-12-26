<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->organizations()
            ->where('organization_id', $project->organization_id)
            ->exists();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->organizations()
            ->where('organization_id', $project->organization_id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }
}

