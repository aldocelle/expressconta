<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Report;
use App\Models\WorkItem;
use App\Services\Audit;
use App\Services\Notices;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController
{
    public function index(Request $r, StudyContext $c)
    {
        $d = $r->validate(['company_id' => 'nullable|integer']);

        return $c->scope(Report::query())->with(['company:id,name', 'work:id,period,title', 'files'])->when(! $c->staff(), fn ($q) => $q->whereNotNull('published_at')->whereNotNull('approved_at'))->when($d['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))->latest()->get();
    }

    public function store(Request $r, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['work_item_id' => 'required|integer', 'title' => 'required|string|max:180', 'file' => 'required|file|mimes:pdf|max:10240']);
        $w = $c->scope(WorkItem::query())->findOrFail($d['work_item_id']);
        $file = $r->file('file');
        $path = $file->store('studies/'.$c->studyId.'/reports', 'local');
        try {
            return DB::transaction(function () use ($w, $d, $c, $file, $path) {
                $w = WorkItem::lockForUpdate()->findOrFail($w->id);
                abort_if(in_array($w->status, ['delivered', 'cancelled']), 422, 'Reabre el trabajo antes de crear otro informe.');
                $report = Report::create(['study_id' => $c->studyId, 'company_id' => $w->company_id, 'work_item_id' => $w->id, 'title' => $d['title']]);
                $report->files()->create(['study_id' => $c->studyId, 'company_id' => $w->company_id, 'uploaded_by' => $c->user->id, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                Audit::record('report.created', $report);

                return response()->json($report, 201);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function publish(Request $r, int $report, StudyContext $c)
    {
        abort_unless($c->canReview(), 403);
        $rep = $c->scope(Report::query())->findOrFail($report);

        return DB::transaction(function () use ($rep, $c) {
            $w = WorkItem::lockForUpdate()->findOrFail($rep->work_item_id);
            $rep = Report::lockForUpdate()->findOrFail($rep->id);
            abort_unless($w->approved_at && in_array($w->status, ['approved', 'delivered']), 422, 'Aprueba el trabajo antes de publicar el informe.');
            abort_unless($rep->files()->exists(), 422, 'El informe no tiene archivo.');
            if (! $rep->published_at) {
                $rep->update(['approved_by' => $c->user->id, 'approved_at' => now(), 'published_at' => now()]);
                Delivery::create(['report_id' => $rep->id, 'user_id' => $c->user->id, 'action' => 'published']);
                Audit::record('report.published', $rep);
                Notices::company($rep->company,'client','Nuevo informe disponible: '.$rep->title,'/reports');
            }

return $rep;
        });
    }
}
