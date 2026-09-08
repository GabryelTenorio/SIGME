<x-layouts.app title="Organizações" active="organizations">
    <header class="page-header">
        <div><h1>Organizações</h1><p>Estruturas independentes e redes de escolas cadastradas no SIGME.</p></div>
        <div class="page-header__actions">
            <x-ui.button :href="route('organizations.create')" variant="primary"><x-ui.icon name="plus" /> Nova organização</x-ui.button>
        </div>
    </header>

    <section class="stat-grid" aria-label="Resumo das organizações">
        <article class="stat-card"><span>Organizações</span><strong>{{ $organizations->count() }}</strong><small>Total cadastrado</small><span class="stat-card__icon"><x-ui.icon name="organization" /></span></article>
        <article class="stat-card"><span>Escolas</span><strong>{{ $organizations->sum('schools_count') }}</strong><small>Unidades vinculadas</small><span class="stat-card__icon"><x-ui.icon name="school" /></span></article>
        <article class="stat-card"><span>Redes</span><strong>{{ $organizations->where('mode', 'network')->count() }}</strong><small>Com múltiplas escolas</small><span class="stat-card__icon"><x-ui.icon name="dashboard" /></span></article>
        <article class="stat-card"><span>Ativas</span><strong>{{ $organizations->where('is_active', true)->count() }}</strong><small>Estruturas habilitadas</small><span class="stat-card__icon"><x-ui.icon name="check" /></span></article>
    </section>

    <section class="table-card">
        <table class="data-table">
            <thead><tr><th>Organização</th><th>Modelo</th><th>Escolas</th><th>Usuários</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td><strong>{{ $organization->name }}</strong><small>{{ $organization->slug }}</small></td>
                        <td><span class="badge badge--primary">{{ $organization->mode === 'network' ? 'Rede de escolas' : 'Escola independente' }}</span></td>
                        <td>{{ $organization->schools_count }}</td>
                        <td>{{ $organization->users_count }}</td>
                        <td><span class="badge {{ $organization->is_active ? 'badge--success' : 'badge--neutral' }}">{{ $organization->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                        <td><div class="table-actions"><a class="icon-button" href="{{ route('organizations.edit', $organization) }}" aria-label="Editar {{ $organization->name }}"><x-ui.icon name="edit" /></a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Nenhuma organização cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
