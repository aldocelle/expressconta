<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Services\StudyContext;

class CompanyPolicy
{
    public function view(User $u, Company $company): bool
    {
        return app(StudyContext::class)->companies()->whereKey($company->id)->exists();
    }

    public function update(User $u, Company $company): bool
    {
        return app(StudyContext::class)->admin() && $this->view($u, $company);
    }
}
