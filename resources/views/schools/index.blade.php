<x-layouts.app title="Escolas" active="schools">
    <header class="page-header">
        <div><h1>Escolas</h1><p>Unidades válidas dentro das organizações do SIGME.</p></div>
        <div class="page-header__actions"><x-ui.button :href="route('schools.create')" variant="primary"><x-ui.icon name="plus" /> Nova escola</x-ui.button></div>
    </header>

    @if (auth()->user()->is_platform_admin && $organizations->count() > 1)
        <form method="GET" class="filter-bar">
            <select name="organization_id" data-auto-submit>
                <option value="">Todas as organizações</option>
                @foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected(request('organization_id') == $organization->id)>{{ $organization->name }}</option>@endforeach
            </select>
        </form>
    @endif

    <section class="table-card">
        <table class="data-table">
            <thead><tr><th>Escola</th><th>Organização</th><th>Código</th><th>Usuários</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                @forelse ($schools as $school)
                    <tr>
                        <td><strong>{{ $school->name }}</strong><small>Unidade escolar</small></td>
                        <td>{{ $school->organization->name }}</td>
                        <td>{{ $school->code }}</td>
                        <td>{{ $school->users_count }}</td>
                        <td><span class="badge {{ $school->is_active ? 'badge--success' : 'badge--neutral' }}">{{ $school->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                        <td><div class="table-actions"><a class="icon-button" href="{{ route('schools.edit', $school) }}" aria-label="Editar {{ $school->name }}"><x-ui.icon name="edit" /></a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Nenhuma escola disponível.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
