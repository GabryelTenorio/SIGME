<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_receives_a_password_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_notifications_enabled' => false]);

        $this->assertFalse($user->email_notifications_enabled);

        $this->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se a conta estiver cadastrada, enviaremos um link para definir uma nova senha.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_pending_first_access_user_can_request_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->pendingFirstAccess()->create(['email_notifications_enabled' => false]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se a conta estiver cadastrada, enviaremos um link para definir uma nova senha.');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertTrue($user->fresh()->requiresFirstAccess());
        $this->assertDatabaseHas('first_access_tokens', ['user_id' => $user->id]);
    }

    public function test_unknown_email_receives_the_same_public_response(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nao-existe@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se a conta estiver cadastrada, enviaremos um link para definir uma nova senha.');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_the_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga-segura']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura-123',
            'password_confirmation' => 'nova-senha-segura-123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Senha definida. Agora você já pode entrar.');

        $this->assertTrue(Hash::check('nova-senha-segura-123', $user->fresh()->password));
    }

    public function test_invalid_reset_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga-segura']);

        $this->post(route('password.update'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nova-senha-segura-123',
            'password_confirmation' => 'nova-senha-segura-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('senha-antiga-segura', $user->fresh()->password));
    }
}
