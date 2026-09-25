<x-layouts.app title="Usuários" active="users">
    <header class="page-header">
        <div><h1>Usuários</h1><p>Contas, escolas e perfis acumuláveis dentro do escopo autorizado.</p></div>
        @can('create', \App\Models\User::class)
            <div class="page-header__actions"><x-ui.button :href="route('users.create', request()->only('organization_id'))" variant="primary"><x-ui.icon name="plus" /> Novo usuário</x-ui.button></div>
        @endcan
    </header>

    <form method="GET" class="filter-bar">
        <input name="search" value="{{ request('search') }}" placeholder="Buscar por nome ou e-mail">
        @if (auth()->user()->is_platform_admin)
            <select name="organization_id" data-auto-submit>
                <option value="">Todas as organizações</option>
                @foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected(request('organization_id') == $organization->id)>{{ $organization->name }}</option>@endforeach
            </select>
        @endif
        <x-ui.button type="submit">Buscar</x-ui.button>
    </form>

    <section class="table-card">
        <table class="data-table">
            <thead><tr><th>Usuário</th><th>Organização</th><th>Escolas</th><th>Perfis</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                @forelse ($users as $managedUser)
                    <tr>
                        <td>
                            <div class="table-user">
                                <span class="avatar">{{ str($managedUser->name)->substr(0, 1)->upper() }}</span>
                                <span><strong>{{ $managedUser->name }}</strong><small>{{ $managedUser->email }}</small></span>
                            </div>
                        </td>
                        <td>{{ $managedUser->organization?->name }}</td>
                        <td><strong>{{ $managedUser->schools->count() }}</strong><small>{{ $managedUser->schools->pluck('name')->take(2)->join(', ') ?: 'Escopo da organização' }}</small></td>
                        <td><span class="badge badge--primary">{{ $managedUser->roles->pluck('name')->unique()->join(' · ') ?: 'Sem perfil' }}</span></td>
                        <td>
                            @if (! $managedUser->is_active)
                                <span class="badge badge--neutral">Inativo</span>
                            @elseif ($managedUser->requiresFirstAccess())
                                <span class="badge badge--warning">Aguardando primeira senha</span>
                            @else
                                <span class="badge badge--success">Ativo</span>
                            @endif
                        </td>
                        <td><div class="table-actions"><a class="icon-button" href="{{ route('users.edit', $managedUser) }}" aria-label="Editar {{ $managedUser->name }}"><x-ui.icon name="edit" /></a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Nenhum usuário encontrado neste escopo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
