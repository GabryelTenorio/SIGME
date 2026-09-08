<x-layouts.app title="Notificações" active="notifications">
    <header class="page-header">
        <div><h1>Notificações</h1><p>Avisos internos relacionados às escolas e atividades que você acompanha.</p></div>
        @if ($notifications->contains(fn ($notification) => $notification->read_at === null))
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit">Marcar todas como lidas</x-ui.button>
            </form>
        @endif
    </header>

    <form method="POST" action="{{ route('notifications.preferences.update') }}" class="form-card">
        @csrf
        @method('PATCH')
        <div class="section-title">
            <div><h2>Preferências de entrega</h2><p>A notificação interna e o histórico permanecem ativos independentemente desta opção.</p></div>
        </div>
        <input type="hidden" name="email_notifications_enabled" value="0">
        <label class="checkbox-field">
            <input type="checkbox" name="email_notifications_enabled" value="1" @checked(old('email_notifications_enabled', auth()->user()->email_notifications_enabled))>
            <span><strong>Receber notificações também por e-mail</strong><small>Esta opção começa desativada e pode ser alterada somente por você.</small></span>
        </label>
        @error('email_notifications_enabled')<em>{{ $message }}</em>@enderror
        <div class="form-actions"><x-ui.button variant="primary" type="submit">Salvar preferência</x-ui.button></div>
    </form>

    <section class="notification-list" aria-label="Caixa de notificações">
        @forelse ($notifications as $notification)
            <article class="notification-item @if ($notification->read_at === null) notification-item--unread @endif">
                <div class="notification-item__marker" aria-hidden="true"></div>
                <div class="notification-item__content">
                    <div class="notification-item__meta">
                        <span>{{ $notification->school->name }}</span>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    <h2>
                        @if (($notification->data['service_order_id'] ?? null) || ($notification->data['occurrence_id'] ?? null))
                            <a href="{{ route('notifications.open', $notification) }}">{{ $notification->title }}</a>
                        @else
                            {{ $notification->title }}
                        @endif
                    </h2>
                    @if ($notification->body)<p>{{ $notification->body }}</p>@endif
                </div>
                @if ($notification->read_at === null)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="secondary">Marcar como lida</x-ui.button>
                    </form>
                @else
                    <span class="notification-item__read">Lida</span>
                @endif
            </article>
        @empty
            <div class="empty-state">Você ainda não possui notificações.</div>
        @endforelse
    </section>

    @if ($notifications->hasPages())
        <nav class="notification-pagination" aria-label="Paginação de notificações">
            @if ($notifications->previousPageUrl())<x-ui.button :href="$notifications->previousPageUrl()">Anteriores</x-ui.button>@else<span></span>@endif
            @if ($notifications->nextPageUrl())<x-ui.button :href="$notifications->nextPageUrl()">Próximas</x-ui.button>@endif
        </nav>
    @endif
</x-layouts.app>
