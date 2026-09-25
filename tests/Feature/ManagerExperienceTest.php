<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManagerExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_onboarding_reports_and_tenant_scoped_csv_exports(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $reporter = $this->roleUser($organization, $school, 'solicitante');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $reporter, [
            'protocol' => 'SIG-GESTAO-2026-000001',
            'status' => 'RESOLVIDA',
            'confirmed_priority' => 'HIGH',
        ]);
        $order = $this->order($occurrence, $manager, [
            'code' => 'OS-GESTAO-2026-000001',
            'status' => 'CONCLUIDA',
            'completed_at' => now(),
        ]);
        $order->workLogs()->create([
            'user_id' => $manager->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
            'duration_minutes' => 120,
            'description' => 'Atendimento concluído.',
        ]);

        [$outsideOrganization, $outsideSchool, $outsideEnvironment, $outsideCategory] = $this->structure();
        $outsideReporter = $this->roleUser($outsideOrganization, $outsideSchool, 'solicitante');
        $this->occurrence($outsideOrganization, $outsideSchool, $outsideEnvironment, $outsideCategory, $outsideReporter, [
            'protocol' => 'SIG-FORA-2026-000001',
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Primeiros passos da gestão')
            ->assertSee('4/4')
            ->assertSee('Abrir relatórios');

        $this->actingAs($manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Relatórios gerenciais')
            ->assertSee('SIG-GESTAO-2026-000001')
            ->assertDontSee('SIG-FORA-2026-000001');

        $occurrenceCsv = $this->actingAs($manager)->get(route('reports.occurrences.export'));
        $occurrenceCsv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $occurrenceCsvContent = $occurrenceCsv->streamedContent();
        $this->assertStringContainsString('SIG-GESTAO-2026-000001', $occurrenceCsvContent);
        $this->assertStringNotContainsString('SIG-FORA-2026-000001', $occurrenceCsvContent);

        $orderCsv = $this->actingAs($manager)->get(route('reports.service-orders.export'));
        $orderCsv->assertOk();
        $orderCsvContent = $orderCsv->streamedContent();
        $this->assertStringContainsString('OS-GESTAO-2026-000001', $orderCsvContent);
        $this->assertStringContainsString('2 horas', $orderCsvContent);
    }

    public function test_user_without_indicator_permission_cannot_access_reports_or_exports(): void
    {
        [$organization, $school] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');

        $this->actingAs($requester)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($requester)->get(route('reports.occurrences.export'))->assertForbidden();
        $this->actingAs($requester)->get(route('reports.service-orders.export'))->assertForbidden();
    }

    public function test_onboarding_progress_is_individual_and_can_be_reset_without_deleting_school_data(): void
    {
        [$organization, $school] = $this->structure();
        $firstManager = $this->roleUser($organization, $school, 'gestor');
        $secondManager = $this->roleUser($organization, $school, 'gestor');
        $firstManager->forceFill(['onboarding_completed_steps' => []])->save();
        $secondManager->forceFill(['onboarding_completed_steps' => ['structure']])->save();

        $this->actingAs($firstManager)
            ->get(route('onboarding.open', 'team'))
            ->assertRedirect(route('users.index'));

        $this->assertSame(['team'], $firstManager->fresh()->onboarding_completed_steps);
        $this->assertSame(['structure'], $secondManager->fresh()->onboarding_completed_steps);

        $this->actingAs($firstManager)
            ->delete(route('onboarding.reset'))
            ->assertRedirect(route('dashboard'));

        $this->assertSame([], $firstManager->fresh()->onboarding_completed_steps);
        $this->assertDatabaseHas('environments', ['school_id' => $school->id]);
        $this->assertDatabaseHas('occurrence_categories', ['organization_id' => $organization->id]);
    }

    public function test_user_without_indicator_permission_cannot_change_onboarding_progress(): void
    {
        [$organization, $school] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');

        $this->actingAs($requester)->get(route('onboarding.open', 'team'))->assertForbidden();
        $this->actingAs($requester)->delete(route('onboarding.reset'))->assertForbidden();
    }

    public function test_complete_manager_journey_over_several_days_reaches_reports_and_exports(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');

        try {
            [$organization, $school, $environment, $category] = $this->structure(['approval_threshold' => '100.00']);
            $manager = $this->roleUser($organization, $school, 'gestor');
            $requester = $this->roleUser($organization, $school, 'solicitante');
            $technician = $this->roleUser($organization, $school, 'tecnico');

            $this->actingAs($requester)->post(route('occurrences.store'), [
                'school_id' => $school->id,
                'environment_id' => $environment->id,
                'occurrence_category_id' => $category->id,
                'title' => 'Falha elétrica no laboratório',
                'description' => 'As tomadas deixaram de funcionar durante a aula.',
                'impact' => 'HIGH',
                'perceived_urgency' => 'SOON',
            ])->assertRedirect();
            $occurrence = Occurrence::query()->sole();

            Carbon::setTestNow('2026-10-02 09:00:00');
            $this->actingAs($manager)->post(route('occurrences.triage.start', $occurrence), [
                'occurrence_version' => $occurrence->fresh()->concurrencyToken(),
            ])->assertRedirect();
            $this->actingAs($manager)->post(route('occurrences.priority.confirm', $occurrence), [
                'occurrence_version' => $occurrence->fresh()->concurrencyToken(),
                'confirmed_priority' => 'HIGH',
                'triage_note' => 'Impacto confirmado na atividade pedagógica.',
            ])->assertRedirect();
            $this->actingAs($manager)->post(route('occurrences.forward', $occurrence), [
                'occurrence_version' => $occurrence->fresh()->concurrencyToken(),
                'forwarded_destination' => 'Manutenção da escola',
            ])->assertRedirect();

            Carbon::setTestNow('2026-10-03 10:00:00');
            $this->actingAs($manager)->post(route('service-orders.store'), [
                'occurrence_id' => $occurrence->id,
                'assigned_user_id' => $technician->id,
                'member_ids' => [],
                'title' => 'Reparar circuito do laboratório',
                'description' => 'Diagnosticar e corrigir o circuito elétrico.',
                'due_date' => '2026-10-06',
                'estimated_cost' => '500.00',
            ])->assertRedirect();
            $order = ServiceOrder::query()->sole();
            $this->assertSame('AGUARDANDO_APROVACAO', $order->status);
            $this->actingAs($manager)->post(route('service-orders.approve', $order))->assertRedirect();

            Carbon::setTestNow('2026-10-04 08:00:00');
            $this->actingAs($technician)->post(route('service-orders.start', $order))->assertRedirect();
            $this->actingAs($technician)->post(route('service-orders.work-logs', $order), [
                'started_at' => '2026-10-04 08:00:00',
                'ended_at' => '2026-10-04 11:30:00',
                'description' => 'Diagnóstico e substituição do disjuntor.',
            ])->assertRedirect();
            $this->actingAs($technician)->post(route('service-orders.materials', $order), [
                'description' => 'Disjuntor',
                'quantity' => '1',
                'unit' => 'un.',
                'unit_cost' => '85.00',
            ])->assertRedirect();

            Carbon::setTestNow('2026-10-05 14:00:00');
            $this->actingAs($technician)->post(route('service-orders.diagnosis', $order), [
                'diagnosis' => 'Disjuntor danificado por sobrecarga.',
            ])->assertRedirect();
            $this->actingAs($technician)->post(route('service-orders.complete', $order), [
                'solution' => 'Disjuntor substituído e circuito testado.',
            ])->assertRedirect();
            $this->assertSame('CONCLUIDA', $order->fresh()->status);
            $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);

            $this->actingAs($manager)->post(route('occurrences.close', $occurrence))->assertRedirect();
            $this->assertSame('ENCERRADA', $occurrence->fresh()->status);

            $this->actingAs($manager)->get(route('reports.index', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-05',
            ]))->assertOk()
                ->assertSee('OS concluídas')
                ->assertSee('Falha elétrica no laboratório');

            $this->actingAs($manager)->get(route('reports.service-orders.export', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-05',
            ]))->assertOk();

            $this->assertDatabaseHas('service_order_work_logs', ['service_order_id' => $order->id, 'duration_minutes' => 210]);
            $this->assertDatabaseHas('service_order_materials', ['service_order_id' => $order->id, 'total_cost' => '85.00']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @return array{Organization, School, Environment, OccurrenceCategory} */
    private function structure(array $organizationAttributes = []): array
    {
        $organization = Organization::factory()->create(array_merge(['mode' => 'network'], $organizationAttributes));
        $school = School::factory()->for($organization)->create(['is_active' => true]);
        $environment = Environment::factory()->for($school)->create(['is_active' => true]);
        $category = OccurrenceCategory::factory()->for($organization)->create(['is_active' => true]);
        $category->schools()->attach($school);

        return [$organization, $school, $environment, $category];
    }

    private function roleUser(Organization $organization, School $school, string $role): User
    {
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create(['is_active' => true]);
        $user->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles[$role]->id, 'school_id' => $school->id]);

        return $user;
    }

    private function occurrence(Organization $organization, School $school, Environment $environment, OccurrenceCategory $category, User $reporter, array $attributes = []): Occurrence
    {
        return Occurrence::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'environment_id' => $environment->id,
            'occurrence_category_id' => $category->id,
            'reporter_id' => $reporter->id,
        ], $attributes));
    }

    private function order(Occurrence $occurrence, User $responsible, array $attributes = []): ServiceOrder
    {
        $order = ServiceOrder::factory()->create(array_merge([
            'organization_id' => $occurrence->organization_id,
            'school_id' => $occurrence->school_id,
            'occurrence_id' => $occurrence->id,
            'created_by' => $responsible->id,
            'assigned_user_id' => $responsible->id,
        ], $attributes));
        $order->members()->sync([$responsible->id]);

        return $order;
    }
}
