<?php

namespace App\Http\Controllers;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $organization = $user->organization;
        $schools = $user->accessibleSchools();
        $onboarding = null;

        if ($user->is_platform_admin) {
            $stats = [
                ['label' => 'Organizações', 'value' => Organization::query()->count(), 'hint' => 'Estruturas cadastradas', 'icon' => 'organization'],
                ['label' => 'Escolas', 'value' => School::query()->count(), 'hint' => 'Unidades cadastradas', 'icon' => 'school'],
                ['label' => 'Usuários', 'value' => User::query()->where('is_platform_admin', false)->count(), 'hint' => 'Contas institucionais', 'icon' => 'users'],
                ['label' => 'Ambientes', 'value' => Environment::query()->count(), 'hint' => 'Locais cadastrados', 'icon' => 'environment'],
            ];
            $subtitle = 'Visão geral da estrutura administrada pela plataforma.';
        } else {
            $stats = [
                ['label' => 'Organização', 'value' => $organization ? 1 : 0, 'hint' => $organization?->name ?? 'Não vinculada', 'icon' => 'organization'],
                ['label' => 'Escolas acessíveis', 'value' => $schools->count(), 'hint' => 'Dentro do seu escopo', 'icon' => 'school'],
                ['label' => 'Usuários', 'value' => $organization?->users()->count() ?? 0, 'hint' => 'Na organização', 'icon' => 'users'],
                ['label' => 'Ambientes', 'value' => Environment::query()->whereIn('school_id', $schools->pluck('id'))->count(), 'hint' => 'Locais no seu escopo', 'icon' => 'environment'],
            ];
            $subtitle = $organization?->name ?? 'Sua visão operacional do SIGME.';

            $managedSchools = $schools->filter(fn (School $school): bool => $user->hasPermission('indicadores.visualizar', $school));
            if ($managedSchools->isNotEmpty()) {
                $schoolIds = $managedSchools->pluck('id');
                $environmentCount = Environment::query()->whereIn('school_id', $schoolIds)->where('is_active', true)->count();
                $categoryCount = OccurrenceCategory::query()->where('is_active', true)->whereHas('schools', fn ($query) => $query->whereIn('schools.id', $schoolIds))->count();
                $activeUserCount = $organization?->users()->where('is_active', true)->count() ?? 0;
                $occurrenceCount = Occurrence::query()->whereIn('school_id', $schoolIds)->count();
                $orderCount = ServiceOrder::query()->whereIn('school_id', $schoolIds)->count();
                $steps = collect([
                    ['key' => 'structure', 'title' => 'Preparar locais e categorias', 'description' => 'Cadastre ao menos um ambiente e uma categoria disponível.', 'initially_complete' => $environmentCount > 0 && $categoryCount > 0, 'href' => route('environments.index')],
                    ['key' => 'team', 'title' => 'Montar a equipe', 'description' => 'Mantenha pelo menos duas contas ativas para dividir responsabilidades.', 'initially_complete' => $activeUserCount >= 2, 'href' => route('users.index')],
                    ['key' => 'occurrence', 'title' => 'Registrar a primeira ocorrência', 'description' => 'Abra uma solicitação real e acompanhe a triagem.', 'initially_complete' => $occurrenceCount > 0, 'href' => route('occurrences.create')],
                    ['key' => 'service-order', 'title' => 'Acompanhar uma Ordem de Serviço', 'description' => 'Defina responsável, prazos, custos e conclusão.', 'initially_complete' => $orderCount > 0, 'href' => route('service-orders.index')],
                ]);
                if ($user->onboarding_completed_steps === null) {
                    $user->forceFill([
                        'onboarding_completed_steps' => $steps
                            ->where('initially_complete', true)
                            ->pluck('key')
                            ->values()
                            ->all(),
                    ])->saveQuietly();
                    $user->refresh();
                }

                $completedStepKeys = collect($user->onboarding_completed_steps ?? []);
                $steps = $steps->map(fn (array $step): array => [
                    ...$step,
                    'complete' => $completedStepKeys->contains($step['key']),
                ]);
                $completedSteps = $steps->where('complete', true)->count();
                $onboarding = [
                    'steps' => $steps,
                    'completed' => $completedSteps,
                    'total' => $steps->count(),
                    'percentage' => (int) round(($completedSteps / $steps->count()) * 100),
                ];
            }
        }

        return view('dashboard', [
            'user' => $user,
            'organization' => $organization,
            'schools' => $schools,
            'stats' => $stats,
            'subtitle' => $subtitle,
            'onboarding' => $onboarding,
        ]);
    }
}
