<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\FirstAccessInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FirstAccessController extends Controller
{
    public function __construct(private readonly FirstAccessInvitationService $invitations) {}

    public function create(Request $request, string $token): View
    {
        $email = Str::lower($request->string('email')->toString());
        abort_unless($this->invitations->isValid($token, $email), 404);

        return view('auth.first-access', compact('token', 'email'));
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)],
        ], [
            'email.required' => 'O convite não possui um e-mail válido.',
            'password.required' => 'Informe a senha que deseja usar.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve possuir pelo menos 12 caracteres.',
        ]);

        $user = $this->invitations->complete(
            $credentials['token'],
            Str::lower($credentials['email']),
            $credentials['password'],
        );

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Este convite não é válido ou já foi utilizado.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Senha definida. Seu primeiro acesso está liberado.');
    }
}
