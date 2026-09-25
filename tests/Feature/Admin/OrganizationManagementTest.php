<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_creates_an_organization_with_default_profiles(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post(route('organizations.store'), [
            'name' => 'Escola Independente de Demonstração',
            'slug' => '',
            'mode' => 'single_school',
            'approval_threshold' => '500.00',
            'allows_student_representative' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('organizations.index'));

        $organization = Organization::query()->sole();
        $this->assertSame('escola-independente-de-demonstracao', $organization->slug);
        $this->assertSame('single_school', $organization->mode);
        $this->assertTrue($organization->allows_student_representative);
        $this->assertSame(6, Role::query()->whereBelongsTo($organization)->count());
        $this->assertSame(count(AccessCatalog::permissions()), Permission::query()->count());

        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertOk()
            ->assertSeeText('Escola Independente de Demonstração');
        $this->actingAs($admin)->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertSeeText('Editar organização');
    }

    public function test_regular_user_cannot_access_platform_organization_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('organizations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('organizations.create'))->assertForbidden();
    }

    public function test_network_with_multiple_schools_cannot_be_changed_to_single_school(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);
        School::factory()->count(2)->for($organization)->create();

        $this->actingAs($admin)->put(route('organizations.update', $organization), [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'mode' => 'single_school',
            'approval_threshold' => '',
            'is_active' => '1',
        ])->assertSessionHasErrors('mode');

        $this->assertSame('network', $organization->fresh()->mode);
    }
}
