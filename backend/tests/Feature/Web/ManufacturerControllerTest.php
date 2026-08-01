<?php

namespace Tests\Feature\Web;

use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_manufacturers(): void
    {
        $this->get('/manufacturers')->assertRedirect('/login');
    }

    public function test_index_lists_manufacturers_with_platform_count(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create(['name' => 'Nintendo']);
        Platform::factory()->for($manufacturer)->count(2)->create();

        $response = $this->actingAs($user)->get('/manufacturers');

        $response->assertOk();
        $response->assertSee('Nintendo');
        $response->assertSee('2');
    }

    public function test_user_can_create_a_manufacturer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/manufacturers', [
            'name' => 'Sony',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ]);

        $response->assertRedirect(route('web.manufacturers.index'));
        $this->assertDatabaseHas('manufacturers', ['name' => 'Sony', 'slug' => 'sony']);
    }

    public function test_creating_a_manufacturer_requires_name_and_valid_colors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/manufacturers', [
            'name' => '',
            'bg_color' => 'not-a-color',
            'text_color' => '',
            'border_color' => '',
        ])->assertSessionHasErrors(['name', 'bg_color', 'text_color', 'border_color']);
    }

    public function test_manufacturer_name_must_be_unique(): void
    {
        $user = User::factory()->create();
        Manufacturer::factory()->create(['name' => 'Nintendo']);

        $this->actingAs($user)->post('/manufacturers', [
            'name' => 'Nintendo',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ])->assertSessionHasErrors('name');
    }

    public function test_user_can_update_a_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put("/manufacturers/{$manufacturer->id}", [
            'name' => 'New Name',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ]);

        $response->assertRedirect(route('web.manufacturers.index'));
        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturer->id, 'name' => 'New Name', 'slug' => 'new-name']);
    }

    public function test_deleting_a_manufacturer_nullifies_its_platforms(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create();
        $platform = Platform::factory()->for($manufacturer)->create();

        $this->actingAs($user)->delete("/manufacturers/{$manufacturer->id}")
            ->assertRedirect(route('web.manufacturers.index'));

        $this->assertDatabaseMissing('manufacturers', ['id' => $manufacturer->id]);
        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'manufacturer_id' => null]);
    }
}
