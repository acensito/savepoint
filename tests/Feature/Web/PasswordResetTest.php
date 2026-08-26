<?php

namespace Tests\Feature\Web;

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

    public function test_forgot_password_form_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_reset_link_is_sent_for_an_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_gives_the_same_generic_response(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_reset_password_form_can_be_rendered(): void
    {
        $this->get('/reset-password/some-token?email=test@example.com')->assertOk();
    }

    public function test_user_can_reset_their_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Brand-New-Password1',
            'password_confirmation' => 'Brand-New-Password1',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Brand-New-Password1', $user->fresh()->password));
    }

    public function test_resetting_the_password_revokes_existing_api_tokens(): void
    {
        // Regresión (#34): ver ProfileControllerTest::test_changing_the_password_revokes_existing_api_tokens.
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->createToken('MobileApp');
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Brand-New-Password1',
            'password_confirmation' => 'Brand-New-Password1',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Regresión (#51): antes solo el registro público exigía complejidad de
     * contraseña — el reseteo por email se conformaba con min:8, así que
     * "password1" (sin mayúscula ni símbolo) colaba aquí aunque no lo
     * hiciera en /register.
     */
    public function test_reset_requires_the_password_to_be_complex(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->post('/reset-password', [
            'token' => 'not-the-real-token',
            'email' => $user->email,
            'password' => 'Brand-New-Password1',
            'password_confirmation' => 'Brand-New-Password1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
