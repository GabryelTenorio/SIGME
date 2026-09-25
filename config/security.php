<?php

$requiredTwoFactorRoles = array_values(array_filter(array_map(
    static fn (string $role): string => trim($role),
    explode(',', (string) env('TWO_FACTOR_REQUIRED_ROLES', 'administrador-rede,administrador-escola,gestor')),
)));

return [
    'two_factor' => [
        'enforce_privileged' => (bool) env('TWO_FACTOR_ENFORCE_PRIVILEGED', false),
        'required_roles' => $requiredTwoFactorRoles,
        'challenge_lifetime_minutes' => 10,
    ],
];
