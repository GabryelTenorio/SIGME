<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForPrivilegedUsers
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('security.two_factor.enforce_privileged')
            || ! $user
            || ! $user->requiresTwoFactorAuthentication()
            || $user->hasEnabledTwoFactorAuthentication()
            || $request->routeIs('account.security.*', 'logout')) {
            return $next($request);
        }

        return redirect()
            ->route('account.security.show')
            ->with('two_factor_required', 'Configure a autenticação em dois fatores para continuar.');
    }
}
