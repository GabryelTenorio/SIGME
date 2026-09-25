<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f1a2f">
        <title>Recuperar senha · SIGME</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-simple-page">
        <main class="auth-simple-card" aria-labelledby="password-request-title">
            <a class="login-brand login-brand--dark" href="{{ route('login') }}">
                <span class="login-brand__mark">S</span>
                <span><strong>SIGME</strong><small>Manutenção escolar</small></span>
            </a>

            <div class="login-card__heading">
                <span>Recuperação de acesso</span>
                <h1 id="password-request-title">Definir uma nova senha</h1>
                <p>Informe o e-mail da conta. Se ele estiver cadastrado, enviaremos um link temporário.</p>
            </div>

            @if (session('status'))
                <div class="alert alert--success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="login-form">
                @csrf
                <x-ui.input label="E-mail" name="email" type="email" autocomplete="email" required autofocus />

                @if ($errors->any())
                    <div class="alert alert--danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <x-ui.button variant="primary" type="submit" class="button--full">Enviar link de recuperação</x-ui.button>
            </form>

            <p class="auth-secondary-action"><a href="{{ route('login') }}">Voltar ao login</a></p>
        </main>
    </body>
</html>
