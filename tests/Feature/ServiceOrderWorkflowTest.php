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
use App\Models\ServiceOrderHistory;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_order_only_from_forwarded_occurrence_with_server_code_snapshot_and_history(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '500.00']);
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence, $technician))->assertRedirect();

        $order = ServiceOrder::query()->sole();
        $this->assertSame("OS-{$school->code}-".now()->year.'-000001', $order->code);
        $this->assertSame('HIGH', $order->priority_snapshot);
        $this->assertSame('APROVADA', $order->status);
        $this->assertFalse($order->approval_required);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $order->id, 'event_type' => 'created']);
        $this->assertDatabaseHas('service_order_members', ['service_order_id' => $order->id, 'user_id' => $technician->id]);
        $history = ServiceOrderHistory::query()->where('event_type', 'created')->sole();
        $this->assertDatabaseHas('internal_notifications', [
            'user_id' => $technician->id,
            'event_key' => "service-order:{$order->id}:assigned:history:{$history->id}",
            'type' => 'service-order.assigned',
        ]);
        $this->assertDatabaseCount('internal_notifications', 1);
    }

    public function test_manager_changes_team_with_eligibility_history_notifications_and_no_duplicate_event(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $firstTechnician = $this->roleUser($organization, $school, 'tecnico');
        $secondTechnician = $this->roleUser($organization, $school, 'tecnico');
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $order = $this->order($occurrence, $firstTechnician);

        $this->actingAs($firstTechnician)
            ->patch(route('service-orders.team.update', $order), ['assigned_user_id' => $secondTechnician->id, 'member_ids' => [$firstTechnician->id]])
            ->assertForbidden();
        $this->actingAs($manager)
            ->patch(route('service-orders.team.update', $order), ['assigned_user_id' => $secondTechnician->id, 'member_ids' => [$requester->id]])
            ->assertSessionHasErrors('member_ids.0');

        $payload = ['assigned_user_id' => $secondTechnician->id, 'member_ids' => [$firstTechnician->id, $secondTechnician->id]];
        $this->actingAs($manager)->patch(route('service-orders.team.update', $order), $payload)->assertRedirect();

        $order->refresh();
        $this->assertSame($secondTechnician->id, $order->assigned_user_id);
        $this->assertSame(
            collect([$firstTechnician->id, $secondTechnician->id])->sort()->values()->all(),
            $order->members()->pluck('users.id')->sort()->values()->all(),
        );
        $history = ServiceOrderHistory::query()->where('event_type', 'team_changed')->sole();
        foreach ([$firstTechnician, $secondTechnician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', [
                'user_id' => $recipient->id,
                'event_key' => "service-order:{$order->id}:team_changed:history:{$history->id}",
                'type' => 'service-order.team_changed',
            ]);
        }

        $this->actingAs($manager)->patch(route('service-orders.team.update', $order), $payload)->assertRedirect();
        $this->assertSame(1, ServiceOrderHistory::query()->where('event_type', 'team_changed')->count());
        $this->assertSame(2, InternalNotification::query()->where('type', 'service-order.team_changed')->count());
    }

    public function test_new_orders_start_only_as_approved_or_awaiting_approval(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '100.00']);
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $technician, ['estimated_cost' => '100.00']))
            ->assertRedirect();

        foreach ([
            ['estimated_cost' => '100.01'],
            ['external_service' => '1', ...$this->externalServiceData()],
            ['asset_replacement' => '1'],
            ['asset_disposal' => '1'],
            ['extraordinary_purchase' => '1'],
        ] as $approvalTrigger) {
            $this->actingAs($manager)
                ->post(route('service-orders.store'), $this->payload($occurrence, $technician, $approvalTrigger))
                ->assertRedirect();
        }

        $this->assertSame(
            ['APROVADA', 'AGUARDANDO_APROVACAO', 'AGUARDANDO_APROVACAO', 'AGUARDANDO_APROVACAO', 'AGUARDANDO_APROVACAO', 'AGUARDANDO_APROVACAO'],
            ServiceOrder::query()->orderBy('id')->pluck('status')->all(),
        );
        $this->assertNotContains('PLANEJADA', ServiceOrder::STATUSES);
        $this->assertDatabaseMissing('service_orders', ['status' => 'PLANEJADA']);
        $this->assertSame(
            ServiceOrder::query()->count(),
            ServiceOrderHistory::query()->where('event_type', 'created')->count(),
        );
    }

    public function test_invalid_occurrence_and_user_without_permission_cannot_create_order(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $occurrence->update(['status' => 'EM_TRIAGEM']);

        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence))->assertSessionHasErrors('occurrence_id');
        $occurrence->update(['status' => 'ENCAMINHADA']);
        $this->actingAs($requester)->post(route('service-orders.store'), $this->payload($occurrence))->assertForbidden();
        $this->assertDatabaseCount('service_orders', 0);
    }

    public function test_only_active_users_with_technical_capabilities_in_the_occurrence_school_can_be_assigned(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $inactiveTechnician = $this->roleUser($organization, $school, 'tecnico');
        $inactiveTechnician->update(['is_active' => false]);
        [, $otherSchool] = $this->structure($organization);
        $otherSchoolTechnician = $this->roleUser($organization, $otherSchool, 'tecnico');
        [$outsideOrganization, $outsideSchool] = $this->structure();
        $outsideTechnician = $this->roleUser($outsideOrganization, $outsideSchool, 'tecnico');
        $eligibleTechnician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)
            ->get(route('service-orders.create', ['occurrence_id' => $occurrence->id]))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->pluck('id')->all() === [$eligibleTechnician->id]);

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $requester))
            ->assertSessionHasErrors('assigned_user_id');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $eligibleTechnician, ['member_ids' => [$requester->id]]))
            ->assertSessionHasErrors('member_ids.0');

        foreach ([$inactiveTechnician, $otherSchoolTechnician, $outsideTechnician] as $ineligibleTechnician) {
            $this->actingAs($manager)
                ->post(route('service-orders.store'), $this->payload($occurrence, $ineligibleTechnician))
                ->assertSessionHasErrors('assigned_user_id');
        }

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, overrides: ['assigned_user_id' => User::query()->max('id') + 1000]))
            ->assertSessionHasErrors('assigned_user_id');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $eligibleTechnician, ['member_ids' => [$eligibleTechnician->id]]))
            ->assertRedirect();

        $this->assertDatabaseCount('service_orders', 1);
        $this->assertDatabaseHas('service_order_members', [
            'service_order_id' => ServiceOrder::query()->sole()->id,
            'user_id' => $eligibleTechnician->id,
        ]);
    }

    public function test_external_service_requires_structured_provider_data_and_an_eligible_internal_responsible(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, overrides: ['external_service' => '1']))
            ->assertSessionHasErrors(['assigned_user_id', 'external_provider_name', 'external_service_description']);

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $requester, [
                'external_service' => '1',
                ...$this->externalServiceData(),
            ]))
            ->assertSessionHasErrors('assigned_user_id');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $technician, [
                'external_service' => '1',
                ...$this->externalServiceData(),
            ]))
            ->assertRedirect();

        $order = ServiceOrder::query()->sole();
        $this->assertTrue($order->external_service);
        $this->assertTrue($order->approval_required);
        $this->assertSame('AGUARDANDO_APROVACAO', $order->status);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame('Manutenção Externa Ltda.', $order->external_provider_name);
        $this->assertSame('Reparar o equipamento e emitir relatório técnico.', $order->external_service_description);
        $this->assertSame('contato@example.test', $order->external_provider_contact);
        $this->assertSame('12.345.678/0001-90', $order->external_provider_tax_id);
        $this->assertDatabaseHas('service_order_members', ['service_order_id' => $order->id, 'user_id' => $technician->id]);

        $this->actingAs($manager)
            ->get(route('service-orders.show', $order))
            ->assertOk()
            ->assertSeeText('Manutenção Externa Ltda.')
            ->assertSeeText('Reparar o equipamento e emitir relatório técnico.')
            ->assertSeeText($technician->name);
    }

    public function test_external_service_internal_responsible_cannot_be_removed_or_replaced_by_an_ineligible_user(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $replacement = $this->roleUser($organization, $school, 'tecnico');
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $order = $this->order($occurrence, $technician, [
            'external_service' => true,
            ...$this->externalServiceData(),
        ]);

        $this->actingAs($manager)
            ->patch(route('service-orders.team.update', $order), ['assigned_user_id' => null, 'member_ids' => [$technician->id]])
            ->assertSessionHasErrors('assigned_user_id');
        $this->actingAs($manager)
            ->patch(route('service-orders.team.update', $order), ['assigned_user_id' => $requester->id, 'member_ids' => [$technician->id]])
            ->assertSessionHasErrors('assigned_user_id');
        $this->actingAs($manager)
            ->patch(route('service-orders.team.update', $order), ['assigned_user_id' => $replacement->id, 'member_ids' => [$technician->id]])
            ->assertRedirect();

        $this->assertSame($replacement->id, $order->fresh()->assigned_user_id);
        $this->assertDatabaseHas('service_order_members', ['service_order_id' => $order->id, 'user_id' => $replacement->id]);
        $this->assertSame(1, $order->histories()->where('event_type', 'team_changed')->count());
    }

    public function test_external_provider_data_does_not_create_identity_role_permission_or_system_access(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $userCount = User::query()->count();
        $roleAssignmentCount = RoleAssignment::query()->count();
        $schoolUserCount = DB::table('school_user')->count();
        $permissionAssignmentCount = DB::table('permission_role')->count();

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $technician, [
                'external_service' => '1',
                ...$this->externalServiceData(),
            ]))
            ->assertRedirect();

        $order = ServiceOrder::query()->sole();
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($roleAssignmentCount, RoleAssignment::query()->count());
        $this->assertSame($schoolUserCount, DB::table('school_user')->count());
        $this->assertSame($permissionAssignmentCount, DB::table('permission_role')->count());
        $this->assertDatabaseMissing('users', ['email' => 'contato@example.test']);
        $this->assertDatabaseMissing('users', ['name' => 'Manutenção Externa Ltda.']);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame([$technician->id], $order->members()->pluck('users.id')->all());

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get(route('service-orders.show', $order))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => 'contato@example.test',
            'password' => 'qualquer-senha',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_external_service_keeps_cost_documents_evidence_and_completion_linked_to_the_same_order(): void
    {
        Storage::fake('local');
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '500.00']);
        $manager = $this->roleUser($organization, $school, 'gestor');
        $approver = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $technician, [
                'external_service' => '1',
                'estimated_cost' => '1250.75',
                ...$this->externalServiceData(),
            ]))
            ->assertRedirect();

        $order = ServiceOrder::query()->sole();
        $this->assertSame('AGUARDANDO_APROVACAO', $order->status);
        $this->actingAs($approver)->post(route('service-orders.approve', $order))->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.start', $order))->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.updates', $order), [
            'message' => 'Fornecedor recebeu o equipamento e iniciou o reparo.',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.costs', $order), [
            'type' => 'EXTERNAL_SERVICE',
            'description' => 'Reparo contratado',
            'amount' => '1250.75',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.attachments', $order), [
            'evidence' => UploadedFile::fake()->create('laudo-tecnico.pdf', 512, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.attachments', $order), [
            'evidence' => UploadedFile::fake()->image('equipamento-reparado.jpg'),
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.diagnosis', $order), [
            'diagnosis' => 'Componente interno danificado e indisponível para reparo local.',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.complete', $order), [
            'solution' => 'Componente substituído pelo fornecedor e equipamento testado.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('CONCLUIDA', $order->status);
        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame('Manutenção Externa Ltda.', $order->external_provider_name);
        $this->assertSame('1250.75', $order->additionalCostTotal());
        $this->assertDatabaseHas('service_order_costs', [
            'service_order_id' => $order->id,
            'created_by' => $technician->id,
            'type' => 'EXTERNAL_SERVICE',
            'description' => 'Reparo contratado',
            'amount' => '1250.75',
        ]);

        $attachments = $order->attachments()->orderBy('id')->get();
        $this->assertSame(['laudo-tecnico.pdf', 'equipamento-reparado.jpg'], $attachments->pluck('original_name')->all());
        $this->assertSame([$technician->id, $technician->id], $attachments->pluck('uploaded_by')->all());
        foreach ($attachments as $attachment) {
            $this->assertSame($order->id, $attachment->attachable_id);
            $this->assertSame(ServiceOrder::class, $attachment->attachable_type);
            $this->assertSame('local', $attachment->disk);
            Storage::disk('local')->assertExists($attachment->path);
        }

        foreach (['update_added', 'cost_registered', 'attachment_added', 'diagnosis_registered', 'completed'] as $eventType) {
            $this->assertDatabaseHas('service_order_histories', [
                'service_order_id' => $order->id,
                'actor_id' => $technician->id,
                'event_type' => $eventType,
            ]);
        }
        $this->assertSame(2, $order->histories()->where('event_type', 'attachment_added')->count());
        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $occurrence->id,
            'actor_id' => $technician->id,
            'event_type' => 'resolved_by_service_orders',
        ]);
    }

    public function test_codes_are_unique_and_sequential_per_school_and_year(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');

        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence));
        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence, overrides: ['title' => 'Segunda frente de trabalho']));

        $this->assertSame([1, 2], ServiceOrder::query()->orderBy('id')->pluck('code_sequence')->all());
        $this->assertSame(2, ServiceOrder::query()->distinct()->count('code'));

        [, $otherSchool, $otherOccurrence] = $this->structure($organization);
        $otherManager = $this->roleUser($organization, $otherSchool, 'gestor');
        $this->actingAs($otherManager)->post(route('service-orders.store'), $this->payload($otherOccurrence));
        $this->assertSame(1, ServiceOrder::query()->where('school_id', $otherSchool->id)->sole()->code_sequence);
    }

    public function test_threshold_and_special_conditions_require_approval_before_execution(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '100.00']);
        $manager = $this->roleUser($organization, $school, 'gestor');
        $approver = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence, $technician, ['estimated_cost' => '100.01']));
        $order = ServiceOrder::query()->sole();
        $this->assertSame('AGUARDANDO_APROVACAO', $order->status);
        $this->assertDatabaseHas('internal_notifications', ['user_id' => $approver->id, 'type' => 'service-order.awaiting_approval']);
        $this->assertDatabaseMissing('internal_notifications', ['user_id' => $manager->id, 'type' => 'service-order.awaiting_approval']);
        $this->actingAs($technician)->post(route('service-orders.start', $order))->assertSessionHasErrors('status');
        $this->actingAs($technician)->post(route('service-orders.approve', $order))->assertForbidden();
        $this->actingAs($approver)->post(route('service-orders.approve', $order))->assertRedirect();
        $this->assertSame('APROVADA', $order->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $order->id, 'actor_id' => $approver->id, 'event_type' => 'approved']);
        foreach ([$manager, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'service-order.approved']);
        }

        $second = $this->occurrence($organization, $school, $occurrence->environment, $occurrence->category, $occurrence->reporter);
        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($second, $technician, ['estimated_cost' => '1.00', 'external_service' => '1', ...$this->externalServiceData()]));
        $this->assertSame('AGUARDANDO_APROVACAO', ServiceOrder::query()->latest('id')->first()->status);
    }

    public function test_creator_cannot_approve_or_reject_own_order_even_with_accumulated_roles_or_platform_access(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '10.00']);
        $creator = $this->roleUser($organization, $school, 'gestor');
        $approver = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $roles = app(AccessCatalog::class)->provision($organization);
        RoleAssignment::query()->create([
            'user_id' => $creator->id,
            'role_id' => $roles['administrador-escola']->id,
            'school_id' => $school->id,
        ]);
        [, $otherSchool] = $this->structure($organization);
        $otherSchoolApprover = $this->roleUser($organization, $otherSchool, 'gestor');

        $this->actingAs($creator)->post(
            route('service-orders.store'),
            $this->payload($occurrence, $technician, ['estimated_cost' => '20.00']),
        );
        $orderToApprove = ServiceOrder::query()->sole();

        $this->actingAs($creator)
            ->post(route('service-orders.approve', $orderToApprove))
            ->assertSessionHasErrors('approval');
        $this->assertSame('AGUARDANDO_APROVACAO', $orderToApprove->fresh()->status);

        $this->actingAs($otherSchoolApprover)
            ->post(route('service-orders.approve', $orderToApprove))
            ->assertForbidden();

        $this->actingAs($approver)
            ->post(route('service-orders.approve', $orderToApprove))
            ->assertRedirect();
        $this->assertSame('APROVADA', $orderToApprove->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $orderToApprove->id,
            'actor_id' => $approver->id,
            'event_type' => 'approved',
        ]);

        $occurrenceToReject = $this->occurrence(
            $organization,
            $school,
            $occurrence->environment,
            $occurrence->category,
            $occurrence->reporter,
        );
        $this->actingAs($creator)->post(
            route('service-orders.store'),
            $this->payload($occurrenceToReject, $technician, ['estimated_cost' => '20.00']),
        );
        $orderToReject = ServiceOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($creator)
            ->post(route('service-orders.reject', $orderToReject), ['reason' => 'Tentativa do próprio criador.'])
            ->assertSessionHasErrors('approval');
        $this->assertSame('AGUARDANDO_APROVACAO', $orderToReject->fresh()->status);

        $this->actingAs($approver)
            ->post(route('service-orders.reject', $orderToReject), ['reason' => 'Custo não autorizado.'])
            ->assertRedirect();
        $this->assertSame('REJEITADA', $orderToReject->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $orderToReject->id,
            'actor_id' => $approver->id,
            'event_type' => 'rejected',
        ]);
        foreach ([$creator, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'service-order.rejected']);
        }

        $platformAdmin = User::factory()->for($organization)->create(['is_platform_admin' => true]);
        $platformOrder = $this->order($occurrence, $technician, [
            'status' => 'AGUARDANDO_APROVACAO',
            'approval_required' => true,
            'created_by' => $platformAdmin->id,
        ]);

        $this->actingAs($platformAdmin)
            ->post(route('service-orders.approve', $platformOrder))
            ->assertSessionHasErrors('approval');
        $this->actingAs($platformAdmin)
            ->post(route('service-orders.reject', $platformOrder), ['reason' => 'Autorrejeição administrativa.'])
            ->assertSessionHasErrors('approval');
        $this->assertSame('AGUARDANDO_APROVACAO', $platformOrder->fresh()->status);
    }

    public function test_rejection_and_emergency_are_audited_without_deletion(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '10.00']);
        $admin = $this->roleUser($organization, $school, 'administrador-escola');
        $approver = $this->roleUser($organization, $school, 'administrador-escola');
        $emergencyAuthorizer = $this->roleUser($organization, $school, 'administrador-escola');
        $this->actingAs($admin)->post(route('service-orders.store'), $this->payload($occurrence, overrides: ['estimated_cost' => '20.00']));
        $rejected = ServiceOrder::query()->sole();
        $this->actingAs($approver)->post(route('service-orders.reject', $rejected), ['reason' => 'Custo não autorizado.'])->assertRedirect();
        $this->assertSame('REJEITADA', $rejected->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $rejected->id, 'actor_id' => $approver->id, 'event_type' => 'rejected']);

        $urgentOccurrence = $this->occurrence($organization, $school, $occurrence->environment, $occurrence->category, $occurrence->reporter, ['confirmed_priority' => 'URGENT']);
        $this->actingAs($admin)->post(route('service-orders.store'), $this->payload($urgentOccurrence, $admin, ['estimated_cost' => '20.00']));
        $urgent = ServiceOrder::query()->latest('id')->first();
        $this->actingAs($emergencyAuthorizer)->post(route('service-orders.emergency', $urgent), ['reason' => 'Risco elétrico imediato.'])->assertRedirect();
        $this->assertSame('EM_EXECUCAO', $urgent->fresh()->status);
        $this->assertSame('EM_ATENDIMENTO', $urgentOccurrence->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $urgent->id, 'event_type' => 'emergency_started']);
        $this->assertFalse(Route::has('service-orders.destroy'));
        $this->assertDatabaseCount('service_orders', 2);
    }

    public function test_emergency_start_requires_independent_authorizer_eligible_executor_and_next_business_day_deadline(): void
    {
        Carbon::setTestNow('2026-09-04 10:00:00');

        try {
            [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '10.00']);
            $creator = $this->roleUser($organization, $school, 'administrador-escola');
            $authorizer = $this->roleUser($organization, $school, 'gestor');
            $technician = $this->roleUser($organization, $school, 'tecnico');
            [, $otherSchool] = $this->structure($organization);
            $otherSchoolAuthorizer = $this->roleUser($organization, $otherSchool, 'administrador-escola');
            $occurrence->update(['confirmed_priority' => 'URGENT']);

            $this->actingAs($creator)->post(
                route('service-orders.store'),
                $this->payload($occurrence, $technician, ['estimated_cost' => '20.00']),
            );
            $order = ServiceOrder::query()->sole();

            $this->actingAs($creator)
                ->post(route('service-orders.emergency', $order), ['reason' => 'Autoautorização indevida.'])
                ->assertSessionHasErrors('emergency_authorizer');
            $this->assertSame('AGUARDANDO_APROVACAO', $order->fresh()->status);

            $this->actingAs($otherSchoolAuthorizer)
                ->post(route('service-orders.emergency', $order), ['reason' => 'Fora do escopo.'])
                ->assertForbidden();

            $this->actingAs($authorizer)
                ->post(route('service-orders.emergency', $order))
                ->assertSessionHasErrors('reason');

            $this->actingAs($authorizer)
                ->post(route('service-orders.emergency', $order), ['reason' => 'Risco elétrico imediato.'])
                ->assertRedirect();

            $order->refresh();
            $this->assertSame('EM_EXECUCAO', $order->status);
            $this->assertSame($authorizer->id, $order->emergency_authorized_by);
            $this->assertSame('Risco elétrico imediato.', $order->emergency_reason);
            $this->assertSame('2026-09-07 23:59:59', $order->emergency_ratification_due_at->format('Y-m-d H:i:s'));
            $this->assertTrue($order->hasPendingEmergencyRatification());
            $this->assertDatabaseHas('service_order_histories', [
                'service_order_id' => $order->id,
                'actor_id' => $authorizer->id,
                'event_type' => 'emergency_started',
            ]);

            $requester = $this->roleUser($organization, $school, 'solicitante');
            $legacyOrder = $this->order($occurrence, $requester, [
                'status' => 'AGUARDANDO_APROVACAO',
                'priority_snapshot' => 'URGENT',
                'approval_required' => true,
                'created_by' => $creator->id,
            ]);
            $this->actingAs($authorizer)
                ->post(route('service-orders.emergency', $legacyOrder), ['reason' => 'Executor inválido.'])
                ->assertSessionHasErrors('assigned_user_id');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_emergency_ratification_requires_eligible_non_creator_and_is_audited(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');

        try {
            [$organization, $school, $occurrence] = $this->structure();
            $creator = $this->roleUser($organization, $school, 'administrador-escola');
            $authorizer = $this->roleUser($organization, $school, 'gestor');
            $ratifier = $this->roleUser($organization, $school, 'gestor');
            $technician = $this->roleUser($organization, $school, 'tecnico');
            [, $otherSchool] = $this->structure($organization);
            $otherSchoolRatifier = $this->roleUser($organization, $otherSchool, 'gestor');
            $order = $this->order($occurrence, $technician, [
                'status' => 'EM_EXECUCAO',
                'priority_snapshot' => 'URGENT',
                'approval_required' => true,
                'created_by' => $creator->id,
                'emergency_authorized_by' => $authorizer->id,
                'emergency_authorized_at' => now(),
                'emergency_reason' => 'Risco imediato.',
                'emergency_ratification_due_at' => now()->addDay()->endOfDay(),
            ]);

            $this->actingAs($creator)
                ->post(route('service-orders.ratify-emergency', $order))
                ->assertSessionHasErrors('emergency_ratification');
            $this->actingAs($otherSchoolRatifier)
                ->post(route('service-orders.ratify-emergency', $order))
                ->assertForbidden();

            $this->actingAs($ratifier)
                ->get(route('service-orders.show', $order))
                ->assertOk()
                ->assertSeeText('Ratificar emergência');
            $this->actingAs($ratifier)
                ->post(route('service-orders.ratify-emergency', $order))
                ->assertRedirect();

            $order->refresh();
            $this->assertSame($ratifier->id, $order->emergency_ratified_by);
            $this->assertNotNull($order->emergency_ratified_at);
            $this->assertFalse($order->hasPendingEmergencyRatification());
            $this->assertDatabaseHas('service_order_histories', [
                'service_order_id' => $order->id,
                'actor_id' => $ratifier->id,
                'event_type' => 'emergency_ratified',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_overdue_emergency_ratification_blocks_completion_but_not_technical_work(): void
    {
        Carbon::setTestNow('2026-09-03 09:00:00');

        try {
            [$organization, $school, $occurrence] = $this->structure();
            $creator = $this->roleUser($organization, $school, 'administrador-escola');
            $authorizer = $this->roleUser($organization, $school, 'gestor');
            $ratifier = $this->roleUser($organization, $school, 'gestor');
            $technician = $this->roleUser($organization, $school, 'tecnico');
            $order = $this->order($occurrence, $technician, [
                'status' => 'EM_EXECUCAO',
                'priority_snapshot' => 'URGENT',
                'approval_required' => true,
                'created_by' => $creator->id,
                'diagnosis' => 'Falha elétrica crítica.',
                'emergency_authorized_by' => $authorizer->id,
                'emergency_authorized_at' => '2026-09-01 10:00:00',
                'emergency_reason' => 'Risco imediato.',
                'emergency_ratification_due_at' => '2026-09-02 23:59:59',
            ]);
            $occurrence->update(['status' => 'EM_ATENDIMENTO']);

            $this->assertTrue($order->emergencyRatificationIsOverdue());
            $this->actingAs($technician)
                ->post(route('service-orders.updates', $order), ['message' => 'Execução técnica continua.'])
                ->assertRedirect();
            $this->actingAs($technician)
                ->post(route('service-orders.complete', $order), ['solution' => 'Circuito reparado.'])
                ->assertSessionHasErrors('emergency_ratification');
            $this->assertSame('EM_EXECUCAO', $order->fresh()->status);

            $this->actingAs($ratifier)
                ->post(route('service-orders.ratify-emergency', $order))
                ->assertRedirect();
            $this->actingAs($technician)
                ->post(route('service-orders.complete', $order), ['solution' => 'Circuito reparado.'])
                ->assertRedirect();
            $this->assertSame('CONCLUIDA', $order->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_only_assigned_technician_can_view_and_start_order_and_tenant_is_isolated(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $assigned = $this->roleUser($organization, $school, 'tecnico');
        $otherTechnician = $this->roleUser($organization, $school, 'tecnico');
        $this->actingAs($manager)->post(route('service-orders.store'), $this->payload($occurrence, $assigned));
        $order = ServiceOrder::query()->sole();

        $this->actingAs($assigned)->get(route('service-orders.show', $order))->assertOk();
        $this->actingAs($otherTechnician)->get(route('service-orders.show', $order))->assertForbidden();
        $this->actingAs($otherTechnician)->post(route('service-orders.start', $order))->assertForbidden();
        $this->actingAs($assigned)->post(route('service-orders.start', $order))->assertRedirect();
        $this->assertSame('EM_EXECUCAO', $order->fresh()->status);
        $this->assertSame('EM_ATENDIMENTO', $occurrence->fresh()->status);

        [$outsideOrganization, $outsideSchool] = $this->structure();
        $outsideTechnician = $this->roleUser($outsideOrganization, $outsideSchool, 'tecnico');
        $this->actingAs($outsideTechnician)->get(route('service-orders.show', $order))->assertForbidden();
    }

    public function test_notification_link_rechecks_owner_and_current_service_order_authorization(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician);
        $notification = InternalNotification::query()->create([
            'user_id' => $technician->id,
            'school_id' => $school->id,
            'event_key' => "service-order:{$order->id}:secure-link:test",
            'type' => 'service-order.assigned',
            'title' => 'OS disponível',
            'url' => 'https://example.invalid/nao-deve-ser-usada',
            'data' => ['service_order_id' => $order->id],
        ]);

        $this->actingAs($manager)
            ->get(route('notifications.open', $notification))
            ->assertNotFound();
        $this->actingAs($technician)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('service-orders.show', $order));
        $this->assertNull($notification->fresh()->read_at);

        $order->update(['assigned_user_id' => null]);
        $order->members()->detach($technician);

        $this->actingAs($technician)
            ->get(route('notifications.open', $notification))
            ->assertForbidden();
    }

    public function test_material_total_uses_server_decimal_and_invalid_values_are_rejected(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO']);

        $this->actingAs($technician)->post(route('service-orders.materials', $order), ['description' => 'Lâmpada LED', 'quantity' => '2.500', 'unit' => 'un.', 'unit_cost' => '19.90'])->assertRedirect();
        $this->assertDatabaseHas('service_order_materials', ['service_order_id' => $order->id, 'total_cost' => '49.75']);
        $this->assertSame('49.75', $order->materialTotal());
        $this->actingAs($technician)->post(route('service-orders.materials', $order), ['description' => 'Inválido', 'quantity' => '0', 'unit' => 'un.', 'unit_cost' => '1.00'])->assertSessionHasErrors('quantity');
        $this->actingAs($technician)->post(route('service-orders.costs', $order), ['type' => 'OTHER', 'description' => 'Inválido', 'amount' => '-0.01'])->assertSessionHasErrors('amount');
        $this->assertStringNotContainsString('(float)', file_get_contents(app_path('Models/ServiceOrder.php')));
        $this->assertStringNotContainsString('(float)', file_get_contents(app_path('Http/Controllers/ServiceOrderEntryController.php')));
    }

    public function test_fractional_materials_round_half_up_and_money_is_rendered_without_float(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO', 'estimated_cost' => '10.01']);

        $this->actingAs($technician)->post(route('service-orders.materials', $order), [
            'description' => 'Material fracionado',
            'quantity' => '0.500',
            'unit' => 'kg',
            'unit_cost' => '0.05',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.materials', $order), [
            'description' => 'Material de precisão',
            'quantity' => '0.333',
            'unit' => 'kg',
            'unit_cost' => '0.10',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.costs', $order), [
            'type' => 'OTHER',
            'description' => 'Primeiro custo',
            'amount' => '0.10',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.costs', $order), [
            'type' => 'OTHER',
            'description' => 'Segundo custo',
            'amount' => '0.20',
        ])->assertRedirect();

        $this->assertSame(['0.03', '0.03'], $order->materials()->orderBy('id')->pluck('total_cost')->all());
        $this->assertSame('0.06', $order->materialTotal());
        $this->assertSame('0.30', $order->additionalCostTotal());
        $this->assertSame('0.36', $order->totalCost());
        $this->actingAs($technician)
            ->get(route('service-orders.show', $order))
            ->assertOk()
            ->assertSeeText('R$ 10,01')
            ->assertSeeText('R$ 0,03')
            ->assertSeeText('R$ 0,36');
    }

    public function test_monetary_inputs_cannot_exceed_database_precision(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence, $technician, ['estimated_cost' => '10000000000.00']))
            ->assertSessionHasErrors('estimated_cost');

        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO']);
        $this->actingAs($technician)->post(route('service-orders.costs', $order), [
            'type' => 'OTHER',
            'description' => 'Valor acima da capacidade',
            'amount' => '10000000000.00',
        ])->assertSessionHasErrors('amount');
        $this->actingAs($technician)->post(route('service-orders.materials', $order), [
            'description' => 'Total acima da capacidade',
            'quantity' => '999999999.999',
            'unit' => 'un.',
            'unit_cost' => '9999999999.99',
        ])->assertSessionHasErrors('total_cost');

        $this->assertDatabaseCount('service_order_costs', 0);
        $this->assertDatabaseCount('service_order_materials', 0);
    }

    public function test_work_log_calculates_duration_and_rejects_end_before_start(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO']);

        $this->actingAs($technician)->post(route('service-orders.work-logs', $order), ['started_at' => '2026-08-27 13:00:00', 'ended_at' => '2026-08-27 14:15:00', 'description' => 'Diagnóstico'])->assertRedirect();
        $this->assertDatabaseHas('service_order_work_logs', ['service_order_id' => $order->id, 'duration_minutes' => 75]);
        $this->actingAs($technician)->post(route('service-orders.work-logs', $order), ['started_at' => '2026-08-27 14:00:00', 'ended_at' => '2026-08-27 13:00:00', 'description' => 'Inválido'])->assertSessionHasErrors('ended_at');
    }

    public function test_diagnosis_and_solution_are_required_and_completion_resolves_occurrence(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO']);
        $occurrence->update(['status' => 'EM_ATENDIMENTO']);

        $this->actingAs($technician)->post(route('service-orders.complete', $order), ['solution' => 'Peça substituída.'])->assertSessionHasErrors('diagnosis');
        $this->actingAs($technician)->post(route('service-orders.diagnosis', $order), ['diagnosis' => 'Reator com falha.'])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.complete', $order), ['solution' => ''])->assertSessionHasErrors('solution');
        $this->actingAs($technician)->post(route('service-orders.complete', $order), ['solution' => 'Reator substituído e testado.'])->assertRedirect();

        $this->assertSame('CONCLUIDA', $order->fresh()->status);
        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $order->id, 'event_type' => 'completed']);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'resolved_by_service_orders']);
        foreach ([$occurrence->reporter, $manager] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'occurrence.resolved']);
        }
    }

    public function test_occurrence_resolves_only_after_all_valid_orders_are_finished(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $first = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO', 'diagnosis' => 'Falha elétrica.']);
        $second = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO', 'diagnosis' => 'Falha mecânica.']);
        $occurrence->update(['status' => 'EM_ATENDIMENTO']);

        $this->actingAs($technician)->post(route('service-orders.complete', $first), ['solution' => 'Circuito reparado.'])->assertRedirect();
        $this->assertSame('EM_ATENDIMENTO', $occurrence->fresh()->status);
        $this->actingAs($technician)->post(route('service-orders.complete', $second), ['solution' => 'Peça ajustada.'])->assertRedirect();
        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);

        $thirdOccurrence = $this->occurrence($organization, $school, $occurrence->environment, $occurrence->category, $occurrence->reporter, ['status' => 'EM_ATENDIMENTO']);
        $completed = $this->order($thirdOccurrence, $technician, ['status' => 'EM_EXECUCAO', 'diagnosis' => 'Teste.']);
        $cancelled = $this->order($thirdOccurrence, $technician, ['status' => 'CANCELADA']);
        $this->actingAs($technician)->post(route('service-orders.complete', $completed), ['solution' => 'Resolvido.']);
        $this->assertSame('RESOLVIDA', $thirdOccurrence->fresh()->status);
        $this->assertSame('CANCELADA', $cancelled->fresh()->status);
    }

    public function test_updates_costs_wait_pause_resume_and_cancellation_follow_valid_transitions(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO', 'created_by' => $manager->id]);
        $occurrence->update(['status' => 'EM_ATENDIMENTO']);

        $this->actingAs($technician)->post(route('service-orders.updates', $order), ['message' => 'Equipamento desmontado para análise.'])->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.costs', $order), ['type' => 'OTHER', 'description' => 'Deslocamento', 'amount' => '12.34'])->assertRedirect();
        $this->assertSame('12.34', $order->additionalCostTotal());
        $this->actingAs($technician)->post(route('service-orders.wait-material', $order), ['reason' => 'Aguardando peça.'])->assertRedirect();
        $this->assertSame('AGUARDANDO_MATERIAL', $order->fresh()->status);
        $this->assertSame('EM_ATENDIMENTO', $occurrence->fresh()->status);
        foreach ([$manager, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'service-order.waiting_material']);
        }
        $this->actingAs($technician)->post(route('service-orders.resume', $order))->assertRedirect();
        $this->actingAs($technician)->post(route('service-orders.pause', $order), ['reason' => 'Fim do expediente.'])->assertRedirect();
        $this->assertSame('PAUSADA', $order->fresh()->status);
        foreach ([$manager, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'service-order.paused']);
        }
        $this->actingAs($technician)->post(route('service-orders.resume', $order))->assertRedirect();
        $this->actingAs($manager)->post(route('service-orders.cancel', $order), ['reason' => 'Serviço substituído por outra estratégia.'])->assertRedirect();

        $this->assertSame('CANCELADA', $order->fresh()->status);
        $this->assertDatabaseHas('service_order_histories', ['service_order_id' => $order->id, 'event_type' => 'cancelled']);
        $this->assertDatabaseHas('service_orders', ['id' => $order->id]);
    }

    public function test_manager_closes_or_reopens_resolved_occurrence_with_history(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $this->order($occurrence, $technician, ['status' => 'CANCELADA']);
        $occurrence->update(['status' => 'RESOLVIDA']);

        $this->actingAs($manager)
            ->post(route('occurrences.close', $occurrence))
            ->assertSessionHasErrors('service_orders');
        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);

        $this->order($occurrence, $technician, ['status' => 'CONCLUIDA', 'completed_at' => now()]);
        $this->actingAs($manager)->post(route('occurrences.close', $occurrence))->assertRedirect();
        $this->assertSame('ENCERRADA', $occurrence->fresh()->status);
        foreach ([$occurrence->reporter, $manager, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'occurrence.closed']);
        }
        $this->actingAs($manager)->post(route('occurrences.reopen', $occurrence), ['reason' => 'Problema reapareceu.'])->assertRedirect();
        $this->assertSame('EM_TRIAGEM', $occurrence->fresh()->status);
        foreach ([$occurrence->reporter, $manager, $technician] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', ['user_id' => $recipient->id, 'type' => 'occurrence.reopened']);
        }
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'closed']);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'reopened']);
    }

    public function test_author_requests_reopening_without_changing_status_and_each_eligible_decision_maker_is_notified_once(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $author = $this->roleUser($organization, $school, 'solicitante');
        $firstManager = $this->roleUser($organization, $school, 'gestor');
        $secondManager = $this->roleUser($organization, $school, 'gestor');
        $inactiveManager = $this->roleUser($organization, $school, 'gestor');
        $inactiveManager->update(['is_active' => false]);
        $platformAdministrator = User::factory()->create(['is_platform_admin' => true]);
        $roles = app(AccessCatalog::class)->provision($organization);
        RoleAssignment::query()->create([
            'user_id' => $firstManager->id,
            'role_id' => $roles['administrador-escola']->id,
            'school_id' => $school->id,
        ]);
        [, $otherSchool] = $this->structure($organization);
        $otherSchoolManager = $this->roleUser($organization, $otherSchool, 'gestor');
        [$outsideOrganization, $outsideSchool] = $this->structure();
        $outsideManager = $this->roleUser($outsideOrganization, $outsideSchool, 'gestor');
        $occurrence->update(['reporter_id' => $author->id, 'status' => 'ENCERRADA']);

        $this->actingAs($author)
            ->get(route('occurrences.show', $occurrence))
            ->assertOk()
            ->assertSeeText('Solicitar reabertura');
        $this->actingAs($firstManager)
            ->get(route('occurrences.show', $occurrence))
            ->assertOk()
            ->assertDontSeeText('Solicitar reabertura');

        $this->actingAs($author)->post(route('occurrences.reopening.request', $occurrence), [
            'reason' => 'O problema voltou a ocorrer após o encerramento.',
        ])->assertRedirect();

        $occurrence->refresh();
        $this->assertSame('ENCERRADA', $occurrence->status);
        $history = $occurrence->histories()->where('event_type', 'reopen_requested')->sole();
        $this->assertSame($author->id, $history->actor_id);
        $this->assertSame(['status' => 'ENCERRADA'], $history->old_values);
        $this->assertSame(['status' => 'ENCERRADA'], $history->new_values);
        $this->assertSame('O problema voltou a ocorrer após o encerramento.', $history->metadata['reason']);

        $eventKey = "occurrence:{$occurrence->id}:reopen_requested:history:{$history->id}";
        foreach ([$firstManager, $secondManager, $platformAdministrator] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', [
                'user_id' => $recipient->id,
                'event_key' => $eventKey,
                'type' => 'occurrence.reopen_requested',
            ]);
            $this->assertSame(1, InternalNotification::query()->where('user_id', $recipient->id)->where('event_key', $eventKey)->count());
        }
        foreach ([$author, $inactiveManager, $otherSchoolManager, $outsideManager] as $nonRecipient) {
            $this->assertDatabaseMissing('internal_notifications', [
                'user_id' => $nonRecipient->id,
                'event_key' => $eventKey,
            ]);
        }
        $this->assertSame(3, InternalNotification::query()->where('event_key', $eventKey)->count());

        $this->actingAs($firstManager)->post(route('occurrences.reopen', $occurrence), [
            'reason' => 'Recorrência confirmada pelo gestor.',
        ])->assertRedirect();
        $this->assertSame('EM_TRIAGEM', $occurrence->fresh()->status);
        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $occurrence->id,
            'event_type' => 'reopened',
            'actor_id' => $firstManager->id,
        ]);
    }

    public function test_only_the_author_can_request_reopening_and_only_when_the_occurrence_is_closed(): void
    {
        [$organization, $school, $occurrence] = $this->structure();
        $author = $this->roleUser($organization, $school, 'solicitante');
        $otherRequester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $platformAdministrator = User::factory()->create(['is_platform_admin' => true]);
        $occurrence->update(['reporter_id' => $author->id, 'status' => 'ENCERRADA']);
        $payload = ['reason' => 'Tentativa sem autorização.'];

        foreach ([$otherRequester, $manager, $platformAdministrator] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->post(route('occurrences.reopening.request', $occurrence), $payload)
                ->assertForbidden();
        }

        $this->actingAs($author)
            ->post(route('occurrences.reopening.request', $occurrence), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $occurrence->update(['status' => 'RESOLVIDA']);
        $this->actingAs($author)
            ->post(route('occurrences.reopening.request', $occurrence), $payload)
            ->assertForbidden();

        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);
        $this->assertSame(0, $occurrence->histories()->where('event_type', 'reopen_requested')->count());
        $this->assertDatabaseMissing('internal_notifications', ['type' => 'occurrence.reopen_requested']);
    }

    public function test_reopened_occurrence_receives_new_order_and_preserves_previous_orders(): void
    {
        [$organization, $school, $occurrence] = $this->structure(['approval_threshold' => '500.00']);
        $manager = $this->roleUser($organization, $school, 'gestor');
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $previousOrder = $this->order($occurrence, $technician, [
            'status' => 'EM_EXECUCAO',
            'diagnosis' => 'Falha elétrica original.',
        ]);
        $occurrence->update(['status' => 'EM_ATENDIMENTO']);

        $this->actingAs($technician)
            ->post(route('service-orders.complete', $previousOrder), ['solution' => 'Circuito original reparado.'])
            ->assertRedirect();
        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);

        $this->actingAs($manager)
            ->post(route('occurrences.reopen', $occurrence), ['reason' => 'A falha voltou a ocorrer.'])
            ->assertRedirect();
        $this->assertSame('EM_TRIAGEM', $occurrence->fresh()->status);

        $this->actingAs($manager)
            ->post(route('occurrences.forward', $occurrence), [
                'occurrence_version' => $occurrence->fresh()->concurrencyToken(),
                'forwarded_destination' => 'Nova manutenção corretiva',
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('service-orders.store'), $this->payload($occurrence->fresh(), $technician, [
                'title' => 'Correção da recorrência',
            ]))
            ->assertRedirect();

        $newOrder = ServiceOrder::query()->whereKeyNot($previousOrder->id)->sole();
        $this->assertSame('CONCLUIDA', $previousOrder->fresh()->status);
        $this->assertSame('APROVADA', $newOrder->status);
        $this->assertDatabaseCount('service_orders', 2);
        $this->assertDatabaseHas('service_order_histories', [
            'service_order_id' => $previousOrder->id,
            'event_type' => 'completed',
        ]);

        $this->actingAs($technician)->post(route('service-orders.start', $newOrder))->assertRedirect();
        $this->actingAs($technician)
            ->post(route('service-orders.diagnosis', $newOrder), ['diagnosis' => 'Recorrência no mesmo circuito.'])
            ->assertRedirect();
        $this->actingAs($technician)
            ->post(route('service-orders.complete', $newOrder), ['solution' => 'Circuito reforçado e retestado.'])
            ->assertRedirect();

        $this->assertSame('RESOLVIDA', $occurrence->fresh()->status);
        $this->assertSame(2, $occurrence->serviceOrders()->where('status', 'CONCLUIDA')->count());
        $this->assertSame(2, $occurrence->histories()->where('event_type', 'resolved_by_service_orders')->count());
        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $occurrence->id,
            'event_type' => 'reopened',
        ]);
    }

    public function test_private_evidence_is_stored_and_download_is_authorized(): void
    {
        Storage::fake('local');
        [$organization, $school, $occurrence] = $this->structure();
        $technician = $this->roleUser($organization, $school, 'tecnico');
        $outsider = $this->roleUser($organization, $school, 'tecnico');
        $order = $this->order($occurrence, $technician, ['status' => 'EM_EXECUCAO']);

        $this->actingAs($technician)->post(route('service-orders.attachments', $order), ['evidence' => UploadedFile::fake()->image('depois.jpg')])->assertRedirect();
        $attachment = $order->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('local', $attachment->disk);
        $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertNotFound();
        $this->actingAs($technician)->get(route('attachments.download', $attachment))->assertOk();
    }

    private function structure(array|Organization|null $organization = null): array
    {
        $organization = $organization instanceof Organization ? $organization : Organization::factory()->create(array_merge(['mode' => 'network'], $organization ?? []));
        $school = School::factory()->for($organization)->create();
        $environment = Environment::factory()->for($school)->create(['is_active' => true]);
        $category = OccurrenceCategory::factory()->for($organization)->create(['is_active' => true]);
        $category->schools()->attach($school);
        $reporter = User::factory()->for($organization)->create();
        $reporter->schools()->attach($school);
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $reporter);

        return [$organization, $school, $occurrence];
    }

    private function roleUser(Organization $organization, School $school, string $role): User
    {
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles[$role]->id, 'school_id' => $school->id]);

        return $user;
    }

    private function payload(Occurrence $occurrence, ?User $assigned = null, array $overrides = []): array
    {
        return array_merge(['occurrence_id' => $occurrence->id, 'assigned_user_id' => $assigned?->id, 'member_ids' => [], 'title' => 'Troca de luminária', 'description' => 'Diagnosticar e corrigir a falha da luminária.', 'due_date' => now()->addWeek()->toDateString(), 'estimated_cost' => '50.00'], $overrides);
    }

    private function externalServiceData(): array
    {
        return [
            'external_provider_name' => 'Manutenção Externa Ltda.',
            'external_service_description' => 'Reparar o equipamento e emitir relatório técnico.',
            'external_provider_contact' => 'contato@example.test',
            'external_provider_tax_id' => '12.345.678/0001-90',
        ];
    }

    private function occurrence(Organization $organization, School $school, Environment $environment, OccurrenceCategory $category, User $reporter, array $overrides = []): Occurrence
    {
        static $sequence = 300;

        return Occurrence::factory()->create(array_merge(['organization_id' => $organization->id, 'school_id' => $school->id, 'environment_id' => $environment->id, 'occurrence_category_id' => $category->id, 'reporter_id' => $reporter->id, 'protocol' => sprintf('SIG-%s-%d-%06d', $school->code, now()->year, $sequence), 'protocol_year' => now()->year, 'protocol_sequence' => $sequence++, 'status' => 'ENCAMINHADA', 'confirmed_priority' => 'HIGH'], $overrides));
    }

    private function order(Occurrence $occurrence, User $technician, array $overrides = []): ServiceOrder
    {
        static $sequence = 500;
        $order = ServiceOrder::query()->create(array_merge(['organization_id' => $occurrence->organization_id, 'school_id' => $occurrence->school_id, 'occurrence_id' => $occurrence->id, 'created_by' => $technician->id, 'assigned_user_id' => $technician->id, 'code' => sprintf('OS-%s-%d-%06d', $occurrence->school->code, now()->year, $sequence), 'code_year' => now()->year, 'code_sequence' => $sequence++, 'title' => 'Serviço técnico', 'description' => 'Executar manutenção.', 'priority_snapshot' => $occurrence->priority(), 'status' => 'APROVADA', 'estimated_cost' => '0.00'], $overrides));
        $order->members()->sync([$technician->id]);

        return $order;
    }
}
