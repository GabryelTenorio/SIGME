<x-layouts.app title="Relatórios gerenciais" active="reports">
    <header class="page-header">
        <div>
            <span class="eyebrow">Gestão baseada em dados</span>
            <h1>Relatórios gerenciais</h1>
            <p>Indicadores operacionais, prazos e custos dentro do seu escopo de acesso.</p>
        </div>
        <div class="page-header__actions report-export-actions">
            <x-ui.button :href="route('reports.occurrences.export', request()->query())">Exportar ocorrências</x-ui.button>
            <x-ui.button :href="route('reports.service-orders.export', request()->query())" variant="primary">Exportar OS</x-ui.button>
        </div>
    </header>

    <section class="card report-filters" aria-label="Filtros do relatório">
        <form class="filter-bar filter-bar--grid" method="GET" action="{{ route('reports.index') }}">
            <label class="filter-field"><span>Escola</span><select name="school_id"><option value="">Todas as escolas</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) request('school_id') === $school->id)>{{ $school->name }}</option>@endforeach</select></label>
            <label class="filter-field"><span>Data inicial</span><input type="date" name="date_from" value="{{ request('date_from', $dateFrom->toDateString()) }}"></label>
            <label class="filter-field"><span>Data final</span><input type="date" name="date_to" value="{{ request('date_to', $dateTo->toDateString()) }}"></label>
            <x-ui.button variant="primary" type="submit">Atualizar indicadores</x-ui.button>
        </form>
    </section>

    <section class="stat-grid report-stat-grid" aria-label="Indicadores principais">
        @foreach($metrics as $metric)
            <article class="stat-card">
                <span>{{ $metric['label'] }}</span>
                <strong>{{ ($metric['currency'] ?? false) ? \App\Support\BrazilianCurrency::format((string) $metric['value']) : $metric['value'] }}</strong>
                <small>{{ $metric['hint'] }}</small>
                <span class="stat-card__icon"><x-ui.icon :name="$metric['icon']" /></span>
            </article>
        @endforeach
    </section>

    <div class="report-grid">
        <section class="card report-panel">
            <div class="section-title"><div><h2>Ocorrências por estado</h2><p>Distribuição no período selecionado</p></div></div>
            <div class="metric-bars">
                @forelse($occurrenceStatuses as $item)
                    <div class="metric-bar"><div><span>{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div><div class="metric-bar__track"><span style="width: {{ $item['percentage'] }}%"></span></div></div>
                @empty<p class="empty-state">Nenhuma ocorrência no período.</p>@endforelse
            </div>
        </section>

        <section class="card report-panel">
            <div class="section-title"><div><h2>Ordens de Serviço por estado</h2><p>Visão do andamento da manutenção</p></div></div>
            <div class="metric-bars">
                @forelse($orderStatuses as $item)
                    <div class="metric-bar"><div><span>{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div><div class="metric-bar__track"><span style="width: {{ $item['percentage'] }}%"></span></div></div>
                @empty<p class="empty-state">Nenhuma Ordem de Serviço no período.</p>@endforelse
            </div>
        </section>

        <section class="card report-panel">
            <div class="section-title"><div><h2>Prioridades</h2><p>Concentração das demandas</p></div></div>
            <div class="metric-bars">
                @forelse($priorities as $item)
                    <div class="metric-bar"><div><span>{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div><div class="metric-bar__track"><span style="width: {{ $item['percentage'] }}%"></span></div></div>
                @empty<p class="empty-state">Nenhuma prioridade para exibir.</p>@endforelse
            </div>
        </section>

        <section class="card report-panel">
            <div class="section-title"><div><h2>Categorias mais frequentes</h2><p>Principais tipos de ocorrência</p></div></div>
            <div class="report-ranking">
                @forelse($topCategories as $index => $item)
                    <div><span>{{ $index + 1 }}</span><strong>{{ $item['label'] }}</strong><small>{{ $item['value'] }} ocorrência{{ $item['value'] === 1 ? '' : 's' }}</small></div>
                @empty<p class="empty-state">Nenhuma categoria para exibir.</p>@endforelse
            </div>
        </section>
    </div>

    <section class="card report-recent">
        <div class="section-title"><div><h2>Ocorrências recentes</h2><p>Últimos registros incluídos no período</p></div><x-ui.button :href="route('occurrences.index')">Ver todas</x-ui.button></div>
        <div class="table-wrap"><table><thead><tr><th>Protocolo</th><th>Ocorrência</th><th>Escola</th><th>Categoria</th><th>Prioridade</th><th>Estado</th></tr></thead><tbody>
            @forelse($occurrences->take(8) as $occurrence)
                <tr><td><a href="{{ route('occurrences.show', $occurrence) }}">{{ $occurrence->protocol }}</a></td><td>{{ $occurrence->title }}</td><td>{{ $occurrence->school->name }}</td><td>{{ $occurrence->category->name }}</td><td>{{ \App\Models\Occurrence::PRIORITIES[$occurrence->priority()] }}</td><td>{{ \App\Models\Occurrence::STATUS_LABELS[$occurrence->status] }}</td></tr>
            @empty<tr><td colspan="6">Nenhuma ocorrência no período.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
