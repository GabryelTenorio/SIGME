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
            ->assertSeeText($user->email)
            ->assertSeeText('Conta ativa')
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

        $response = $this->actingAs($user)
            ->withSession([
                'auth.two_factor_pending' => ['user_id' => $user->id],
                'temporary_account_data' => 'must-be-removed',
            ])
            ->post(route('logout'));

        $response
            ->assertRedirect(route('login'))
            ->assertHeader('Clear-Site-Data', '"cache", "cookies", "storage"')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertGuest();
        $this->assertFalse(session()->has('auth.two_factor_pending'));
        $this->assertFalse(session()->has('temporary_account_data'));
    }

    public function test_private_pages_are_not_cached_by_the_browser(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
        $response->assertSee('data-logout-form', false);
        $response->assertSee('data-logout-redirect="'.route('login').'"', false);
    }

    public function test_next_login_uses_only_the_new_account_after_logout(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'Conta anterior',
            'password' => 'senha-anterior-123',
        ]);
        $nextUser = User::factory()->create([
            'name' => 'Conta atual',
            'password' => 'senha-atual-123',
        ]);

        $this->post(route('login.store'), [
            'email' => $firstUser->email,
            'password' => 'senha-anterior-123',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $nextUser->email,
            'password' => 'senha-atual-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($nextUser);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Conta atual')
            ->assertDontSeeText('Conta anterior');
    }
}
