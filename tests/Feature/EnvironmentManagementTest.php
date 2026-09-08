<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Database\Seeders\DemoStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnvironmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_creates_normalized_environment_with_history(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $school = School::factory()->create();

        $this->actingAs($admin)->post(route('environments.store'), $this->payload($school, ['code' => ' lab 01 ']))
            ->assertRedirect(route('environments.index', ['school_id' => $school->id]));

        $environment = Environment::query()->sole();
        $this->assertSame('LAB-01', $environment->code);
        $this->assertDatabaseHas('environment_histories', ['environment_id' => $environment->id, 'user_id' => $admin->id, 'action' => 'created']);
    }

    public function test_code_is_unique_case_insensitively_per_school_but_allowed_in_another_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        [$first, $second] = School::factory()->count(2)->create();
        Environment::factory()->for($first)->create(['code' => 'LAB-01']);

        $this->actingAs($admin)->post(route('environments.store'), $this->payload($first, ['code' => 'lab 01']))
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('environments.store'), $this->payload($second, ['code' => 'lab 01']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Environment::query()->where('code', 'LAB-01')->count());
    }

    public function test_parent_must_belong_to_same_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        [$first, $second] = School::factory()->count(2)->create();
        $parent = Environment::factory()->for($second)->create();

        $this->actingAs($admin)->post(route('environments.store'), $this->payload($first, ['parent_id' => $parent->id]))
            ->assertSessionHasErrors('parent_id');
        $this->assertDatabaseCount('environments', 1);
    }

    public function test_hierarchy_cannot_form_direct_or_indirect_cycle(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $school = School::factory()->create();
        $parent = Environment::factory()->for($school)->create(['code' => 'BLOCO-A']);
        $child = Environment::factory()->for($school)->create(['code' => 'ANDAR-1', 'parent_id' => $parent->id]);

        $this->actingAs($admin)->put(route('environments.update', $parent), $this->payload($school, [
            'code' => $parent->code, 'name' => $parent->name, 'type' => $parent->type, 'parent_id' => $child->id,
        ]))->assertSessionHasErrors('parent_id');
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_school_switch_on_creation_loads_only_parents_from_selected_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        [$first, $second] = School::factory()->count(2)->create();
        $visible = Environment::factory()->for($first)->create(['name' => 'Bloco visível']);
        Environment::factory()->for($second)->create(['name' => 'Bloco oculto']);

        $this->actingAs($admin)->get(route('environments.create', ['school_id' => $first->id]))
            ->assertOk()->assertSeeText($visible->name)->assertDontSeeText('Bloco oculto');
    }

    public function test_network_admin_can_filter_without_mixing_unauthorized_organizations(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        [$first, $second] = School::factory()->count(2)->for($organization)->create();
        $outside = School::factory()->create();
        $admin = User::factory()->for($organization)->create();
        RoleAssignment::query()->create(['user_id' => $admin->id, 'role_id' => $roles['administrador-rede']->id, 'school_id' => null]);
        Environment::factory()->for($first)->create(['name' => 'Sala Centro']);
        Environment::factory()->for($second)->create(['name' => 'Sala Norte']);
        Environment::factory()->for($outside)->create(['name' => 'Sala Externa']);

        $this->actingAs($admin)->get(route('environments.index', ['school_id' => $first->id]))
            ->assertOk()->assertSeeText('Sala Centro')->assertDontSeeText('Sala Norte')->assertDontSeeText('Sala Externa');
    }

    public function test_single_school_user_does_not_see_school_switcher(): void
    {
        [$user, $school] = $this->schoolUser('gestor');
        Environment::factory()->for($school)->create();

        $this->actingAs($user)->get(route('environments.index'))
            ->assertOk()->assertDontSeeText('Todas as escolas');
    }

    public function test_manager_can_view_but_cannot_create_or_edit(): void
    {
        [$manager, $school] = $this->schoolUser('gestor');
        $environment = Environment::factory()->for($school)->create();

        $this->actingAs($manager)->get(route('environments.index'))->assertOk();
        $this->actingAs($manager)->get(route('environments.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('environments.edit', $environment))->assertForbidden();
    }

    public function test_school_admin_cannot_access_environment_from_another_school_or_organization(): void
    {
        [$admin, $allowed] = $this->schoolUser('administrador-escola');
        $sameOrganization = School::factory()->for($allowed->organization)->create();
        $outside = School::factory()->create();

        foreach ([$sameOrganization, $outside] as $school) {
            $environment = Environment::factory()->for($school)->create();
            $this->actingAs($admin)->get(route('environments.edit', $environment))->assertForbidden();
            $this->actingAs($admin)->post(route('environments.store'), $this->payload($school))->assertSessionHasErrors('school_id');
        }
    }

    public function test_deactivation_preserves_record_history_and_excludes_new_occurrence_selection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $environment = Environment::factory()->create();

        $this->actingAs($admin)->patch(route('environments.deactivate', $environment))->assertRedirect();

        $this->assertDatabaseHas('environments', ['id' => $environment->id, 'is_active' => false]);
        $this->assertDatabaseHas('environment_histories', ['environment_id' => $environment->id, 'action' => 'deactivated']);
        $this->assertFalse(Environment::query()->availableForNewOccurrences()->whereKey($environment)->exists());
        $this->assertFalse(Route::has('environments.destroy'));
    }

    public function test_demo_seeder_creates_both_requested_scenarios_idempotently(): void
    {
        $this->seed(DemoStructureSeeder::class);
        $this->seed(DemoStructureSeeder::class);

        $single = Organization::query()->where('mode', 'single_school')->sole();
        $network = Organization::query()->where('mode', 'network')->sole();
        $this->assertSame(5, $single->schools()->first()->environments()->count());
        $this->assertSame(3, $network->schools()->where('code', 'CENTRO')->first()->environments()->count());
        $this->assertSame(3, $network->schools()->where('code', 'NORTE')->first()->environments()->count());
    }

    private function schoolUser(string $roleSlug): array
    {
        $organization = Organization::factory()->create();
        $roles = app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles[$roleSlug]->id, 'school_id' => $school->id]);

        return [$user, $school];
    }

    private function payload(School $school, array $overrides = []): array
    {
        return array_merge([
            'school_id' => $school->id, 'parent_id' => null, 'code' => 'AMB-01', 'name' => 'Ambiente teste',
            'type' => 'classroom', 'building' => 'Bloco A', 'floor' => '1º andar', 'capacity' => 30,
            'description' => 'Descrição do ambiente', 'is_active' => '1',
        ], $overrides);
    }
}
