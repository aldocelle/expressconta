<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Study::class => \App\Policies\StudyPolicy::class,
        \App\Models\Company::class => \App\Policies\CompanyPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
