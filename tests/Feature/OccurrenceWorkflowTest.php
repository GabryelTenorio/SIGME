<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\InternalNotification;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\OccurrenceHistory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OccurrenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggested_priority_matrix_is_explicit_and_deterministic(): void
    {
        $expected = [
            ['LOW', 'NORMAL', 'LOW'], ['MEDIUM', 'NORMAL', 'MEDIUM'], ['HIGH', 'NORMAL', 'HIGH'],
            ['LOW', 'SOON', 'MEDIUM'], ['MEDIUM', 'SOON', 'HIGH'], ['HIGH', 'SOON', 'HIGH'],
            ['LOW', 'IMMEDIATE', 'URGENT'], ['MEDIUM', 'IMMEDIATE', 'URGENT'], ['HIGH', 'IMMEDIATE', 'URGENT'],
        ];
        foreach ($expected as [$impact, $urgency, $priority]) {
            $this->assertSame($priority, Occurrence::suggestedPriority($impact, $urgency));
        }
    }

    public function test_requester_creates_valid_occurrence_with_server_fields_protocol_priority_and_history(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');

        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, ['impact' => 'LOW', 'perceived_urgency' => 'SOON', 'organization_id' => 999, 'reporter_id' => 999, 'status' => 'ENCAMINHADA']))->assertRedirect();

        $occurrence = Occurrence::query()->sole();
        $this->assertSame("SIG-{$school->code}-".now()->year.'-000001', $occurrence->protocol);
        $this->assertSame($organization->id, $occurrence->organization_id);
        $this->assertSame($requester->id, $occurrence->reporter_id);
        $this->assertSame('ABERTA', $occurrence->status);
        $this->assertSame('MEDIUM', $occurrence->suggested_priority);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'created']);
    }

    public function test_initial_image_and_pdf_attachments_are_stored_privately(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');

        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, [
            'attachments' => [
                UploadedFile::fake()->image('painel.jpg'),
                UploadedFile::fake()->create('laudo.pdf', 512, 'application/pdf'),
            ],
        ]))->assertRedirect();

        $occurrence = Occurrence::query()->sole();
        $attachments = $occurrence->attachments()->orderBy('id')->get();
        $this->assertCount(2, $attachments);
        $this->assertSame(['image/jpeg', 'application/pdf'], $attachments->pluck('mime_type')->all());
        foreach ($attachments as $attachment) {
            $this->assertSame('local', $attachment->disk);
            $this->assertStringStartsWith("occurrences/{$occurrence->id}/", $attachment->path);
            Storage::disk('local')->assertExists($attachment->path);
        }
        $this->actingAs($requester)
            ->get(route('occurrences.show', $occurrence))
            ->assertOk()
            ->assertSeeText('painel.jpg')
            ->assertSeeText('laudo.pdf');
        $this->actingAs($requester)
            ->get(route('attachments.download', $attachments->first()))
            ->assertOk();
    }

    public function test_initial_attachment_rejects_unsupported_type_and_file_over_10_mb(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');

        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, [
            'attachments' => [UploadedFile::fake()->create('script.txt', 1, 'text/plain')],
        ]))->assertSessionHasErrors('attachments.0');
        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, [
            'attachments' => [UploadedFile::fake()->image('grande.jpg')->size(10241)],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('occurrences', 0);
        $this->assertDatabaseCount('private_attachments', 0);
    }

    public function test_initial_attachment_count_is_limited_to_five(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $fiveFiles = collect(range(1, 5))
            ->map(fn (int $number): UploadedFile => UploadedFile::fake()->image("evidencia-{$number}.jpg"))
            ->all();

        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, [
            'attachments' => $fiveFiles,
        ]))->assertRedirect();
        $this->assertDatabaseCount('occurrences', 1);
        $this->assertDatabaseCount('private_attachments', 5);

        $sixFiles = collect(range(1, 6))
            ->map(fn (int $number): UploadedFile => UploadedFile::fake()->image("excesso-{$number}.jpg"))
            ->all();
        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, [
            'title' => 'Ocorrência com anexos em excesso',
            'attachments' => $sixFiles,
        ]))->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('occurrences', 1);
        $this->assertDatabaseCount('private_attachments', 5);
    }

    public function test_occurrence_attachment_download_is_authorized_and_isolated_between_schools(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $sameSchoolManager = $this->roleUser($organization, $school, 'gestor');
        $otherRequester = $this->roleUser($organization, $school, 'solicitante');
        [, $otherSchool] = $this->structure($organization);
        $otherSchoolManager = $this->roleUser($organization, $otherSchool, 'gestor');
        [$outsideOrganization, $outsideSchool] = $this->structure();
        $outsideManager = $this->roleUser($outsideOrganization, $outsideSchool, 'gestor');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester);
        $path = "occurrences/{$occurrence->id}/evidencia.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 conteúdo de teste');
        $attachment = $occurrence->attachments()->create([
            'uploaded_by' => $requester->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'evidencia.pdf',
            'mime_type' => 'application/pdf',
            'size' => 26,
        ]);

        $this->get(route('attachments.download', $attachment))->assertRedirect(route('login'));
        $this->actingAs($requester)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($sameSchoolManager)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($otherRequester)->get(route('attachments.download', $attachment))->assertNotFound();
        $this->actingAs($otherSchoolManager)->get(route('attachments.download', $attachment))->assertNotFound();
        $this->actingAs($outsideManager)->get(route('attachments.download', $attachment))->assertNotFound();
        Storage::disk('local')->assertExists($path);
    }

    public function test_author_adds_later_evidence_only_in_the_three_approved_states_without_changing_status(): void
    {
        Storage::fake('local');

        foreach (['ABERTA', 'EM_TRIAGEM', 'AGUARDANDO_INFORMACOES'] as $status) {
            [$organization, $school, $environment, $category] = $this->structure();
            $requester = $this->roleUser($organization, $school, 'solicitante');
            $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => $status]);

            $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
                'evidence' => UploadedFile::fake()->image("evidencia-{$status}.jpg"),
            ])->assertRedirect();

            $this->assertSame($status, $occurrence->fresh()->status);
            $attachment = $occurrence->attachments()->sole();
            $this->assertSame($requester->id, $attachment->uploaded_by);
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertDatabaseHas('occurrence_histories', [
                'occurrence_id' => $occurrence->id,
                'actor_id' => $requester->id,
                'event_type' => 'attachment_added',
            ]);
        }
    }

    public function test_later_evidence_is_forbidden_for_other_users_and_unapproved_states(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $otherRequester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $platformAdministrator = User::factory()->create(['is_platform_admin' => true]);
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => 'ABERTA']);

        foreach ([$otherRequester, $manager, $platformAdministrator] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)->post(route('occurrences.attachments', $occurrence), [
                'evidence' => UploadedFile::fake()->image("negada-{$unauthorizedUser->id}.jpg"),
            ])->assertForbidden();
        }

        $occurrence->update(['status' => 'ENCAMINHADA']);
        $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
            'evidence' => UploadedFile::fake()->image('estado-negado.jpg'),
        ])->assertForbidden();
        $this->assertDatabaseCount('private_attachments', 0);
        $this->assertDatabaseCount('occurrence_histories', 0);
    }

    public function test_later_evidence_preserves_validation_and_total_limit_of_twenty(): void
    {
        Storage::fake('local');
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => 'ABERTA']);

        foreach (range(1, 19) as $number) {
            $occurrence->attachments()->create([
                'uploaded_by' => $requester->id,
                'disk' => 'local',
                'path' => "occurrences/{$occurrence->id}/existente-{$number}.jpg",
                'original_name' => "existente-{$number}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 1,
            ]);
        }

        $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
            'evidence' => UploadedFile::fake()->create('invalida.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('evidence');
        $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
            'evidence' => UploadedFile::fake()->image('grande.jpg')->size(10241),
        ])->assertSessionHasErrors('evidence');
        $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
            'evidence' => UploadedFile::fake()->create('vigesima.pdf', 512, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(20, $occurrence->attachments()->count());

        $this->actingAs($requester)->post(route('occurrences.attachments', $occurrence), [
            'evidence' => UploadedFile::fake()->image('vigesima-primeira.jpg'),
        ])->assertSessionHasErrors('evidence');

        $this->assertSame(20, $occurrence->attachments()->count());
        $this->assertSame(1, $occurrence->histories()->where('event_type', 'attachment_added')->count());
        $this->assertDatabaseMissing('private_attachments', ['original_name' => 'vigesima-primeira.jpg']);
    }

    public function test_protocols_are_unique_sequential_per_school_and_year(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category));
        $this->actingAs($requester)->post(route('occurrences.store'), $this->payload($school, $environment, $category, ['title' => 'Segundo problema']));

        $this->assertSame([1, 2], Occurrence::query()->orderBy('id')->pluck('protocol_sequence')->all());
        $this->assertSame(2, Occurrence::query()->distinct()->count('protocol'));
    }

    public function test_same_sequence_is_allowed_for_different_schools(): void
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $first = $this->structure($organization);
        $second = $this->structure($organization);
        foreach ([$first, $second] as [, $school, $environment, $category]) {
            $user = $this->roleUser($organization, $school, 'solicitante');
            $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $environment, $category));
        }
        $this->assertSame([1, 1], Occurrence::query()->orderBy('id')->pluck('protocol_sequence')->all());
        $this->assertSame(2, Occurrence::query()->distinct()->count('protocol'));
    }

    public function test_environment_from_another_school_is_rejected(): void
    {
        [$organization, $school, , $category] = $this->structure();
        [, , $outsideEnvironment] = $this->structure($organization);
        $user = $this->roleUser($organization, $school, 'solicitante');
        $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $outsideEnvironment, $category))->assertSessionHasErrors('environment_id');
    }

    public function test_unavailable_or_inactive_category_and_inactive_environment_are_rejected(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $user = $this->roleUser($organization, $school, 'solicitante');
        $category->schools()->detach($school);
        $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $environment, $category))->assertSessionHasErrors('occurrence_category_id');
        $category->schools()->attach($school);
        $category->update(['is_active' => false]);
        $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $environment, $category))->assertSessionHasErrors('occurrence_category_id');
        $category->update(['is_active' => true]);
        $environment->update(['is_active' => false]);
        $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $environment, $category))->assertSessionHasErrors('environment_id');
        $this->assertDatabaseCount('occurrences', 0);
    }

    public function test_user_without_school_link_cannot_register(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $otherSchool = School::factory()->for($organization)->create();
        $user = $this->roleUser($organization, $otherSchool, 'solicitante');
        $this->actingAs($user)->post(route('occurrences.store'), $this->payload($school, $environment, $category))->assertSessionHasErrors('school_id');
    }

    public function test_requester_sees_own_but_not_another_users_occurrence(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $first = $this->roleUser($organization, $school, 'solicitante');
        $second = $this->roleUser($organization, $school, 'solicitante');
        $own = $this->occurrence($organization, $school, $environment, $category, $first);
        $other = $this->occurrence($organization, $school, $environment, $category, $second);
        $this->actingAs($first)->get(route('occurrences.show', $own))->assertOk();
        $this->actingAs($first)->get(route('occurrences.show', $other))->assertForbidden();
        $this->actingAs($first)->get(route('occurrences.index'))->assertSeeText($own->protocol)->assertDontSeeText($other->protocol);
    }

    public function test_notification_link_rechecks_current_occurrence_authorization(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester);
        $notification = InternalNotification::query()->create([
            'user_id' => $requester->id,
            'school_id' => $school->id,
            'event_key' => "occurrence:{$occurrence->id}:secure-link:test",
            'type' => 'occurrence.created',
            'title' => 'Ocorrência disponível',
            'url' => 'https://example.invalid/nao-deve-ser-usada',
            'data' => ['occurrence_id' => $occurrence->id],
        ]);

        $this->actingAs($requester)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('occurrences.show', $occurrence));

        RoleAssignment::query()->where('user_id', $requester->id)->delete();

        $this->actingAs($requester)
            ->get(route('notifications.open', $notification))
            ->assertForbidden();
    }

    public function test_manager_sees_own_school_but_not_other_school_or_organization(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $manager = $this->roleUser($organization, $school, 'gestor');
        $reporter = $this->roleUser($organization, $school, 'solicitante');
        $ownSchoolOccurrence = $this->occurrence($organization, $school, $environment, $category, $reporter);
        [$sameOrg, $otherSchool, $otherEnvironment, $otherCategory] = $this->structure($organization);
        $other = $this->occurrence($sameOrg, $otherSchool, $otherEnvironment, $otherCategory, $reporter);
        [$outsideOrg, $outsideSchool, $outsideEnv, $outsideCat] = $this->structure();
        $outside = $this->occurrence($outsideOrg, $outsideSchool, $outsideEnv, $outsideCat, User::factory()->for($outsideOrg)->create());
        $this->actingAs($manager)->get(route('occurrences.show', $ownSchoolOccurrence))->assertOk();
        $this->actingAs($manager)->get(route('occurrences.show', $other))->assertForbidden();
        $this->actingAs($manager)->get(route('occurrences.show', $outside))->assertForbidden();
    }

    public function test_requester_cannot_confirm_priority_but_manager_can_and_history_is_created(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => 'EM_TRIAGEM']);
        $data = ['confirmed_priority' => 'HIGH', 'triage_note' => 'Sala ficará indisponível.'];
        $this->actingAs($requester)->post(route('occurrences.priority.confirm', $occurrence), $data)->assertForbidden();
        $this->actingAs($manager)->post(route('occurrences.priority.confirm', $occurrence), $this->versioned($occurrence, $data))->assertRedirect();
        $this->assertSame('HIGH', $occurrence->fresh()->confirmed_priority);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'priority_confirmed']);
    }

    public function test_stale_triage_write_is_rejected_without_overwriting_the_first_change(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $firstManager = $this->roleUser($organization, $school, 'gestor');
        $secondManager = $this->roleUser($organization, $school, 'gestor');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => 'EM_TRIAGEM']);
        $versionReadByBothManagers = $occurrence->concurrencyToken();

        $this->actingAs($firstManager)
            ->get(route('occurrences.show', $occurrence))
            ->assertOk()
            ->assertSee('name="occurrence_version"', false)
            ->assertSee($versionReadByBothManagers);

        $this->actingAs($firstManager)->post(route('occurrences.priority.confirm', $occurrence), [
            'occurrence_version' => $versionReadByBothManagers,
            'confirmed_priority' => 'HIGH',
            'triage_note' => 'Primeira avaliação registrada.',
        ])->assertRedirect();

        $this->actingAs($secondManager)->post(route('occurrences.priority.confirm', $occurrence), [
            'occurrence_version' => $versionReadByBothManagers,
            'confirmed_priority' => 'LOW',
            'triage_note' => 'Avaliação baseada em uma tela desatualizada.',
        ])->assertSessionHasErrors('occurrence_version');

        $occurrence->refresh();
        $this->assertSame('HIGH', $occurrence->confirmed_priority);
        $this->assertSame('Primeira avaliação registrada.', $occurrence->triage_note);
        $this->assertSame($firstManager->id, $occurrence->triaged_by);
        $this->assertDatabaseCount('occurrence_histories', 1);
        $this->assertDatabaseHas('occurrence_histories', [
            'occurrence_id' => $occurrence->id,
            'actor_id' => $firstManager->id,
            'event_type' => 'priority_confirmed',
        ]);
    }

    public function test_manager_starts_triage_requests_information_and_requester_responds(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $secondManager = $this->roleUser($organization, $school, 'gestor');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester);
        $this->actingAs($manager)->post(route('occurrences.triage.start', $occurrence), $this->versioned($occurrence))->assertRedirect();
        $this->actingAs($manager)->post(route('occurrences.information.request', $occurrence), $this->versioned($occurrence, ['message' => 'Informe o patrimônio.']))->assertRedirect();
        $this->assertSame('AGUARDANDO_INFORMACOES', $occurrence->fresh()->status);
        $requestHistory = OccurrenceHistory::query()->where('event_type', 'information_requested')->sole();
        $this->assertDatabaseHas('internal_notifications', [
            'user_id' => $requester->id,
            'school_id' => $school->id,
            'event_key' => "occurrence:{$occurrence->id}:information_requested:history:{$requestHistory->id}",
            'type' => 'occurrence.information_requested',
            'body' => 'Informe o patrimônio.',
        ]);
        $this->assertDatabaseMissing('internal_notifications', ['user_id' => $manager->id, 'type' => 'occurrence.information_requested']);
        $this->actingAs($requester)->post(route('occurrences.information.provide', $occurrence), $this->versioned($occurrence, ['message' => 'Patrimônio 123.']))->assertRedirect();
        $this->assertSame('EM_TRIAGEM', $occurrence->fresh()->status);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'information_provided']);
        $providedHistory = OccurrenceHistory::query()->where('event_type', 'information_provided')->sole();
        foreach ([$manager, $secondManager] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', [
                'user_id' => $recipient->id,
                'event_key' => "occurrence:{$occurrence->id}:information_provided:history:{$providedHistory->id}",
                'type' => 'occurrence.information_provided',
                'body' => 'Patrimônio 123.',
            ]);
        }
        $this->assertDatabaseCount('internal_notifications', 3);
    }

    public function test_manager_marks_not_applicable_or_duplicate_with_principal_reference(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $principal = $this->occurrence($organization, $school, $environment, $category, $requester);
        $duplicate = $this->occurrence($organization, $school, $environment, $category, $requester);
        $notApplicable = $this->occurrence($organization, $school, $environment, $category, $requester);
        $this->actingAs($manager)->post(route('occurrences.duplicate', $duplicate), $this->versioned($duplicate, ['duplicate_of_id' => $principal->id]))->assertRedirect();
        $this->actingAs($manager)->post(route('occurrences.not-applicable', $notApplicable), $this->versioned($notApplicable, ['reason' => 'Não é manutenção.']))->assertRedirect();
        $this->assertDatabaseHas('occurrences', ['id' => $duplicate->id, 'status' => 'DUPLICADA', 'duplicate_of_id' => $principal->id]);
        $this->assertDatabaseHas('occurrences', ['id' => $notApplicable->id, 'status' => 'NAO_PROCEDE']);
        $this->assertDatabaseHas('occurrences', ['id' => $principal->id]);
    }

    public function test_manager_forwards_only_after_priority_and_invalid_transition_is_rejected(): void
    {
        [$organization, $school, $environment, $category] = $this->structure();
        $requester = $this->roleUser($organization, $school, 'solicitante');
        $manager = $this->roleUser($organization, $school, 'gestor');
        $secondManager = $this->roleUser($organization, $school, 'gestor');
        $occurrence = $this->occurrence($organization, $school, $environment, $category, $requester, ['status' => 'EM_TRIAGEM']);
        $this->actingAs($manager)->post(route('occurrences.forward', $occurrence), $this->versioned($occurrence, ['forwarded_destination' => 'Manutenção']))->assertSessionHasErrors('confirmed_priority');
        $occurrence->update(['confirmed_priority' => 'HIGH']);
        $this->actingAs($manager)->post(route('occurrences.forward', $occurrence), $this->versioned($occurrence, ['forwarded_destination' => 'Manutenção']))->assertRedirect();
        $this->assertSame('ENCAMINHADA', $occurrence->fresh()->status);
        $this->assertDatabaseHas('occurrence_histories', ['occurrence_id' => $occurrence->id, 'event_type' => 'forwarded']);
        $forwardHistory = OccurrenceHistory::query()->where('event_type', 'forwarded')->sole();
        foreach ([$manager, $secondManager] as $recipient) {
            $this->assertDatabaseHas('internal_notifications', [
                'user_id' => $recipient->id,
                'event_key' => "occurrence:{$occurrence->id}:forwarded:history:{$forwardHistory->id}",
                'type' => 'occurrence.forwarded',
            ]);
        }
        $this->assertDatabaseCount('internal_notifications', 2);
        $this->actingAs($manager)->post(route('occurrences.triage.start', $occurrence), $this->versioned($occurrence))->assertSessionHasErrors('status');
        $this->assertFalse(Route::has('occurrences.destroy'));
    }

    private function structure(?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create(['mode' => 'network']);
        $school = School::factory()->for($organization)->create();
        $environment = Environment::factory()->for($school)->create(['is_active' => true]);
        $category = OccurrenceCategory::factory()->for($organization)->create(['is_active' => true]);
        $category->schools()->attach($school);

        return [$organization, $school, $environment, $category];
    }

    private function roleUser(Organization $organization, School $school, string $role): User
    {
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create();
        $user->schools()->attach($school);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $roles[$role]->id, 'school_id' => $school->id]);

        return $user;
    }

    private function payload(School $school, Environment $environment, OccurrenceCategory $category, array $overrides = []): array
    {
        return array_merge(['school_id' => $school->id, 'environment_id' => $environment->id, 'occurrence_category_id' => $category->id, 'title' => 'Tomada sem funcionar', 'description' => 'A tomada ao lado da lousa não funciona.', 'impact' => 'MEDIUM', 'perceived_urgency' => 'NORMAL'], $overrides);
    }

    private function versioned(Occurrence $occurrence, array $data = []): array
    {
        $current = $occurrence->fresh();

        return ['occurrence_version' => $current->concurrencyToken()] + $data;
    }

    private function occurrence(Organization $organization, School $school, Environment $environment, OccurrenceCategory $category, User $reporter, array $overrides = []): Occurrence
    {
        static $sequence = 100;

        return Occurrence::factory()->create(array_merge(['organization_id' => $organization->id, 'school_id' => $school->id, 'environment_id' => $environment->id, 'occurrence_category_id' => $category->id, 'reporter_id' => $reporter->id, 'protocol' => sprintf('SIG-%s-%d-%06d', $school->code, now()->year, $sequence), 'protocol_year' => now()->year, 'protocol_sequence' => $sequence++], $overrides));
    }
}
