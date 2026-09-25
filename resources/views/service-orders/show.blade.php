<x-layouts.app :title="$order->code" active="service-orders">
    @php($statusVariant = match($order->status) {'CONCLUIDA' => 'success', 'REJEITADA','CANCELADA' => 'danger', 'AGUARDANDO_APROVACAO','AGUARDANDO_MATERIAL','PAUSADA' => 'warning', default => 'primary'})
    @php($executionStatuses = ['EM_EXECUCAO', 'AGUARDANDO_MATERIAL', 'PAUSADA'])
    <header class="page-header"><div><span class="eyebrow">{{ $order->code }}</span><h1>{{ $order->title }}</h1><p>{{ $order->school->name }} · criada em {{ $order->created_at->format('d/m/Y H:i') }}</p></div><div class="page-header__actions"><span class="priority priority--{{ strtolower($order->priority_snapshot) }}">{{ \App\Models\Occurrence::PRIORITIES[$order->priority_snapshot] }}</span><span class="badge badge--{{ $statusVariant }}">{{ \App\Models\ServiceOrder::STATUS_LABELS[$order->status] }}</span></div></header>

    <div class="service-order-layout service-order-layout--single">
        <div class="service-order-main">
            <section class="card"><div class="section-title"><div><h2>Origem e serviço</h2><p>Contexto herdado da ocorrência</p></div>@can('view', $order->occurrence)<a class="code-pill" href="{{ route('occurrences.show', $order->occurrence) }}">{{ $order->occurrence->protocol }}</a>@else<span class="code-pill">{{ $order->occurrence->protocol }}</span>@endcan</div>
                <dl class="detail-grid"><div><dt>Escola</dt><dd>{{ $order->school->name }}</dd></div><div><dt>Ambiente</dt><dd>{{ $order->occurrence->environment->name }}</dd></div><div><dt>Solicitante</dt><dd>{{ $order->occurrence->reporter->name }}</dd></div><div><dt>Responsável</dt><dd>{{ $order->assignedUser?->name ?? 'A definir' }}</dd></div><div><dt>Prazo</dt><dd>{{ $order->due_date?->format('d/m/Y') ?? 'Sem prazo' }}</dd></div><div><dt>Planejada para</dt><dd>{{ $order->planned_at?->format('d/m/Y') ?? 'Não definida' }}</dd></div></dl>
                <div class="description-block"><strong>Descrição solicitada</strong><p>{{ $order->description }}</p></div>
                @if ($order->notes)<div class="description-block"><strong>Observações</strong><p>{{ $order->notes }}</p></div>@endif
                @if ($order->external_service)
                    <div class="description-block">
                        <strong>Fornecedor externo</strong>
                        <p>{{ $order->external_provider_name }}</p>
                        <p>{{ $order->external_service_description }}</p>
                        @if ($order->external_provider_contact)<p>Contato: {{ $order->external_provider_contact }}</p>@endif
                        @if ($order->external_provider_tax_id)<p>CNPJ/CPF: {{ $order->external_provider_tax_id }}</p>@endif
                        <p>Responsável interno: {{ $order->assignedUser?->name ?? 'Não definido' }}</p>
                    </div>
                @endif
                <div class="diagnosis-grid"><div><strong>Diagnóstico</strong><p>{{ $order->diagnosis ?: 'Ainda não registrado.' }}</p></div><div><strong>Solução aplicada</strong><p>{{ $order->solution ?: 'Ainda não concluída.' }}</p></div></div>
                @if($order->emergency_authorized_at)
                    <div class="description-block">
                        <strong>Autorização emergencial</strong>
                        <p>Autorizada por {{ $order->emergencyAuthorizer?->name ?? 'Usuário indisponível' }} em {{ $order->emergency_authorized_at->format('d/m/Y H:i') }}.</p>
                        <p>Motivo: {{ $order->emergency_reason }}</p>
                        <p>Prazo para ratificação: {{ $order->emergency_ratification_due_at?->format('d/m/Y H:i') }}.</p>
                        @if($order->emergency_ratified_at)
                            <p>Ratificada por {{ $order->emergencyRatifier?->name ?? 'Usuário indisponível' }} em {{ $order->emergency_ratified_at->format('d/m/Y H:i') }}.</p>
                        @elseif($order->emergencyRatificationIsOverdue())
                            <p><strong>Ratificação vencida.</strong> A conclusão permanecerá bloqueada até a ratificação.</p>
                        @else
                            <p>Ratificação pendente.</p>
                        @endif
                    </div>
                @endif
            </section>

            <section class="service-order-actions card"><div class="section-title"><div><h2>Ações</h2><p>Conforme estado e permissão</p></div></div>
                <div class="service-order-actions__content">
                <div class="action-group-label">Ação principal</div>
                @if($order->status === 'AGUARDANDO_APROVACAO')@can('approve',$order)<form method="POST" action="{{ route('service-orders.approve',$order) }}">@csrf<x-ui.button variant="primary" type="submit">Aprovar OS</x-ui.button></form>@endcan @endif
                @if($order->status === 'APROVADA')@can('start',$order)<form method="POST" action="{{ route('service-orders.start',$order) }}">@csrf<x-ui.button variant="primary" type="submit">Iniciar execução</x-ui.button></form>@endcan @endif
                @if(in_array($order->status,['AGUARDANDO_MATERIAL','PAUSADA']))@can('start',$order)<form method="POST" action="{{ route('service-orders.resume',$order) }}">@csrf<x-ui.button variant="primary" type="submit">Retomar execução</x-ui.button></form>@endcan @endif
                @if($order->hasPendingEmergencyRatification())@can('ratify',$order)@if(auth()->id() !== $order->created_by)<form method="POST" action="{{ route('service-orders.ratify-emergency',$order) }}">@csrf<x-ui.button variant="primary" type="submit">Ratificar emergência</x-ui.button></form>@endif @endcan @endif
                @if($order->status === 'EM_EXECUCAO' && blank($order->diagnosis))@can('diagnose',$order)<form class="completion-form diagnosis-step-form" method="POST" action="{{ route('service-orders.diagnosis',$order) }}">@csrf
                    <div class="completion-form__header"><strong>Registrar diagnóstico técnico</strong><small>Identifique a causa do problema antes de informar a solução e concluir a OS.</small></div>
                    <label class="form-field" for="primary-diagnosis"><span>Diagnóstico do problema *</span><textarea id="primary-diagnosis" name="diagnosis" required aria-describedby="primary-diagnosis-help @error('diagnosis') primary-diagnosis-error @enderror">{{ old('diagnosis') }}</textarea><small id="primary-diagnosis-help">Depois de salvar, a conclusão será liberada como próximo passo.</small>@error('diagnosis')<small class="form-error" id="primary-diagnosis-error">{{ $message }}</small>@enderror</label>
                    <x-ui.button variant="primary" type="submit">Salvar diagnóstico e continuar</x-ui.button>
                </form>@endcan @endif
                @if($order->status === 'EM_EXECUCAO' && filled($order->diagnosis))@can('complete',$order)<form class="completion-form" method="POST" action="{{ route('service-orders.complete',$order) }}">@csrf
                    <div class="completion-form__header"><strong>Concluir Ordem de Serviço</strong><small>O diagnóstico já foi registrado. Informe somente a solução aplicada para finalizar.</small></div>
                    <label class="form-field" for="completion-solution"><span>Solução aplicada *</span><textarea id="completion-solution" name="solution" required @error('solution') aria-describedby="completion-solution-error" @enderror>{{ old('solution') }}</textarea>@error('solution')<small class="form-error" id="completion-solution-error">{{ $message }}</small>@enderror</label>
                    <x-ui.button variant="primary" type="submit">Concluir OS</x-ui.button>
                </form>@endcan @endif

                <div class="action-group-label">Outras ações</div>
                @can('assign',$order)<details class="action-details"><summary>Alterar responsável</summary><form method="POST" action="{{ route('service-orders.team.update',$order) }}">@csrf @method('PATCH')<label class="form-field"><span>Responsável</span><select name="assigned_user_id"><option value="">A definir</option>@foreach($teamUsers as $teamUser)<option value="{{ $teamUser->id }}" @selected((int) old('assigned_user_id',$order->assigned_user_id)===$teamUser->id)>{{ $teamUser->name }}</option>@endforeach</select></label><x-ui.button type="submit">Salvar responsável</x-ui.button></form></details>@endcan
                @if($order->status === 'AGUARDANDO_APROVACAO')@can('reject',$order)<details class="action-details action-details--danger"><summary>Rejeitar Ordem de Serviço</summary><form method="POST" action="{{ route('service-orders.reject',$order) }}">@csrf<x-ui.input label="Motivo da rejeição" name="reason" required/><x-ui.button variant="danger" type="submit">Confirmar rejeição</x-ui.button></form></details>@endcan @endif
                @if($order->status === 'AGUARDANDO_APROVACAO' && $order->priority_snapshot === 'URGENT')@can('emergency',$order)<details class="action-details action-details--danger"><summary>Iniciar atendimento emergencial</summary><form method="POST" action="{{ route('service-orders.emergency',$order) }}">@csrf<x-ui.input label="Motivo da emergência" name="reason" required/><x-ui.button variant="danger" type="submit">Confirmar início emergencial</x-ui.button></form></details>@endcan @endif
                @if($order->status === 'EM_EXECUCAO')@can('pause',$order)<details class="action-details action-details--workflow"><summary>Pausar ou aguardar material</summary><div class="action-details__content"><form method="POST" action="{{ route('service-orders.wait-material',$order) }}">@csrf<x-ui.input label="Impedimento / material" name="reason" required/><x-ui.button type="submit">Aguardar material</x-ui.button></form><form method="POST" action="{{ route('service-orders.pause',$order) }}">@csrf<x-ui.input label="Motivo da pausa" name="reason" required/><x-ui.button type="submit">Pausar execução</x-ui.button></form></div></details>@endcan @endif
                @if(in_array($order->status, $executionStatuses, true))
                    @if($order->status !== 'EM_EXECUCAO' || filled($order->diagnosis))
                        @can('diagnose',$order)<details class="action-details"><summary>{{ filled($order->diagnosis) ? 'Revisar diagnóstico' : 'Registrar diagnóstico' }}</summary><form method="POST" action="{{ route('service-orders.diagnosis',$order) }}">@csrf<label class="form-field"><span>Diagnóstico</span><textarea name="diagnosis" required>{{ $order->diagnosis }}</textarea></label><x-ui.button type="submit">Salvar diagnóstico</x-ui.button></form></details>@endcan
                    @endif
                    @can('update',$order)<details class="action-details"><summary>Adicionar atualização</summary><form method="POST" action="{{ route('service-orders.updates',$order) }}">@csrf<label class="form-field"><span>Atualização</span><textarea name="message" required></textarea></label><x-ui.button type="submit">Registrar</x-ui.button></form></details>@endcan
                    @can('material',$order)<details class="action-details"><summary>Registrar material</summary><form method="POST" action="{{ route('service-orders.materials',$order) }}">@csrf<x-ui.input label="Material" name="description" required/><div class="inline-fields"><x-ui.input label="Quantidade" name="quantity" type="number" step="0.001" min="0.001" required/><x-ui.input label="Unidade" name="unit" value="un." required/></div><x-ui.input label="Valor unitário (R$)" name="unit_cost" type="number" step="0.01" min="0" required/><x-ui.button type="submit">Adicionar material</x-ui.button></form></details>@endcan
                    @can('time',$order)<details class="action-details"><summary>Registrar tempo</summary><form method="POST" action="{{ route('service-orders.work-logs',$order) }}">@csrf<x-ui.input label="Início" name="started_at" type="datetime-local" required/><x-ui.input label="Fim" name="ended_at" type="datetime-local" required/><x-ui.input label="Descrição" name="description" required/><x-ui.button type="submit">Registrar tempo</x-ui.button></form></details>@endcan
                    @can('cost',$order)<details class="action-details"><summary>Registrar custo</summary><form method="POST" action="{{ route('service-orders.costs',$order) }}">@csrf<x-ui.select label="Tipo" name="type" required><option value="EXTERNAL_SERVICE">Serviço externo</option><option value="OTHER">Outro custo</option></x-ui.select><x-ui.input label="Descrição" name="description" required/><x-ui.input label="Valor (R$)" name="amount" type="number" step="0.01" min="0" required/><x-ui.button type="submit">Registrar custo</x-ui.button></form></details>@endcan
                    @can('update',$order)<details class="action-details"><summary>Anexar evidência</summary><form method="POST" enctype="multipart/form-data" action="{{ route('service-orders.attachments',$order) }}">@csrf<x-ui.input label="Foto ou PDF" name="evidence" type="file" accept="image/jpeg,image/png,application/pdf" required/><x-ui.button type="submit">Anexar arquivo privado</x-ui.button></form></details>@endcan
                @endif
                @if(!in_array($order->status,['CONCLUIDA','REJEITADA','CANCELADA']))@can('cancel',$order)<details class="action-details action-details--danger"><summary>Cancelar Ordem de Serviço</summary><form method="POST" action="{{ route('service-orders.cancel',$order) }}">@csrf<x-ui.input label="Motivo" name="reason" required/><x-ui.button variant="danger" type="submit">Confirmar cancelamento</x-ui.button></form></details>@endcan @endif
                <x-ui.button :href="route('service-orders.index')">Voltar à fila</x-ui.button>
                </div>
            </section>

            <section class="card"><div class="section-title"><div><h2>Execução</h2><p>Tempos do atendimento, materiais e evidências privadas</p></div></div>
                <div class="service-time-grid">
                    <div><span>Tempo decorrido da solicitação</span><strong>{{ \App\Support\DurationFormatter::minutes($order->requestElapsedMinutes()) }}</strong><small>{{ $order->completed_at ? 'Da abertura da ocorrência à conclusão da OS' : 'Desde a abertura da ocorrência até agora' }}</small></div>
                    <div><span>Tempo efetivamente trabalhado</span><strong>{{ \App\Support\DurationFormatter::minutes($order->workedMinutes()) }}</strong><small>Soma dos períodos registrados pela equipe</small></div>
                </div>
                <div class="execution-columns"><div><h3>Registros de trabalho</h3>@forelse ($order->workLogs as $log)<div class="compact-entry"><strong>{{ $log->description }}</strong><small>{{ $log->started_at->format('d/m H:i') }} → {{ $log->ended_at->format('d/m H:i') }} · {{ \App\Support\DurationFormatter::minutes($log->duration_minutes) }}</small></div>@empty<p class="muted-copy">Nenhum período de trabalho registrado.</p>@endforelse</div>
                    <div><h3>Materiais</h3>@forelse ($order->materials as $material)<div class="compact-entry"><strong>{{ $material->description }} · {{ $material->quantity }} {{ $material->unit }}</strong><small>{{ \App\Support\BrazilianCurrency::format($material->unit_cost) }} por unidade · {{ \App\Support\BrazilianCurrency::format($material->total_cost) }}</small></div>@empty<p class="muted-copy">Nenhum material registrado.</p>@endforelse</div>
                </div>
                <div class="evidence-list"><h3>Evidências</h3>@forelse ($order->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}"><x-ui.icon name="check" /> {{ $attachment->original_name }} <small>{{ number_format($attachment->size / 1024, 0, ',', '.') }} KB</small></a>@empty<p class="muted-copy">Nenhuma evidência anexada.</p>@endforelse</div>
            </section>

            <section class="card"><div class="section-title"><div><h2>Financeiro</h2><p>Valores registrados na Ordem de Serviço</p></div></div><div class="finance-grid"><div><span>Estimado</span><strong>{{ \App\Support\BrazilianCurrency::format($order->estimated_cost) }}</strong></div><div><span>Materiais</span><strong>{{ \App\Support\BrazilianCurrency::format($order->materialTotal()) }}</strong></div><div><span>Serviço externo e outros</span><strong>{{ \App\Support\BrazilianCurrency::format($order->additionalCostTotal()) }}</strong></div><div class="finance-total"><span>Total registrado</span><strong>{{ \App\Support\BrazilianCurrency::format($order->totalCost()) }}</strong></div></div>
                @foreach ($order->costs as $cost)<div class="compact-entry"><strong>{{ $cost->description }}</strong><small>{{ $cost->type === 'EXTERNAL_SERVICE' ? 'Serviço externo' : 'Outro custo' }} · {{ \App\Support\BrazilianCurrency::format($cost->amount) }}</small></div>@endforeach
            </section>

            <section class="card history-card"><div class="section-title"><div><h2>Histórico</h2><p>Timeline imutável da OS</p></div></div><div class="history-list">@foreach ($order->histories as $history)<div><span class="history-dot"></span><strong>{{ match($history->event_type) {'created' => 'Ordem de Serviço criada', 'team_changed' => 'Responsável ou equipe alterados', 'approved' => 'OS aprovada', 'rejected' => 'OS rejeitada', 'cancelled' => 'OS cancelada', 'started' => 'Execução iniciada', 'emergency_started' => 'Atendimento emergencial iniciado', 'emergency_ratified' => 'Atendimento emergencial ratificado', 'waiting_material' => 'Aguardando material', 'paused' => 'Execução pausada', 'resumed' => 'Execução retomada', 'diagnosis_registered' => 'Diagnóstico registrado', 'update_added' => 'Atualização adicionada', 'material_registered' => 'Material registrado', 'work_logged' => 'Tempo trabalhado registrado', 'cost_registered' => 'Custo registrado', 'attachment_added' => 'Evidência anexada', 'completed' => 'Ordem de Serviço concluída', default => 'Ordem de Serviço atualizada'} }}</strong><small>{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->actor?->name ?? 'Sistema' }}</small>@if($history->metadata['message'] ?? null)<p>{{ $history->metadata['message'] }}</p>@endif @if($history->metadata['reason'] ?? null)<p>{{ $history->metadata['reason'] }}</p>@endif</div>@endforeach</div></section>
        </div>


    </div>
</x-layouts.app>
