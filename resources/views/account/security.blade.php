<x-layouts.app title="Segurança da conta" active="security">
    <header class="page-header">
        <div>
            <span class="eyebrow">Minha conta</span>
            <h1>Segurança da conta</h1>
            <p>Proteja o acesso com senha, aplicativo autenticador e códigos de recuperação.</p>
        </div>
    </header>

    @if ($twoFactorRequired && ! auth()->user()->hasEnabledTwoFactorAuthentication())
        <div class="alert alert--warning" role="alert">Seu perfil exige autenticação em dois fatores. Conclua a configuração para acessar as demais áreas.</div>
    @elseif (session('two_factor_required'))
        <div class="alert alert--warning" role="alert">{{ session('two_factor_required') }}</div>
    @endif

    <section class="security-grid" aria-label="Autenticação em dois fatores">
        <article class="security-card security-card--status">
            <div>
                <span class="eyebrow">Segundo fator</span>
                <h2>Aplicativo autenticador</h2>
                <p>Os códigos são gerados no seu celular e funcionam mesmo sem internet.</p>
            </div>
            <span class="badge badge--{{ auth()->user()->hasEnabledTwoFactorAuthentication() ? 'success' : 'warning' }}">
                {{ auth()->user()->hasEnabledTwoFactorAuthentication() ? 'Ativo' : 'Pendente' }}
            </span>
        </article>

        @if (! auth()->user()->two_factor_secret)
            <article class="security-card security-card--enrollment">
                <h2>Começar configuração</h2>
                <p>Confirme sua senha para gerar uma chave exclusiva e os códigos de recuperação.</p>
                <form method="POST" action="{{ route('account.security.two-factor.enable') }}" class="security-form">
                    @csrf
                    <x-ui.input label="Senha atual" name="current_password" type="password" autocomplete="current-password" required />
                    <x-ui.button variant="primary" type="submit">Gerar QR Code</x-ui.button>
                </form>
            </article>
        @elseif (! auth()->user()->hasEnabledTwoFactorAuthentication())
            <article class="security-card security-setup">
                <div>
                    <h2>1. Escaneie o QR Code</h2>
                    <p>Use Microsoft Authenticator, Google Authenticator, 2FAS ou outro aplicativo TOTP.</p>
                    <div class="two-factor-qr" aria-label="QR Code para configurar o autenticador">{!! $qrCodeSvg !!}</div>
                    <p class="security-secret"><span>Chave manual</span><code>{{ auth()->user()->two_factor_secret }}</code></p>
                </div>
                <div>
                    <h2>2. Confirme um código</h2>
                    <p>Digite o código atual exibido pelo aplicativo.</p>
                    <form method="POST" action="{{ route('account.security.two-factor.confirm') }}" class="security-form">
                        @csrf
                        <x-ui.input label="Código de seis dígitos" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                        <x-ui.button variant="primary" type="submit">Ativar proteção</x-ui.button>
                    </form>
                </div>
            </article>
        @else
            <article class="security-card">
                <h2>Códigos de recuperação</h2>
                <p>Gere novos códigos se perder os anteriores. Cada código funciona uma única vez.</p>
                <form method="POST" action="{{ route('account.security.two-factor.recovery-codes') }}" class="security-form">
                    @csrf
                    <x-ui.input label="Senha atual" name="current_password" type="password" autocomplete="current-password" required />
                    <x-ui.button variant="secondary" type="submit">Gerar novos códigos</x-ui.button>
                </form>
            </article>

            <article class="security-card security-card--danger">
                <h2>Desativar segundo fator</h2>
                <p>O próximo login exigirá apenas a senha. Perfis privilegiados poderão ser obrigados a configurar novamente.</p>
                <form method="POST" action="{{ route('account.security.two-factor.disable') }}" class="security-form">
                    @csrf
                    @method('DELETE')
                    <x-ui.input label="Senha atual" name="current_password" type="password" autocomplete="current-password" required />
                    <x-ui.button variant="danger" type="submit">Desativar 2FA</x-ui.button>
                </form>
            </article>
        @endif

        @if (count($recoveryCodes) > 0)
            <article class="security-card security-card--codes">
                <h2>Guarde estes códigos agora</h2>
                <p>Eles não serão exibidos novamente. Guarde-os fora do computador onde usa o SIGME.</p>
                <ul class="recovery-code-list">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li><code>{{ $recoveryCode }}</code></li>
                    @endforeach
                </ul>
            </article>
        @endif
    </section>
</x-layouts.app>
