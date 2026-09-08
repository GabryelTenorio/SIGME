<x-layouts.app title="Editar ambiente" active="environments">
    <header class="page-header"><div><h1>Editar ambiente</h1><p>{{ $environment->code }} · {{ $environment->name }}</p></div></header>
    <form method="POST" action="{{ route('environments.update', $environment) }}" class="form-card">@csrf @method('PUT') @include('environments._form')</form>

    <section class="card history-card">
        <div class="section-title"><div><h2>Histórico</h2><p>Alterações preservadas para rastreabilidade.</p></div></div>
        <div class="history-list">
            @forelse ($environment->histories as $history)
                <div><span class="history-dot"></span><strong>{{ match($history->action) { 'created' => 'Ambiente criado', 'deactivated' => 'Ambiente desativado', 'reactivated' => 'Ambiente reativado', default => 'Cadastro atualizado' } }}</strong><small>{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->user?->name ?? 'Sistema' }}</small></div>
            @empty <p class="empty-state">Nenhum registro de histórico.</p> @endforelse
        </div>
    </section>
</x-layouts.app>
