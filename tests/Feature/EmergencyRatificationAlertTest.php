<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\InternalNotification;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\AccessCatalog;
use App\Support\EmergencyRatificationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmergencyRatificationAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_notifies_only_eligible_decision_makers_once_per_order_and_day(): void
    {
        Carbon::setTestNow('2026-09-08 09:00:00');

        try {
            $organization = Organization::factory()->create(['mode' => 'network']);
            $school = School::factory()->for($organization)->create();
            $otherSchool = School::factory()->for($organization)->create();
            $creator = $this->roleUser($organization, $school, 'administrador-escola');
            $manager = $this->roleUser($organization, $school, 'gestor');
            $networkAdministrator = $this->roleUser($organization, null, 'administrador-rede');
            $inactiveManager = $this->roleUser($organization, $school, 'gestor', false);
            $otherSchoolManager = $this->roleUser($organization, $otherSchool, 'gestor');
            $technician = $this->roleUser($organization, $school, 'tecnico');
            $platformAdministrator = User::factory()->create(['is_platform_admin' => true]);
            $roles = app(AccessCatalog::class)->provision($organization);
            RoleAssignment::query()->create([
                'user_id' => $manager->id,
                'role_id' => $roles['administrador-escola']->id,
                'school_id' => $school->id,
            ]);
            $occurrence = $this->occurrence($organization, $school, $creator);
            $overdueOrder = $this->emergencyOrder($occurrence, $creator, $manager, [
                'emergency_ratification_due_at' => '2026-09-07 23:59:59',
            ]);
            $this->emergencyOrder($occurrence, $creator, $manager, [
                'emergency_ratification_due_at' => '2026-09-09 23:59:59',
            ]);
            $this->emergencyOrder($occurrence, $creator, $manager, [
                'emergency_ratification_due_at' => '2026-09-07 23:59:59',
                'emergency_ratified_at' => '2026-09-08 08:00:00',
                'emergency_ratified_by' => $networkAdministrator->id,
            ]);
            $this->emergencyOrder($occurrence, $creator, $manager, [
                'status' => 'CANCELADA',
                'emergency_ratification_due_at' => '2026-09-07 23:59:59',
            ]);

            $eventKey = "service-order:{$overdueOrder->id}:emergency-ratification-overdue:2026-09-08";
            $this->assertSame(3, $this->sendAlerts());
            $this->assertSame(0, $this->sendAlerts());

            foreach ([$manager, $networkAdministrator, $platformAdministrator] as $recipient) {
                $this->assertDatabaseHas('internal_notifications', [
                    'user_id' => $recipient->id,
                    'event_key' => $eventKey,
                    'type' => 'service-order.emergency-ratification-overdue',
                ]);
            }
            foreach ([$creator, $inactiveManager, $otherSchoolManager, $technician] as $nonRecipient) {
                $this->assertDatabaseMissing('internal_notifications', [
                    'user_id' => $nonRecipient->id,
                    'event_key' => $eventKey,
                ]);
            }
            $this->assertSame(3, InternalNotification::query()->where('event_key', $eventKey)->count());
            $this->assertDatabaseCount('internal_notifications', 3);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_weekend_reuses_the_due_date_and_next_business_day_creates_one_new_alert(): void
    {
        Carbon::setTestNow('2026-09-12 09:00:00');

        try {
            $organization = Organization::factory()->create();
            $school = School::factory()->for($organization)->create();
            $creator = $this->roleUser($organization, $school, 'administrador-escola');
            $manager = $this->roleUser($organization, $school, 'gestor');
            $occurrence = $this->occurrence($organization, $school, $creator);
            $order = $this->emergencyOrder($occurrence, $creator, $manager, [
                'emergency_ratification_due_at' => '2026-09-11 23:59:59',
            ]);

            $this->assertSame(1, $this->sendAlerts());
            $this->assertSame([$manager->id], InternalNotification::query()->pluck('user_id')->all());
            Carbon::setTestNow('2026-09-13 09:00:00');
            $this->assertSame(0, $this->sendAlerts());

            $this->assertSame(1, InternalNotification::query()
                ->where('event_key', "service-order:{$order->id}:emergency-ratification-overdue:2026-09-11")
                ->count());

            Carbon::setTestNow('2026-09-14 09:00:00');
            $this->assertSame(1, $this->sendAlerts());
            $this->assertSame(0, $this->sendAlerts());

            $this->assertSame(1, InternalNotification::query()
                ->where('event_key', "service-order:{$order->id}:emergency-ratification-overdue:2026-09-14")
                ->count());
            $this->assertDatabaseCount('internal_notifications', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function sendAlerts(): int
    {
        return app(EmergencyRatificationAlertService::class)->send();
    }

    private function roleUser(Organization $organization, ?School $school, string $roleSlug, bool $active = true): User
    {
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create(['is_active' => $active]);
        if ($school !== null) {
            $user->schools()->attach($school);
        }
        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $roles[$roleSlug]->id,
            'school_id' => $school?->id,
        ]);

        return $user;
    }

    private function occurrence(Organization $organization, School $school, User $reporter): Occurrence
    {
        $environment = Environment::factory()->for($school)->create();
        $category = OccurrenceCategory::factory()->for($organization)->create();
        $category->schools()->attach($school);

        return Occurrence::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'environment_id' => $environment->id,
            'occurrence_category_id' => $category->id,
            'reporter_id' => $reporter->id,
            'status' => 'EM_ATENDIMENTO',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function emergencyOrder(Occurrence $occurrence, User $creator, User $authorizer, array $overrides = []): ServiceOrder
    {
        return ServiceOrder::factory()->create(array_merge([
            'organization_id' => $occurrence->organization_id,
            'school_id' => $occurrence->school_id,
            'occurrence_id' => $occurrence->id,
            'created_by' => $creator->id,
            'status' => 'EM_EXECUCAO',
            'priority_snapshot' => 'URGENT',
            'approval_required' => true,
            'emergency_authorized_by' => $authorizer->id,
            'emergency_authorized_at' => '2026-09-04 10:00:00',
            'emergency_reason' => 'Risco imediato.',
        ], $overrides));
    }
}
