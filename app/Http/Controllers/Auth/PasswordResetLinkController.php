<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Informe o e-mail da conta.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return back()->with(
            'status',
            'Se a conta estiver cadastrada, enviaremos um link para definir uma nova senha.',
        );
    }
}
