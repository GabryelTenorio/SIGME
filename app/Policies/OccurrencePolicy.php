<?php

namespace App\Policies;

use App\Models\Occurrence;
use App\Models\User;

class OccurrencePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_platform_admin && ! in_array($ability, ['addEvidence', 'requestReopening'], true) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ocorrencias.visualizar_rede')
            || $user->accessibleSchools()->contains(fn ($school) => collect([
                'ocorrencias.visualizar_proprias', 'ocorrencias.visualizar_escola', 'ocorrencias.visualizar_encaminhadas', 'ocorrencias.triar',
            ])->contains(fn ($permission) => $user->hasPermission($permission, $school)));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Occurrence $occurrence): bool
    {
        if ($occurrence->reporter_id === $user->id && $user->hasPermission('ocorrencias.visualizar_proprias', $occurrence->school)) {
            return true;
        }
        if (! $user->canAccessSchool($occurrence->school)) {
            return false;
        }
        if ($user->hasPermission('ocorrencias.visualizar_rede') || $user->hasPermission('ocorrencias.visualizar_escola', $occurrence->school) || $user->hasPermission('ocorrencias.triar', $occurrence->school)) {
            return true;
        }

        return $occurrence->status === 'ENCAMINHADA' && $user->hasPermission('ocorrencias.visualizar_encaminhadas', $occurrence->school);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->accessibleSchools()->contains(fn ($school) => $user->hasPermission('ocorrencias.criar', $school));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Occurrence $occurrence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Occurrence $occurrence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Occurrence $occurrence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Occurrence $occurrence): bool
    {
        return false;
    }

    public function triage(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.triar');
    }

    public function confirmPriority(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.confirmar_prioridade');
    }

    public function requestInformation(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.solicitar_informacoes');
    }

    public function forward(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.encaminhar');
    }

    public function markDuplicate(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.marcar_duplicada');
    }

    public function markNotApplicable(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.marcar_nao_procede');
    }

    public function provideInformation(User $user, Occurrence $occurrence): bool
    {
        return $occurrence->reporter_id === $user->id
            && $occurrence->status === 'AGUARDANDO_INFORMACOES'
            && $user->hasPermission('ocorrencias.visualizar_proprias', $occurrence->school);
    }

    public function addEvidence(User $user, Occurrence $occurrence): bool
    {
        return $occurrence->reporter_id === $user->id
            && in_array($occurrence->status, ['ABERTA', 'EM_TRIAGEM', 'AGUARDANDO_INFORMACOES'], true)
            && $user->hasPermission('ocorrencias.visualizar_proprias', $occurrence->school);
    }

    public function close(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.encerrar');
    }

    public function reopen(User $user, Occurrence $occurrence): bool
    {
        return $this->schoolCapability($user, $occurrence, 'ocorrencias.reabrir');
    }

    public function requestReopening(User $user, Occurrence $occurrence): bool
    {
        return $occurrence->reporter_id === $user->id
            && $occurrence->status === 'ENCERRADA'
            && $user->hasPermission('ocorrencias.visualizar_proprias', $occurrence->school);
    }

    private function schoolCapability(User $user, Occurrence $occurrence, string $permission): bool
    {
        return $user->canAccessSchool($occurrence->school) && $user->hasPermission($permission, $occurrence->school);
    }
}
