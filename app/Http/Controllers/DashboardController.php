<?php

namespace App\Http\Controllers;

use App\Models\Environment;
use App\Models\Organization;
use App\Models\School;
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
        }

        return view('dashboard', [
            'user' => $user,
            'organization' => $organization,
            'schools' => $schools,
            'stats' => $stats,
            'subtitle' => $subtitle,
        ]);
    }
}
