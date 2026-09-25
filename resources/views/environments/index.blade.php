<x-layouts.app title="Ambientes" active="environments">
    <header class="page-header">
        <div><h1>Ambientes</h1><p>Locais válidos das escolas para o registro de ocorrências.</p></div>
        @if ($schools->contains(fn ($school) => auth()->user()->hasPermission('ambientes.criar', $school) || auth()->user()->hasPermission('ambientes.gerenciar', $school)))
            @can('create', \App\Models\Environment::class)
                <div class="page-header__actions"><x-ui.button :href="route('environments.create', ['school_id' => $selectedSchoolId])" variant="primary"><x-ui.icon name="plus" /> Novo ambiente</x-ui.button></div>
            @endcan
        @endif
    </header>

    <form method="GET" class="filter-bar">
        @if ($schools->count() > 1)
            <select name="school_id" aria-label="Filtrar por escola" data-auto-submit>
                <option value="">Todas as escolas</option>
                @foreach ($schools as $school)<option value="{{ $school->id }}" @selected($selectedSchoolId === $school->id)>{{ $school->name }}</option>@endforeach
            </select>
        @endif
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar por nome ou código">
        <select name="type" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
            @foreach (\App\Models\Environment::TYPES as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach
        </select>
        <select name="status" aria-label="Filtrar por situação">
            <option value="">Todas as situações</option><option value="active" @selected(request('status') === 'active')>Ativos</option><option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </select>
        <x-ui.button type="submit">Filtrar</x-ui.button>
    </form>

    <section class="table-card">
        <table class="data-table">
            <thead><tr><th>Escola</th><th>Código</th><th>Nome</th><th>Tipo</th><th>Localização</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
                @forelse ($environments as $environment)
                    <tr>
                        <td><strong>{{ $environment->school->name }}</strong><small>{{ $environment->school->organization->name }}</small></td>
                        <td><span class="code-pill">{{ $environment->code }}</span></td>
                        <td><strong>{{ $environment->name }}</strong>@if ($environment->children_count)<small>{{ $environment->children_count }} subambiente(s)</small>@endif</td>
                        <td>{{ $environment->typeLabel() }}</td>
                        <td>{{ $environment->locationLabel() }}</td>
                        <td><span class="badge {{ $environment->is_active ? 'badge--success' : 'badge--neutral' }}">{{ $environment->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td><div class="table-actions">
                            @can('update', $environment)<a class="icon-button" href="{{ route('environments.edit', $environment) }}" aria-label="Editar {{ $environment->name }}"><x-ui.icon name="edit" /></a>@endcan
                            @if ($environment->is_active && auth()->user()->can('deactivate', $environment))
                                <form method="POST" action="{{ route('environments.deactivate', $environment) }}" data-confirm="Desativar este ambiente? O histórico será preservado.">@csrf @method('PATCH')<button class="icon-button" type="submit" aria-label="Desativar {{ $environment->name }}"><x-ui.icon name="logout" /></button></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Nenhum ambiente encontrado neste contexto.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
