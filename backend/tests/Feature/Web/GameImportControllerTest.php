<?php

namespace Tests\Feature\Web;

use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GameImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content, string $name = 'games.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_guest_cannot_access_the_import_form(): void
    {
        $this->get('/games/import')->assertRedirect('/login');
    }

    public function test_import_form_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/games/import')->assertOk();
    }

    public function test_template_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/games/import/template');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee('Título', false);
    }

    public function test_user_can_import_games_creating_missing_platform_and_edition(): void
    {
        $user = User::factory()->create();

        $csv = "Título,Plataforma,Edición,Propiedad,Estado de juego,Valoración\r\n"
             . "Celeste,Nintendo Switch,Coleccionista,En colección,Terminado,5\r\n";

        $response = $this->actingAs($user)->post('/games/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect(route('web.games.import'));
        $response->assertSessionHas('importResult.imported', 1);
        $response->assertSessionHas('importResult.createdPlatforms', 1);
        $response->assertSessionHas('importResult.createdEditions', 1);

        $platform = Platform::where('name', 'Nintendo Switch')->firstOrFail();
        $edition = Edition::where('name', 'Coleccionista')->firstOrFail();

        $this->assertDatabaseHas('games', [
            'user_id' => $user->id,
            'title' => 'Celeste',
            'platform_id' => $platform->id,
            'edition_id' => $edition->id,
            'status' => 'owned',
            'play_status' => 'finished',
            'rating' => 5,
        ]);
    }

    public function test_import_reuses_an_existing_platform_instead_of_duplicating_it(): void
    {
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);

        $csv = "Título,Plataforma\r\nHollow Knight,nintendo switch\r\n";

        $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)])
            ->assertSessionHas('importResult.createdPlatforms', 0);

        $this->assertDatabaseHas('games', ['title' => 'Hollow Knight', 'platform_id' => $platform->id]);
        $this->assertSame(1, Platform::where('name', 'Nintendo Switch')->count());
    }

    public function test_import_skips_rows_without_a_title_and_reports_them_as_errors(): void
    {
        $user = User::factory()->create();

        $csv = "Título,Plataforma\r\nHollow Knight,\r\n,Nintendo Switch\r\n";

        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        $response->assertSessionHas('importResult.imported', 1);
        $errors = $response->getSession()->get('importResult')['errors'];
        $this->assertCount(1, $errors);
        $this->assertDatabaseCount('games', 1);
    }

    public function test_import_supports_semicolon_delimited_files(): void
    {
        $user = User::factory()->create();

        $csv = "Título;Plataforma;Precio pagado\r\nCeleste;Nintendo Switch;19,99\r\n";

        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        $response->assertSessionHas('importResult.imported', 1);
        $this->assertDatabaseHas('games', ['title' => 'Celeste', 'price_paid' => 19.99]);
    }

    public function test_import_requires_a_title_column(): void
    {
        $user = User::factory()->create();

        $csv = "Plataforma\r\nNintendo Switch\r\n";

        $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_import_requires_a_csv_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/games/import', [])
            ->assertSessionHasErrors('file');
    }
}
