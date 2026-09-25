<?php

namespace App\Http\Controllers;

use App\Support\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticationService $twoFactor): View
    {
        $user = $request->user();
        $qrCodeSvg = null;

        if ($user->two_factor_secret && ! $user->hasEnabledTwoFactorAuthentication()) {
            $qrCodeSvg = $twoFactor->qrCodeSvg($user);
        }

        return view('account.security', [
            'qrCodeSvg' => $qrCodeSvg,
            'recoveryCodes' => session('two_factor_recovery_codes', []),
            'twoFactorRequired' => (bool) config('security.two_factor.enforce_privileged')
                && $user->requiresTwoFactorAuthentication(),
        ]);
    }

    public function enable(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $this->validateCurrentPassword($request);

        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return back()->with('success', 'A autenticação em dois fatores já está ativa.');
        }

        $recoveryCodes = $twoFactor->beginEnrollment($request->user());

        return back()
            ->with('success', 'Escaneie o QR Code e confirme um código para concluir a ativação.')
            ->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function confirm(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Informe o código exibido no aplicativo autenticador.',
            'code.digits' => 'O código deve possuir seis dígitos.',
        ]);

        if (! $twoFactor->confirmEnrollment($request->user(), $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'O código é inválido ou já foi utilizado.',
            ]);
        }

        return back()->with('success', 'Autenticação em dois fatores ativada com sucesso.');
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $this->validateCurrentPassword($request);

        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'current_password' => 'Ative a autenticação em dois fatores primeiro.',
            ]);
        }

        return back()
            ->with('success', 'Novos códigos de recuperação foram gerados. Os anteriores deixaram de funcionar.')
            ->with('two_factor_recovery_codes', $twoFactor->regenerateRecoveryCodes($request->user()));
    }

    public function disable(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $this->validateCurrentPassword($request);
        $twoFactor->disable($request->user());

        return back()->with('success', 'Autenticação em dois fatores desativada.');
    }

    private function validateCurrentPassword(Request $request): void
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ], [
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
        ]);
    }
}
