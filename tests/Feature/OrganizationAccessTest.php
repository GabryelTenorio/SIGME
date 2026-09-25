<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_permission_does_not_leak_to_another_school(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $firstSchool = School::factory()->for($organization)->create();
        $secondSchool = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $permission = Permission::query()->create([
            'name' => 'Criar ocorrências',
            'slug' => 'ocorrencias.criar',
        ]);
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Solicitante',
            'slug' => 'requester',
            'scope' => 'school',
        ]);
        $role->permissions()->attach($permission);
        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'school_id' => $firstSchool->id,
        ]);

        $this->assertTrue($user->hasPermission('ocorrencias.criar', $firstSchool));
        $this->assertFalse($user->hasPermission('ocorrencias.criar', $secondSchool));
        $this->assertTrue($user->canAccessSchool($firstSchool));
        $this->assertFalse($user->canAccessSchool($secondSchool));
    }

    public function test_organization_role_applies_to_every_school_in_the_same_organization(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $schools = School::factory()->count(2)->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $permission = Permission::query()->create([
            'name' => 'Realizar triagem',
            'slug' => 'ocorrencias.triar',
        ]);
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Administrador da rede',
            'slug' => 'network-admin',
            'scope' => 'organization',
        ]);
        $role->permissions()->attach($permission);
        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'school_id' => null,
        ]);

        foreach ($schools as $school) {
            $this->assertTrue($user->hasPermission('ocorrencias.triar', $school));
            $this->assertTrue($user->canAccessSchool($school));
        }

        $this->assertCount(2, $user->accessibleSchools());
    }

    public function test_user_cannot_access_a_school_from_another_organization(): void
    {
        $userOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->for($userOrganization)->create();
        $otherSchool = School::factory()->for($otherOrganization)->create();

        $this->assertFalse($user->canAccessSchool($otherSchool));
        $this->assertFalse($user->hasPermission('ocorrencias.criar', $otherSchool));
    }

    public function test_platform_administrator_can_access_all_schools(): void
    {
        $schools = School::factory()->count(2)->create();
        $user = User::factory()->create(['is_platform_admin' => true]);

        foreach ($schools as $school) {
            $this->assertTrue($user->canAccessSchool($school));
            $this->assertTrue($user->hasPermission('any.permission', $school));
        }
    }

    public function test_access_catalog_provisions_accumulative_default_roles(): void
    {
        $organization = Organization::factory()->create();
        $roles = app(AccessCatalog::class)->provision($organization);

        $this->assertCount(6, $roles);
        $this->assertTrue($roles->has('solicitante'));
        $this->assertTrue($roles->has('gestor'));
        $this->assertTrue($roles->has('tecnico'));
        $this->assertTrue($roles['solicitante']->permissions->contains('slug', 'ocorrencias.criar'));
        $this->assertFalse($roles['tecnico']->permissions->contains('slug', 'ordens_servico.aprovar'));
        $this->assertTrue($roles['gestor']->permissions->contains('slug', 'ordens_servico.aprovar'));
        $this->assertTrue($roles['gestor']->permissions->contains('slug', 'ordens_servico.registrar_custo'));
        $this->assertTrue($roles['gestor']->permissions->contains('slug', 'usuarios.gerenciar'));
        $this->assertFalse($roles['gestor']->permissions->contains('slug', 'usuarios.criar'));
        $this->assertTrue($roles['administrador-rede']->permissions->contains('slug', 'usuarios.criar'));
        $this->assertTrue($roles['administrador-escola']->permissions->contains('slug', 'usuarios.criar'));
        $this->assertFalse($roles['gestor']->permissions->contains('slug', 'ocorrencias.visualizar_rede'));
        $this->assertFalse($roles['gestor']->permissions->contains('slug', 'configuracoes.gerenciar'));
    }
}
