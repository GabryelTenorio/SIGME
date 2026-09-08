<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Manutenção escolar com informação no lugar certo.')
            ->assertSeeText('Entrar no SIGME');
    }

    public function test_active_user_can_authenticate_and_open_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura-123']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText($user->name)
            ->assertSeeText('Organizações e acesso');
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'password' => 'senha-segura-123',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
