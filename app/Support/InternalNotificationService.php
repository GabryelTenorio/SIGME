<?php

namespace App\Support;

use App\Mail\InternalNotificationMail;
use App\Models\InternalNotification;
use App\Models\Occurrence;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class InternalNotificationService
{
    public function __construct(private readonly NotificationRecipientResolver $recipientResolver) {}

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function send(
        Collection $recipients,
        School $school,
        string $eventKey,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        array $data = [],
    ): int {
        if (! Schema::hasTable('internal_notifications')) {
            return 0;
        }

        $timestamp = now();
        $deliveries = $recipients
            ->unique('id')
            ->filter(fn (User $recipient): bool => $recipient->is_active && $recipient->canAccessSchool($school))
            ->map(fn (User $recipient): array => [
                'recipient' => $recipient,
                'attributes' => [
                    'user_id' => $recipient->id,
                    'school_id' => $school->id,
                    'event_key' => $eventKey,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'data' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
                    'read_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ])
            ->values()
            ->all();

        $inserted = 0;

        foreach ($deliveries as $delivery) {
            if (InternalNotification::query()->insertOrIgnore([$delivery['attributes']]) !== 1) {
                continue;
            }

            $inserted++;
            $notification = InternalNotification::query()
                ->where('user_id', $delivery['recipient']->id)
                ->where('event_key', $eventKey)
                ->sole();
            $this->queueEmail($delivery['recipient'], $notification);
        }

        return $inserted;
    }

    /**
     * @param  array<int, string>|string  $permissions
     * @param  array<string, mixed>  $data
     */
    public function sendToSchoolPermissions(
        School $school,
        array|string $permissions,
        string $eventKey,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        array $data = [],
    ): int {
        return $this->send(
            $this->recipientResolver->forSchoolAndPermissions($school, $permissions),
            $school,
            $eventKey,
            $type,
            $title,
            $body,
            $url,
            $data,
        );
    }

    private function queueEmail(User $recipient, InternalNotification $notification): void
    {
        if (! $recipient->email_notifications_enabled) {
            return;
        }

        $data = $notification->data ?? [];
        $serviceOrderId = $this->positiveInteger($data['service_order_id'] ?? null);
        $occurrenceId = $this->positiveInteger($data['occurrence_id'] ?? null);

        if (($serviceOrderId === null) === ($occurrenceId === null)) {
            return;
        }

        if ($serviceOrderId !== null) {
            $resource = ServiceOrder::query()->find($serviceOrderId);
            $protocol = $resource?->code;
        } else {
            $resource = Occurrence::query()->find($occurrenceId);
            $protocol = $resource?->protocol;
        }

        if ($resource === null || $protocol === null) {
            return;
        }

        $status = is_string($data['status'] ?? null) && $data['status'] !== ''
            ? $data['status']
            : $resource->status;

        Mail::to($recipient->email)->queue(
            (new InternalNotificationMail(
                $protocol,
                $notification->type,
                $status,
                route('notifications.open', $notification),
            ))->afterCommit(),
        );
    }

    private function positiveInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : $validated;
    }
}
