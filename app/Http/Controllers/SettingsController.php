<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Study;
use App\Models\StudyMembership;
use App\Models\User;
use App\Services\Audit;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController
{
    public function index(StudyContext $c)
    {
        abort_unless($c->admin(), 403);

        return ['study' => Study::findOrFail($c->studyId), 'members' => StudyMembership::where('study_id', $c->studyId)->get()->map(fn ($m) => ['id' => $m->user_id, 'role' => $m->role, 'name' => User::find($m->user_id)->name, 'email' => User::find($m->user_id)->email, 'company_ids' => Company::where('study_id', $c->studyId)->whereHas('members', fn ($q) => $q->where('users.id', $m->user_id))->pluck('id')]), 'integrations' => ['email' => config('mail.default') === 'log' ? 'log' : 'configured', 'whatsapp' => 'not_configured', 'payments' => 'not_configured']];
    }

    public function user(Request $r, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['name' => 'required|string|max:180', 'email' => ['required', 'email', Rule::unique('users')], 'password' => 'required|string|min:12', 'role' => 'required|in:admin,accountant,reviewer,client', 'company_ids' => 'present|array|max:200', 'company_ids.*' => 'integer|distinct']);
        foreach ($d['company_ids'] as $id) {
            $c->company($id);
        }

return DB::transaction(function () use ($d, $c) {
            $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password']]);
            StudyMembership::create(['study_id' => $c->studyId, 'user_id' => $u->id, 'role' => $d['role']]);
            $u->companies()->sync($d['company_ids']);
            Audit::record('member.created', Study::findOrFail($c->studyId), ['member_id' => $u->id, 'role' => $d['role']]);

            return response()->json($u, 201);
        });
    }

    public function update(Request $r, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['name' => 'required|string|max:180']);
        $study = Study::findOrFail($c->studyId);
        $study->update($d);
        Audit::record('study.updated', $study);

        return $study;
    }

    public function member(Request $r, int $member, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $m = StudyMembership::where('study_id', $c->studyId)->where('user_id', $member)->firstOrFail();
        $d = $r->validate(['role' => 'required|in:admin,accountant,reviewer,client', 'company_ids' => 'present|array|max:200', 'company_ids.*' => 'integer|distinct']);
        foreach ($d['company_ids'] as $id) {
            $c->company($id);
        }

        return DB::transaction(function () use ($m, $d, $c) {
            $admins = StudyMembership::where('study_id', $c->studyId)->where('role', 'admin')->lockForUpdate()->get();
            abort_if($m->role === 'admin' && $d['role'] !== 'admin' && $admins->count() <= 1, 422, 'El estudio debe conservar al menos un administrador.');
            $m->update(['role' => $d['role']]);
            $user = User::findOrFail($m->user_id);
            $current = $user->companies()->where('study_id', $c->studyId)->pluck('companies.id')->all();
            $user->companies()->detach($current);
            $user->companies()->syncWithoutDetaching($d['company_ids']);
            Audit::record('member.permissions_updated', Study::findOrFail($c->studyId), ['member_id' => $user->id, 'role' => $d['role'], 'company_ids' => $d['company_ids']]);

            return ['saved' => true];
        });
    }
}
