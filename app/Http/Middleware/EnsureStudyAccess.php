<?php

namespace App\Http\Middleware;

use App\Models\StudyMembership;
use App\Services\StudyContext;
use Closure;
use Illuminate\Http\Request;

class EnsureStudyAccess
{
    public function handle(Request $request, Closure $next)
    {
        $membership = StudyMembership::where('user_id', $request->user()->id)->where('study_id', $request->session()->get('study_id'))->first();
        abort_unless($membership, 403, 'Selecciona un estudio autorizado.');
        app()->instance(StudyContext::class, new StudyContext($membership->study_id, $request->user(), $membership->role));

        return $next($request);
    }
}
