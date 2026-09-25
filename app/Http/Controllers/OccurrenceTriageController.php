<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\OccurrenceHistory;
use App\Models\ServiceOrder;
use App\Support\InternalNotificationService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OccurrenceTriageController extends Controller
{
    public function __construct(private readonly InternalNotificationService $notificationService) {}

    public function start(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('triage', $occurrence);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($request): void {
            $this->requireStatus($current, ['ABERTA']);
            $this->transition($current, $request, 'EM_TRIAGEM', 'triage_started', ['triaged_by' => $request->user()->id, 'triaged_at' => now()]);
        });

        return back()->with('success', 'Triagem iniciada.');
    }

    public function confirmPriority(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('confirmPriority', $occurrence);
        $data = $request->validate(['confirmed_priority' => ['required', Rule::in(array_keys(Occurrence::PRIORITIES))], 'triage_note' => ['required', 'string', 'max:3000']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['EM_TRIAGEM']);
            $old = ['confirmed_priority' => $current->confirmed_priority];
            $current->update($data + ['triaged_by' => $request->user()->id, 'triaged_at' => now()]);
            $this->history($current, $request, 'priority_confirmed', $old, $data);
        });

        return back()->with('success', 'Prioridade confirmada.');
    }

    public function requestInformation(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('requestInformation', $occurrence);
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['ABERTA', 'EM_TRIAGEM']);
            $history = $this->transition($current, $request, 'AGUARDANDO_INFORMACOES', 'information_requested', [], $data);
            $this->notificationService->send(
                collect([$current->reporter]),
                $current->school,
                "occurrence:{$current->id}:information_requested:history:{$history->id}",
                'occurrence.information_requested',
                "Informação solicitada na ocorrência {$current->protocol}",
                $data['message'],
                route('occurrences.show', $current),
                ['occurrence_id' => $current->id, 'history_id' => $history->id, 'status' => $current->status],
            );
        });

        return back()->with('success', 'Informação adicional solicitada.');
    }

    public function provideInformation(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('provideInformation', $occurrence);
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['AGUARDANDO_INFORMACOES']);
            $history = $this->transition($current, $request, 'EM_TRIAGEM', 'information_provided', [], $data);
            $this->notificationService->sendToSchoolPermissions(
                $current->school,
                'ocorrencias.triar',
                "occurrence:{$current->id}:information_provided:history:{$history->id}",
                'occurrence.information_provided',
                "Informação fornecida na ocorrência {$current->protocol}",
                $data['message'],
                route('occurrences.show', $current),
                ['occurrence_id' => $current->id, 'history_id' => $history->id, 'status' => $current->status],
            );
        });

        return back()->with('success', 'Informação enviada para a triagem.');
    }

    public function notApplicable(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('markNotApplicable', $occurrence);
        $data = $request->validate(['reason' => ['required', 'string', 'max:3000']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['ABERTA', 'EM_TRIAGEM', 'AGUARDANDO_INFORMACOES']);
            $this->transition($current, $request, 'NAO_PROCEDE', 'marked_not_applicable', [], $data);
        });

        return back()->with('success', 'Ocorrência marcada como não procede.');
    }

    public function duplicate(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('markDuplicate', $occurrence);
        $data = $request->validate(['duplicate_of_id' => ['required', 'integer', Rule::exists(Occurrence::class, 'id')->where('school_id', $occurrence->school_id)], 'reason' => ['nullable', 'string', 'max:3000']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['ABERTA', 'EM_TRIAGEM', 'AGUARDANDO_INFORMACOES']);
            if ((int) $data['duplicate_of_id'] === $current->id) {
                throw ValidationException::withMessages(['duplicate_of_id' => 'A ocorrência não pode ser duplicada dela mesma.']);
            }
            $this->transition($current, $request, 'DUPLICADA', 'marked_duplicate', ['duplicate_of_id' => $data['duplicate_of_id']], ['reason' => $data['reason'] ?? null]);
        });

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência marcada como duplicada.');
    }

    public function forward(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $this->authorize('forward', $occurrence);
        $data = $request->validate(['forwarded_destination' => ['required', 'string', 'max:255']]);
        $this->mutateCurrentOccurrence($request, $occurrence, function (Occurrence $current) use ($data, $request): void {
            $this->requireStatus($current, ['EM_TRIAGEM']);
            if (! $current->confirmed_priority) {
                throw ValidationException::withMessages(['confirmed_priority' => 'Confirme a prioridade antes de encaminhar.']);
            }
            $history = $this->transition($current, $request, 'ENCAMINHADA', 'forwarded', $data);
            $this->notificationService->sendToSchoolPermissions(
                $current->school,
                ['ordens_servico.criar', 'ordens_servico.atribuir'],
                "occurrence:{$current->id}:forwarded:history:{$history->id}",
                'occurrence.forwarded',
                "Ocorrência {$current->protocol} encaminhada",
                'A ocorrência está pronta para criação e atribuição de uma Ordem de Serviço.',
                route('occurrences.show', $current),
                ['occurrence_id' => $current->id, 'history_id' => $history->id, 'status' => $current->status],
            );
        });

        if ($request->user()->can('create', ServiceOrder::class)) {
            return redirect()->route('service-orders.create', ['occurrence_id' => $occurrence->id])
                ->with('success', 'Triagem concluída. Agora crie a Ordem de Serviço.');
        }

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência encaminhada para manutenção.');
    }

    /** @param Closure(Occurrence): void $mutation */
    private function mutateCurrentOccurrence(Request $request, Occurrence $occurrence, Closure $mutation): void
    {
        $expectedVersion = $request->validate([
            'occurrence_version' => ['required', 'string', 'size:64'],
        ])['occurrence_version'];

        DB::transaction(function () use ($expectedVersion, $mutation, $occurrence): void {
            $current = Occurrence::query()->lockForUpdate()->findOrFail($occurrence->id);

            if (! hash_equals($current->concurrencyToken(), $expectedVersion)) {
                throw ValidationException::withMessages([
                    'occurrence_version' => 'Esta ocorrência foi alterada por outro usuário. Recarregue a página antes de continuar.',
                ]);
            }

            $mutation($current);
        });
    }

    private function requireStatus(Occurrence $occurrence, array $allowed): void
    {
        if (! in_array($occurrence->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Esta transição não é permitida no estado atual.']);
        }
    }

    private function transition(Occurrence $occurrence, Request $request, string $status, string $event, array $attributes = [], array $metadata = []): OccurrenceHistory
    {
        $old = ['status' => $occurrence->status];
        $occurrence->update($attributes + ['status' => $status]);

        return $this->history($occurrence, $request, $event, $old, ['status' => $status] + $attributes, $metadata);
    }

    private function history(Occurrence $occurrence, Request $request, string $event, ?array $old = null, ?array $new = null, ?array $metadata = null): OccurrenceHistory
    {
        return OccurrenceHistory::query()->create(['occurrence_id' => $occurrence->id, 'actor_id' => $request->user()->id, 'event_type' => $event, 'old_values' => $old, 'new_values' => $new, 'metadata' => $metadata]);
    }
}
