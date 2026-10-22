<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DocumentRequest;
use App\Models\DocumentReview;
use App\Models\FileVersion;
use App\Models\WorkItem;
use App\Services\Audit;
use App\Services\Notices;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController
{
    public function index(Request $r, StudyContext $c)
    {
        $d = $r->validate(['company_id' => 'nullable|integer', 'work_item_id' => 'nullable|integer']);

        return $c->scope(DocumentRequest::query())->with(['company:id,name', 'files', 'work:id,period,title'])->when($d['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))->when($d['work_item_id'] ?? null, fn ($q, $v) => $q->where('work_item_id', $v))->latest()->get();
    }

    public function store(Request $r, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['work_item_id' => 'required|integer', 'title' => 'required|string|max:180', 'instructions' => 'nullable|string|max:3000', 'required' => 'required|boolean', 'due_on' => 'nullable|date_format:Y-m-d']);
        $w = $c->scope(WorkItem::query())->findOrFail($d['work_item_id']);

        return DB::transaction(function () use ($w, $d, $c) {
            $w = WorkItem::lockForUpdate()->findOrFail($w->id);
            abort_if(in_array($w->status, ['approved', 'delivered', 'cancelled']), 422, 'Reabre el trabajo antes de agregar solicitudes.');
            $doc = DocumentRequest::create($d + ['study_id' => $c->studyId, 'company_id' => $w->company_id]);
            Audit::record('document.requested', $doc);

            return response()->json($doc, 201);
        });
    }

    public function upload(Request $r, int $document, StudyContext $c)
    {
        $doc = $c->scope(DocumentRequest::query())->findOrFail($document);
        $r->validate(['file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,csv,txt']);
        $file = $r->file('file');
        $path = $file->store('studies/'.$c->studyId.'/companies/'.$doc->company_id, 'local');
        try {
            return DB::transaction(function () use ($doc, $file, $path, $c) {
                WorkItem::lockForUpdate()->findOrFail($doc->work_item_id);
                $doc = DocumentRequest::lockForUpdate()->findOrFail($doc->id);
                abort_unless(in_array($doc->work->status, ['not_started', 'waiting', 'preparing', 'review', 'changes']), 422, 'El trabajo ya está cerrado.');
                abort_unless(in_array($doc->status, ['pending', 'received', 'reviewing', 'correction']), 422, 'Esta solicitud ya está resuelta.');
                $f = $doc->files()->create(['study_id' => $c->studyId, 'company_id' => $doc->company_id, 'uploaded_by' => $c->user->id, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                $doc->update(['status' => 'received', 'review_comment' => null, 'version' => $doc->version + 1]);
                Audit::record('document.uploaded', $doc, ['file_id' => $f->id]);
                Notices::company($doc->company, 'staff', 'Nuevo antecedente de '.$doc->company->name, '/documents?company_id='.$doc->company_id);

                return response()->json($f, 201);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function review(Request $r, int $document, StudyContext $c)
    {
        abort_unless($c->staff(), 403);
        $d = $r->validate(['status' => 'required|in:reviewing,accepted,correction,not_applicable', 'comment' => 'nullable|string|max:3000', 'version' => 'required|integer', 'file_id' => 'nullable|integer']);
        $doc = $c->scope(DocumentRequest::query())->findOrFail($document);

        return DB::transaction(function () use ($doc, $d, $c) {
            $w = WorkItem::lockForUpdate()->findOrFail($doc->work_item_id);
            abort_if(in_array($w->status, ['approved', 'delivered', 'cancelled']), 422, 'Reabre el trabajo antes de revisar documentos.');
            $doc = DocumentRequest::lockForUpdate()->findOrFail($doc->id);
            abort_if($doc->version !== $d['version'], 409, 'La solicitud cambió. Actualiza para revisar la versión vigente.');
            if (in_array($d['status'], ['correction', 'not_applicable']) && ! trim($d['comment'] ?? '')) {
                throw ValidationException::withMessages(['comment' => 'Indica el motivo.']);
            }if ($d['status'] === 'not_applicable') {
                abort_unless($c->canReview(), 403);
            }$latest = $doc->files()->first();
            if ($d['status'] !== 'not_applicable') {
                abort_unless($latest, 422, 'No hay archivos para revisar.');
                abort_if(($d['file_id'] ?? null) !== $latest->id, 409, 'Revisa la versión más reciente.');
            }if ($latest) {
                DocumentReview::create(['file_version_id' => $latest->id, 'user_id' => $c->user->id, 'decision' => $d['status'], 'comment' => $d['comment'] ?? null]);
            }$doc->update(['status' => $d['status'], 'review_comment' => $d['comment'] ?? null, 'version' => $doc->version + 1]);
            Audit::record('document.reviewed', $doc, ['status' => $d['status'], 'file_id' => $latest?->id]);
            if ($d['status'] === 'correction') {
                Notices::company($doc->company, 'client', 'Corrección solicitada: '.$doc->title, '/documents?company_id='.$doc->company_id);
            }

return $doc->load('files');
        });
    }

    public function download(int $file, StudyContext $c)
    {
        $f = $c->scope(FileVersion::query())->findOrFail($file);
        if ($f->report_id) {
            abort_unless($c->staff() || ($f->report?->published_at && $f->report?->approved_at), 404);
            Delivery::create(['report_id' => $f->report_id, 'user_id' => $c->user->id, 'action' => 'download']);
        }abort_unless(Storage::disk('local')->exists($f->path), 404);
        Audit::record('file.downloaded',$f);

        return Storage::disk('local')->download($f->path,$f->original_name,['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
