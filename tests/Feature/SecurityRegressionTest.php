<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\PrivateAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\AuditScenario;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use AuditScenario;
    use RefreshDatabase;

    public function test_web_responses_include_defensive_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));

        $secureResponse = app(SecurityHeaders::class)->handle(
            Request::create('https://sigme.test/entrar'),
            fn (): Response => new Response,
        );
        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $secureResponse->headers->get('Strict-Transport-Security'),
        );
    }

    public function test_templates_do_not_use_inline_event_handlers_blocked_by_the_csp(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/\son[a-z]+\s*=/i',
                $file->getContents(),
                "Inline event handler found in {$file->getRelativePathname()}.",
            );
        }
    }

    public function test_login_has_a_uniform_failure_message_and_is_rate_limited(): void
    {
        $known = User::factory()->create(['email' => 'known@security.invalid', 'password' => 'CorrectPassword2026!']);

        $knownFailure = $this->postJson(route('login.store'), [
            'email' => $known->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
        $unknownFailure = $this->postJson(route('login.store'), [
            'email' => 'unknown@security.invalid',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertSame(
            $knownFailure->json('errors.email.0'),
            $unknownFailure->json('errors.email.0'),
        );

        $email = 'rate-limit@security.invalid';
        RateLimiter::clear(strtolower($email).'|127.0.0.1');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $limited = $this->postJson(route('login.store'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertUnprocessable();
        $this->assertStringStartsWith(
            'Muitas tentativas.',
            $limited->json('errors.email.0'),
        );
    }

    public function test_user_creation_ignores_platform_admin_mass_assignment(): void
    {
        $scenario = $this->auditScenario();

        $this->actingAs($scenario['users']['platform'])->post(route('users.store'), [
            'organization_id' => $scenario['organization']->id,
            'name' => 'Usuário sem privilégio técnico',
            'email' => 'mass-assignment@security.invalid',
            'password' => 'MassAssignment2026!',
            'is_active' => '1',
            'is_platform_admin' => '1',
            'school_ids' => [$scenario['school']->id],
            'role_ids' => [$scenario['roles']['solicitante']->id],
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'mass-assignment@security.invalid')->sole();
        $this->assertFalse($created->is_platform_admin);
        $this->assertSame($scenario['organization']->id, $created->organization_id);
    }

    public function test_sql_injection_payload_does_not_expand_user_search_results(): void
    {
        $scenario = $this->auditScenario();

        $response = $this->actingAs($scenario['users']['platform'])->get(route('users.index', [
            'organization_id' => $scenario['organization']->id,
            'search' => "' OR 1=1 --",
        ]));

        $response->assertOk();
        foreach ($scenario['users'] as $key => $user) {
            if ($key !== 'platform') {
                $response->assertDontSeeText($user->email);
            }
        }
    }

    public function test_stored_xss_payload_is_escaped_in_occurrence_pages(): void
    {
        $scenario = $this->auditScenario();
        $payload = '<script>window.__sigme_xss = true</script>';
        $scenario['occurrence']->update(['title' => $payload, 'description' => $payload]);

        $this->actingAs($scenario['users']['platform'])
            ->get(route('occurrences.show', $scenario['occurrence']))
            ->assertOk()
            ->assertSeeText($payload)
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;window.__sigme_xss = true&lt;/script&gt;', false);
    }

    public function test_dangerous_upload_content_is_rejected_even_with_an_image_extension(): void
    {
        Storage::fake('local');
        $scenario = $this->auditScenario();
        $scenario['occurrence']->update(['status' => 'ABERTA']);

        $this->actingAs($scenario['users']['solicitante'])->post(
            route('occurrences.attachments', $scenario['occurrence']),
            ['evidence' => UploadedFile::fake()->createWithContent('shell.php.jpg', '<?php echo "executed";')],
        )->assertSessionHasErrors('evidence');

        $this->assertSame(0, $scenario['occurrence']->attachments()->count());
        Storage::disk('local')->assertDirectoryEmpty('occurrences');
    }

    public function test_direct_object_access_is_blocked_between_schools_and_for_private_downloads(): void
    {
        Storage::fake('local');
        $scenario = $this->auditScenario();
        $otherEnvironment = Environment::factory()->for($scenario['otherSchool'])->create();
        $otherOccurrence = Occurrence::factory()->create([
            'organization_id' => $scenario['organization']->id,
            'school_id' => $scenario['otherSchool']->id,
            'environment_id' => $otherEnvironment->id,
            'occurrence_category_id' => $scenario['category']->id,
            'reporter_id' => $scenario['users']['gestor']->id,
        ]);
        Storage::disk('local')->put('occurrences/private-proof.pdf', '%PDF-private');
        $attachment = $otherOccurrence->attachments()->create([
            'uploaded_by' => $scenario['users']['gestor']->id,
            'disk' => 'local',
            'path' => 'occurrences/private-proof.pdf',
            'original_name' => 'private-proof.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]);

        $this->actingAs($scenario['users']['solicitante'])
            ->get(route('occurrences.show', $otherOccurrence))
            ->assertForbidden();
        $this->get(route('attachments.download', $attachment))->assertNotFound();
        $this->assertDatabaseHas(PrivateAttachment::class, ['id' => $attachment->id]);
    }
}
