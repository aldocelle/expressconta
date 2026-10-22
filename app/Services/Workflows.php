<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Engagement;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Workflows
{
    const TRANSITIONS = ['not_started' => ['waiting', 'preparing', 'cancelled'], 'waiting' => ['preparing', 'cancelled'], 'preparing' => ['review', 'waiting', 'cancelled'], 'review' => ['changes', 'approved', 'cancelled'], 'changes' => ['preparing', 'review', 'cancelled'], 'approved' => ['delivered', 'changes'], 'delivered' => ['changes'], 'cancelled' => ['not_started']];

    public function transition(WorkItem $work, string $status, int $version, ?string $reason): WorkItem
    {
        return DB::transaction(function () use ($work, $status, $version, $reason) {
            $w = WorkItem::lockForUpdate()->findOrFail($work->id);
            abort_if($w->version !== $version, 409, 'Este trabajo cambió. Actualiza antes de continuar.');
            if (! in_array($status, self::TRANSITIONS[$w->status] ?? [])) {
                throw ValidationException::withMessages(['status' => 'Transición no permitida.']);
            }
            if (in_array($status, ['approved', 'delivered', 'changes']) || $w->status === 'cancelled') {
                abort_unless(app(StudyContext::class)->canReview(), 403);
            }
            if (in_array($status, ['changes', 'cancelled']) || in_array($w->status, ['delivered', 'cancelled'])) {
                if (! trim((string) $reason)) {
                    throw ValidationException::withMessages(['reason' => 'Indica el motivo del cambio.']);
                }
            }
            if (in_array($status, ['preparing', 'review', 'approved', 'delivered']) && $w->requests()->where('required', true)->whereNotIn('status', ['accepted', 'not_applicable'])->exists()) {
                throw ValidationException::withMessages(['status' => 'Faltan antecedentes obligatorios aceptados.']);
            }
            if (in_array($status, ['review', 'approved', 'delivered']) && collect($w->checklist)->contains(fn ($t) => ! $t['done'])) {
                throw ValidationException::withMessages(['status' => 'Completa la lista de verificación.']);
            }
            if ($status === 'delivered' && ! $w->reports()->whereNotNull('approved_at')->whereNotNull('published_at')->exists()) {
                throw ValidationException::withMessages(['status' => 'Publica un informe aprobado antes de entregar.']);
            }
            $previous = $w->status;
            $w->status = $status;
            $w->version++;
            if ($status === 'approved') {
                $w->approved_by = app(StudyContext::class)->user->id;
                $w->approved_at = now();
            }
            if ($status === 'changes') {
                $w->approved_by = null;
                $w->approved_at = null;
            }
            $w->save();
            Audit::record('work.transition', $w, ['from' => $previous, 'to' => $status, 'reason' => $reason]);

            return $w;
        });
    }

    public function generate(string $period): array
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period), 422);
        $date = CarbonImmutable::createFromFormat('!Y-m', $period);
        $counts = ['works' => 0, 'charges' => 0];
        foreach (Engagement::with(['template', 'company'])->where('active', true)->whereDate('starts_on', '<=', $date->endOfMonth())->whereHas('company', fn ($q) => $q->whereNull('archived_at'))->whereHas('template', fn ($q) => $q->where('active', true))->get() as $e) {
            DB::transaction(function () use ($e, $period, $date, &$counts) {
                // Lock the agreement before checking uniqueness, so concurrent scheduler runs serialize.
                Engagement::whereKey($e->id)->lockForUpdate()->firstOrFail();
                $w = WorkItem::firstOrCreate(['engagement_id' => $e->id, 'period' => $period], ['study_id' => $e->study_id, 'company_id' => $e->company_id, 'title' => $e->template->name, 'status' => 'waiting', 'responsible_id' => $e->company->responsible_id, 'due_on' => $date->endOfMonth()->toDateString(), 'checklist' => $e->template->checklist]);
                if ($w->wasRecentlyCreated) {
                    $counts['works']++;
                    foreach ($e->template->requests as $r) {
                        $w->requests()->create(['study_id' => $e->study_id, 'company_id' => $e->company_id, 'title' => $r['title'], 'instructions' => $r['instructions'] ?? null, 'required' => $r['required'] ?? true, 'due_on' => $date->endOfMonth()->toDateString()]);
                    }
                }
                $charge = Charge::firstOrCreate(['engagement_id' => $e->id, 'period' => $period], ['study_id' => $e->study_id, 'company_id' => $e->company_id, 'description' => $e->template->name, 'amount' => $e->monthly_fee, 'due_on' => $date->day(25)->toDateString()]);
                if ($charge->wasRecentlyCreated) {
                    $counts['charges']++;
                }
            });
        }

return $counts;
    }
}
