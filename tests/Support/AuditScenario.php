<?php

namespace Tests\Support;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\AccessCatalog;

trait AuditScenario
{
    private function auditScenario(): array
    {
        $organization = Organization::factory()->create(['mode' => 'network', 'allows_student_representative' => true]);
        $school = School::factory()->for($organization)->create();
        $otherSchool = School::factory()->for($organization)->create();
        $environment = Environment::factory()->for($school)->create();
        $category = OccurrenceCategory::factory()->for($organization)->create();
        $category->schools()->attach($school);
        $roles = app(AccessCatalog::class)->provision($organization);
        $users = ['platform' => User::factory()->create(['is_platform_admin' => true, 'organization_id' => null, 'email' => 'platform@audit.invalid', 'password' => 'SigmeAuditOnly2026!'])];
        foreach ($roles as $slug => $role) {
            $user = User::factory()->for($organization)->create(['name' => $role->name, 'email' => $slug.'@audit.invalid', 'password' => 'SigmeAuditOnly2026!']);
            $user->schools()->attach($school);
            RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'school_id' => $role->scope === 'organization' ? null : $school->id]);
            $users[$slug] = $user;
        }
        $occurrence = Occurrence::factory()->create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'environment_id' => $environment->id,
            'occurrence_category_id' => $category->id, 'reporter_id' => $users['solicitante']->id,
            'status' => 'ENCAMINHADA', 'confirmed_priority' => 'HIGH', 'title' => 'Lâmpada da sala precisa de manutenção',
        ]);
        $order = ServiceOrder::factory()->create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'occurrence_id' => $occurrence->id,
            'created_by' => $users['gestor']->id, 'assigned_user_id' => $users['tecnico']->id,
            'status' => 'APROVADA', 'title' => 'Manutenção da iluminação', 'diagnosis' => 'Lâmpada queimada.',
        ]);

        return compact('organization', 'school', 'otherSchool', 'environment', 'category', 'roles', 'users', 'occurrence', 'order');
    }
}
