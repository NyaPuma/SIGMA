<?php

namespace Tests\Authentication;

use App\Enums\UserRoleEnum;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class PasswordResetFlowTest extends FeatureTestCase
{
    private function createUser(): User
    {
        return $this->createUserWithPassword(
            UserRoleEnum::User->value,
            'reset@example.com',
            'password-antiga-1',
            ['api_token' => Str::random(60)],
        );
    }

    #[Test]
    public function send_reset_link_returns_token_in_local_and_testing(): void
    {
        Mail::fake();

        $user = $this->createUser();

        $response = $this->postJson('/api/password/email', [
            'email' => 'RESET@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);
        $this->assertTrue(Hash::check($token, $record->token));

        Mail::assertQueued(PasswordResetMail::class, fn (PasswordResetMail $mail) => $mail->hasTo($user->email));
    }

    #[Test]
    public function send_reset_link_never_returns_the_token_outside_local_and_testing(): void
    {
        Mail::fake();

        $user = $this->createUser();

        // Staging and preprod deployments are not `production`, and the previous
        // condition handed them the plaintext recovery token in the JSON body.
        $this->app['env'] = 'staging';

        $response = $this->postJson('/api/password/email', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertJson(['token' => null]);

        Mail::assertQueued(PasswordResetMail::class, fn (PasswordResetMail $mail) => $mail->hasTo($user->email));
    }

    #[Test]
    public function send_reset_link_sends_email_with_reset_url_for_existing_user(): void
    {
        Mail::fake();

        $user = $this->createUser();

        $response = $this->postJson('/api/password/email', [
            'email' => $user->email,
        ]);

        $token = $response->json('token');

        Mail::assertQueued(
            PasswordResetMail::class,
            function (PasswordResetMail $mail) use ($user, $token): bool {
                return $mail->hasTo($user->email)
                    && str_contains(
                        $mail->render(),
                        route('api.password.reset.form', ['token' => $token])
                    );
            }
        );
    }

    #[Test]
    public function send_reset_link_does_not_throw_for_unknown_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/password/email', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertOk();

        Mail::assertNothingSent();
    }

    #[Test]
    public function send_reset_link_validates_email_format(): void
    {
        $response = $this->postJson('/api/password/email', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function send_reset_link_normalizes_email_to_lowercase(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/password/email', [
            'email' => '  '.strtoupper($user->email).'  ',
        ]);

        $response->assertOk();

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);
    }

    #[Test]
    public function reset_password_flow_updates_password_and_revokes_tokens(): void
    {
        $user = $this->createUser();

        $tokenResponse = $this->postJson('/api/password/email', ['email' => $user->email]);
        $token = $tokenResponse->json('token');

        $response = $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Nova-password-1',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('Nova-password-1', $user->fresh()->password));
        $this->assertNull($user->fresh()->api_token);
        $this->assertEquals(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    #[Test]
    public function reset_password_rejects_invalid_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/password/reset', [
            'email' => 'reset@example.com',
            'token' => 'token-inválido',
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Nova-password-1',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function reset_password_rejects_expired_token(): void
    {
        $user = $this->createUser();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('token-antigo'),
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => 'token-antigo',
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Nova-password-1',
        ]);

        $response->assertStatus(422);
        $this->assertFalse(Hash::check('Nova-password-1', $user->fresh()->password));
    }

    #[Test]
    public function reset_password_validates_password_policy(): void
    {
        $user = $this->createUser();

        $tokenResponse = $this->postJson('/api/password/email', ['email' => $user->email]);
        $token = $tokenResponse->json('token');

        $response = $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'fraco',
            'password_confirmation' => 'fraco',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function reset_password_validation_edge_cases(): void
    {
        // Missing fields
        $this->postJson('/api/password/reset', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);

        // Email mal formatado
        $this->postJson('/api/password/reset', [
            'email' => 'not-an-email',
            'token' => 'qualquer-token',
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Nova-password-1',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        // Password confirmation does not match
        $this->postJson('/api/password/reset', [
            'email' => 'reset@example.com',
            'token' => 'qualquer-token',
            'password' => 'Nova-password-1',
            'password_confirmation' => 'Outra-password-1',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
