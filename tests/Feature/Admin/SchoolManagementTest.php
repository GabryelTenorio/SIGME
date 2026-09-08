<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_open_school_create_and_edit_forms(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $school = School::factory()->create();

        $this->actingAs($admin)->get(route('schools.create'))->assertOk();
        $this->actingAs($admin)->get(route('schools.edit', $school))->assertOk();
    }

    public function test_school_administrator_sees_the_school_navigation_link_for_their_scope(): void
    {
        $organization = Organization::factory()->create();
        $roles = app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create();
        $admin->schools()->attach($school);
        RoleAssignment::query()->create([
            'user_id' => $admin->id,
            'role_id' => $roles['administrador-escola']->id,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->get(route('schools.index'))
            ->assertOk()
            ->assertSee('href="'.route('schools.index').'"', false);
    }

    public function test_single_school_organization_accepts_only_one_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'single_school']);

        $this->actingAs($admin)->post(route('schools.store'), [
            'organization_id' => $organization->id,
            'name' => 'Escola Independente de Demonstração',
            'code' => 'UNICA',
            'is_active' => '1',
        ])->assertRedirect(route('schools.index'));

        $this->actingAs($admin)->post(route('schools.store'), [
            'organization_id' => $organization->id,
            'name' => 'Segunda escola indevida',
            'code' => 'SEGUNDA',
            'is_active' => '1',
        ])->assertSessionHasErrors('organization_id');

        $this->assertSame(1, $organization->schools()->count());
    }

    public function test_network_organization_accepts_multiple_schools(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);

        foreach (['Centro', 'Norte', 'Sul'] as $index => $unit) {
            $this->actingAs($admin)->post(route('schools.store'), [
                'organization_id' => $organization->id,
                'name' => "Escola Unidade {$unit}",
                'code' => 'UNI-'.($index + 1),
                'is_active' => '1',
            ])->assertRedirect(route('schools.index'));
        }

        $this->assertSame(3, $organization->schools()->count());
        $this->actingAs($admin)->get(route('schools.index', ['organization_id' => $organization->id]))
            ->assertOk()
            ->assertSeeText('Escola Unidade Centro')
            ->assertSeeText('Escola Unidade Norte')
            ->assertSeeText('Escola Unidade Sul');
    }

    public function test_school_code_is_unique_only_inside_its_organization(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();
        School::factory()->for($firstOrganization)->create(['code' => 'CENTRO']);

        $this->actingAs($admin)->post(route('schools.store'), [
            'organization_id' => $secondOrganization->id,
            'name' => 'Outra Unidade Centro',
            'code' => 'CENTRO',
            'is_active' => '1',
        ])->assertRedirect(route('schools.index'));

        $this->assertDatabaseHas('schools', ['organization_id' => $secondOrganization->id, 'code' => 'CENTRO']);
    }
}
