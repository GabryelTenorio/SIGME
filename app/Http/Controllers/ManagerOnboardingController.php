<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerOnboardingController extends Controller
{
    /** @var array<string, string> */
    private const STEPS = [
        'structure' => 'environments.index',
        'team' => 'users.index',
        'occurrence' => 'occurrences.create',
        'service-order' => 'service-orders.index',
    ];

    public function open(Request $request, string $step): RedirectResponse
    {
        abort_unless(array_key_exists($step, self::STEPS), 404);
        $this->ensureUserCanUseGuide($request);

        $user = $request->user();
        $completedSteps = collect($user->onboarding_completed_steps ?? [])
            ->push($step)
            ->unique()
            ->values()
            ->all();

        $user->forceFill(['onboarding_completed_steps' => $completedSteps])->save();

        return redirect()->route(self::STEPS[$step]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->ensureUserCanUseGuide($request);
        $request->user()->forceFill(['onboarding_completed_steps' => []])->save();

        return redirect()->route('dashboard')->with('success', 'Seu guia rápido foi reiniciado.');
    }

    private function ensureUserCanUseGuide(Request $request): void
    {
        $user = $request->user();
        $canUseGuide = $user->is_platform_admin || $user->accessibleSchools()->contains(
            fn (School $school): bool => $user->hasPermission('indicadores.visualizar', $school),
        );

        abort_unless($canUseGuide, 403);
    }
}
