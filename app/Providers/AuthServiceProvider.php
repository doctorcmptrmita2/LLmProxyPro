<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Project;
use App\Policies\OrganizationPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Organization::class => OrganizationPolicy::class,
        Project::class => ProjectPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}

