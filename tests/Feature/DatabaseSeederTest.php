<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_uses_configured_development_credentials_and_remains_idempotent(): void
    {
        config([
            'app.dev_credentials.email' => 'configured@example.test',
            'app.dev_credentials.password' => 'configured-password',
        ]);

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

        $user = User::where('email', 'configured@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(1, User::where('email', 'configured@example.test')->count());
        $this->assertTrue(Hash::check('configured-password', $user->password));
        $this->assertTrue($user->is_admin);
    }

    public function test_seeder_skips_development_user_when_credentials_are_empty(): void
    {
        config([
            'app.dev_credentials.email' => '',
            'app.dev_credentials.password' => '',
        ]);

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

        $this->assertDatabaseCount('users', 0);
    }
}
