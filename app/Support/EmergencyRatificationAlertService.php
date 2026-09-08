<?php

namespace App\Support;

use App\Models\InternalNotification;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class EmergencyRatificationAlertService
{
    public function __construct(
        private readonly InternalNotificationService $notificationService,
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {}

    public function send(?CarbonInterface $at = null): int
    {
        $now = CarbonImmutable::instance($at ?? now());
        $inserted = 0;

        ServiceOrder::query()
            ->with('school')
            ->whereIn('status', ['EM_EXECUCAO', 'AGUARDANDO_MATERIAL', 'PAUSADA'])
            ->whereNotNull('emergency_authorized_at')
            ->whereNull('emergency_ratified_at')
            ->whereNotNull('emergency_ratification_due_at')
            ->where('emergency_ratification_due_at', '<', $now)
            ->chunkById(100, function ($orders) use ($now, &$inserted): void {
                foreach ($orders as $order) {
                    $alertDate = $now->isWeekend()
                        ? $order->emergency_ratification_due_at->toDateString()
                        : $now->toDateString();
                    $eventKey = "service-order:{$order->id}:emergency-ratification-overdue:{$alertDate}";

                    if ($now->isWeekend() && InternalNotification::query()->where('event_key', $eventKey)->exists()) {
                        continue;
                    }

                    $recipients = $this->recipientResolver
                        ->forSchoolAndPermissions($order->school, [
                            'ordens_servico.aprovar',
                            'ordens_servico.iniciar_emergencial',
                        ])
                        ->reject(fn (User $user): bool => $user->id === $order->created_by)
                        ->values();

                    $inserted += $this->notificationService->send(
                        $recipients,
                        $order->school,
                        $eventKey,
                        'service-order.emergency-ratification-overdue',
                        "Ratificação emergencial vencida: {$order->code}",
                        'A autorização emergencial permanece pendente de ratificação.',
                        route('service-orders.show', $order),
                        [
                            'service_order_id' => $order->id,
                            'status' => $order->status,
                            'ratification_due_at' => $order->emergency_ratification_due_at->toIso8601String(),
                            'alert_date' => $alertDate,
                        ],
                    );
                }
            });

        return $inserted;
    }
}
