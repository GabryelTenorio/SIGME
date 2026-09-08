<x-layouts.app title="Categorias" active="categories">
    <header class="page-header">
        <div><h1>Categorias de ocorrência</h1><p>Classificação padronizada por organização e disponibilidade por escola.</p></div>
        @if ($canCreateAny)<div class="page-header__actions"><x-ui.button :href="route('categories.create', ['organization_id' => $selectedOrganizationId])" variant="primary"><x-ui.icon name="plus" /> Nova categoria</x-ui.button></div>@endif
    </header>

    <form method="GET" class="filter-bar">
        @if (auth()->user()->is_platform_admin && $organizations->count() > 1)
            <select name="organization_id" onchange="this.form.submit()" aria-label="Filtrar por organização"><option value="">Todas as organizações</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedOrganizationId === $organization->id)>{{ $organization->name }}</option>@endforeach</select>
        @endif
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar categoria">
        <select name="status" aria-label="Filtrar por situação"><option value="">Todas as situações</option><option value="active" @selected(request('status') === 'active')>Ativas</option><option value="inactive" @selected(request('status') === 'inactive')>Inativas</option></select>
        <x-ui.button type="submit">Filtrar</x-ui.button>
    </form>

    <section class="table-card">
        <table class="data-table">
            <thead><tr><th>Ordem</th><th>Categoria</th><th>Organização</th><th>Disponibilidade</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->display_order }}</td>
                        <td><strong>{{ $category->name }}</strong><small>{{ $category->identifier }}@if ($category->is_fallback) · fallback @endif</small></td>
                        <td>{{ $category->organization->name }}</td>
                        <td>{{ $category->organization->mode === 'single_school' ? 'Escola única' : $category->schools->count().' de '.$category->organization->schools_count.' escolas' }}</td>
                        <td><span class="badge {{ $category->is_active ? 'badge--success' : 'badge--neutral' }}">{{ $category->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                        <td><div class="table-actions">
                            @can('update', $category)<a class="icon-button" href="{{ route('categories.edit', $category) }}" aria-label="Editar {{ $category->name }}"><x-ui.icon name="edit" /></a>@endcan
                            @if ($category->organization->mode === 'network') @can('manageAvailability', $category)<a class="icon-button" href="{{ route('categories.availability', $category) }}" aria-label="Disponibilidade de {{ $category->name }}"><x-ui.icon name="school" /></a>@endcan @endif
                            @if ($category->is_active && ! $category->is_fallback && auth()->user()->can('deactivate', $category))<form method="POST" action="{{ route('categories.deactivate', $category) }}" onsubmit="return confirm('Desativar esta categoria? Os registros antigos serão preservados.')">@csrf @method('PATCH')<button type="submit" class="icon-button" aria-label="Desativar {{ $category->name }}"><x-ui.icon name="logout" /></button></form>@endif
                        </div></td>
                    </tr>
                @empty <tr><td colspan="6" class="empty-state">Nenhuma categoria encontrada.</td></tr> @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
