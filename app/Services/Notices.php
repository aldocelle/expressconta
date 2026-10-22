<?php

namespace App\Services;

use App\Models\Company;
use App\Models\StudyMembership;
use App\Models\User;
use App\Notifications\StudioNotice;

class Notices
{
    public static function company(Company $company, string $audience, string $title, string $path): void
    {
        $members = StudyMembership::where('study_id', $company->study_id)->when($audience === 'client', fn ($q) => $q->where('role', 'client'), fn ($q) => $q->where('role', '!=', 'client'))->get();
        foreach ($members as $member) {
            if ($member->role !== 'admin' && ! $company->members()->where('users.id', $member->user_id)->exists()) {
                continue;
            }User::find($member->user_id)?->notify(new StudioNotice(['study_id' => $company->study_id, 'company_id' => $company->id, 'title' => $title, 'path' => $path]));
        }
    }
}
