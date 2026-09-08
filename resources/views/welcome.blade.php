<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ambiente local do SIGME">
        <meta name="theme-color" content="#071b1d">

        <title>{{ config('app.name', 'SIGME') }} · Ambiente local</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="sigme-page">
        <div class="page-glow page-glow--top" aria-hidden="true"></div>
        <div class="page-glow page-glow--bottom" aria-hidden="true"></div>

        <header class="site-header">
            <a class="brand" href="{{ route('home') }}" aria-label="Página inicial do SIGME">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 44 44" role="img">
                        <path d="M13 10.5h16.5a6 6 0 0 1 0 12H18.25a6 6 0 0 0 0 12H34" />
                        <circle cx="12" cy="10.5" r="3" />
                        <circle cx="34" cy="34.5" r="3" />
                    </svg>
                </span>
                <span class="brand-copy">
                    <strong>SIGME</strong>
                    <small>Ambiente local</small>
                </span>
            </a>

            <span class="beta-badge">
                <span class="status-dot" aria-hidden="true"></span>
                Beta local
            </span>
        </header>

        <main>
            <section class="hero" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <p class="eyebrow">
                        <span aria-hidden="true">✦</span>
                        Fundação técnica ativa
                    </p>

                    <h1 id="hero-title">
                        Seu ambiente local
                        <span>está pronto.</span>
                    </h1>

                    <p class="hero-description">
                        O SIGME já está executando neste computador com os serviços essenciais
                        preparados para receber os módulos funcionais.
                    </p>

                    <div class="hero-actions">
                        <a class="button button--primary" href="{{ route('login') }}">
                            Entrar no SIGME
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="m7.5 4.5 5 5-5 5" />
                            </svg>
                        </a>
                        <a
                            class="button button--secondary"
                            href="http://127.0.0.1:8025"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Abrir e-mails locais
                        </a>
                    </div>

                    <p class="local-note">
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M10 2.75 16 5v4.6c0 3.55-2.45 6.78-6 7.65-3.55-.87-6-4.1-6-7.65V5l6-2.25Z" />
                            <path d="m7.3 10 1.65 1.65 3.75-3.8" />
                        </svg>
                        Execução local, sem hospedagem contratada.
                    </p>
                </div>

                <aside class="environment-card" aria-label="Resumo do ambiente">
                    <div class="environment-card__header">
                        <div>
                            <span class="card-kicker">Ambiente</span>
                            <h2>Serviços essenciais</h2>
                        </div>
                        <span class="online-pill">
                            <span class="status-dot" aria-hidden="true"></span>
                            Online
                        </span>
                    </div>

                    <div class="service-list">
                        <div class="service-row">
                            <span class="service-icon service-icon--cyan" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3.5" y="5" width="17" height="14" rx="3" />
                                    <path d="M7.5 9h9M7.5 13h5" />
                                </svg>
                            </span>
                            <span class="service-copy">
                                <strong>Aplicação</strong>
                                <small>{{ request()->getHttpHost() }}</small>
                            </span>
                            <span class="service-state">Ativa</span>
                        </div>

                        <div class="service-row">
                            <span class="service-icon service-icon--violet" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <ellipse cx="12" cy="6.5" rx="7.5" ry="3" />
                                    <path d="M4.5 6.5v5c0 1.65 3.35 3 7.5 3s7.5-1.35 7.5-3v-5M4.5 11.5v5c0 1.65 3.35 3 7.5 3s7.5-1.35 7.5-3v-5" />
                                </svg>
                            </span>
                            <span class="service-copy">
                                <strong>Banco de dados</strong>
                                <small>MySQL isolado</small>
                            </span>
                            <span class="service-state service-state--muted">Local</span>
                        </div>

                        <div class="service-row">
                            <span class="service-icon service-icon--amber" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
                                    <path d="m5 8 7 5 7-5" />
                                </svg>
                            </span>
                            <span class="service-copy">
                                <strong>E-mails de teste</strong>
                                <small>Mailpit local</small>
                            </span>
                            <span class="service-state service-state--muted">Pronto</span>
                        </div>

                        <div class="service-row">
                            <span class="service-icon service-icon--green" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M5 7.5h9.5M5 12h14M5 16.5h9.5" />
                                    <path d="m15.5 5 3 2.5-3 2.5M14.5 14l-3 2.5 3 2.5" />
                                </svg>
                            </span>
                            <span class="service-copy">
                                <strong>Processos internos</strong>
                                <small>Fila e agendador</small>
                            </span>
                            <span class="service-state service-state--muted">Ativos</span>
                        </div>
                    </div>

                    <a class="diagnostic-link" href="{{ route('health') }}">
                        Abrir diagnóstico técnico
                        <span aria-hidden="true">↗</span>
                    </a>
                </aside>
            </section>

            <section class="foundation" aria-labelledby="foundation-title">
                <div class="section-heading">
                    <p class="eyebrow">Base disponível</p>
                    <h2 id="foundation-title">Pronto para construir com segurança.</h2>
                    <p>
                        A infraestrutura está organizada. O próximo avanço será guiado pelos
                        requisitos reais do SIGME.
                    </p>
                </div>

                <div class="foundation-grid">
                    <article class="foundation-item">
                        <span>01</span>
                        <h3>Dados preservados</h3>
                        <p>Banco, uploads e configurações ficam fora do código da aplicação.</p>
                    </article>
                    <article class="foundation-item">
                        <span>02</span>
                        <h3>Diagnóstico objetivo</h3>
                        <p>O endpoint de saúde confirma banco e armazenamento sem expor detalhes.</p>
                    </article>
                    <article class="foundation-item">
                        <span>03</span>
                        <h3>Evolução controlada</h3>
                        <p>Nenhum módulo de negócio será presumido sem requisitos aprovados.</p>
                    </article>
                </div>
            </section>

            <section class="next-step" aria-labelledby="next-step-title">
                <div class="next-step__mark" aria-hidden="true">S</div>
                <div>
                    <span class="card-kicker">Próxima etapa</span>
                    <h2 id="next-step-title">Transformar a base técnica no SIGME funcional.</h2>
                    <p>
                        Vamos definir usuários, permissões, informações do painel e o primeiro
                        fluxo de trabalho antes de implementar os módulos.
                    </p>
                </div>
                <span class="next-step__label">Aguardando requisitos</span>
            </section>
        </main>

        <footer class="site-footer">
            <span>© {{ date('Y') }} SIGME</span>
            <span class="footer-separator" aria-hidden="true"></span>
            <span>Beta executado localmente</span>
        </footer>
    </body>
</html>
