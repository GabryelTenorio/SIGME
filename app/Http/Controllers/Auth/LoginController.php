<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();
        $request->session()->regenerate();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('auth.two_factor_pending', [
                'user_id' => $user->getKey(),
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addMinutes((int) config('security.two_factor.challenge_lifetime_minutes', 10))->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
                'Clear-Site-Data' => '"cache", "cookies", "storage"',
                'Expires' => '0',
                'Pragma' => 'no-cache',
            ]);
    }
}
