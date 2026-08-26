<?php

namespace Tests\Feature\Web;

use App\Models\Game;
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

    public function test_changing_the_password_revokes_existing_api_tokens(): void
    {
        // Regresión (#34): un token de la app móvil robado no debe seguir
        // sirviendo tras cambiar la contraseña — la respuesta estándar ante
        // sospecha de robo debe cortar también el acceso ya conseguido.
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $user->createToken('MobileApp');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
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

    public function test_user_can_delete_their_own_account_with_all_their_data(): void
    {
        Storage::fake('public');

        $avatarPath = 'avatars/1/photo.jpg';
        Storage::disk('public')->put($avatarPath, 'dummy');

        $user = User::factory()->create(['password' => Hash::make('current-password'), 'avatar_path' => $avatarPath]);
        $user->createToken('MobileApp');

        $coverPath = 'covers/owned.jpg';
        Storage::disk('public')->put($coverPath, 'dummy');
        $ownedGame = Game::factory()->for($user)->create(['cover' => $coverPath]);

        // También los que ya estaban en la papelera: no deben quedar
        // huérfanos (ni ellos ni su carátula) tras borrar la cuenta.
        $trashedCoverPath = 'covers/trashed.jpg';
        Storage::disk('public')->put($trashedCoverPath, 'dummy');
        $trashedGame = Game::factory()->for($user)->create(['cover' => $trashedCoverPath]);
        $trashedGame->delete();

        $response = $this->actingAs($user)->delete('/profile', [
            'current_password' => 'current-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('games', ['id' => $ownedGame->id]);
        $this->assertDatabaseMissing('games', ['id' => $trashedGame->id]);
        Storage::disk('public')->assertMissing($coverPath);
        Storage::disk('public')->assertMissing($trashedCoverPath);
        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deleting_the_account_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete('/profile', [
            'current_password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('games', ['id' => $game->id]);
    }

    public function test_guest_cannot_delete_an_account(): void
    {
        $this->delete('/profile', ['current_password' => 'whatever'])
            ->assertRedirect('/login');
    }
}
