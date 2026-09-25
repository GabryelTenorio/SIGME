<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_two_factor_authentication_can_sign_in_normally(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura-123']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_privileged_manager_must_enroll_before_opening_business_pages(): void
    {
        config()->set('security.two_factor.enforce_privileged', true);
        $user = $this->managerUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('account.security.show'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSessionHas('two_factor_required');

        $this->actingAs($user)
            ->get(route('account.security.show'))
            ->assertOk()
            ->assertSeeText('Seu perfil exige autenticação em dois fatores');
    }

    public function test_user_can_enroll_with_current_password_and_confirm_a_totp(): void
    {
        $user = $this->managerUser();

        $this->actingAs($user)
            ->post(route('account.security.two-factor.enable'), [
                'current_password' => 'senha-segura-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('two_factor_recovery_codes', fn (array $codes): bool => count($codes) === 8);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $code = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);

        $this->actingAs($user)
            ->post(route('account.security.two-factor.confirm'), ['code' => $code])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_enabled_two_factor_authentication_requires_and_accepts_a_fresh_totp(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->create([
            'password' => 'senha-segura-123',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();

        $this->post(route('two-factor.verify'), [
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $recoveryCode = 'ABCDE-FGHIJ';
        $user = User::factory()->create([
            'password' => 'senha-segura-123',
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(32),
            'two_factor_recovery_codes' => [hash('sha256', 'ABCDEFGHIJ')],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->startTwoFactorLogin($user);

        $this->post(route('two-factor.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);

        $this->post(route('logout'));
        $this->startTwoFactorLogin($user);

        $this->post(route('two-factor.verify'), ['code' => $recoveryCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_two_factor_secrets_are_hidden_and_encrypted_at_rest(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $recoveryHash = hash('sha256', 'ABCDEFGHIJ');
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [$recoveryHash],
            'two_factor_confirmed_at' => now(),
        ]);

        $serialized = $user->toArray();
        $this->assertArrayNotHasKey('two_factor_secret', $serialized);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $serialized);

        $stored = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($secret, $stored->two_factor_secret);
        $this->assertStringNotContainsString($recoveryHash, $stored->two_factor_recovery_codes);
    }

    private function managerUser(): User
    {
        $organization = Organization::factory()->create();
        $school = School::factory()->for($organization)->create();
        $roles = app(AccessCatalog::class)->provision($organization);
        $user = User::factory()->for($organization)->create(['password' => 'senha-segura-123']);
        $user->schools()->attach($school);
        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $roles['gestor']->id,
            'school_id' => $school->id,
        ]);

        return $user;
    }

    private function startTwoFactorLogin(User $user): void
    {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-segura-123',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }
}
