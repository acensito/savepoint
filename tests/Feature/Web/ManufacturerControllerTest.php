<?php

namespace Tests\Feature\Web;

use App\Models\Manufacturer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo por cuenta (issue #175): cada usuario gestiona sus propios
 * fabricantes, sin dato compartido con el resto — ver ManufacturerPolicy.
 */
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
        $manufacturer = Manufacturer::factory()->for($user)->create(['name' => 'Nintendo']);
        Platform::factory()->for($user)->for($manufacturer)->count(2)->create();

        $response = $this->actingAs($user)->get('/manufacturers');

        $response->assertOk();
        $response->assertSee('Nintendo');
        $response->assertSee('2');
    }

    public function test_index_does_not_list_another_users_manufacturers(): void
    {
        $user = User::factory()->create();
        Manufacturer::factory()->create(['name' => 'Ajeno']);

        $response = $this->actingAs($user)->get('/manufacturers');

        $response->assertOk();
        $response->assertDontSee('Ajeno');
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
        $this->assertDatabaseHas('manufacturers', ['user_id' => $user->id, 'name' => 'Sony', 'slug' => 'sony']);
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

    public function test_manufacturer_name_must_be_unique_within_the_same_account(): void
    {
        $user = User::factory()->create();
        Manufacturer::factory()->for($user)->create(['name' => 'Nintendo']);

        $this->actingAs($user)->post('/manufacturers', [
            'name' => 'Nintendo',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ])->assertSessionHasErrors('name');
    }

    /**
     * Issue #175: la unicidad del nombre es por cuenta, así que dos usuarios
     * pueden tener cada uno su propio "Nintendo" sin chocar entre sí.
     */
    public function test_manufacturer_name_does_not_need_to_be_unique_across_accounts(): void
    {
        $user = User::factory()->create();
        Manufacturer::factory()->create(['name' => 'Nintendo']);

        $this->actingAs($user)->post('/manufacturers', [
            'name' => 'Nintendo',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ])->assertRedirect(route('web.manufacturers.index'));

        $this->assertDatabaseHas('manufacturers', ['user_id' => $user->id, 'name' => 'Nintendo']);
    }

    public function test_user_can_update_a_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->for($user)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put("/manufacturers/{$manufacturer->id}", [
            'name' => 'New Name',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ]);

        $response->assertRedirect(route('web.manufacturers.index'));
        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturer->id, 'name' => 'New Name', 'slug' => 'new-name']);
    }

    public function test_user_cannot_view_the_edit_form_of_another_users_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAs($user)->get("/manufacturers/{$manufacturer->id}/edit")->assertForbidden();
    }

    public function test_user_cannot_update_another_users_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create(['name' => 'Ajeno']);

        $this->actingAs($user)->put("/manufacturers/{$manufacturer->id}", [
            'name' => 'Hijacked',
            'bg_color' => '#000000',
            'text_color' => '#FFFFFF',
            'border_color' => '#111111',
        ])->assertForbidden();

        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturer->id, 'name' => 'Ajeno']);
    }

    public function test_deleting_a_manufacturer_nullifies_its_platforms(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->for($user)->create();
        $platform = Platform::factory()->for($user)->for($manufacturer)->create();

        $this->actingAs($user)->delete("/manufacturers/{$manufacturer->id}")
            ->assertRedirect(route('web.manufacturers.index'));

        $this->assertDatabaseMissing('manufacturers', ['id' => $manufacturer->id]);
        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'manufacturer_id' => null]);
    }

    public function test_user_cannot_delete_another_users_manufacturer(): void
    {
        $user = User::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAs($user)->delete("/manufacturers/{$manufacturer->id}")->assertForbidden();

        $this->assertDatabaseHas('manufacturers', ['id' => $manufacturer->id]);
    }
}
