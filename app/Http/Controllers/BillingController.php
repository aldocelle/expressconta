<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Services\Audit;
use App\Services\StudyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController
{
    public function index(Request $r, StudyContext $c)
    {
        $d = $r->validate(['period' => 'nullable|date_format:Y-m', 'company_id' => 'nullable|integer']);

        return $c->scope(Charge::query())->with(['company:id,name', 'payments'])->when($d['period'] ?? null, fn ($q, $v) => $q->where('period', $v))->when($d['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))->orderBy('due_on')->get()->map(function ($ch) {
            $paid = $ch->payments->sum('amount');
            $ch->setAttribute('paid', $paid);
            $ch->setAttribute('balance', $ch->amount - $paid);
            $ch->setAttribute('payment_status', $paid >= $ch->amount ? 'paid' : ($ch->due_on->lt(today('America/Santiago')) ? 'overdue' : ($paid > 0 ? 'partial' : 'pending')));

            return $ch;
        });
    }

    public function store(Request $r, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['company_id' => 'required|integer', 'period' => 'required|date_format:Y-m', 'description' => 'required|string|max:180', 'amount' => 'required|integer|min:1|max:1000000000', 'due_on' => 'required|date_format:Y-m-d']);
        $c->company($d['company_id']);
        $ch = Charge::create($d + ['study_id' => $c->studyId]);
        Audit::record('charge.created', $ch);

        return response()->json($ch, 201);
    }

    public function payment(Request $r, int $charge, StudyContext $c)
    {
        abort_unless($c->admin(), 403);
        $d = $r->validate(['amount' => 'required|integer|min:1|max:1000000000', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:'.today('America/Santiago')->toDateString(), 'reference' => 'required|string|max:100']);
        $ch = $c->scope(Charge::query())->findOrFail($charge);

        return DB::transaction(function () use ($ch, $d, $c) {
            $ch = Charge::lockForUpdate()->findOrFail($ch->id);
            $existing = $ch->payments()->where('reference', $d['reference'])->first();
            if ($existing) {
                abort_if($existing->amount !== $d['amount'] || $existing->paid_on->format('Y-m-d') !== $d['paid_on'], 409, 'La referencia ya se utilizó con otros datos.');

                return $existing;
            }abort_if($d['amount'] > $ch->amount - $ch->payments()->sum('amount'), 422, 'El pago supera el saldo pendiente.');
            $p = $ch->payments()->create($d + ['recorded_by' => $c->user->id]);
            Audit::record('payment.recorded', $ch, ['payment_id' => $p->id, 'amount' => $p->amount]);

            return response()->json($p,201);
        });
    }
}
