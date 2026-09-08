<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\User;
use App\Support\InternalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OccurrenceResolutionController extends Controller
{
    public function __construct(private readonly InternalNotificationService $notificationService) {}

    public function close(Request $r, Occurrence $o): RedirectResponse
    {
        $this->authorize('close', $o);
        if ($o->status !== 'RESOLVIDA') {
            throw ValidationException::withMessages(['status' => 'Somente ocorrência resolvida pode ser encerrada.']);
        }
        if (! $o->canBeResolvedFromServiceOrders()) {
            throw ValidationException::withMessages([
                'service_orders' => 'A ocorrência exige ao menos uma Ordem de Serviço concluída e nenhuma OS válida pendente antes do encerramento.',
            ]);
        }
        $this->move($r, $o, 'ENCERRADA', 'closed');

        return back()->with('success', 'Ocorrência encerrada.');
    }

    public function reopen(Request $r, Occurrence $o): RedirectResponse
    {
        $this->authorize('reopen', $o);
        if (! in_array($o->status, ['RESOLVIDA', 'ENCERRADA'], true)) {
            throw ValidationException::withMessages(['status' => 'Ocorrência não pode ser reaberta neste estado.']);
        }
        $reason = $r->validate(['reason' => 'required|string|max:3000'])['reason'];
        $this->move($r, $o, 'EM_TRIAGEM', 'reopened', ['reason' => $reason]);

        return back()->with('success', 'Ocorrência reaberta para triagem.');
    }

    public function requestReopening(Request $request, Occurrence $o): RedirectResponse
    {
        $this->authorize('requestReopening', $o);
        $reason = $request->validate(['reason' => 'required|string|max:3000'])['reason'];

        DB::transaction(function () use ($request, $o, $reason): void {
            $occurrence = Occurrence::query()->lockForUpdate()->findOrFail($o->id);
            $this->authorize('requestReopening', $occurrence);
            $history = OccurrenceHistory::query()->create([
                'occurrence_id' => $occurrence->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'reopen_requested',
                'old_values' => ['status' => $occurrence->status],
                'new_values' => ['status' => $occurrence->status],
                'metadata' => ['reason' => $reason],
            ]);
            $this->notificationService->sendToSchoolPermissions(
                $occurrence->school,
                'ocorrencias.reabrir',
                "occurrence:{$occurrence->id}:reopen_requested:history:{$history->id}",
                'occurrence.reopen_requested',
                "Reabertura solicitada para {$occurrence->protocol}",
                $reason,
                route('occurrences.show', $occurrence),
                ['occurrence_id' => $occurrence->id, 'history_id' => $history->id, 'status' => $occurrence->status],
            );
        });

        return back()->with('success', 'Pedido de reabertura enviado para decisão do gestor.');
    }

    private function move(Request $r, Occurrence $o, string $status, string $event, array $meta = []): void
    {
        DB::transaction(function () use ($r, $o, $status, $event, $meta): void {
            $occurrence = Occurrence::query()->lockForUpdate()->findOrFail($o->id);
            $oldStatus = $occurrence->status;
            $occurrence->update(['status' => $status]);
            $history = OccurrenceHistory::query()->create(['occurrence_id' => $occurrence->id, 'actor_id' => $r->user()->id, 'event_type' => $event, 'old_values' => ['status' => $oldStatus], 'new_values' => ['status' => $status], 'metadata' => $meta]);
            $eventKey = "occurrence:{$occurrence->id}:{$event}:history:{$history->id}";
            $notificationType = "occurrence.{$event}";
            $title = $event === 'closed'
                ? "Ocorrência {$occurrence->protocol} encerrada"
                : "Ocorrência {$occurrence->protocol} reaberta";
            $body = isset($meta['reason']) ? (string) $meta['reason'] : 'O ciclo da ocorrência foi encerrado.';
            $team = $occurrence->serviceOrders()
                ->with(['assignedUser', 'members'])
                ->get()
                ->flatMap(fn ($order) => collect([$order->assignedUser])->merge($order->members))
                ->filter(fn (mixed $user): bool => $user instanceof User);
            $directRecipients = collect([$occurrence->reporter])->merge($team)->filter(fn (mixed $user): bool => $user instanceof User)->values();
            $notificationData = ['occurrence_id' => $occurrence->id, 'history_id' => $history->id, 'status' => $occurrence->status];

            $this->notificationService->send(
                $directRecipients,
                $occurrence->school,
                $eventKey,
                $notificationType,
                $title,
                $body,
                route('occurrences.show', $occurrence),
                $notificationData,
            );
            $this->notificationService->sendToSchoolPermissions(
                $occurrence->school,
                $event === 'closed' ? 'ocorrencias.encerrar' : 'ocorrencias.reabrir',
                $eventKey,
                $notificationType,
                $title,
                $body,
                route('occurrences.show', $occurrence),
                $notificationData,
            );
        });
    }
}
