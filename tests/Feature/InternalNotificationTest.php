<?php

namespace Tests\Feature;

use App\Mail\InternalNotificationMail;
use App\Models\Environment;
use App\Models\InternalNotification;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\InternalNotificationService;
use App\Support\NotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InternalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_and_mark_only_own_notifications_as_read(): void
    {
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $otherUser = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        $otherUser->schools()->attach($school);

        $service = app(InternalNotificationService::class);
        $service->send(
            collect([$user]), $school, 'occurrence:10:created:history:25', 'occurrence.created',
            'Nova ocorrência SIG-001', 'Uma ocorrência precisa de triagem.', '/ocorrencias/10', ['occurrence_id' => 10],
        );
        $service->send(
            collect([$otherUser]), $school, 'occurrence:11:created:history:26', 'occurrence.created',
            'Notificação de outro usuário',
        );

        $notification = $user->internalNotifications()->sole();
        $otherNotification = $otherUser->internalNotifications()->sole();

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Nova ocorrência SIG-001')
            ->assertSeeText('Uma ocorrência precisa de triagem.')
            ->assertSee(route('notifications.open', $notification), false)
            ->assertDontSee('href="/ocorrencias/10"', false)
            ->assertDontSeeText('Notificação de outro usuário');

        $this->actingAs($user)->patch(route('notifications.read', $otherNotification))->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);

        $this->actingAs($user)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_without_changing_another_inbox(): void
    {
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $otherUser = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        $otherUser->schools()->attach($school);

        InternalNotification::query()->create($this->notificationData($user, $school, 'event:1'));
        InternalNotification::query()->create($this->notificationData($user, $school, 'event:2'));
        $outside = InternalNotification::query()->create($this->notificationData($otherUser, $school, 'event:3'));

        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->internalNotifications()->whereNull('read_at')->count());
        $this->assertNull($outside->fresh()->read_at);
    }

    public function test_email_preference_starts_disabled_and_user_updates_only_their_own_setting(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create();
        $otherUser = User::factory()->for($organization)->create();

        $this->assertFalse($user->fresh()->email_notifications_enabled);
        $this->assertFalse($otherUser->fresh()->email_notifications_enabled);
        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Receber notificações também por e-mail');

        $this->actingAs($user)
            ->patch(route('notifications.preferences.update'), ['email_notifications_enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->email_notifications_enabled);
        $this->assertFalse($otherUser->fresh()->email_notifications_enabled);

        $this->actingAs($user)
            ->patch(route('notifications.preferences.update'), ['email_notifications_enabled' => '0'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->email_notifications_enabled);
    }

    public function test_disabled_email_preference_does_not_disable_internal_notification(): void
    {
        Mail::fake();
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create(['email_notifications_enabled' => false]);
        $user->schools()->attach($school);

        $inserted = app(InternalNotificationService::class)->send(
            collect([$user]), $school, 'event:email-disabled', 'test.event', 'Notificação interna preservada',
        );

        $this->assertSame(1, $inserted);
        $this->assertDatabaseHas('internal_notifications', [
            'user_id' => $user->id,
            'event_key' => 'event:email-disabled',
        ]);
        Mail::assertNotQueued(InternalNotificationMail::class);
    }

    public function test_enabled_email_contains_only_approved_fields_and_authenticated_link(): void
    {
        Mail::fake();
        config()->set('mail.from.address', 'notificacoes@sigme.example');
        config()->set('mail.from.name', 'SIGME');
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $environment = Environment::factory()->for($school)->create();
        $category = OccurrenceCategory::factory()->for($organization)->create();
        $category->schools()->attach($school);
        $user = User::factory()->for($organization)->create(['email_notifications_enabled' => true]);
        $user->schools()->attach($school);
        $occurrence = Occurrence::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'environment_id' => $environment->id,
            'occurrence_category_id' => $category->id,
            'reporter_id' => $user->id,
            'protocol' => 'SIG-ESC-2026-000123',
            'status' => 'EM_TRIAGEM',
            'title' => 'Título interno sigiloso',
            'description' => 'Descrição interna sigilosa',
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'occurrence_id' => $occurrence->id,
            'created_by' => $user->id,
            'assigned_user_id' => $user->id,
            'code' => 'OS-ESC-2026-000456',
            'status' => 'APROVADA',
            'title' => 'OS interna sigilosa',
            'description' => 'Diagnóstico interno sigiloso',
        ]);
        $service = app(InternalNotificationService::class);

        $sendOccurrence = fn (): int => $service->send(
            collect([$user]),
            $school,
            "occurrence:{$occurrence->id}:created:email-test",
            'occurrence.created',
            'Título interno sigiloso',
            'Descrição interna sigilosa',
            route('occurrences.show', $occurrence),
            ['occurrence_id' => $occurrence->id, 'status' => 'EM_TRIAGEM'],
        );
        $this->assertSame(1, $sendOccurrence());
        $this->assertSame(0, $sendOccurrence());

        $this->assertSame(1, $service->send(
            collect([$user]),
            $school,
            "service-order:{$serviceOrder->id}:approved:email-test",
            'service-order.approved',
            'OS interna sigilosa',
            'Diagnóstico interno sigiloso',
            route('service-orders.show', $serviceOrder),
            ['service_order_id' => $serviceOrder->id, 'status' => 'APROVADA'],
        ));

        $occurrenceNotification = $user->internalNotifications()->where('type', 'occurrence.created')->sole();
        $serviceOrderNotification = $user->internalNotifications()->where('type', 'service-order.approved')->sole();
        Mail::assertQueued(InternalNotificationMail::class, function (InternalNotificationMail $mail) use ($occurrenceNotification, $user): bool {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->hasFrom('notificacoes@sigme.example', 'SIGME')
                && $mail->protocol === 'SIG-ESC-2026-000123'
                && $mail->event === 'occurrence.created'
                && $mail->status === 'EM_TRIAGEM'
                && $mail->link === route('notifications.open', $occurrenceNotification)
                && str_contains($html, 'SIG-ESC-2026-000123')
                && str_contains($html, 'occurrence.created')
                && str_contains($html, 'EM_TRIAGEM')
                && ! str_contains($html, 'Título interno sigiloso')
                && ! str_contains($html, 'Descrição interna sigilosa');
        });
        Mail::assertQueued(InternalNotificationMail::class, function (InternalNotificationMail $mail) use ($serviceOrderNotification): bool {
            $html = $mail->render();

            return $mail->protocol === 'OS-ESC-2026-000456'
                && $mail->event === 'service-order.approved'
                && $mail->status === 'APROVADA'
                && $mail->link === route('notifications.open', $serviceOrderNotification)
                && str_contains($html, 'OS-ESC-2026-000456')
                && ! str_contains($html, 'OS interna sigilosa')
                && ! str_contains($html, 'Diagnóstico interno sigiloso');
        });
        Mail::assertQueued(InternalNotificationMail::class, 2);
    }

    public function test_recipient_resolution_respects_activity_organization_school_and_accumulated_roles(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $school = School::factory()->for($organization)->create();
        $otherSchool = School::factory()->for($organization)->create();
        $otherOrganization = Organization::factory()->create();
        $permission = Permission::query()->create(['name' => 'Aprovar OS', 'slug' => 'ordens_servico.aprovar']);
        $schoolRole = $this->role($organization, $permission, 'school-approver', 'school');
        $secondSchoolRole = $this->role($organization, $permission, 'backup-approver', 'school');
        $organizationRole = $this->role($organization, $permission, 'network-approver', 'organization');

        $multiRoleUser = User::factory()->for($organization)->create();
        $this->assign($multiRoleUser, $schoolRole, $school);
        $this->assign($multiRoleUser, $secondSchoolRole, $school);
        $organizationUser = User::factory()->for($organization)->create();
        $this->assign($organizationUser, $organizationRole);
        $inactiveUser = User::factory()->for($organization)->create(['is_active' => false]);
        $this->assign($inactiveUser, $schoolRole, $school);
        $wrongSchoolUser = User::factory()->for($organization)->create();
        $this->assign($wrongSchoolUser, $schoolRole, $otherSchool);
        $outsideRole = $this->role($otherOrganization, $permission, 'outside-approver', 'school');
        $outsideUser = User::factory()->for($otherOrganization)->create();
        $this->assign($outsideUser, $outsideRole, School::factory()->for($otherOrganization)->create());
        $platformAdministrator = User::factory()->create(['is_platform_admin' => true]);
        User::factory()->for($organization)->create();

        $recipients = app(NotificationRecipientResolver::class)
            ->forSchoolAndPermissions($school, ['ordens_servico.aprovar', 'ordens_servico.aprovar']);

        $this->assertSame(
            collect([$multiRoleUser->id, $organizationUser->id, $platformAdministrator->id])->sort()->values()->all(),
            $recipients->pluck('id')->all(),
        );
    }

    public function test_delivery_is_deduplicated_by_user_and_event_even_when_retried(): void
    {
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        $service = app(InternalNotificationService::class);

        $firstInsertCount = $service->send(
            collect([$user, $user]), $school, 'service-order:8:approved:history:40',
            'service-order.approved', 'OS aprovada',
        );
        $retryInsertCount = $service->send(
            collect([$user]), $school, 'service-order:8:approved:history:40',
            'service-order.approved', 'OS aprovada',
        );

        $this->assertSame(1, $firstInsertCount);
        $this->assertSame(0, $retryInsertCount);
        $this->assertDatabaseCount('internal_notifications', 1);
    }

    private function role(Organization $organization, Permission $permission, string $slug, string $scope): Role
    {
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => $slug,
            'slug' => $slug,
            'scope' => $scope,
        ]);
        $role->permissions()->attach($permission);

        return $role;
    }

    private function assign(User $user, Role $role, ?School $school = null): void
    {
        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'school_id' => $school?->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function notificationData(User $user, School $school, string $eventKey): array
    {
        return [
            'user_id' => $user->id,
            'school_id' => $school->id,
            'event_key' => $eventKey,
            'type' => 'test.event',
            'title' => 'Notificação de teste',
        ];
    }
}
