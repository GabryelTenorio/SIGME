@props(['title', 'active' => 'dashboard'])

@php
    $currentUser = auth()->user();
    $initials = str($currentUser->name)->explode(' ')->filter()->take(2)->map(fn ($part) => str($part)->substr(0, 1))->join('');
    $roleName = $currentUser->is_platform_admin
        ? 'Administrador da plataforma'
        : ($currentUser->roles()->value('name') ?? 'Usuário SIGME');
    $showOrganizations = $currentUser->is_platform_admin;
    $showOwnOrganization = ! $showOrganizations && $currentUser->organization && $currentUser->can('update', $currentUser->organization);
    $showSchools = $currentUser->is_platform_admin || $currentUser->hasPermission('escolas.gerenciar')
        || $currentUser->accessibleSchools()->contains(fn ($school) => $currentUser->hasPermission('escolas.gerenciar', $school));
    $showUsers = $currentUser->is_platform_admin || $currentUser->hasPermission('usuarios.gerenciar')
        || $currentUser->accessibleSchools()->contains(fn ($school) => $currentUser->hasPermission('usuarios.gerenciar', $school));
    $showEnvironments = $currentUser->is_platform_admin || $currentUser->accessibleSchools()->contains(
        fn ($school) => $currentUser->hasPermission('ambientes.visualizar', $school) || $currentUser->hasPermission('ambientes.gerenciar', $school)
    );
    $showCategories = $currentUser->is_platform_admin || $currentUser->accessibleSchools()->contains(
        fn ($school) => $currentUser->hasPermission('categorias.visualizar', $school) || $currentUser->hasPermission('categorias.gerenciar_disponibilidade', $school)
    );
    $showOccurrences = $currentUser->can('viewAny', \App\Models\Occurrence::class);
    $showServiceOrders = $currentUser->can('viewAny', \App\Models\ServiceOrder::class);
    $showReports = $currentUser->is_platform_admin || $currentUser->accessibleSchools()->contains(
        fn ($school) => $currentUser->hasPermission('indicadores.visualizar', $school)
    );
    $notificationsAvailable = \Illuminate\Support\Facades\Schema::hasTable('internal_notifications');
    $unreadNotificationCount = $notificationsAvailable
        ? $currentUser->internalNotifications()->whereNull('read_at')->count()
        : 0;
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f1a2f">
        <title>{{ $title }} · SIGME</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-body">
        <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
        <div class="app-shell">
            <div class="sidebar-overlay" data-sidebar-close></div>
            <aside class="sidebar" id="app-sidebar" aria-label="Navegação principal">
                <a class="sidebar-brand" href="{{ route('dashboard') }}">
                    <span class="sidebar-brand__mark">S</span>
                    <span><strong>SIGME</strong><small>Manutenção escolar</small></span>
                </a>

                <nav class="sidebar-nav">
                    <span class="sidebar-nav__label">Visão geral</span>
                    <a class="sidebar-nav__item @if ($active === 'dashboard') is-active @endif" href="{{ route('dashboard') }}">
                        <x-ui.icon name="dashboard" /><span>Dashboard</span>
                    </a>

                    @if ($showOrganizations || $showOwnOrganization || $showSchools || $showUsers)
                        <span class="sidebar-nav__label">Administração</span>
                        @if ($showOrganizations)
                            <a class="sidebar-nav__item @if ($active === 'organizations') is-active @endif" href="{{ route('organizations.index') }}">
                                <x-ui.icon name="organization" /><span>Organizações</span>
                            </a>
                        @endif
                        @if ($showOwnOrganization)
                            <a class="sidebar-nav__item @if ($active === 'organizations') is-active @endif" href="{{ route('organizations.edit', $currentUser->organization) }}"><x-ui.icon name="organization" /><span>Minha organização</span></a>
                        @endif
                        @if ($showSchools)
                            <a class="sidebar-nav__item @if ($active === 'schools') is-active @endif" href="{{ route('schools.index') }}">
                                <x-ui.icon name="school" /><span>Escolas</span>
                            </a>
                        @endif
                        @if ($showUsers)
                            <a class="sidebar-nav__item @if ($active === 'users') is-active @endif" href="{{ route('users.index') }}">
                                <x-ui.icon name="users" /><span>Usuários</span>
                            </a>
                        @endif
                    @endif

                    <span class="sidebar-nav__label">Operação</span>
                    @if ($showEnvironments)
                        <a class="sidebar-nav__item @if ($active === 'environments') is-active @endif" href="{{ route('environments.index') }}"><x-ui.icon name="environment" /><span>Ambientes</span></a>
                    @endif
                    @if ($showCategories)
                        <a class="sidebar-nav__item @if ($active === 'categories') is-active @endif" href="{{ route('categories.index') }}"><x-ui.icon name="occurrence" /><span>Categorias</span></a>
                    @endif
                    @if ($showOccurrences)<a class="sidebar-nav__item @if ($active === 'occurrences') is-active @endif" href="{{ route('occurrences.index') }}"><x-ui.icon name="occurrence" /><span>Ocorrências</span></a>@endif
                    @if ($showServiceOrders)<a class="sidebar-nav__item @if ($active === 'service-orders') is-active @endif" href="{{ route('service-orders.index') }}"><x-ui.icon name="service-order" /><span>Ordens de Serviço</span></a>@endif
                    @if ($showReports)<a class="sidebar-nav__item @if ($active === 'reports') is-active @endif" href="{{ route('reports.index') }}"><x-ui.icon name="chart" /><span>Relatórios</span></a>@endif
                    @if ($notificationsAvailable)
                        <a class="sidebar-nav__item @if ($active === 'notifications') is-active @endif" href="{{ route('notifications.index') }}">
                            <x-ui.icon name="notification" /><span>Notificações</span>
                            @if ($unreadNotificationCount > 0)<span class="sidebar-nav__count">{{ min($unreadNotificationCount, 99) }}{{ $unreadNotificationCount > 99 ? '+' : '' }}</span>@endif
                        </a>
                    @endif

                    <span class="sidebar-nav__label">Minha conta</span>
                    <a class="sidebar-nav__item @if ($active === 'security') is-active @endif" href="{{ route('account.security.show') }}">
                        <x-ui.icon name="shield" /><span>Segurança</span>
                    </a>
                </nav>

                <div class="sidebar-user">
                    <span class="avatar">{{ str($initials)->upper() }}</span>
                    <span class="sidebar-user__text">
                        <span class="sidebar-user__label">Conta ativa</span>
                        <strong>{{ $currentUser->name }}</strong>
                        <small title="{{ $currentUser->email }}">{{ $currentUser->email }}</small>
                        <span class="sidebar-user__role">{{ $roleName }}</span>
                    </span>
                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                        data-logout-form
                        data-logout-redirect="{{ route('login') }}"
                    >
                        @csrf
                        <button class="sidebar-logout" type="submit" aria-label="Sair"><x-ui.icon name="logout" /></button>
                    </form>
                </div>
            </aside>

            <div class="app-content">
                <header class="app-header">
                    <button type="button" class="mobile-menu-button" data-sidebar-open aria-controls="app-sidebar" aria-expanded="false">
                        <x-ui.icon name="menu" /><span class="sr-only">Abrir menu</span>
                    </button>
                    <strong class="app-header__brand">SIGME</strong>
                    <div class="account-indicator" aria-label="Conta ativa: {{ $currentUser->name }}, {{ $currentUser->email }}, perfil {{ $roleName }}">
                        <span class="avatar avatar--small">{{ str($initials)->upper() }}</span>
                        <span class="account-indicator__identity">
                            <span>Conta ativa</span>
                            <strong>{{ $currentUser->name }}</strong>
                            <small>{{ $currentUser->email }}</small>
                        </span>
                        <span class="account-indicator__role">{{ $roleName }}</span>
                    </div>
                </header>

                <main class="main-area" id="main-content" tabindex="-1">
                    <x-ui.alert />
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
