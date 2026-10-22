<?php

namespace App\Http\Controllers;

use App\Services\StudyContext;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $r, StudyContext $c)
    {
        $allowed = $c->companies()->pluck('id')->all();

        return $r->user()->notifications()->latest()->limit(100)->get()->filter(fn ($n) => (int) ($n->data['study_id'] ?? 0) === $c->studyId && in_array((int) ($n->data['company_id'] ?? 0), $allowed))->values()->take(30);
    }

    public function read(Request $r, string $notification, StudyContext $c)
    {
        $n = $r->user()->notifications()->findOrFail($notification);
        abort_unless((int) ($n->data['study_id'] ?? 0) === $c->studyId, 404);
        $c->company((int) $n->data['company_id']);
        $n->markAsRead();

        return response()->noContent();
    }
}
