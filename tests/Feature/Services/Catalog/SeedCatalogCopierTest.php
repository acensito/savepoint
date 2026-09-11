<?php

namespace Tests\Feature\Services\Catalog;

use App\Models\Edition;
use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use App\Services\Catalog\SeedCatalogCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #175: única fuente del catálogo base que se copia a cada cuenta
 * nueva, usada por DatabaseSeeder/RegisterController/UserController por
 * igual.
 */
class SeedCatalogCopierTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_the_base_catalog_scoped_to_the_given_user(): void
    {
        $user = User::factory()->create();

        app(SeedCatalogCopier::class)->copyTo($user);

        $switch = Platform::where('user_id', $user->id)->where('slug', 'nintendo-switch')->first();
        $this->assertNotNull($switch);

        $nintendo = Manufacturer::where('user_id', $user->id)->where('slug', 'nintendo')->first();
        $this->assertNotNull($nintendo);
        $this->assertSame($nintendo->id, $switch->manufacturer_id);

        // PC no tiene fabricante.
        $pc = Platform::where('user_id', $user->id)->where('slug', 'pc')->first();
        $this->assertNotNull($pc);
        $this->assertNull($pc->manufacturer_id);

        $normal = Edition::where('user_id', $user->id)->where('name', 'Normal')->first();
        $this->assertNotNull($normal);
        $this->assertSame(Edition::FORMAT_PHYSICAL_DISC, $normal->format);
    }

    public function test_two_users_get_independent_copies(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $copier = app(SeedCatalogCopier::class);

        $copier->copyTo($userA);
        $copier->copyTo($userB);

        $platformA = Platform::where('user_id', $userA->id)->where('slug', 'nintendo-switch')->firstOrFail();
        $platformB = Platform::where('user_id', $userB->id)->where('slug', 'nintendo-switch')->firstOrFail();

        $this->assertNotSame($platformA->id, $platformB->id);
    }

    public function test_running_it_twice_for_the_same_user_does_not_duplicate_rows(): void
    {
        $user = User::factory()->create();
        $copier = app(SeedCatalogCopier::class);

        $copier->copyTo($user);
        $copier->copyTo($user);

        $this->assertSame(1, Platform::where('user_id', $user->id)->where('slug', 'nintendo-switch')->count());
        $this->assertSame(1, Manufacturer::where('user_id', $user->id)->where('slug', 'nintendo')->count());
        $this->assertSame(1, Edition::where('user_id', $user->id)->where('name', 'Normal')->count());
    }
}
