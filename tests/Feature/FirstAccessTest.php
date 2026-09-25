<?php

namespace Tests\Feature;

use App\Models\FirstAccessToken;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Notifications\FirstAccessInvitation;
use App\Support\AccessCatalog;
use App\Support\FirstAccessInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class FirstAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_creates_pending_user_without_an_administrator_defined_password(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create(['mode' => 'network']);
        $roles = app(AccessCatalog::class)->provision($organization);
        $school = School::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->get(route('users.create', ['organization_id' => $organization->id]))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSeeText('O usuário receberá um e-mail para criar a própria senha.');

        $this->actingAs($admin)->post(route('users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Nova Gestora',
            'email' => 'nova.gestora@example.test',
            'is_active' => '1',
            'school_ids' => [$school->id],
            'role_ids' => [$roles['gestor']->id],
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'nova.gestora@example.test')->sole();

        $this->assertTrue($user->requiresFirstAccess());
        $this->assertTrue($user->schools()->whereKey($school->id)->exists());
        $this->assertTrue($user->roles()->whereKey($roles['gestor']->id)->exists());
        $this->assertDatabaseHas('first_access_tokens', ['user_id' => $user->id]);
        Notification::assertSentTo($user, FirstAccessInvitation::class);
    }

    public function test_invitation_is_stored_as_a_hash_has_no_time_expiry_and_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create();
        $token = app(FirstAccessInvitationService::class)->issue($user);

        $invitation = FirstAccessToken::query()->whereBelongsTo($user)->sole();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);

        $this->travel(10)->years();

        $this->get(route('first-access.show', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSeeText('Crie sua senha de acesso');

        $this->post(route('first-access.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'minha-nova-senha-2026',
            'password_confirmation' => 'minha-nova-senha-2026',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Senha definida. Seu primeiro acesso está liberado.');

        $user->refresh();
        $this->assertFalse($user->requiresFirstAccess());
        $this->assertTrue(Hash::check('minha-nova-senha-2026', $user->password));
        $this->assertDatabaseMissing('first_access_tokens', ['user_id' => $user->id]);
        $this->get(route('first-access.show', ['token' => $token, 'email' => $user->email]))
            ->assertNotFound();
    }

    public function test_regular_password_reset_can_complete_first_access_and_revokes_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create(['email_notifications_enabled' => false]);
        $invitationToken = app(FirstAccessInvitationService::class)->issue($user);
        $resetToken = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'senha-escolhida-pelo-usuario',
            'password_confirmation' => 'senha-escolhida-pelo-usuario',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertFalse($user->requiresFirstAccess());
        $this->assertTrue(Hash::check('senha-escolhida-pelo-usuario', $user->password));
        $this->assertDatabaseMissing('first_access_tokens', ['user_id' => $user->id]);
        $this->get(route('first-access.show', ['token' => $invitationToken, 'email' => $user->email]))
            ->assertNotFound();
    }

    public function test_pending_user_cannot_log_in_even_if_the_placeholder_password_is_known(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create(['password' => 'placeholder-conhecido-2026']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'placeholder-conhecido-2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_server_command_creates_pending_administrator_and_never_prints_a_password(): void
    {
        Notification::fake();

        $this->artisan('sigme:create-admin', [
            '--name' => 'Administradora do servidor',
            '--email' => 'admin.servidor@example.test',
            '--generate' => true,
        ])->expectsOutput('Administrador criado: admin.servidor@example.test')
            ->expectsOutput('O convite para definir a senha foi enviado por e-mail.')
            ->doesntExpectOutputToContain('Senha gerada')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin.servidor@example.test')->sole();

        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue($user->requiresFirstAccess());
        Notification::assertSentTo($user, FirstAccessInvitation::class);
    }

    public function test_server_command_updates_an_existing_administrator_without_replacing_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'admin.existente@example.test',
            'password' => 'senha-existente-segura',
            'is_platform_admin' => false,
        ]);

        $this->artisan('sigme:create-admin', [
            '--name' => 'Administradora atualizada',
            '--email' => $user->email,
        ])->expectsOutput("Administrador atualizado sem alterar a senha: {$user->email}")
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('Administradora atualizada', $user->name);
        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue(Hash::check('senha-existente-segura', $user->password));
        Notification::assertNothingSent();
    }

    public function test_changing_the_email_of_a_pending_account_rotates_the_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create(['email' => 'antigo@example.test']);
        $oldToken = app(FirstAccessInvitationService::class)->issue($user);
        $oldHash = hash('sha256', $oldToken);

        $user->update(['email' => 'novo@example.test']);

        $newInvitation = FirstAccessToken::query()->whereBelongsTo($user)->sole();
        $this->assertNotSame($oldHash, $newInvitation->token_hash);
        $this->get(route('first-access.show', ['token' => $oldToken, 'email' => 'antigo@example.test']))
            ->assertNotFound();
        Notification::assertSentToTimes($user, FirstAccessInvitation::class, 3);
    }

    public function test_inactive_account_cannot_use_an_existing_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create();
        $token = app(FirstAccessInvitationService::class)->issue($user);
        $user->update(['is_active' => false]);

        $this->get(route('first-access.show', ['token' => $token, 'email' => $user->email]))
            ->assertNotFound();

        $this->post(route('first-access.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'senha-nao-deve-ser-usada',
            'password_confirmation' => 'senha-nao-deve-ser-usada',
        ])->assertSessionHasErrors('email');

        $this->assertTrue($user->fresh()->requiresFirstAccess());
    }
}
