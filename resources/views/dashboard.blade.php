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

    @if($onboarding)
        <section class="card onboarding-card" aria-labelledby="manager-onboarding-title">
            <div class="onboarding-card__header">
                <div><span class="eyebrow">Guia rápido</span><h2 id="manager-onboarding-title">Primeiros passos da gestão</h2><p>Use esta sequência para deixar a escola pronta e acompanhar o primeiro atendimento.</p></div>
                <div class="onboarding-card__progress"><strong>{{ $onboarding['completed'] }}/{{ $onboarding['total'] }}</strong><span>etapas concluídas</span></div>
            </div>
            <div class="onboarding-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $onboarding['percentage'] }}"><span style="width: {{ $onboarding['percentage'] }}%"></span></div>
            <ol class="onboarding-steps">
                @foreach($onboarding['steps'] as $index => $step)
                    <li class="{{ $step['complete'] ? 'is-complete' : '' }}">
                        <span class="onboarding-step__number">@if($step['complete'])<x-ui.icon name="check" />@else{{ $index + 1 }}@endif</span>
                        <span><strong>{{ $step['title'] }}</strong><small>{{ $step['description'] }}</small></span>
                        <div class="onboarding-step__actions">
                            <a href="{{ route('onboarding.open', $step['key']) }}">{{ $step['complete'] ? 'Revisar' : 'Abrir etapa' }}</a>
                            @if($step['complete'])
                                <span>Concluída</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="onboarding-card__footer">
                <span>Seu progresso é individual e não altera os dados da escola.</span>
                <div class="onboarding-card__footer-actions">
                    <form method="POST" action="{{ route('onboarding.reset') }}" data-confirm="Reiniciar somente o seu guia rápido? Nenhum dado da escola será apagado.">
                        @csrf
                        @method('DELETE')
                        <button class="button" type="submit">Reiniciar meu guia</button>
                    </form>
                    <x-ui.button :href="route('reports.index')" variant="primary">Abrir relatórios</x-ui.button>
                </div>
            </div>
        </section>
    @endif

    <div class="dashboard-grid">
        <section class="card">
            <div class="section-title">
                <div><h2>Estrutura do SIGME</h2><p>Os menus exibem os módulos disponíveis para o seu perfil.</p></div>
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
                    <span class="badge badge--success">Disponível</span>
                </div>
                <div class="module-row"><span class="module-row__icon"><x-ui.icon name="service-order" /></span><span><strong>Ordens de Serviço</strong><small>Aprovação, execução técnica, evidências, materiais e custos</small></span><span class="badge badge--success">Disponível</span></div>
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
