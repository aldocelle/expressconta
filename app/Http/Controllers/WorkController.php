<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Task;
use App\Models\WorkItem;
use App\Models\WorkTemplate;
use App\Services\Audit;
use App\Services\StudyContext;
use App\Services\Workflows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkController
{
    public function index(Request $r, StudyContext $c)
    {
        $d = $r->validate(['period' => 'nullable|date_format:Y-m', 'company_id' => 'nullable|integer', 'status' => 'nullable|string|max:30', 'responsible_id' => 'nullable|integer']);

        return $c->scope(WorkItem::query())->with(['company:id,name', 'requests', 'reports' => fn ($q) => $q->when(! $c->staff(), fn ($b) => $b->whereNotNull('published_at'))])->when($d['period'] ?? null, fn ($q, $v) => $q->where('period', $v))->when($d['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))->when($d['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($d['responsible_id'] ?? null, fn ($q, $v) => $q->where('responsible_id', $v))->orderBy('due_on')->get();
    }

    public function store(Request $r, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['company_id' => 'required|integer', 'title' => 'required|string|max:180', 'period' => 'required|date_format:Y-m', 'due_on' => 'nullable|date_format:Y-m-d', 'responsible_id' => 'nullable|integer', 'reviewer_id' => 'nullable|integer']);
        $co = $c->company($d['company_id']);
        abort_if($co->archived_at, 422, 'Empresa archivada.');
        $c->staffMember($d['responsible_id'] ?? null);
        $c->staffMember($d['reviewer_id'] ?? null);
        $w = WorkItem::create($d + ['study_id' => $c->studyId, 'checklist' => [['title' => 'Verificar antecedentes', 'done' => false], ['title' => 'Preparar informe', 'done' => false]]]);
        Audit::record('work.created', $w);

        return response()->json($w, 201);
    }

    public function update(Request $r, int $work, StudyContext $c, Workflows $flows)
    {
        abort_unless($c->staff(), 403);
        $w = $c->scope(WorkItem::query())->findOrFail($work);
        $d = $r->validate(['status' => 'required|string', 'version' => 'required|integer|min:1', 'reason' => 'nullable|string|max:2000']);

        return $flows->transition($w, $d['status'], $d['version'], $d['reason'] ?? null);
    }

    public function checklist(Request $r, int $work, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['version' => 'required|integer', 'checklist' => 'required|array|max:50', 'checklist.*.title' => 'required|string|max:180', 'checklist.*.done' => 'required|boolean']);
        $w = $c->scope(WorkItem::query())->findOrFail($work);

        return DB::transaction(function () use ($w, $d) {
            $w = WorkItem::lockForUpdate()->findOrFail($w->id);
            abort_if($w->version !== $d['version'], 409, 'Actualiza el trabajo.');
            abort_if(in_array($w->status, ['approved', 'delivered', 'cancelled']), 422, 'Reabre el trabajo antes de modificarlo.');
            $w->update(['checklist' => $d['checklist'], 'version' => $w->version + 1]);
            Audit::record('work.checklist_updated', $w);

            return $w;
        });
    }

    public function templates(StudyContext $c)
    {
        abort_unless($c->staff(), 403);

        return WorkTemplate::where('study_id', $c->studyId)->get();
    }

    public function saveTemplate(Request $r, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['name' => 'required|string|max:180', 'checklist' => 'required|array|max:50', 'checklist.*.title' => 'required|string|max:180', 'checklist.*.done' => 'required|boolean', 'requests' => 'required|array|max:30', 'requests.*.title' => 'required|string|max:180', 'requests.*.instructions' => 'nullable|string|max:2000', 'requests.*.required' => 'required|boolean']);

        return WorkTemplate::create($d + ['study_id' => $c->studyId]);
    }

    public function engage(Request $r, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['company_id' => 'required|integer', 'work_template_id' => 'required|integer', 'monthly_fee' => 'required|integer|min:0|max:1000000000', 'starts_on' => 'required|date_format:Y-m-d']);
        $c->company($d['company_id']);
        WorkTemplate::where('study_id', $c->studyId)->findOrFail($d['work_template_id']);
        $e = Engagement::updateOrCreate(['company_id' => $d['company_id'], 'work_template_id' => $d['work_template_id']], $d + ['study_id' => $c->studyId, 'active' => true]);
        Audit::record('engagement.saved', $e);

        return $e;
    }

    public function tasks(StudyContext $c)
    {
        abort_unless($c->staff(), 403);

        return $c->scope(Task::query())->with('company:id,name')->orderBy('due_on')->get();
    }

    public function saveTask(Request $r, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['company_id' => 'required|integer', 'title' => 'required|string|max:180', 'due_on' => 'nullable|date_format:Y-m-d', 'responsible_id' => 'nullable|integer', 'priority' => 'required|in:normal,high']);
        $c->company($d['company_id']);
        $c->staffMember($d['responsible_id'] ?? null);
        $t = Task::create($d + ['study_id' => $c->studyId]);
        Audit::record('task.created', $t);

        return $t;
    }

    public function updateTask(Request $r, int $task, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $t = $c->scope(Task::query())->findOrFail($task);
        $d = $r->validate(['status' => 'required|in:pending,in_progress,completed']);
        $t->update($d);
        Audit::record('task.updated',$t,$d);

        return $t;
    }
}
