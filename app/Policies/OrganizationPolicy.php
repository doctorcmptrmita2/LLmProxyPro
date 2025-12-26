<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations()->where('organization_id', $organization->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organization_id', $organization->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }
}

