<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Verificação em duas etapas do SIGME">
        <meta name="theme-color" content="#0f1a2f">
        <title>Verificação em duas etapas · SIGME</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-simple-page">
        <main class="auth-simple-card" aria-labelledby="two-factor-title">
            <a class="login-brand login-brand--dark" href="{{ route('home') }}">
                <span class="login-brand__mark">S</span>
                <span><strong>SIGME</strong><small>Manutenção escolar</small></span>
            </a>

            <div class="login-card__heading">
                <span>Proteção da conta</span>
                <h1 id="two-factor-title">Confirme o segundo fator</h1>
                <p>Informe o código de seis dígitos do aplicativo autenticador. Você também pode usar um código de recuperação.</p>
            </div>

            <form method="POST" action="{{ route('two-factor.verify') }}" class="login-form">
                @csrf
                <x-ui.input
                    label="Código de verificação"
                    name="code"
                    inputmode="text"
                    autocomplete="one-time-code"
                    maxlength="32"
                    required
                    autofocus
                    hint="Exemplo: 123456 ou ABCDE-FGHIJ"
                />

                @if ($errors->any())
                    <div class="alert alert--danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <x-ui.button variant="primary" type="submit" class="button--full">Verificar e entrar</x-ui.button>
            </form>

            <form method="POST" action="{{ route('two-factor.cancel') }}" class="auth-secondary-action">
                @csrf
                <button type="submit">Cancelar e voltar ao login</button>
            </form>
        </main>
    </body>
</html>
