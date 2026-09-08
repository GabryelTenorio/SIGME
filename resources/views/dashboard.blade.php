<x-layouts.app title="Dashboard" active="dashboard">
    <header class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p>{{ $subtitle }}</p>
        </div>
        <span class="badge badge--success">Sistema online</span>
    </header>

    <section class="stat-grid" aria-label="Resumo da estrutura">
        @foreach ($stats as $stat)
            <article class="stat-card">
                <span>{{ $stat['label'] }}</span>
                <strong>{{ $stat['value'] }}</strong>
                <small>{{ $stat['hint'] }}</small>
                <span class="stat-card__icon"><x-ui.icon :name="$stat['icon']" /></span>
            </article>
        @endforeach
    </section>

    <div class="dashboard-grid">
        <section class="card">
            <div class="section-title">
                <div><h2>Estrutura do SIGME</h2><p>Base disponível para os próximos fluxos operacionais</p></div>
            </div>

            <div class="module-list">
                <div class="module-row">
                    <span class="module-row__icon"><x-ui.icon name="organization" /></span>
                    <span><strong>Organizações e acesso</strong><small>Escolas, usuários, perfis e permissões</small></span>
                    <span class="badge badge--success">Disponível</span>
                </div>
                <div class="module-row">
                    <span class="module-row__icon"><x-ui.icon name="environment" /></span>
                    <span><strong>Ambientes</strong><small>Locais válidos para vincular ocorrências</small></span>
                    <span class="badge badge--success">Disponível</span>
                </div>
                <div class="module-row">
                    <span class="module-row__icon"><x-ui.icon name="occurrence" /></span>
                    <span><strong>Categorias</strong><small>Classificação controlada das ocorrências</small></span>
                    <span class="badge badge--success">Disponível</span>
                </div>
                <div class="module-row">
                    <span class="module-row__icon"><x-ui.icon name="occurrence" /></span>
                    <span><strong>Ocorrências</strong><small>Protocolo, triagem e acompanhamento</small></span>
                    <span class="badge badge--success">Fase 1 disponível</span>
                </div>
                <div class="module-row"><span class="module-row__icon"><x-ui.icon name="service-order" /></span><span><strong>Ordens de Serviço</strong><small>Aprovação, execução técnica, evidências, materiais e custos</small></span><span class="badge badge--success">Fase 1 disponível</span></div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">
                <div><h2>Seu contexto de acesso</h2><p>Escopo aplicado à sessão atual</p></div>
            </div>

            <div class="module-list">
                <div class="module-row">
                    <span class="module-row__icon"><x-ui.icon name="users" /></span>
                    <span><strong>{{ $user->name }}</strong><small>{{ $user->is_platform_admin ? 'Administrador da plataforma' : ($organization?->name ?? 'Sem organização') }}</small></span>
                </div>
                @forelse ($schools->take(3) as $school)
                    <div class="module-row">
                        <span class="module-row__icon"><x-ui.icon name="school" /></span>
                        <span><strong>{{ $school->name }}</strong><small>{{ $school->code }}</small></span>
                        <span class="badge badge--success">Ativa</span>
                    </div>
                @empty
                    <p class="empty-state">Nenhuma escola vinculada diretamente a esta conta.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
