<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement: Guest Access to Registration Form
     */
    public function test_guest_can_view_registration_form(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Crear cuenta');
        $response->assertSee(route('login'));
    }

    /**
     * Requirement: Guest Middleware Redirection
     */
    public function test_authenticated_user_redirected_from_register_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect('/');
    }

    /**
     * Requirement: Successful Registration, Persistence & Auto-Login
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player1@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('web.games.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Player One',
            'email' => 'player1@example.com',
            'is_admin' => false,
        ]);
    }

    /**
     * Requirement: Laravel Registered Event Dispatch
     */
    public function test_registered_event_is_dispatched(): void
    {
        Event::fake([Registered::class]);

        $this->post(route('web.register.attempt'), [
            'name' => 'Player Two',
            'email' => 'player2@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        Event::assertDispatched(Registered::class, function (Registered $event) {
            return $event->user->email === 'player2@example.com';
        });
    }

    /**
     * Requirement: Validation - Name is required
     */
    public function test_registration_requires_name(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => '',
            'email' => 'valid@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Valid email format
     */
    public function test_registration_requires_valid_email(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'invalid-email',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Unique email
     */
    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('web.register.attempt'), [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password minimum 8 characters
     */
    public function test_registration_requires_password_min_8_chars(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password requires uppercase letter
     */
    public function test_registration_requires_password_with_uppercase_letter(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password requires lowercase letter
     */
    public function test_registration_requires_password_with_lowercase_letter(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'SECRET123!',
            'password_confirmation' => 'SECRET123!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password requires a number
     */
    public function test_registration_requires_password_with_number(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'Secretabc!',
            'password_confirmation' => 'Secretabc!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password requires a symbol
     */
    public function test_registration_requires_password_with_symbol(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Validation - Password confirmation must match
     */
    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Requirement: Privilege Escalation Prevention (is_admin = false)
     */
    public function test_registration_cannot_escalate_to_admin(): void
    {
        $this->post(route('web.register.attempt'), [
            'name' => 'Hacker One',
            'email' => 'hacker@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'is_admin' => true,
        ]);

        $user = User::where('email', 'hacker@example.com')->firstOrFail();
        $this->assertFalse($user->is_admin);
    }

    /**
     * Requirement: Password securely hashed in database
     */
    public function test_password_is_stored_hashed(): void
    {
        $this->post(route('web.register.attempt'), [
            'name' => 'Player One',
            'email' => 'player@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', 'player@example.com')->firstOrFail();
        $this->assertNotEquals('Secret123!', $user->password);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
    }

    /**
     * Requirement: Intended URL Redirection
     */
    public function test_registration_redirects_to_intended_destination(): void
    {
        $this->get(route('web.stats.index')); // Sets intended URL in session

        $response = $this->post(route('web.register.attempt'), [
            'name' => 'Player Intended',
            'email' => 'intended@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('web.stats.index'));
    }

    /**
     * Requirement: Rate Limiting on Registration Endpoint
     */
    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('web.register.attempt'), [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ]);
            // Logout after each successful registration to allow next guest attempt from same IP
            auth()->logout();
        }

        $response = $this->post(route('web.register.attempt'), [
            'name' => 'User Blocked',
            'email' => 'blocked@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(429);
    }

    /**
     * Requirement: Cross-Link Navigation
     */
    public function test_login_view_has_link_to_register(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('register'));
        $response->assertSee('Regístrate');
    }
}
