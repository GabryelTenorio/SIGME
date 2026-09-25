<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)],
        ], [
            'email.required' => 'Informe o e-mail da conta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve possuir pelo menos 12 caracteres.',
        ]);

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'password_set_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->firstAccessToken()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'O link é inválido ou expirou. Solicite uma nova recuperação.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Senha definida. Agora você já pode entrar.');
    }
}
