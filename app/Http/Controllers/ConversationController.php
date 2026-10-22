<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Audit;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController
{
    public function index(StudyContext $c)
    {
        return $c->scope(Conversation::query())->with(['company:id,name', 'messages' => fn ($q) => $q->when(! $c->staff(), fn ($b) => $b->where('internal', false))->with('user:id,name')->orderBy('id')])->latest('updated_at')->get();
    }

    public function store(Request $r, StudyContext $c)
    {
        $d = $r->validate(['company_id' => 'required|integer', 'subject' => 'required|string|max:180', 'body' => 'required|string|max:5000']);
        $c->company($d['company_id']);

        return DB::transaction(function () use ($d, $c) {
            $co = Conversation::create(['study_id' => $c->studyId, 'company_id' => $d['company_id'], 'subject' => $d['subject']]);
            $co->messages()->create(['body' => $d['body'], 'user_id' => $c->user->id, 'internal' => false]);
            Audit::record('conversation.created', $co);

            return response()->json($co, 201);
        });
    }

    public function message(Request $r, int $conversation, StudyContext $c)
    {
        $co = $c->scope(Conversation::query())->findOrFail($conversation);
        $d = $r->validate(['body' => 'required|string|max:5000', 'internal' => 'required|boolean']);
        abort_if($d['internal'] && ! $c->staff(), 403);
        $m = $co->messages()->create($d + ['user_id' => $c->user->id]);
        $co->touch();
        Audit::record('conversation.replied', $co, ['internal' => $d['internal']]);

        return response()->json($m, 201);
    }

    public function update(Request $r, int $conversation, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $co = $c->scope(Conversation::query())->findOrFail($conversation);
        $co->update($r->validate(['status' => 'required|in:open,waiting,resolved']));
        Audit::record('conversation.status', $co, ['status' => $co->status]);

        return $co;
    }
}
