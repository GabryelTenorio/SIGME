<x-layouts.app title="Ordens de Serviço" active="service-orders">
    <header class="page-header">
        <div><h1>Ordens de Serviço</h1><p>Planejamento, aprovação e execução da manutenção escolar.</p></div>
    </header>

    <section class="stat-grid" aria-label="Resumo das ordens de serviço">
        @foreach ([['label' => 'Total abertas', 'value' => $stats['open'], 'hint' => 'Ordens ainda operacionais'], ['label' => 'Em execução', 'value' => $stats['running'], 'hint' => 'Atendimento em andamento'], ['label' => 'Aguardando material', 'value' => $stats['material'], 'hint' => 'Execução temporariamente impedida'], ['label' => 'Atrasadas', 'value' => $stats['late'], 'hint' => 'Prazo previsto vencido']] as $stat)
            <article class="stat-card"><span>{{ $stat['label'] }}</span><strong>{{ $stat['value'] }}</strong><small>{{ $stat['hint'] }}</small><span class="stat-card__icon"><x-ui.icon name="service-order" /></span></article>
        @endforeach
    </section>

    <form method="GET" class="filter-bar filter-bar--wrap">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="OS, ocorrência ou serviço">
        @if ($schools->count() > 1)<select name="school_id" aria-label="Escola"><option value="">Todas as escolas</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(request('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</select>@endif
        <select name="status" aria-label="Status"><option value="">Todos os status</option>@foreach (\App\Models\ServiceOrder::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="priority" aria-label="Prioridade"><option value="">Todas as prioridades</option>@foreach (\App\Models\Occurrence::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="assigned_user_id" aria-label="Técnico"><option value="">Todos os responsáveis</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected(request('assigned_user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Data inicial"><input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Data final">
        <label class="filter-check"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Somente atrasadas</label>
        <x-ui.button type="submit">Filtrar</x-ui.button>
    </form>

    <section class="table-card"><table class="data-table service-order-table"><thead><tr><th>OS</th><th>Ocorrência</th><th>Serviço</th><th>Ambiente</th><th>Prioridade</th><th>Responsável</th><th>Status</th><th>Prazo</th><th>Custo</th></tr></thead><tbody>
        @forelse ($orders as $order)<tr class="clickable-row" onclick="window.location='{{ route('service-orders.show', $order) }}'">
            <td><strong>{{ $order->code }}</strong><small>{{ $order->school->name }}</small></td><td>{{ $order->occurrence->protocol }}</td><td><strong>{{ $order->title }}</strong></td><td>{{ $order->occurrence->environment->name }}</td><td><span class="priority priority--{{ strtolower($order->priority_snapshot) }}">{{ \App\Models\Occurrence::PRIORITIES[$order->priority_snapshot] }}</span></td><td>{{ $order->assignedUser?->name ?? 'A definir' }}</td><td><span class="badge badge--{{ match($order->status) {'CONCLUIDA' => 'success', 'REJEITADA','CANCELADA' => 'danger', 'AGUARDANDO_APROVACAO','AGUARDANDO_MATERIAL','PAUSADA' => 'warning', default => 'primary'} }}">{{ \App\Models\ServiceOrder::STATUS_LABELS[$order->status] }}</span></td><td>{{ $order->due_date?->format('d/m/Y') ?? 'Sem prazo' }}</td><td>{{ \App\Support\BrazilianCurrency::format($order->totalCost()) }}</td>
        </tr>@empty<tr><td colspan="9" class="empty-state">Nenhuma Ordem de Serviço encontrada.</td></tr>@endforelse
    </tbody></table></section>
</x-layouts.app>
