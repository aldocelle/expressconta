<?php

namespace App\Services;

use App\Models\Company;
use App\Models\StudyMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudyContext
{
    public function __construct(public int $studyId, public User $user, public string $role) {}

    public function staff(): bool
    {
        return $this->role !== 'client';
    }

    public function admin(): bool
    {
        return $this->role === 'admin';
    }

    public function canReview(): bool
    {
        return in_array($this->role, ['admin', 'reviewer']);
    }

    public function companies(): Builder
    {
        $q = Company::where('study_id', $this->studyId);
        if (! $this->admin()) {
            $q->whereHas('members', fn ($m) => $m->where('users.id', $this->user->id));
        }

        return $q;
    }

    public function company(int $id): Company
    {
        return $this->companies()->findOrFail($id);
    }

    public function scope(Builder $query): Builder
    {
        return $query->where('study_id', $this->studyId)->whereIn('company_id', $this->companies()->select('companies.id'));
    }

    public function staffMember(?int $id): void
    {
        if ($id === null) {
            return;
        }
        abort_unless(StudyMembership::where('study_id', $this->studyId)->where('user_id', $id)->where('role', '!=', 'client')->exists(), 422, 'Responsable ajeno al equipo.');
    }
}
