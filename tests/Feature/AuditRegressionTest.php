<?php

namespace Tests\Feature;

use App\Http\Controllers\ServiceOrderWorkflowController;
use App\Models\InternalNotification;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuditScenario;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use AuditScenario, RefreshDatabase;

    public static function profileCases(): array
    {
        return array_combine($roles = ['platform', ...array_keys(AccessCatalog::roles())], array_map(fn ($role) => [$role], $roles));
    }

    #[DataProvider('profileCases')]
    public function test_profile_page_matrix(string $profile): void
    {
        $s = $this->auditScenario();
        $this->actingAs($s['users'][$profile]);
        $admin = in_array($profile, ['platform', 'administrador-rede', 'administrador-escola'], true);
        $manager = $admin || $profile === 'gestor';
        $operator = $manager || $profile === 'tecnico';
        $matrix = [
            ['dashboard', [], true], ['notifications.index', [], true],
            ['organizations.index', [], $profile === 'platform'], ['organizations.create', [], $profile === 'platform'],
            ['organizations.edit', [$s['organization']], in_array($profile, ['platform', 'administrador-rede'], true)],
            ['schools.index', [], $manager], ['schools.create', [], in_array($profile, ['platform', 'administrador-rede'], true)], ['schools.edit', [$s['school']], $manager],
            ['users.index', [], $manager], ['users.create', [], $admin], ['users.edit', [$s['users']['solicitante']], $manager],
            ['environments.index', [], true], ['environments.create', [], $manager], ['environments.edit', [$s['environment']], $manager],
            ['categories.index', [], $manager], ['categories.create', [], $manager], ['categories.edit', [$s['category']], in_array($profile, ['platform', 'administrador-rede'], true)],
            ['categories.availability', [$s['category']], $manager], ['occurrences.index', [], true], ['occurrences.create', [], true],
            ['service-orders.index', [], $operator], ['service-orders.create', ['occurrence_id' => $s['occurrence']->id], $manager],
            ['service-orders.show', [$s['order']], $operator],
        ];
        foreach ($matrix as [$route, $parameters, $allowed]) {
            $response = $this->get(route($route, $parameters));
            $this->assertSame($allowed ? 200 : 403, $response->status(), $profile.' '.$route);
        }
    }

    public function test_school_admin_cannot_grant_network_role_or_take_over_network_admin(): void
    {
        $s = $this->auditScenario();
        $this->actingAs($s['users']['administrador-escola']);
        $payload = ['name' => 'Tentativa', 'email' => 'attempt@audit.invalid', 'password' => 'AuditPassword2026!', 'is_active' => 1, 'school_ids' => [$s['school']->id], 'role_ids' => [$s['roles']['administrador-rede']->id]];
        $this->post(route('users.store'), $payload)->assertSessionHasErrors('role_ids');
        $this->put(route('users.update', $s['users']['administrador-escola']), $payload)->assertSessionHasErrors('role_ids');
        $target = $s['users']['administrador-rede'];
        $hash = $target->password;
        $this->put(route('users.update', $target), $payload)->assertForbidden();
        $this->assertSame($hash, $target->fresh()->password);
        $this->assertDatabaseMissing('users', ['email' => 'attempt@audit.invalid']);
    }

    public function test_school_admin_cannot_rewrite_multi_school_target(): void
    {
        $s = $this->auditScenario();
        $target = $s['users']['tecnico'];
        $target->schools()->attach($s['otherSchool']);
        RoleAssignment::query()->create(['user_id' => $target->id, 'school_id' => $s['otherSchool']->id, 'role_id' => $s['roles']['tecnico']->id]);
        $this->actingAs($s['users']['administrador-escola'])->get(route('users.edit', $target))->assertForbidden();
        $this->assertSame(2, $target->schools()->count());
    }

    public function test_network_admin_cannot_read_another_organization_form_choices(): void
    {
        $s = $this->auditScenario();
        $outside = Organization::factory()->create();
        School::factory()->for($outside)->create(['name' => 'PRIVATE OUTSIDE SCHOOL']);
        app(AccessCatalog::class)->provision($outside);
        $this->actingAs($s['users']['administrador-rede'])->get(route('users.create', ['organization_id' => $outside->id]))
            ->assertOk()->assertDontSeeText('PRIVATE OUTSIDE SCHOOL')->assertViewHas('selectedOrganizationId', $s['organization']->id);
    }

    public function test_deactivated_sessions_and_login_are_blocked(): void
    {
        $s = $this->auditScenario();
        foreach (['platform', 'solicitante'] as $profile) {
            $user = $s['users'][$profile];
            $this->actingAs($user);
            $user->update(['is_active' => false]);
            $this->get(route('dashboard'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
        $s['organization']->update(['is_active' => false]);
        $this->actingAs($s['users']['gestor']->fresh())->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => 'gestor@audit.invalid', 'password' => 'SigmeAuditOnly2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_revoked_school_membership_removes_authors_occurrence_from_queue(): void
    {
        $s = $this->auditScenario();
        $author = $s['users']['solicitante'];
        $author->schools()->detach($s['school']);
        $author->roleAssignments()->delete();
        $author->schools()->attach($s['otherSchool']);
        RoleAssignment::query()->create(['user_id' => $author->id, 'school_id' => $s['otherSchool']->id, 'role_id' => $s['roles']['solicitante']->id]);
        $this->actingAs($author->fresh())->get(route('occurrences.index'))->assertOk()->assertDontSeeText($s['occurrence']->title);
        $this->get(route('occurrences.show', $s['occurrence']))->assertForbidden();
    }

    public function test_empty_estimate_and_invalid_filter_dates_never_return_500(): void
    {
        $s = $this->auditScenario();
        $this->actingAs($s['users']['gestor']);
        $this->post(route('service-orders.store'), ['occurrence_id' => $s['occurrence']->id, 'title' => 'Sem custo estimado', 'description' => 'Diagnosticar a falha descrita.', 'estimated_cost' => '', 'assigned_user_id' => $s['users']['tecnico']->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_orders', ['title' => 'Sem custo estimado', 'estimated_cost' => '0.00']);
        foreach (['occurrences.index', 'service-orders.index'] as $route) {
            $this->getJson(route($route, ['date_from' => 'not-a-date']))->assertUnprocessable();
        }
    }

    public function test_cancelling_last_pending_order_resolves_only_with_completed_order(): void
    {
        $s = $this->auditScenario();
        $s['occurrence']->update(['status' => 'EM_ATENDIMENTO']);
        $s['order']->update(['status' => 'CONCLUIDA']);
        $remaining = $s['order']->replicate(['code', 'code_sequence']);
        $remaining->fill(['code' => 'OS-AUDIT-SECOND', 'code_sequence' => 1000000, 'status' => 'APROVADA'])->save();
        $this->actingAs($s['users']['gestor'])->post(route('service-orders.cancel', $remaining), ['reason' => 'Serviço já contemplado na primeira OS.'])->assertRedirect();
        $this->assertSame('RESOLVIDA', $s['occurrence']->fresh()->status);
    }

    public function test_stale_order_cannot_overwrite_cancelled_status(): void
    {
        $s = $this->auditScenario();
        $stale = $s['order'];
        ServiceOrder::query()->whereKey($stale->id)->update(['status' => 'CANCELADA']);
        $this->actingAs($s['users']['tecnico']);
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $s['users']['tecnico']);
        try {
            app(ServiceOrderWorkflowController::class)->start($request, $stale);
            $this->fail('An obsolete order must not overwrite a cancellation.');
        } catch (ValidationException) {
            $this->assertSame('CANCELADA', $stale->fresh()->status);
            $this->assertDatabaseMissing('service_order_histories', ['service_order_id' => $stale->id, 'event_type' => 'started']);
        }
    }

    public function test_terminal_order_rejects_evidence_and_storage_failure_leaves_no_metadata(): void
    {
        $s = $this->auditScenario();
        Storage::fake('local');
        $s['order']->update(['status' => 'CONCLUIDA']);
        $this->actingAs($s['users']['tecnico'])->post(route('service-orders.attachments', $s['order']), ['evidence' => UploadedFile::fake()->image('foto.png')])->assertSessionHasErrors('status');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('private_attachments', 0);
        $s['order']->update(['status' => 'APROVADA']);
        ServiceOrderHistory::creating(function (): void {
            throw new \RuntimeException('Simulated history failure');
        });
        try {
            $this->post(route('service-orders.attachments', $s['order']), ['evidence' => UploadedFile::fake()->image('foto.png')])->assertStatus(500);
            $this->assertDatabaseCount('private_attachments', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            ServiceOrderHistory::flushEventListeners();
            ServiceOrderHistory::clearBootedModels();
        }
    }

    public function test_mark_all_notifications_remains_available_for_unread_later_page(): void
    {
        $s = $this->auditScenario();
        $user = $s['users']['solicitante'];
        for ($index = 0; $index < 31; $index++) {
            InternalNotification::query()->create(['user_id' => $user->id, 'school_id' => $s['school']->id, 'event_key' => 'audit:'.$index, 'type' => 'audit', 'title' => 'Notificação '.$index, 'read_at' => $index === 0 ? null : now(), 'created_at' => now()->addSeconds($index)]);
        }
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSeeText('Marcar todas como lidas');
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->internalNotifications()->whereNull('read_at')->count());
    }

    public function test_due_today_is_not_overdue_and_technician_filter_does_not_expose_unrelated_users(): void
    {
        $s = $this->auditScenario();
        $s['order']->update(['due_date' => today()]);
        User::factory()->for($s['organization'])->create(['name' => 'PRIVATE UNRELATED USER']);
        $this->actingAs($s['users']['tecnico'])->get(route('service-orders.index'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['late'] === 0)->assertDontSeeText('PRIVATE UNRELATED USER');
    }

    public function test_loaded_cost_totals_remain_exact_without_repeated_queries(): void
    {
        $s = $this->auditScenario();
        $order = $s['order'];
        foreach (['9999999999.99', '0.01'] as $amount) {
            $order->costs()->create(['created_by' => $s['users']['gestor']->id, 'type' => 'OTHER', 'description' => 'Custo fictício', 'amount' => $amount]);
        }
        $order->materials()->create(['created_by' => $s['users']['gestor']->id, 'description' => 'Material fictício', 'quantity' => '1.001', 'unit' => 'm', 'unit_cost' => '1.00', 'total_cost' => '1.00']);
        $this->assertSame('10000000001.00', $order->totalCost());
        $order->load(['materials', 'costs']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame('1.00', $order->materialTotal());
            $this->assertSame('10000000000.00', $order->additionalCostTotal());
            $this->assertSame('10000000001.00', $order->totalCost());
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_material_and_diagnosis_roll_back_when_history_fails(): void
    {
        $s = $this->auditScenario();
        $s['order']->update(['status' => 'EM_EXECUCAO']);
        $this->actingAs($s['users']['tecnico']);
        ServiceOrderHistory::creating(function (): void {
            throw new \RuntimeException('Simulated history failure');
        });
        try {
            $this->post(route('service-orders.materials', $s['order']), ['description' => 'Não persistir', 'quantity' => '1', 'unit' => 'un', 'unit_cost' => '2.50'])->assertStatus(500);
            $this->assertDatabaseCount('service_order_materials', 0);
            $original = $s['order']->diagnosis;
            $this->post(route('service-orders.diagnosis', $s['order']), ['diagnosis' => 'Não persistir diagnóstico'])->assertStatus(500);
            $this->assertSame($original, $s['order']->fresh()->diagnosis);
        } finally {
            ServiceOrderHistory::flushEventListeners();
            ServiceOrderHistory::clearBootedModels();
        }
    }

    public function test_time_shorter_than_one_minute_is_rejected(): void
    {
        $s = $this->auditScenario();
        $s['order']->update(['status' => 'EM_EXECUCAO']);
        $this->actingAs($s['users']['tecnico'])->post(route('service-orders.work-logs', $s['order']), ['description' => 'Curto demais', 'started_at' => '2026-09-10 10:00:00', 'ended_at' => '2026-09-10 10:00:30'])->assertSessionHasErrors('ended_at');
        $this->assertDatabaseCount('service_order_work_logs', 0);
    }

    public function test_platform_can_edit_user_of_inactive_organization(): void
    {
        $s = $this->auditScenario();
        $s['organization']->update(['is_active' => false]);
        $this->actingAs($s['users']['platform'])->get(route('users.edit', $s['users']['solicitante']))->assertOk();
    }

    public function test_malformed_text_fields_return_validation_errors_instead_of_500(): void
    {
        $s = $this->auditScenario();
        $this->postJson(route('login.store'), ['email' => ['bad'], 'password' => 'invalid'])->assertUnprocessable();
        $this->actingAs($s['users']['platform']);
        foreach ([['organizations.store', 'slug'], ['schools.store', 'code'], ['environments.store', 'code'], ['categories.store', 'name'], ['users.store', 'email']] as [$route, $field]) {
            $this->postJson(route($route), [$field => ['bad']])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
