<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login')->withErrors([
                'email' => 'A verificação expirou. Entre novamente.',
            ]);
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ], [
            'code.required' => 'Informe o código do autenticador ou um código de recuperação.',
        ]);
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'A verificação expirou. Entre novamente.',
            ]);
        }

        $rateLimitKey = 'two-factor-challenge:'.$user->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'code' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        if (! $twoFactor->verifyLoginCode($user, $data['code'])) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'code' => 'O código informado é inválido ou já foi utilizado.',
            ]);
        }

        $pending = $request->session()->pull('auth.two_factor_pending');
        Auth::guard('web')->login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        RateLimiter::clear($rateLimitKey);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('auth.two_factor_pending');
        $request->session()->regenerate();

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('auth.two_factor_pending');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('auth.two_factor_pending');

            return null;
        }

        $user = User::query()->find($pending['user_id'] ?? null);

        if (! $user?->hasActiveAccess() || ! $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->forget('auth.two_factor_pending');

            return null;
        }

        return $user;
    }
}
