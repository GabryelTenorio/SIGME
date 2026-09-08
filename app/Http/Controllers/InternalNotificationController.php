<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use App\Models\Occurrence;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->internalNotifications()
            ->with('school')
            ->latest()
            ->simplePaginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Request $request, InternalNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $serviceOrderId = $this->positiveInteger($notification->data['service_order_id'] ?? null);
        $occurrenceId = $this->positiveInteger($notification->data['occurrence_id'] ?? null);

        abort_if(($serviceOrderId === null) === ($occurrenceId === null), 404);

        if ($serviceOrderId !== null) {
            $serviceOrder = ServiceOrder::query()->findOrFail($serviceOrderId);
            $this->authorize('view', $serviceOrder);

            return redirect()->route('service-orders.show', $serviceOrder);
        }

        $occurrence = Occurrence::query()->findOrFail($occurrenceId);
        $this->authorize('view', $occurrence);

        return redirect()->route('occurrences.show', $occurrence);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_notifications_enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'email_notifications_enabled' => $data['email_notifications_enabled'],
        ]);

        return back()->with('success', 'Preferências de notificação atualizadas.');
    }

    public function read(Request $request, InternalNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notificação marcada como lida.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->internalNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    private function positiveInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : $validated;
    }
}
