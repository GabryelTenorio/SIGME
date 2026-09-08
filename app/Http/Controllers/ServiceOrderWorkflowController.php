<?php

namespace App\Http\Controllers;

use App\Models\OccurrenceHistory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\User;
use App\Support\EmergencyRatificationDeadline;
use App\Support\InternalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderWorkflowController extends Controller
{
    public function __construct(
        private EmergencyRatificationDeadline $ratificationDeadline,
        private InternalNotificationService $notificationService,
    ) {}

    public function approve(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('approve', $o);
        $this->requireIndependentDecisionMaker($r, $o);

        return $this->move($r, $o, ['AGUARDANDO_APROVACAO'], 'APROVADA', 'approved');
    }

    public function reject(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('reject', $o);
        $this->requireIndependentDecisionMaker($r, $o);
        $r->validate(['reason' => 'required|string|max:3000']);

        return $this->move($r, $o, ['AGUARDANDO_APROVACAO'], 'REJEITADA', 'rejected', ['reason' => $r->input('reason')]);
    }

    public function cancel(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('cancel', $o);
        $r->validate(['reason' => 'required|string|max:3000']);

        return $this->move($r, $o, ['AGUARDANDO_APROVACAO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_MATERIAL', 'PAUSADA'], 'CANCELADA', 'cancelled', ['reason' => $r->input('reason')]);
    }

    public function start(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('start', $o);
        $result = $this->move($r, $o, ['APROVADA'], 'EM_EXECUCAO', 'started', [], ['started_at' => now()]);
        $this->syncAttendance($o);

        return $result;
    }

    public function waitMaterial(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('pause', $o);

        return $this->move($r, $o, ['EM_EXECUCAO'], 'AGUARDANDO_MATERIAL', 'waiting_material', ['reason' => $r->validate(['reason' => 'required|string|max:3000'])['reason']]);
    }

    public function pause(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('pause', $o);

        return $this->move($r, $o, ['EM_EXECUCAO'], 'PAUSADA', 'paused', ['reason' => $r->validate(['reason' => 'required|string|max:3000'])['reason']]);
    }

    public function resume(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('start', $o);

        return $this->move($r, $o, ['AGUARDANDO_MATERIAL', 'PAUSADA'], 'EM_EXECUCAO', 'resumed');
    }

    public function emergency(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('emergency', $o);
        $reason = $r->validate(['reason' => 'required|string|max:3000'])['reason'];

        return DB::transaction(function () use ($o, $r, $reason): RedirectResponse {
            $current = ServiceOrder::query()->lockForUpdate()->findOrFail($o->id);
            $this->require($current, ['AGUARDANDO_APROVACAO']);

            if ($current->priority_snapshot !== 'URGENT') {
                throw ValidationException::withMessages(['status' => 'Atendimento emergencial exige prioridade urgente.']);
            }

            $this->requireIndependentEmergencyAuthorizer($r, $current);
            $this->requireEligibleEmergencyExecutor($current);
            $authorizedAt = now();
            $ratificationDueAt = $this->ratificationDeadline->calculate($authorizedAt);
            $result = $this->move(
                $r,
                $current,
                ['AGUARDANDO_APROVACAO'],
                'EM_EXECUCAO',
                'emergency_started',
                [
                    'reason' => $reason,
                    'authorized_by' => $r->user()->id,
                    'ratification_due_at' => $ratificationDueAt->toIso8601String(),
                ],
                [
                    'started_at' => $authorizedAt,
                    'emergency_authorized_by' => $r->user()->id,
                    'emergency_authorized_at' => $authorizedAt,
                    'emergency_reason' => $reason,
                    'emergency_ratification_due_at' => $ratificationDueAt,
                ],
            );
            $this->syncAttendance($current);

            return $result;
        });
    }

    public function ratifyEmergency(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('ratify', $o);

        return DB::transaction(function () use ($o, $r): RedirectResponse {
            $current = ServiceOrder::query()->lockForUpdate()->findOrFail($o->id);
            $this->requireIndependentEmergencyRatifier($r, $current);

            if (! $current->hasPendingEmergencyRatification()) {
                throw ValidationException::withMessages([
                    'emergency_ratification' => 'Esta Ordem de Serviço não possui ratificação emergencial pendente.',
                ]);
            }

            $ratifiedAt = now();
            $wasOverdue = $current->emergencyRatificationIsOverdue($ratifiedAt);
            $current->update([
                'emergency_ratified_by' => $r->user()->id,
                'emergency_ratified_at' => $ratifiedAt,
            ]);
            $this->history($r, $current, 'emergency_ratified', [
                'ratified_at' => $ratifiedAt->toIso8601String(),
                'ratification_due_at' => $current->emergency_ratification_due_at?->toIso8601String(),
                'was_overdue' => $wasOverdue,
            ]);

            return back()->with('success', 'Atendimento emergencial ratificado.');
        });
    }

    public function complete(Request $r, ServiceOrder $o): RedirectResponse
    {
        $this->authorize('complete', $o);
        $data = $r->validate(['solution' => 'required|string|max:5000']);

        DB::transaction(function () use ($r, $o, $data): void {
            $order = ServiceOrder::query()->lockForUpdate()->findOrFail($o->id);
            $this->authorize('complete', $order);
            $this->require($order, ['EM_EXECUCAO']);
            if (! $order->diagnosis) {
                throw ValidationException::withMessages(['diagnosis' => 'Registre o diagnóstico antes da conclusão.']);
            }
            if ($order->emergencyRatificationIsOverdue()) {
                throw ValidationException::withMessages([
                    'emergency_ratification' => 'A ratificação emergencial está vencida. Ratifique a autorização antes de concluir a OS.',
                ]);
            }

            $order->update($data + ['status' => 'CONCLUIDA', 'completed_at' => now()]);
            $this->history($r, $order, 'completed', ['status' => 'CONCLUIDA']);
            if ($order->occurrence->canBeResolvedFromServiceOrders()) {
                $occurrence = $order->occurrence;
                $oldStatus = $occurrence->status;
                $occurrence->update(['status' => 'RESOLVIDA']);
                $history = OccurrenceHistory::query()->create(['occurrence_id' => $occurrence->id, 'actor_id' => $r->user()->id, 'event_type' => 'resolved_by_service_orders', 'old_values' => ['status' => $oldStatus], 'new_values' => ['status' => 'RESOLVIDA']]);
                $eventKey = "occurrence:{$occurrence->id}:resolved:history:{$history->id}";
                $notificationData = ['occurrence_id' => $occurrence->id, 'history_id' => $history->id, 'status' => $occurrence->status];
                $this->notificationService->send(
                    collect([$occurrence->reporter]),
                    $occurrence->school,
                    $eventKey,
                    'occurrence.resolved',
                    "Ocorrência {$occurrence->protocol} resolvida",
                    'Todas as Ordens de Serviço válidas foram concluídas.',
                    route('occurrences.show', $occurrence),
                    $notificationData,
                );
                $this->notificationService->sendToSchoolPermissions(
                    $occurrence->school,
                    'ocorrencias.encerrar',
                    $eventKey,
                    'occurrence.resolved',
                    "Ocorrência {$occurrence->protocol} resolvida",
                    'A ocorrência está pronta para encerramento.',
                    route('occurrences.show', $occurrence),
                    $notificationData,
                );
            }
        });

        return back()->with('success', 'Ordem de Serviço concluída.');
    }

    private function move(Request $r, ServiceOrder $o, array $from, string $to, string $event, array $meta = [], array $extra = []): RedirectResponse
    {
        return DB::transaction(function () use ($r, $o, $from, $to, $event, $meta, $extra): RedirectResponse {
            $this->require($o, $from);
            $old = $o->status;
            $o->update(['status' => $to] + $extra);
            $history = $this->history($r, $o, $event, ['old_status' => $old, 'status' => $to] + $meta);
            $this->notifyTransition($o, $history, $event, $meta);

            return back()->with('success', 'Status atualizado para '.ServiceOrder::STATUS_LABELS[$to].'.');
        });
    }

    private function require(ServiceOrder $o, array $states): void
    {
        if (! in_array($o->status, $states, true)) {
            throw ValidationException::withMessages(['status' => 'Transição inválida para o estado atual.']);
        }
    }

    private function requireIndependentDecisionMaker(Request $request, ServiceOrder $serviceOrder): void
    {
        if ((int) $serviceOrder->created_by === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'approval' => 'O criador da Ordem de Serviço não pode aprovar nem rejeitar a própria solicitação.',
            ]);
        }
    }

    private function requireIndependentEmergencyAuthorizer(Request $request, ServiceOrder $serviceOrder): void
    {
        $user = $request->user();
        if ((int) $serviceOrder->created_by === (int) $user->id
            || ! $user->is_active
            || $user->organization_id !== $serviceOrder->organization_id
            || ! $user->canAccessSchool($serviceOrder->school)
            || ! $user->hasPermission('ordens_servico.iniciar_emergencial', $serviceOrder->school)) {
            throw ValidationException::withMessages([
                'emergency_authorizer' => 'O início emergencial exige outro gestor ou administrador ativo e autorizado na mesma escola.',
            ]);
        }
    }

    private function requireEligibleEmergencyExecutor(ServiceOrder $serviceOrder): void
    {
        $assignedUser = $serviceOrder->assignedUser;
        if (! $assignedUser || ! $assignedUser->isEligibleForServiceOrderAssignment($serviceOrder->school)) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Defina um responsável técnico elegível antes de iniciar o atendimento emergencial.',
            ]);
        }
    }

    private function requireIndependentEmergencyRatifier(Request $request, ServiceOrder $serviceOrder): void
    {
        $user = $request->user();
        if ((int) $serviceOrder->created_by === (int) $user->id
            || ! $user->is_active
            || $user->organization_id !== $serviceOrder->organization_id
            || ! $user->canAccessSchool($serviceOrder->school)
            || ! $user->hasPermission('ordens_servico.aprovar', $serviceOrder->school)) {
            throw ValidationException::withMessages([
                'emergency_ratification' => 'A ratificação exige outro aprovador ativo e autorizado na mesma escola.',
            ]);
        }
    }

    private function history(Request $r, ServiceOrder $o, string $event, array $meta = []): ServiceOrderHistory
    {
        return ServiceOrderHistory::query()->create(['service_order_id' => $o->id, 'actor_id' => $r->user()->id, 'event_type' => $event, 'metadata' => $meta]);
    }

    private function notifyTransition(ServiceOrder $order, ServiceOrderHistory $history, string $event, array $metadata): void
    {
        $notificationType = match ($event) {
            'approved' => 'service-order.approved',
            'rejected' => 'service-order.rejected',
            'waiting_material' => 'service-order.waiting_material',
            'paused' => 'service-order.paused',
            default => null,
        };
        if ($notificationType === null) {
            return;
        }

        $recipients = collect([$order->creator, $order->assignedUser])
            ->merge($order->members)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->values();
        $title = match ($event) {
            'approved' => "{$order->code} aprovada",
            'rejected' => "{$order->code} rejeitada",
            'waiting_material' => "{$order->code} aguardando material",
            'paused' => "{$order->code} pausada",
        };
        $body = isset($metadata['reason']) ? (string) $metadata['reason'] : $order->title;

        $this->notificationService->send(
            $recipients,
            $order->school,
            "service-order:{$order->id}:{$event}:history:{$history->id}",
            $notificationType,
            $title,
            $body,
            route('service-orders.show', $order),
            ['service_order_id' => $order->id, 'history_id' => $history->id, 'status' => $order->status],
        );
    }

    private function syncAttendance(ServiceOrder $o): void
    {
        $occ = $o->occurrence;
        if ($occ->status !== 'EM_ATENDIMENTO') {
            $old = $occ->status;
            $occ->update(['status' => 'EM_ATENDIMENTO']);
            OccurrenceHistory::query()->create(['occurrence_id' => $occ->id, 'actor_id' => auth()->id(), 'event_type' => 'service_order_started', 'old_values' => ['status' => $old], 'new_values' => ['status' => 'EM_ATENDIMENTO']]);
        }
    }
}
