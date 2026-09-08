<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceOrderRequest;
use App\Http\Requests\ServiceOrderTeamRequest;
use App\Models\Occurrence;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\ServiceOrderSequence;
use App\Models\User;
use App\Support\InternalNotificationService;
use App\Support\NotificationRecipientResolver;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly InternalNotificationService $notificationService,
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $user = $request->user();
        $schools = $user->accessibleSchools()->filter(fn ($school) => $user->hasPermission('ordens_servico.visualizar', $school));
        $managedSchoolIds = $schools->filter(fn ($school) => $user->hasPermission('ordens_servico.criar', $school) || $user->hasPermission('ordens_servico.aprovar', $school) || $user->hasPermission('ordens_servico.atribuir', $school))->pluck('id');
        $orders = ServiceOrder::query()->with(['occurrence.environment', 'school', 'assignedUser', 'materials', 'costs'])->whereIn('school_id', $schools->pluck('id'))
            ->when(! $user->is_platform_admin, fn (Builder $q) => $q->where(fn (Builder $x) => $x->whereIn('school_id', $managedSchoolIds)->orWhere('assigned_user_id', $user->id)->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id))))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $x) => $x->where('code', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')->orWhereHas('occurrence', fn (Builder $occurrence) => $occurrence->where('protocol', 'like', '%'.$request->string('search').'%'))))
            ->when($request->integer('school_id'), fn (Builder $q) => $q->where('school_id', $request->integer('school_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority_snapshot', $request->string('priority')))
            ->when($request->integer('assigned_user_id'), fn (Builder $q) => $q->where('assigned_user_id', $request->integer('assigned_user_id')))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->whereDate('due_date', '<', today())->whereNotIn('status', ['CONCLUIDA', 'REJEITADA', 'CANCELADA']))
            ->latest()->get();

        return view('service-orders.index', ['orders' => $orders, 'schools' => $schools, 'users' => User::query()->whereIn('organization_id', $schools->pluck('organization_id'))->where('is_active', true)->orderBy('name')->get(), 'stats' => ['open' => $orders->whereNotIn('status', ['CONCLUIDA', 'REJEITADA', 'CANCELADA'])->count(), 'running' => $orders->where('status', 'EM_EXECUCAO')->count(), 'material' => $orders->where('status', 'AGUARDANDO_MATERIAL')->count(), 'late' => $orders->filter(fn ($o) => $o->due_date?->isPast() && ! in_array($o->status, ['CONCLUIDA', 'REJEITADA', 'CANCELADA'], true))->count()]]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ServiceOrder::class);
        $occurrence = Occurrence::query()->findOrFail($request->integer('occurrence_id'));
        abort_unless($occurrence->status === 'ENCAMINHADA' && $request->user()->canAccessSchool($occurrence->school), 403);

        $users = User::query()
            ->where('organization_id', $occurrence->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->isEligibleForServiceOrderAssignment($occurrence->school))
            ->values();

        return view('service-orders.create', ['occurrence' => $occurrence->load(['school', 'environment', 'category']), 'users' => $users]);
    }

    public function store(ServiceOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request) {
            $occurrence = Occurrence::query()->lockForUpdate()->findOrFail($request->integer('occurrence_id'));
            $school = $occurrence->school;
            $year = now()->year;
            DB::table('service_order_sequences')->insertOrIgnore(['school_id' => $school->id, 'year' => $year, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $counter = ServiceOrderSequence::query()->where('school_id', $school->id)->where('year', $year)->lockForUpdate()->sole();
            $seq = $counter->next_number;
            $counter->update(['next_number' => $seq + 1]);
            $d = $request->validated();
            $always = $d['external_service'] || $d['asset_replacement'] || $d['asset_disposal'] || $d['extraordinary_purchase'];
            $threshold = (string) ($occurrence->organization->approval_threshold ?? '0.00');
            $approval = $always || BigDecimal::of((string) $d['estimated_cost'])->isGreaterThan(BigDecimal::of($threshold));
            $order = ServiceOrder::query()->create(Arr::except($d, 'member_ids') + ['organization_id' => $occurrence->organization_id, 'school_id' => $school->id, 'created_by' => $request->user()->id, 'code_year' => $year, 'code_sequence' => $seq, 'code' => sprintf('OS-%s-%d-%06d', $school->code, $year, $seq), 'priority_snapshot' => $occurrence->priority(), 'approval_required' => $approval, 'status' => $approval ? 'AGUARDANDO_APROVACAO' : 'APROVADA']);
            $order->members()->sync(array_unique(array_filter([...$d['member_ids'], $d['assigned_user_id'] ?? null])));
            $history = ServiceOrderHistory::query()->create(['service_order_id' => $order->id, 'actor_id' => $request->user()->id, 'event_type' => 'created', 'new_values' => ['status' => $order->status, 'approval_required' => $approval, 'assigned_user_id' => $order->assigned_user_id, 'member_ids' => $order->members()->pluck('users.id')->sort()->values()->all(), 'external_service' => $order->external_service, 'external_provider_name' => $order->external_provider_name]]);
            $this->notificationService->send(
                $order->members()->get(),
                $school,
                "service-order:{$order->id}:assigned:history:{$history->id}",
                'service-order.assigned',
                "Você foi incluído na {$order->code}",
                $order->title,
                route('service-orders.show', $order),
                ['service_order_id' => $order->id, 'history_id' => $history->id, 'status' => $order->status],
            );
            if ($approval) {
                $approvers = $this->recipientResolver
                    ->forSchoolAndPermissions($school, 'ordens_servico.aprovar')
                    ->reject(fn (User $user): bool => $user->id === $order->created_by)
                    ->values();
                $this->notificationService->send(
                    $approvers,
                    $school,
                    "service-order:{$order->id}:awaiting_approval:history:{$history->id}",
                    'service-order.awaiting_approval',
                    "{$order->code} aguardando aprovação",
                    $order->title,
                    route('service-orders.show', $order),
                    ['service_order_id' => $order->id, 'history_id' => $history->id, 'status' => $order->status],
                );
            }

            return $order;
        });

        return redirect()->route('service-orders.show', $order)->with('success', 'Ordem de Serviço criada: '.$order->code);
    }

    public function show(Request $request, ServiceOrder $serviceOrder): View
    {
        $this->authorize('view', $serviceOrder);
        $teamUsers = collect();
        if ($request->user()->can('assign', $serviceOrder)) {
            $teamUsers = User::query()
                ->where('organization_id', $serviceOrder->organization_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user): bool => $user->isEligibleForServiceOrderAssignment($serviceOrder->school))
                ->values();
        }

        return view('service-orders.show', ['order' => $serviceOrder->load(['occurrence.reporter', 'occurrence.environment', 'school', 'assignedUser', 'emergencyAuthorizer', 'emergencyRatifier', 'members', 'materials', 'workLogs', 'costs', 'attachments', 'histories.actor']), 'teamUsers' => $teamUsers]);
    }

    public function updateTeam(ServiceOrderTeamRequest $request, ServiceOrder $o): RedirectResponse
    {
        DB::transaction(function () use ($request, $o): void {
            $order = ServiceOrder::query()->lockForUpdate()->findOrFail($o->id);
            $this->authorize('assign', $order);

            $data = $request->validated();
            $assignedUserId = isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null;
            $memberIds = collect([...$data['member_ids'], $assignedUserId])->filter()->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
            $oldMemberIds = $order->members()->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values();

            if ($order->assigned_user_id === $assignedUserId && $oldMemberIds->all() === $memberIds->all()) {
                return;
            }

            $oldValues = ['assigned_user_id' => $order->assigned_user_id, 'member_ids' => $oldMemberIds->all()];
            $newValues = ['assigned_user_id' => $assignedUserId, 'member_ids' => $memberIds->all()];
            $order->update(['assigned_user_id' => $assignedUserId]);
            $order->members()->sync($memberIds->all());
            $history = ServiceOrderHistory::query()->create([
                'service_order_id' => $order->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'team_changed',
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
            $this->notificationService->send(
                $order->members()->get(),
                $order->school,
                "service-order:{$order->id}:team_changed:history:{$history->id}",
                'service-order.team_changed',
                "Equipe atualizada na {$order->code}",
                $order->title,
                route('service-orders.show', $order),
                ['service_order_id' => $order->id, 'history_id' => $history->id, 'status' => $order->status],
            );
        });

        return back()->with('success', 'Responsável e equipe atualizados.');
    }
}
