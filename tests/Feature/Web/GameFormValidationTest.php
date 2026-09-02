<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\TestCase;

/**
 * Batería de validación del formulario de alta/edición de juegos
 * (GameController::validated(), reglas compartidas por store() y update()).
 * Cubre límites de tamaño, campos numéricos que no deben aceptar texto,
 * rangos, enums y el resto de reglas de resources/views/games/_form.blade.php.
 * El resto de comportamiento de store()/update() (carátula externa, IGDB,
 * duplicados por EAN...) ya está cubierto en GameControllerTest.
 */
class GameFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Juego de prueba',
            'play_status' => 'pending',
        ], $overrides);
    }

    // --- title ---------------------------------------------------------

    public function test_title_accepts_exactly_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'title' => str_repeat('a', 255),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['title' => str_repeat('a', 255)]);
    }

    public function test_title_rejects_more_than_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'title' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('games', 0);
    }

    // --- ean -------------------------------------------------------------

    public function test_ean_accepts_exactly_50_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'ean' => str_repeat('1', 50),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['ean' => str_repeat('1', 50)]);
    }

    public function test_ean_rejects_more_than_50_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'ean' => str_repeat('1', 51),
        ]));

        $response->assertSessionHasErrors('ean');
        $this->assertDatabaseCount('games', 0);
    }

    // --- developer -------------------------------------------------------

    public function test_developer_accepts_exactly_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'developer' => str_repeat('a', 255),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['developer' => str_repeat('a', 255)]);
    }

    public function test_developer_rejects_more_than_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'developer' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('developer');
        $this->assertDatabaseCount('games', 0);
    }

    // --- genres ------------------------------------------------------------

    public function test_genres_accepts_exactly_500_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'genres' => str_repeat('a', 500),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseCount('games', 1);
    }

    public function test_genres_rejects_more_than_500_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'genres' => str_repeat('a', 501),
        ]));

        $response->assertSessionHasErrors('genres');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_genres_are_split_and_trimmed_into_an_array(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games', $this->validPayload([
            'genres' => 'Acción,  Aventura ,RPG',
        ]));

        $game = Game::where('title', 'Juego de prueba')->firstOrFail();
        $this->assertSame(['Acción', 'Aventura', 'RPG'], $game->genres);
    }

    // --- purchase_place ----------------------------------------------------

    public function test_purchase_place_accepts_exactly_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'purchase_place' => str_repeat('a', 255),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['purchase_place' => str_repeat('a', 255)]);
    }

    public function test_purchase_place_rejects_more_than_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'purchase_place' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('purchase_place');
        $this->assertDatabaseCount('games', 0);
    }

    // --- wishlist_store ------------------------------------------------------

    public function test_wishlist_store_accepts_exactly_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_store' => str_repeat('a', 255),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['wishlist_store' => str_repeat('a', 255)]);
    }

    public function test_wishlist_store_rejects_more_than_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_store' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('wishlist_store');
        $this->assertDatabaseCount('games', 0);
    }

    // --- age_rating_select / age_rating_other -------------------------------

    public function test_age_rating_select_accepts_a_known_preset_and_saves_it_with_a_space(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => 'PEGI-12',
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['age_rating' => 'PEGI 12']);
    }

    public function test_age_rating_select_rejects_more_than_20_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => str_repeat('a', 21),
        ]));

        $response->assertSessionHasErrors('age_rating_select');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_age_rating_other_is_required_when_age_rating_select_is_other(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => 'other',
        ]));

        $response->assertSessionHasErrors('age_rating_other');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_age_rating_other_accepts_exactly_20_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => 'other',
            'age_rating_other' => str_repeat('a', 20),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['age_rating' => str_repeat('a', 20)]);
    }

    public function test_age_rating_other_rejects_more_than_20_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => 'other',
            'age_rating_other' => str_repeat('a', 21),
        ]));

        $response->assertSessionHasErrors('age_rating_other');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_age_rating_ignores_age_rating_other_when_age_rating_select_is_a_preset(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'age_rating_select' => 'USK-18',
            'age_rating_other' => 'Se ignora',
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['age_rating' => 'USK 18']);
    }

    // --- notes ---------------------------------------------------------------

    public function test_notes_accepts_exactly_2000_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'notes' => str_repeat('a', 2000),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseCount('games', 1);
    }

    public function test_notes_rejects_more_than_2000_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'notes' => str_repeat('a', 2001),
        ]));

        $response->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('games', 0);
    }

    // --- region_select / region_other ----------------------------------------

    public function test_region_select_rejects_more_than_20_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => str_repeat('a', 21),
        ]));

        $response->assertSessionHasErrors('region_select');
        $this->assertDatabaseCount('games', 0);
    }

    /**
     * region_select no está limitado a GameController::REGION_PRESETS por una
     * regla Rule::in: el desplegable del formulario solo ofrece esos valores,
     * pero el servidor acepta cualquier cadena de hasta 20 caracteres que no
     * sea "other". Se documenta aquí el comportamiento actual, no se asume
     * que sea un bug.
     */
    public function test_region_select_accepts_any_value_up_to_20_characters_not_just_the_presets(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => 'NO-ES-UN-PRESET',
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['region' => 'NO-ES-UN-PRESET']);
    }

    public function test_region_other_is_required_when_region_select_is_other(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => 'other',
        ]));

        $response->assertSessionHasErrors('region_other');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_region_other_accepts_exactly_50_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => 'other',
            'region_other' => str_repeat('a', 50),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['region' => str_repeat('a', 50)]);
    }

    public function test_region_other_rejects_more_than_50_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => 'other',
            'region_other' => str_repeat('a', 51),
        ]));

        $response->assertSessionHasErrors('region_other');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_region_ignores_region_other_when_region_select_is_a_preset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games', $this->validPayload([
            'region_select' => 'PAL-ES',
            'region_other' => 'Esto no debería guardarse',
        ]));

        $game = Game::where('title', 'Juego de prueba')->firstOrFail();
        $this->assertSame('PAL-ES', $game->region);
    }

    // --- campos numéricos: rechazan texto -------------------------------------

    public function test_price_paid_rejects_non_numeric_text(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'price_paid' => 'gratis',
        ]));

        $response->assertSessionHasErrors('price_paid');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_wishlist_estimated_price_rejects_non_numeric_text(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_estimated_price' => 'caro',
        ]));

        $response->assertSessionHasErrors('wishlist_estimated_price');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_playtime_hours_rejects_non_numeric_text(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'playtime_hours' => 'muchas',
        ]));

        $response->assertSessionHasErrors('playtime_hours');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_rating_rejects_non_numeric_text(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'rating' => 'excelente',
        ]));

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_wishlist_priority_rejects_non_numeric_text(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_priority' => 'alta',
        ]));

        $response->assertSessionHasErrors('wishlist_priority');
        $this->assertDatabaseCount('games', 0);
    }

    // --- rating / wishlist_priority: enteros, no decimales -----------------

    public function test_rating_rejects_a_decimal_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'rating' => '4.5',
        ]));

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_wishlist_priority_rejects_a_decimal_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_priority' => '1.5',
        ]));

        $response->assertSessionHasErrors('wishlist_priority');
        $this->assertDatabaseCount('games', 0);
    }

    // --- rating: rango 1-5 -----------------------------------------------------

    public function test_rating_accepts_the_minimum_and_maximum_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games', $this->validPayload(['title' => 'Min', 'rating' => 1]));
        $this->actingAs($user)->post('/games', $this->validPayload(['title' => 'Max', 'rating' => 5]));

        $this->assertSame(1, Game::where('title', 'Min')->firstOrFail()->rating);
        $this->assertSame(5, Game::where('title', 'Max')->firstOrFail()->rating);
    }

    public function test_rating_rejects_a_value_below_the_minimum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload(['rating' => 0]));

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_rating_rejects_a_value_above_the_maximum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload(['rating' => 6]));

        $response->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('games', 0);
    }

    // --- wishlist_priority: rango 1-3 -------------------------------------------

    public function test_wishlist_priority_accepts_the_minimum_and_maximum_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games', $this->validPayload([
            'title' => 'Min', 'status' => 'wishlist', 'wishlist_priority' => 1,
        ]));
        $this->actingAs($user)->post('/games', $this->validPayload([
            'title' => 'Max', 'status' => 'wishlist', 'wishlist_priority' => 3,
        ]));

        $this->assertSame(1, Game::where('title', 'Min')->firstOrFail()->wishlist_priority);
        $this->assertSame(3, Game::where('title', 'Max')->firstOrFail()->wishlist_priority);
    }

    public function test_wishlist_priority_rejects_a_value_below_the_minimum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist', 'wishlist_priority' => 0,
        ]));

        $response->assertSessionHasErrors('wishlist_priority');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_wishlist_priority_rejects_a_value_above_the_maximum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist', 'wishlist_priority' => 4,
        ]));

        $response->assertSessionHasErrors('wishlist_priority');
        $this->assertDatabaseCount('games', 0);
    }

    // --- playtime_hours: rango 0-99999.9 (tope real de decimal(6,1)) -----------

    public function test_playtime_hours_accepts_the_maximum_allowed_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'playtime_hours' => '99999.9',
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertEquals(99999.9, Game::where('title', 'Juego de prueba')->firstOrFail()->playtime_hours);
    }

    public function test_playtime_hours_rejects_a_value_above_the_maximum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'playtime_hours' => '100000',
        ]));

        $response->assertSessionHasErrors('playtime_hours');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_playtime_hours_rejects_a_negative_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'playtime_hours' => '-1',
        ]));

        $response->assertSessionHasErrors('playtime_hours');
        $this->assertDatabaseCount('games', 0);
    }

    // --- price_paid / wishlist_estimated_price: no negativos --------------------

    public function test_price_paid_accepts_zero(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload(['price_paid' => 0]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertEquals(0, Game::where('title', 'Juego de prueba')->firstOrFail()->price_paid);
    }

    public function test_price_paid_rejects_a_negative_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload(['price_paid' => '-10']));

        $response->assertSessionHasErrors('price_paid');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_wishlist_estimated_price_rejects_a_negative_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_estimated_price' => '-5',
        ]));

        $response->assertSessionHasErrors('wishlist_estimated_price');
        $this->assertDatabaseCount('games', 0);
    }

    /**
     * price_paid es decimal(10,2): sin un max: que lo refleje, un valor de 9
     * dígitos pasaba la validación de Laravel y reventaba como
     * QueryException (numeric field overflow) en vez de un aviso normal del
     * formulario, igual que ya documentaba el comentario de playtime_hours.
     */
    public function test_price_paid_rejects_a_value_beyond_the_database_column_size(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'price_paid' => '123456789.12',
        ]));

        $response->assertSessionHasErrors('price_paid');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_price_paid_accepts_the_maximum_allowed_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'price_paid' => '99999999.99',
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertEquals(99999999.99, Game::where('title', 'Juego de prueba')->firstOrFail()->price_paid);
    }

    public function test_wishlist_estimated_price_rejects_a_value_beyond_the_database_column_size(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'wishlist',
            'wishlist_estimated_price' => '123456789.12',
        ]));

        $response->assertSessionHasErrors('wishlist_estimated_price');
        $this->assertDatabaseCount('games', 0);
    }

    // --- enums: status / play_status / manual_status ----------------------------

    public function test_status_rejects_a_value_outside_the_allowed_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'status' => 'no-es-un-estado-valido',
        ]));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_play_status_rejects_a_value_outside_the_allowed_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'play_status' => 'no-es-un-estado-valido',
        ]));

        $response->assertSessionHasErrors('play_status');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_manual_status_rejects_a_value_outside_the_allowed_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'manual_status' => 'no-es-un-estado-valido',
        ]));

        $response->assertSessionHasErrors('manual_status');
        $this->assertDatabaseCount('games', 0);
    }

    // --- claves foráneas: platform_id / edition_id ----------------------------

    public function test_platform_id_rejects_an_id_that_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'platform_id' => 999999,
        ]));

        $response->assertSessionHasErrors('platform_id');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_platform_id_accepts_an_existing_id(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'platform_id' => $platform->id,
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['platform_id' => $platform->id]);
    }

    public function test_edition_id_rejects_an_id_that_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'edition_id' => 999999,
        ]));

        $response->assertSessionHasErrors('edition_id');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_edition_id_accepts_an_existing_id(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'edition_id' => $edition->id,
        ]));

        $response->assertRedirect(route('web.games.index'));
        $this->assertDatabaseHas('games', ['edition_id' => $edition->id]);
    }

    // --- fechas ------------------------------------------------------------------

    public function test_release_date_rejects_an_invalid_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'release_date' => 'no-es-una-fecha',
        ]));

        $response->assertSessionHasErrors('release_date');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_purchase_date_rejects_an_invalid_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'purchase_date' => 'no-es-una-fecha',
        ]));

        $response->assertSessionHasErrors('purchase_date');
        $this->assertDatabaseCount('games', 0);
    }

    // --- carátula ------------------------------------------------------------------

    public function test_cover_rejects_a_non_image_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'cover' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('cover');
        $this->assertDatabaseCount('games', 0);
    }

    public function test_cover_rejects_a_file_larger_than_the_1_megabyte_limit(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'cover' => UploadedFile::fake()->image('cover.jpg')->size(1025),
        ]));

        $response->assertSessionHasErrors(['cover' => 'No se admiten imágenes superiores a 1 MB.']);
        $this->assertDatabaseCount('games', 0);
    }

    public function test_cover_reports_the_generic_upload_failure_message_when_php_rejects_the_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'cover-upload-');
        file_put_contents($path, 'placeholder');

        try {
            $response = $this->actingAs($user)->post('/games', $this->validPayload([
                'cover' => new SymfonyUploadedFile($path, 'cover.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true),
            ]));

            $response->assertSessionHasErrors(['cover' => 'No se pudo subir la carátula. Comprueba el archivo e inténtalo de nuevo.']);
            $this->assertDatabaseCount('games', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_cover_accepts_a_file_at_exactly_the_size_limit(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/games', $this->validPayload([
            'cover' => UploadedFile::fake()->image('cover.jpg')->size(1024),
        ]));

        $response->assertRedirect(route('web.games.index'));
        $game = Game::where('title', 'Juego de prueba')->firstOrFail();
        Storage::disk('public')->assertExists($game->cover);
    }

    // --- update() aplica las mismas reglas (comparten validated()) ---------------

    public function test_updating_a_game_also_rejects_a_title_over_255_characters(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['title' => 'Original']);

        $response = $this->actingAs($user)->put("/games/{$game->id}", $this->validPayload([
            'title' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('title');
        $this->assertSame('Original', $game->fresh()->title);
    }

    public function test_updating_a_game_also_rejects_non_numeric_price_paid(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['price_paid' => 10]);

        $response = $this->actingAs($user)->put("/games/{$game->id}", $this->validPayload([
            'price_paid' => 'gratis',
        ]));

        $response->assertSessionHasErrors('price_paid');
        $this->assertEquals(10, $game->fresh()->price_paid);
    }

    public function test_updating_a_game_also_rejects_an_invalid_play_status(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create(['play_status' => 'pending']);

        $response = $this->actingAs($user)->put("/games/{$game->id}", $this->validPayload([
            'play_status' => 'no-es-un-estado-valido',
        ]));

        $response->assertSessionHasErrors('play_status');
        $this->assertSame('pending', $game->fresh()->play_status);
    }

    public function test_updating_a_game_also_rejects_a_non_image_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $game = Game::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/games/{$game->id}", $this->validPayload([
            'cover' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('cover');
    }
}
