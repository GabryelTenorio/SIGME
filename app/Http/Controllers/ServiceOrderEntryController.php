<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCost;
use App\Models\ServiceOrderHistory;
use App\Models\ServiceOrderMaterial;
use App\Models\ServiceOrderWorkLog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderEntryController extends Controller
{
    public function diagnosis(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('diagnose', $o);
        $this->requireOperational($o);
        $d = $r->validate(['diagnosis' => 'required|string|max:5000']);
        $this->record($o, 'diagnose', function (ServiceOrder $o) use ($r, $d): void {
            $o->update($d);
            $this->history($r, $o, 'diagnosis_registered');
        });

        return back()->with('success', 'Diagnóstico registrado.');
    }

    public function update(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('update', $o);
        $this->requireOperational($o);
        $m = $r->validate(['message' => 'required|string|max:3000'])['message'];
        $this->record($o, 'update', function (ServiceOrder $o) use ($r, $m): void {
            $this->history($r, $o, 'update_added', ['message' => $m]);
        });

        return back()->with('success', 'Atualização registrada.');
    }

    public function material(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('material', $o);
        $this->requireOperational($o);
        $d = $r->validate(['description' => 'required|string|max:255', 'quantity' => 'required|decimal:0,3|gt:0|max:999999999.999', 'unit' => 'required|string|max:30', 'unit_cost' => 'required|decimal:0,2|min:0|max:9999999999.99']);
        $unroundedTotal = BigDecimal::of($d['quantity'])->multipliedBy($d['unit_cost']);
        if ($unroundedTotal->isGreaterThan(BigDecimal::of('9999999999.99'))) {
            throw ValidationException::withMessages([
                'total_cost' => 'O custo total do material excede o limite monetário permitido.',
            ]);
        }
        $total = $unroundedTotal->toScale(2, RoundingMode::HalfUp);
        $this->record($o, 'material', function (ServiceOrder $o) use ($r, $d, $total): void {
            ServiceOrderMaterial::query()->create($d + ['service_order_id' => $o->id, 'created_by' => $r->user()->id, 'total_cost' => (string) $total]);
            $this->history($r, $o, 'material_registered', ['description' => $d['description'], 'total' => (string) $total]);
        });

        return back()->with('success', 'Material registrado.');
    }

    public function time(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('time', $o);
        $this->requireOperational($o);
        $d = $r->validate(['started_at' => 'required|date', 'ended_at' => 'required|date', 'description' => 'required|string|max:255']);
        $start = Carbon::parse($d['started_at']);
        $end = Carbon::parse($d['ended_at']);
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ended_at' => 'O fim deve ser posterior ao início.']);
        }
        $minutes = (int) $start->diffInMinutes($end);
        if ($minutes < 1 || $minutes > 4294967295) {
            throw ValidationException::withMessages(['ended_at' => 'Informe uma duração válida de ao menos um minuto.']);
        }
        $this->record($o, 'time', function (ServiceOrder $o) use ($r, $d, $minutes): void {
            ServiceOrderWorkLog::query()->create($d + ['service_order_id' => $o->id, 'user_id' => $r->user()->id, 'duration_minutes' => $minutes]);
            $this->history($r, $o, 'work_logged', ['minutes' => $minutes]);
        });

        return back()->with('success', 'Tempo trabalhado registrado.');
    }

    public function cost(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('cost', $o);
        $this->requireOperational($o);
        $d = $r->validate(['type' => ['required', Rule::in(['EXTERNAL_SERVICE', 'OTHER'])], 'description' => 'required|string|max:255', 'amount' => 'required|decimal:0,2|min:0|max:9999999999.99']);
        $this->record($o, 'cost', function (ServiceOrder $o) use ($r, $d): void {
            ServiceOrderCost::query()->create($d + ['service_order_id' => $o->id, 'created_by' => $r->user()->id]);
            $this->history($r, $o, 'cost_registered', ['amount' => $d['amount'], 'type' => $d['type']]);
        });

        return back()->with('success', 'Custo registrado.');
    }

    private function record(ServiceOrder $order, string $ability, callable $callback): void
    {
        DB::transaction(function () use ($order, $ability, $callback): void {
            Occurrence::query()->lockForUpdate()->findOrFail($order->occurrence_id);
            $current = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->authorize($ability, $current);
            $this->requireOperational($current);
            $callback($current);
        });
    }

    private function history(Request $r, ServiceOrder $o, string $event, array $metadata = []): void
    {
        ServiceOrderHistory::query()->create(['service_order_id' => $o->id, 'actor_id' => $r->user()->id, 'event_type' => $event, 'metadata' => $metadata]);
    }

    private function requireOperational(ServiceOrder $o): void
    {
        if (! in_array($o->status, ['EM_EXECUCAO', 'AGUARDANDO_MATERIAL', 'PAUSADA'], true)) {
            throw ValidationException::withMessages(['status' => 'Inicie a execução antes de registrar atividades na OS.']);
        }
    }
}
