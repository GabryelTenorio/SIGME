<?php

namespace App\Policies;

use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->accessibleSchools()->contains(fn ($school) => $user->hasPermission('ordens_servico.visualizar', $school));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceOrder $serviceOrder): bool
    {
        if (! $user->canAccessSchool($serviceOrder->school) || ! $user->hasPermission('ordens_servico.visualizar', $serviceOrder->school)) {
            return false;
        }
        if ($user->hasPermission('ordens_servico.criar', $serviceOrder->school) || $user->hasPermission('ordens_servico.aprovar', $serviceOrder->school)) {
            return true;
        }

        return $serviceOrder->assigned_user_id === $user->id || $serviceOrder->members()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->accessibleSchools()->contains(fn ($school) => $user->hasPermission('ordens_servico.criar', $school));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceOrder $serviceOrder): bool
    {
        return $this->capability($user, $serviceOrder, 'ordens_servico.atualizar');
    }

    public function assign(User $user, ServiceOrder $serviceOrder): bool
    {
        return $user->canAccessSchool($serviceOrder->school)
            && $user->hasPermission('ordens_servico.atribuir', $serviceOrder->school);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceOrder $serviceOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ServiceOrder $serviceOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ServiceOrder $serviceOrder): bool
    {
        return false;
    }

    public function approve(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.aprovar', false);
    }

    public function reject(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.rejeitar', false);
    }

    public function cancel(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.cancelar', false);
    }

    public function start(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.iniciar');
    }

    public function pause(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.pausar');
    }

    public function complete(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.concluir');
    }

    public function diagnose(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.registrar_diagnostico');
    }

    public function material(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.registrar_material');
    }

    public function time(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.registrar_tempo');
    }

    public function cost(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.registrar_custo');
    }

    public function emergency(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.iniciar_emergencial');
    }

    public function ratify(User $user, ServiceOrder $order): bool
    {
        return $this->capability($user, $order, 'ordens_servico.aprovar', false);
    }

    private function capability(User $user, ServiceOrder $order, string $permission, bool $assigned = true): bool
    {
        if (! $user->canAccessSchool($order->school) || ! $user->hasPermission($permission, $order->school)) {
            return false;
        }

        return ! $assigned || $order->assigned_user_id === $user->id || $order->members()->whereKey($user->id)->exists() || $user->hasPermission('ordens_servico.atribuir', $order->school);
    }
}
