<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_view_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Ada Lovelace');
    }

    public function test_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ]);

        $response->assertRedirect(route('web.profile.edit'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ]);
    }

    public function test_updating_the_email_must_stay_unique(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_user_can_change_their_password_with_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('web.profile.edit'));
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_changing_the_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_changing_the_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');
    }

    public function test_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar_path' => null]);
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('web.profile.edit'));
        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_user_can_replace_an_existing_avatar(): void
    {
        Storage::fake('public');

        $oldPath = 'avatars/1/old.jpg';
        Storage::disk('public')->put($oldPath, 'dummy');

        $user = User::factory()->create(['avatar_path' => $oldPath]);
        $newFile = UploadedFile::fake()->image('new.png', 100, 100);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $newFile,
        ]);

        $response->assertRedirect(route('web.profile.edit'));
        $user->refresh();

        $this->assertNotEquals($oldPath, $user->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_user_can_remove_their_avatar(): void
    {
        Storage::fake('public');

        $path = 'avatars/1/photo.jpg';
        Storage::disk('public')->put($path, 'dummy');

        $user = User::factory()->create(['avatar_path' => $path]);

        $response = $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => '1',
        ]);

        $response->assertRedirect(route('web.profile.edit'));
        $user->refresh();

        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }
}
