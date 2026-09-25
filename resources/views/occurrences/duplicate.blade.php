<x-layouts.app title="Selecionar ocorrência duplicada" active="occurrences">
    @php($occurrenceVersion = $occurrence->concurrencyToken())
    <header class="page-header">
        <div>
            <span class="eyebrow">{{ $occurrence->protocol }}</span>
            <h1>Selecionar ocorrência principal</h1>
            <p>Busque e compare ocorrências da mesma escola antes de marcar a duplicidade.</p>
        </div>
        <div class="page-header__actions"><x-ui.button :href="route('occurrences.show', $occurrence)">Voltar à ocorrência</x-ui.button></div>
    </header>

    <section class="card duplicate-source">
        <div><span>Ocorrência que será marcada como duplicada</span><strong>{{ $occurrence->title }}</strong></div>
        <div><span>Escola</span><strong>{{ $occurrence->school->name }}</strong></div>
        <div><span>Ambiente</span><strong>{{ $occurrence->environment->code }} · {{ $occurrence->environment->name }}</strong></div>
    </section>

    <form method="GET" class="filter-bar duplicate-search">
        <label class="filter-field">
            <span>Buscar ocorrência principal</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Protocolo, título, ambiente ou categoria" autofocus>
        </label>
        <x-ui.button type="submit" variant="primary">Buscar</x-ui.button>
        @if (request()->filled('search'))<x-ui.button :href="route('occurrences.duplicate.select', $occurrence)">Limpar busca</x-ui.button>@endif
    </form>

    <section class="table-card">
        <table class="data-table duplicate-table">
            <thead><tr><th>Protocolo</th><th>Ocorrência</th><th>Ambiente</th><th>Categoria</th><th>Registrada em</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                @forelse ($candidates as $candidate)
                    <tr>
                        <td><strong><a href="{{ route('occurrences.show', $candidate) }}" target="_blank" rel="noopener">{{ $candidate->protocol }}</a></strong></td>
                        <td><strong>{{ $candidate->title }}</strong><small>{{ str($candidate->description)->limit(90) }}</small></td>
                        <td>{{ $candidate->environment->code }} · {{ $candidate->environment->name }}</td>
                        <td>{{ $candidate->category->name }}</td>
                        <td>{{ $candidate->created_at->format('d/m/Y H:i') }}</td>
                        <td><span class="badge badge--primary">{{ \App\Models\Occurrence::STATUS_LABELS[$candidate->status] }}</span></td>
                        <td>
                            <details class="duplicate-choice">
                                <summary>Selecionar</summary>
                                <form method="POST" action="{{ route('occurrences.duplicate', $occurrence) }}">
                                    @csrf
                                    <input type="hidden" name="occurrence_version" value="{{ $occurrenceVersion }}">
                                    <input type="hidden" name="duplicate_of_id" value="{{ $candidate->id }}">
                                    <x-ui.input label="Motivo opcional" name="reason" placeholder="Ex.: mesmo problema já registrado" />
                                    <x-ui.button type="submit" variant="primary">Confirmar duplicidade</x-ui.button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Nenhuma ocorrência da mesma escola corresponde à busca.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="notification-pagination">{{ $candidates->links() }}</div>
</x-layouts.app>
