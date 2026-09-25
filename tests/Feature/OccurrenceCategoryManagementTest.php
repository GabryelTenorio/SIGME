<?php

namespace Tests\Feature;

use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OccurrenceCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_network_admin_creates_category_for_organization(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $schools = School::factory()->count(2)->for($organization)->create();
        $admin = $this->roleUser($organization, 'administrador-rede');

        $this->actingAs($admin)->post(route('categories.store'), $this->payload($organization, [
            'name' => 'Informática / TI', 'school_ids' => $schools->pluck('id')->all(),
        ]))->assertRedirect(route('categories.index', ['organization_id' => $organization->id]));

        $category = OccurrenceCategory::query()->sole();
        $this->assertSame('informatica-ti', $category->identifier);
        $this->assertCount(2, $category->schools);
    }

    public function test_category_can_be_edited(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $category = OccurrenceCategory::factory()->create(['name' => 'Antigo', 'identifier' => 'antigo']);

        $this->actingAs($admin)->put(route('categories.update', $category), $this->payload($category->organization, ['name' => 'Novo nome']))
            ->assertRedirect();

        $this->assertDatabaseHas('occurrence_categories', ['id' => $category->id, 'name' => 'Novo nome', 'identifier' => 'novo-nome']);
    }

    public function test_deactivation_preserves_category_and_removes_it_from_new_occurrence_selection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $school = School::factory()->create();
        $category = OccurrenceCategory::factory()->for($school->organization)->create();
        $category->schools()->attach($school);

        $this->actingAs($admin)->patch(route('categories.deactivate', $category))->assertRedirect();

        $this->assertDatabaseHas('occurrence_categories', ['id' => $category->id, 'is_active' => false]);
        $this->assertFalse(OccurrenceCategory::query()->availableForSchool($school)->whereKey($category)->exists());
        $this->assertFalse(Route::has('categories.destroy'));
    }

    public function test_inactive_category_remains_visible_in_administrative_history(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $category = OccurrenceCategory::factory()->create(['name' => 'Categoria histórica', 'is_active' => false]);

        $this->actingAs($admin)->get(route('categories.index'))
            ->assertOk()->assertSeeText($category->name)->assertSeeText('Inativa');
    }

    public function test_duplicate_name_is_rejected_in_same_organization_case_insensitively(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create();
        School::factory()->for($organization)->create();
        OccurrenceCategory::factory()->for($organization)->create(['name' => 'Elétrica', 'identifier' => 'eletrica']);

        $this->actingAs($admin)->post(route('categories.store'), $this->payload($organization, ['name' => 'ELÉTRICA']))
            ->assertSessionHasErrors('identifier');
        $this->assertDatabaseCount('occurrence_categories', 1);
    }

    public function test_same_name_is_allowed_in_different_organizations(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        [$first, $second] = Organization::factory()->count(2)->create();
        School::factory()->for($first)->create();
        School::factory()->for($second)->create();
        OccurrenceCategory::factory()->for($first)->create(['name' => 'Elétrica', 'identifier' => 'eletrica']);

        $this->actingAs($admin)->post(route('categories.store'), $this->payload($second, ['name' => 'Elétrica']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, OccurrenceCategory::query()->where('identifier', 'eletrica')->count());
    }

    public function test_user_from_one_organization_cannot_access_category_from_another(): void
    {
        $first = Organization::factory()->create(['mode' => 'network']);
        $second = Organization::factory()->create(['mode' => 'network']);
        $admin = $this->roleUser($first, 'administrador-rede');
        $category = OccurrenceCategory::factory()->for($second)->create();

        $this->actingAs($admin)->get(route('categories.edit', $category))->assertForbidden();
        $this->actingAs($admin)->put(route('categories.update', $category), $this->payload($second))->assertForbidden();
    }

    public function test_network_limits_category_availability_to_selected_schools(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        [$first, $second] = School::factory()->count(2)->for($organization)->create();
        $admin = $this->roleUser($organization, 'administrador-rede');
        $category = OccurrenceCategory::factory()->for($organization)->create();
        $category->schools()->attach([$first->id, $second->id]);

        $this->actingAs($admin)->put(route('categories.availability.update', $category), ['school_ids' => [$first->id]])
            ->assertRedirect();

        $this->assertTrue(OccurrenceCategory::query()->availableForSchool($first)->whereKey($category)->exists());
        $this->assertFalse(OccurrenceCategory::query()->availableForSchool($second)->whereKey($category)->exists());
    }

    public function test_school_cannot_link_category_from_another_organization(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $school = School::factory()->for($organization)->create();
        $admin = $this->roleUser($organization, 'administrador-escola', $school);
        $outsideCategory = OccurrenceCategory::factory()->create();

        $this->actingAs($admin)->put(route('categories.availability.update', $outsideCategory), ['school_ids' => [$school->id]])
            ->assertForbidden();
        $this->assertDatabaseMissing('category_school', ['occurrence_category_id' => $outsideCategory->id, 'school_id' => $school->id]);
    }

    public function test_school_admin_in_network_cannot_change_global_definition_but_controls_own_availability(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        [$ownSchool, $otherSchool] = School::factory()->count(2)->for($organization)->create();
        $admin = $this->roleUser($organization, 'administrador-escola', $ownSchool);
        $category = OccurrenceCategory::factory()->for($organization)->create(['name' => 'Elétrica', 'identifier' => 'eletrica']);
        $category->schools()->attach($otherSchool);

        $this->actingAs($admin)->get(route('categories.edit', $category))->assertForbidden();
        $this->actingAs($admin)->put(route('categories.availability.update', $category), ['school_ids' => [$ownSchool->id]])->assertRedirect();

        $this->assertSame('Elétrica', $category->fresh()->name);
        $this->assertEqualsCanonicalizing([$ownSchool->id, $otherSchool->id], $category->schools()->pluck('schools.id')->all());
    }

    public function test_single_school_admin_manages_category_without_availability_complexity(): void
    {
        $organization = Organization::factory()->create(['mode' => 'single_school']);
        $school = School::factory()->for($organization)->create();
        $admin = $this->roleUser($organization, 'administrador-escola', $school);

        $this->actingAs($admin)->get(route('categories.create'))->assertOk()->assertSeeText('Disponibilidade automática na escola única.');
        $this->actingAs($admin)->post(route('categories.store'), $this->payload($organization, ['school_ids' => []]))->assertRedirect();

        $category = OccurrenceCategory::query()->sole();
        $this->assertTrue($category->schools->contains($school));
        $this->actingAs($admin)->get(route('categories.edit', $category))->assertOk();
    }

    public function test_single_school_manager_can_create_and_edit_category_definition(): void
    {
        $organization = Organization::factory()->create(['mode' => 'single_school']);
        $school = School::factory()->for($organization)->create();
        $manager = $this->roleUser($organization, 'gestor', $school);

        $this->actingAs($manager)->get(route('categories.index'))->assertOk()->assertSeeText('Nova categoria');
        $this->actingAs($manager)->get(route('categories.create'))->assertOk();
        $this->actingAs($manager)->post(route('categories.store'), $this->payload($organization, [
            'name' => 'Tecnologia educacional',
            'school_ids' => [$school->id],
        ]))->assertRedirect(route('categories.index', ['organization_id' => $organization->id]));

        $category = OccurrenceCategory::query()->sole();
        $this->assertTrue($category->schools->contains($school));
        $this->actingAs($manager)->get(route('categories.edit', $category))->assertOk();
    }

    public function test_network_manager_creates_category_only_for_assigned_school(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        [$ownSchool, $otherSchool] = School::factory()->count(2)->for($organization)->create();
        $manager = $this->roleUser($organization, 'gestor', $ownSchool);

        $this->actingAs($manager)->get(route('categories.create'))
            ->assertOk()->assertSeeText($ownSchool->name)->assertDontSeeText($otherSchool->name);
        $this->actingAs($manager)->post(route('categories.store'), $this->payload($organization, [
            'name' => 'Categoria indevida',
            'school_ids' => [$otherSchool->id],
        ]))->assertSessionHasErrors('school_ids');
        $this->assertDatabaseCount('occurrence_categories', 0);

        $this->actingAs($manager)->post(route('categories.store'), $this->payload($organization, [
            'name' => 'Categoria da escola',
            'school_ids' => [$ownSchool->id],
        ]))->assertRedirect();
        $this->assertEqualsCanonicalizing([$ownSchool->id], OccurrenceCategory::query()->sole()->schools()->pluck('schools.id')->all());
    }

    public function test_fallback_category_cannot_be_deactivated(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $category = OccurrenceCategory::factory()->create(['name' => 'Outros', 'identifier' => 'outros', 'is_fallback' => true]);

        $this->actingAs($admin)->patch(route('categories.deactivate', $category))->assertStatus(422);
        $this->assertTrue($category->fresh()->is_active);
    }

    private function roleUser(Organization $organization, string $roleSlug, ?School $school = null): User
    {
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create();
        if ($school) {
            $user->schools()->attach($school);
        }
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles[$roleSlug]->id, 'school_id' => $school?->id]);

        return $user;
    }

    private function payload(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organization->id,
            'name' => 'Categoria teste',
            'description' => 'Descrição opcional',
            'display_order' => 10,
            'is_active' => '1',
            'school_ids' => $organization->schools()->pluck('id')->all(),
        ], $overrides);
    }
}
