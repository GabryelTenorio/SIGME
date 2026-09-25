<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_creates_user_with_multiple_roles_and_schools(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        $schools = School::factory()->count(2)->for($organization)->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Técnica Solicitante',
            'email' => 'tecnica@demo.local',
            'password' => 'senha-segura-123',
            'is_active' => '1',
            'school_ids' => $schools->pluck('id')->all(),
            'role_ids' => [$roles['solicitante']->id, $roles['tecnico']->id],
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'tecnica@demo.local')->sole();
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertCount(2, $user->schools);
        $this->assertSame(4, RoleAssignment::query()->whereBelongsTo($user)->count());
        $this->assertTrue($user->hasPermission('ocorrencias.criar', $schools->first()));
        $this->assertTrue($user->hasPermission('ordens_servico.executar', $schools->last()));
        $this->assertFalse($user->hasPermission('ordens_servico.aprovar', $schools->first()));

        $this->actingAs($admin)->get(route('users.index', ['organization_id' => $organization->id]))
            ->assertOk()
            ->assertSeeText('Técnica Solicitante');
        $this->actingAs($admin)->get(route('users.edit', $user))
            ->assertOk()
            ->assertSeeText('Editar usuário');
    }

    public function test_organization_administrator_cannot_create_user_in_another_organization(): void
    {
        $firstOrganization = Organization::factory()->create(['mode' => 'network']);
        $secondOrganization = Organization::factory()->create(['mode' => 'network']);
        $firstRoles = app(AccessCatalog::class)->provision($firstOrganization);
        $secondRoles = app(AccessCatalog::class)->provision($secondOrganization);
        $firstSchool = School::factory()->for($firstOrganization)->create();
        $secondSchool = School::factory()->for($secondOrganization)->create();
        $admin = User::factory()->for($firstOrganization)->create();
        RoleAssignment::query()->create([
            'user_id' => $admin->id,
            'role_id' => $firstRoles['administrador-rede']->id,
            'school_id' => null,
        ]);

        $this->actingAs($admin)->post(route('users.store'), [
            'organization_id' => $secondOrganization->id,
            'name' => 'Usuário fora do escopo',
            'email' => 'fora@demo.local',
            'password' => 'senha-segura-123',
            'is_active' => '1',
            'school_ids' => [$secondSchool->id],
            'role_ids' => [$secondRoles['solicitante']->id],
        ])->assertSessionHasErrors(['school_ids', 'role_ids']);

        $this->assertDatabaseMissing('users', ['email' => 'fora@demo.local']);
        $this->assertTrue($admin->canAccessSchool($firstSchool));
        $this->assertFalse($admin->canAccessSchool($secondSchool));
    }

    public function test_user_is_deactivated_without_being_deleted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create();
        $roles = app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles['solicitante']->id, 'school_id' => $school->id]);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'organization_id' => $organization->id,
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'school_ids' => [$school->id],
            'role_ids' => [$roles['solicitante']->id],
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_school_manager_can_manage_existing_users_but_cannot_create_new_accounts(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();
        $manager = User::factory()->for($organization)->create();
        $manager->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $manager->id, 'role_id' => $roles['gestor']->id, 'school_id' => $school->id]);
        $technician = User::factory()->for($organization)->create();
        $technician->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $technician->id, 'role_id' => $roles['tecnico']->id, 'school_id' => $school->id]);

        $this->actingAs($manager)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSeeText($technician->name)
            ->assertDontSeeText('Novo usuário');

        $this->actingAs($manager)
            ->get(route('users.edit', $technician))
            ->assertOk()
            ->assertSeeText('Editar usuário');

        $this->actingAs($manager)
            ->get(route('users.create'))
            ->assertForbidden();

        $this->actingAs($manager)->post(route('users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Técnico da escola',
            'email' => 'tecnico.escola@example.test',
            'is_active' => '1',
            'school_ids' => [$school->id],
            'role_ids' => [$roles['tecnico']->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'tecnico.escola@example.test']);
    }

    public function test_manager_profile_accepts_exactly_one_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        $schools = School::factory()->count(2)->for($organization)->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Gestor de duas escolas',
            'email' => 'gestor.duas@example.test',
            'is_active' => '1',
            'school_ids' => $schools->pluck('id')->all(),
            'role_ids' => [$roles['gestor']->id],
        ])->assertSessionHasErrors('school_ids');

        $this->assertDatabaseMissing('users', ['email' => 'gestor.duas@example.test']);
    }

    public function test_reprovisioning_removes_the_redundant_occurrence_capability_without_expanding_access(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        $redundantPermission = Permission::query()->create([
            'slug' => 'ocorrencias.visualizar_todas',
            'name' => 'Visualizar todas as ocorrências',
        ]);
        $roles['gestor']->permissions()->attach($redundantPermission);

        app(AccessCatalog::class)->provision($organization);

        $this->assertArrayNotHasKey('ocorrencias.visualizar_todas', AccessCatalog::permissions());
        $this->assertNotContains('ocorrencias.visualizar_todas', AccessCatalog::roles()['gestor']['permissions']);
        $this->assertFalse($roles['gestor']->fresh()->permissions()->whereKey($redundantPermission->id)->exists());
        $this->assertTrue($roles['gestor']->fresh()->permissions()->where('slug', 'ocorrencias.visualizar_escola')->exists());
    }

    public function test_user_interface_lists_only_standard_profiles_and_rejects_a_custom_profile_id(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);
        app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();
        $customRole = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Perfil personalizado indevido',
            'slug' => 'personalizado',
            'scope' => 'school',
            'is_system' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('users.create', ['organization_id' => $organization->id]));

        $response->assertOk()
            ->assertSeeText('Administrador da rede')
            ->assertSeeText('Representante de aluno')
            ->assertDontSeeText($customRole->name)
            ->assertDontSee('permission_ids', false);

        $this->actingAs($admin)->post(route('users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Usuário com perfil indevido',
            'email' => 'perfil.invalido@demo.local',
            'password' => 'senha-segura-123',
            'is_active' => '1',
            'school_ids' => [$school->id],
            'role_ids' => [$customRole->id],
        ])->assertSessionHasErrors(['role_ids']);

        $this->assertDatabaseMissing('users', ['email' => 'perfil.invalido@demo.local']);
    }

    public function test_no_profile_or_permission_customization_route_is_exposed(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(fn ($route) => str_starts_with((string) $route->getName(), 'roles.')));
        $this->assertFalse($routes->contains(fn ($route) => str_starts_with((string) $route->getName(), 'permissions.')));
        $this->assertFalse($routes->contains(fn ($route) => preg_match('#(^|/)(perfis|roles|permissoes|permissions)(/|$)#', $route->uri()) === 1));
    }
}
