<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\StudyMembership;
use App\Models\User;
use App\Services\Audit;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompanyController
{
    public function index(Request $r, StudyContext $c)
    {
        $d = $r->validate(['search' => 'nullable|string|max:180', 'archived' => 'nullable|boolean', 'page' => 'nullable|integer|min:1']);

        return $c->companies()->with('responsible:id,name')->when(! ($d['archived'] ?? false), fn ($q) => $q->whereNull('archived_at'))->when($d['search'] ?? null, fn ($q, $s) => $q->where(fn ($b) => $b->where('name', 'like', '%'.$s.'%')->orWhere('rut', 'like', '%'.$s.'%')))->orderBy('name')->paginate(20);
    }

    public function show(int $company, StudyContext $c)
    {
        $co = $c->company($company);
        Gate::authorize('view', $co);

        return $co->load('responsible:id,name');
    }

    public function store(CompanyRequest $r, StudyContext $c)
    {
        $d = $r->validated();
        $c->staffMember($d['responsible_id'] ?? null);
        $company = DB::transaction(function () use ($d, $c) {
            $co = Company::create($d + ['study_id' => $c->studyId]);
            if ($co->responsible_id) {
                $co->members()->syncWithoutDetaching([$co->responsible_id]);
            }Audit::record('company.created', $co);

            return $co;
        });

        return response()->json($company, 201);
    }

    public function update(CompanyRequest $r, int $company, StudyContext $c)
    {
        $co = $c->company($company);
        Gate::authorize('update', $co);
        $d = $r->validated();
        $c->staffMember($d['responsible_id'] ?? null);

        return DB::transaction(function () use ($co, $d) {
            $co->update($d);
            if ($co->responsible_id) {
                $co->members()->syncWithoutDetaching([$co->responsible_id]);
            }Audit::record('company.updated', $co);

            return $co;
        });
    }

    public function archive(int $company, StudyContext $c)
    {
        $co = $c->company($company);
        Gate::authorize('update', $co);
        $co->update(['archived_at' => $co->archived_at ? null : now()]);
        Audit::record('company.archive_toggled', $co);

        return $co;
    }

    public function team(StudyContext $c)
    {
        abort_unless($c->staff(), 403);

        return User::whereIn('id', StudyMembership::where('study_id', $c->studyId)->where('role', '!=', 'client')->select('user_id'))->get(['id', 'name', 'email']);
    }

    public function activity(StudyContext $c)
    {
        abort_unless($c->staff(), 403);

        return AuditEvent::where('study_id', $c->studyId)->when(! $c->admin(), fn ($q) => $q->whereIn('company_id',$c->companies()->select('companies.id')))->latest('id')->paginate(30);
    }
}
