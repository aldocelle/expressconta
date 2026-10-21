<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStudyAccess
{
    public function handle(Request $request, Closure $next)
    {
        $studyId = $request->route('study') ?? $request->input('study_id');

        if (! $studyId) {
            return response()->json(['message' => 'Study context is required.'], 400);
        }

        $user = $request->user();
        if (! $user || ! $user->studies()->whereKey($studyId)->exists()) {
            return response()->json(['message' => 'No access to this study.'], 403);
        }

        return $next($request);
    }
}
