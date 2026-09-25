<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f1a2f">
        <title>Nova senha · SIGME</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-simple-page">
        <main class="auth-simple-card" aria-labelledby="password-reset-title">
            <a class="login-brand login-brand--dark" href="{{ route('login') }}">
                <span class="login-brand__mark">S</span>
                <span><strong>SIGME</strong><small>Manutenção escolar</small></span>
            </a>

            <div class="login-card__heading">
                <span>Recuperação de acesso</span>
                <h1 id="password-reset-title">Crie sua nova senha</h1>
                <p>Use pelo menos 12 caracteres e não reutilize a senha do seu e-mail.</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="login-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-ui.input label="E-mail" name="email" type="email" :value="$email" autocomplete="username" required />
                <x-ui.input label="Nova senha" name="password" type="password" autocomplete="new-password" minlength="12" required />
                <x-ui.input label="Confirmar nova senha" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required />

                @if ($errors->any())
                    <div class="alert alert--danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <x-ui.button variant="primary" type="submit" class="button--full">Salvar nova senha</x-ui.button>
            </form>
        </main>
    </body>
</html>
