<x-layouts.app :title="$occurrence->protocol" active="occurrences">
    @php($occurrenceVersion = $occurrence->concurrencyToken())
    <header class="page-header"><div><span class="eyebrow">{{ $occurrence->protocol }}</span><h1>{{ $occurrence->title }}</h1><p>{{ $occurrence->school->name }} · {{ $occurrence->environment->name }}</p></div><div class="page-header__actions"><span class="priority priority--{{ strtolower($occurrence->priority()) }}">{{ \App\Models\Occurrence::PRIORITIES[$occurrence->priority()] }}</span><span class="badge badge--primary">{{ \App\Models\Occurrence::STATUS_LABELS[$occurrence->status] }}</span></div></header>

    <div class="occurrence-layout">
        <div class="occurrence-main">
            <section class="card"><div class="section-title"><div><h2>Dados da ocorrência</h2><p>Informações registradas pelo solicitante</p></div></div>
                <dl class="detail-grid"><div><dt>Escola</dt><dd>{{ $occurrence->school->name }}</dd></div><div><dt>Ambiente</dt><dd>{{ $occurrence->environment->code }} · {{ $occurrence->environment->name }}</dd></div><div><dt>Categoria</dt><dd>{{ $occurrence->category->name }}</dd></div><div><dt>Solicitante</dt><dd>{{ $occurrence->reporter->name }}</dd></div><div><dt>Impacto</dt><dd>{{ \App\Models\Occurrence::IMPACTS[$occurrence->impact] }}</dd></div><div><dt>Urgência</dt><dd>{{ \App\Models\Occurrence::URGENCIES[$occurrence->perceived_urgency] }}</dd></div><div><dt>Prioridade sugerida</dt><dd>{{ \App\Models\Occurrence::PRIORITIES[$occurrence->suggested_priority] }}</dd></div><div><dt>Prioridade confirmada</dt><dd>{{ $occurrence->confirmed_priority ? \App\Models\Occurrence::PRIORITIES[$occurrence->confirmed_priority] : 'Aguardando triagem' }}</dd></div><div><dt>Registrada em</dt><dd>{{ $occurrence->created_at->format('d/m/Y H:i') }}</dd></div><div><dt>Responsável pela triagem</dt><dd>{{ $occurrence->triageResponsible?->name ?? 'Não definido' }}</dd></div></dl>
                <div class="description-block"><strong>Descrição</strong><p>{{ $occurrence->description }}</p></div>
                @if ($occurrence->triage_note)<div class="description-block"><strong>Observação da triagem</strong><p>{{ $occurrence->triage_note }}</p></div>@endif
                @if ($occurrence->duplicateOf)<div class="duplicate-reference"><strong>Duplicada de:</strong> @can('view', $occurrence->duplicateOf)<a href="{{ route('occurrences.show', $occurrence->duplicateOf) }}">{{ $occurrence->duplicateOf->protocol }}</a>@else{{ $occurrence->duplicateOf->protocol }}@endcan</div>@endif
            </section>

            <section class="card"><div class="section-title"><div><h2>Evidências privadas</h2><p>{{ $occurrence->attachments->count() }} de 20 arquivos · disponíveis somente para usuários autorizados</p></div></div>
                <div class="evidence-list">@forelse ($occurrence->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}"><x-ui.icon name="check" /> {{ $attachment->original_name }} <small>{{ number_format($attachment->size / 1024, 0, ',', '.') }} KB</small></a>@empty<p class="muted-copy">Nenhuma evidência anexada.</p>@endforelse</div>
                @can('addEvidence', $occurrence)
                    @if ($occurrence->attachments->count() < 20)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('occurrences.attachments', $occurrence) }}">@csrf
                            <x-ui.input label="Adicionar evidência" name="evidence" type="file" accept="image/jpeg,image/png,application/pdf" required hint="Imagem JPEG/PNG ou PDF, até 10 MB." />
                            <div class="form-actions"><x-ui.button type="submit">Anexar arquivo privado</x-ui.button></div>
                        </form>
                    @else
                        <div class="alert alert--danger">Esta ocorrência atingiu o limite total de 20 arquivos.</div>
                    @endif
                @endcan
            </section>

            @if ($occurrence->serviceOrders->isNotEmpty() || $occurrence->status === 'ENCAMINHADA')
                <section class="card"><div class="section-title"><div><h2>Ordens de Serviço</h2><p>Atendimentos técnicos vinculados à ocorrência</p></div>@if($occurrence->status === 'ENCAMINHADA')@can('create', \App\Models\ServiceOrder::class)<x-ui.button :href="route('service-orders.create', ['occurrence_id' => $occurrence->id])" variant="primary"><x-ui.icon name="plus" /> Criar OS</x-ui.button>@endcan @endif</div>
                    <div class="linked-orders">@forelse($occurrence->serviceOrders as $order)
                        @can('view', $order)<a href="{{ route('service-orders.show', $order) }}">@else<div class="linked-order-summary">@endcan
                            <span><strong>{{ $order->code }}</strong><small>{{ $order->title }} · {{ $order->assignedUser?->name ?? 'Responsável a definir' }}</small></span><span class="badge badge--primary">{{ \App\Models\ServiceOrder::STATUS_LABELS[$order->status] }}</span>
                        @can('view', $order)</a>@else</div>@endcan
                    @empty<p class="muted-copy">Nenhuma Ordem de Serviço criada.</p>@endforelse</div>
                </section>
            @endif

            <section class="card history-card"><div class="section-title"><div><h2>Histórico</h2><p>Eventos estruturados e imutáveis</p></div></div><div class="history-list">
                @foreach ($occurrence->histories as $history)<div><span class="history-dot"></span><strong>{{ match($history->event_type) { 'created' => 'Ocorrência registrada', 'triage_started' => 'Ocorrência entrou em triagem', 'priority_confirmed' => 'Prioridade confirmada', 'information_requested' => 'Informação adicional solicitada', 'information_provided' => 'Informação adicional fornecida', 'marked_not_applicable' => 'Ocorrência marcada como não procede', 'marked_duplicate' => 'Ocorrência marcada como duplicada', 'forwarded' => 'Ocorrência encaminhada para manutenção', 'closed' => 'Ocorrência encerrada', 'reopen_requested' => 'Reabertura solicitada', 'reopened' => 'Ocorrência reaberta para triagem', default => 'Ocorrência atualizada' } }}</strong><small>{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->actor?->name ?? 'Sistema' }}</small>@if (($history->metadata['message'] ?? null) || ($history->metadata['reason'] ?? null))<p>{{ $history->metadata['message'] ?? $history->metadata['reason'] }}</p>@endif</div>@endforeach
            </div></section>
        </div>

        <aside class="occurrence-actions card"><div class="section-title"><div><h2>Ações</h2><p>Disponíveis conforme estado e permissão</p></div></div>
            @if ($occurrence->status === 'ABERTA') @can('triage', $occurrence)<form method="POST" action="{{ route('occurrences.triage.start', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><x-ui.button variant="primary" type="submit">Iniciar triagem</x-ui.button></form>@endcan @endif
            @if (in_array($occurrence->status, ['ABERTA','EM_TRIAGEM'])) @can('requestInformation', $occurrence)<form method="POST" action="{{ route('occurrences.information.request', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><label class="form-field"><span>Solicitar informação</span><textarea name="message" rows="3" required></textarea></label><x-ui.button type="submit">Enviar solicitação</x-ui.button></form>@endcan @endif
            @if ($occurrence->status === 'AGUARDANDO_INFORMACOES') @can('provideInformation', $occurrence)<form method="POST" action="{{ route('occurrences.information.provide', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><label class="form-field"><span>Informação adicional</span><textarea name="message" rows="4" required></textarea></label><x-ui.button variant="primary" type="submit">Enviar informação</x-ui.button></form>@endcan @endif
            @if ($occurrence->status === 'EM_TRIAGEM') @can('confirmPriority', $occurrence)<form method="POST" action="{{ route('occurrences.priority.confirm', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><x-ui.select label="Prioridade confirmada" name="confirmed_priority" required>@foreach (\App\Models\Occurrence::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected($occurrence->priority() === $value)>{{ $label }}</option>@endforeach</x-ui.select><label class="form-field"><span>Justificativa</span><textarea name="triage_note" rows="3" required>{{ $occurrence->triage_note }}</textarea></label><x-ui.button type="submit">Confirmar prioridade</x-ui.button></form>@endcan @endif
            @if ($occurrence->status === 'EM_TRIAGEM' && $occurrence->confirmed_priority) @can('forward', $occurrence)<form method="POST" action="{{ route('occurrences.forward', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><x-ui.input label="Destino" name="forwarded_destination" value="Equipe de manutenção" required /><x-ui.button variant="primary" type="submit">Encaminhar para manutenção</x-ui.button></form>@endcan @endif
            @if (in_array($occurrence->status, ['ABERTA','EM_TRIAGEM','AGUARDANDO_INFORMACOES']))
                @can('markDuplicate', $occurrence)<form method="POST" action="{{ route('occurrences.duplicate', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><x-ui.select label="Marcar como duplicada de" name="duplicate_of_id" required><option value="">Selecione</option>@foreach ($duplicateCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->protocol }} · {{ $candidate->title }}</option>@endforeach</x-ui.select><x-ui.input label="Motivo opcional" name="reason" /><x-ui.button type="submit">Marcar duplicada</x-ui.button></form>@endcan
                @can('markNotApplicable', $occurrence)<form method="POST" action="{{ route('occurrences.not-applicable', $occurrence) }}">@csrf <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}"><x-ui.input label="Motivo para não proceder" name="reason" required /><x-ui.button type="submit">Marcar como não procede</x-ui.button></form>@endcan
            @endif
            @if($occurrence->status === 'RESOLVIDA')@can('close', $occurrence)<form method="POST" action="{{ route('occurrences.close', $occurrence) }}">@csrf<x-ui.button variant="primary" type="submit">Confirmar encerramento</x-ui.button></form>@endcan @endif
            @if($occurrence->status === 'ENCERRADA')@can('requestReopening', $occurrence)<form method="POST" action="{{ route('occurrences.reopening.request', $occurrence) }}">@csrf<label class="form-field"><span>Relato da recorrência</span><textarea name="reason" rows="4" required></textarea></label><x-ui.button variant="primary" type="submit">Solicitar reabertura</x-ui.button></form>@endcan @endif
            @if(in_array($occurrence->status, ['RESOLVIDA','ENCERRADA']))@can('reopen', $occurrence)<form method="POST" action="{{ route('occurrences.reopen', $occurrence) }}">@csrf<x-ui.input label="Motivo da reabertura" name="reason" required/><x-ui.button type="submit">Reabrir ocorrência</x-ui.button></form>@endcan @endif
            <x-ui.button :href="route('occurrences.index')">Voltar à fila</x-ui.button>
        </aside>
    </div>
</x-layouts.app>
