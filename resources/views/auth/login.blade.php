<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Acesso ao Sistema Integrado de Gestão da Manutenção Escolar">
        <meta name="theme-color" content="#0f1a2f">
        <title>Entrar · SIGME</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="login-page">
        <main class="login-shell">
            <section class="login-intro" aria-labelledby="login-title">
                <a class="login-brand" href="{{ route('home') }}">
                    <span class="login-brand__mark">S</span>
                    <span><strong>SIGME</strong><small>Manutenção escolar</small></span>
                </a>

                <div class="login-message">
                    <span>Gestão integrada</span>
                    <h1 id="login-title">Manutenção escolar com informação no lugar certo.</h1>
                    <p>Organizações, escolas, usuários e permissões ficam separados com segurança em um único ambiente.</p>
                </div>

                <small class="login-footnote">Beta local · Ambiente protegido</small>
            </section>

            <section class="login-card" aria-label="Formulário de acesso">
                <div class="login-card__heading">
                    <span>Acesso ao sistema</span>
                    <h2>Entrar no SIGME</h2>
                    <p>Use a conta fornecida pelo administrador responsável.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert--success login-status" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf

                    <x-ui.input label="E-mail" name="email" type="email" autocomplete="username" required autofocus />
                    <x-ui.input label="Senha" name="password" type="password" autocomplete="current-password" required />

                    <label class="remember-option">
                        <input name="remember" type="checkbox" value="1">
                        <span>Manter acesso neste computador</span>
                    </label>

                    <a class="login-forgot-password" href="{{ route('password.request') }}">Esqueci minha senha</a>

                    @if ($errors->any())
                        <div class="alert alert--danger" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <x-ui.button variant="primary" type="submit" class="button--full">Entrar</x-ui.button>
                </form>

                <p class="login-help">Não há cadastro público. Contas são administradas dentro do SIGME.</p>
            </section>
        </main>
    </body>
</html>
