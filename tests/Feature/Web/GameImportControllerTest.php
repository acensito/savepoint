<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\GameImportController;
use App\Models\Edition;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GameImportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * store() persiste el CSV en el disco 'local' (ver
     * GameImportController::store()/Jobs\ImportGamesFromCsv) antes de
     * despacharlo: sin fingir el disco, cada corrida de los tests escribiría
     * en el storage real de desarrollo, igual que Storage::fake('public') ya
     * evita eso para las carátulas en otros tests de este controlador.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function csvFile(string $content, string $name = 'games.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * store() ya no deja el resultado listo en la propia redirección (ver
     * Jobs\ImportGamesFromCsv): dispara la importación y hay que consultar
     * /games/import/status/{id} para verlo. QUEUE_CONNECTION=sync en
     * phpunit.xml hace que el job ya haya terminado para cuando llega aquí.
     */
    private function importStatus(TestResponse $storeResponse): TestResponse
    {
        $importId = $storeResponse->getSession()->get('importId');

        return $this->getJson("/games/import/status/{$importId}");
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

        $csv = "Título,Plataforma,Edición,Propiedad,Estado de juego,Conservación\r\n"
             ."Celeste,Nintendo Switch,Coleccionista,En colección,Terminado,5\r\n";

        $response = $this->actingAs($user)->post('/games/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect(route('web.games.import'));
        $status = $this->importStatus($response);
        $status->assertJsonPath('done', true);
        $status->assertJsonPath('imported', 1);
        $status->assertJsonPath('createdPlatforms', 1);
        $status->assertJsonPath('createdEditions', 1);

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

        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);
        $this->importStatus($response)->assertJsonPath('createdPlatforms', 0);

        $this->assertDatabaseHas('games', ['title' => 'Hollow Knight', 'platform_id' => $platform->id]);
        $this->assertSame(1, Platform::where('name', 'Nintendo Switch')->count());
    }

    public function test_import_skips_rows_without_a_title_and_reports_them_as_errors(): void
    {
        $user = User::factory()->create();

        $csv = "Título,Plataforma\r\nHollow Knight,\r\n,Nintendo Switch\r\n";

        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        $status = $this->importStatus($response);
        $status->assertJsonPath('imported', 1);
        $this->assertCount(1, $status->json('errors'));
        $this->assertDatabaseCount('games', 1);
    }

    public function test_import_supports_semicolon_delimited_files(): void
    {
        $user = User::factory()->create();

        $csv = "Título;Plataforma;Precio pagado\r\nCeleste;Nintendo Switch;19,99\r\n";

        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        $this->importStatus($response)->assertJsonPath('imported', 1);
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

    public function test_import_deletes_the_stored_csv_after_processing(): void
    {
        $user = User::factory()->create();
        $csv = "Título\r\nCeleste\r\n";

        $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        // Solo hacía falta mientras Jobs\ImportGamesFromCsv la procesaba (ver
        // su comentario): no debe quedar huérfana en el disco.
        Storage::disk('local')->assertDirectoryEmpty('imports');
    }

    public function test_import_status_reports_in_progress_before_the_job_runs(): void
    {
        // A diferencia del resto de tests (QUEUE_CONNECTION=sync deja el job
        // ya terminado para cuando llega la respuesta), esto comprueba el
        // estado que deja store() antes de despachar el job — el que vería
        // un sondeo real contra un worker de cola de verdad.
        $user = User::factory()->create();
        $importId = (string) Str::uuid();
        Cache::put(
            GameImportController::cacheKey($importId),
            ['user_id' => $user->id, 'done' => false],
            now()->addHour(),
        );

        $this->actingAs($user)->getJson("/games/import/status/{$importId}")
            ->assertOk()
            ->assertJsonPath('done', false);
    }

    /**
     * Complementario a #119: el TTL más largo por sí solo no evita que el
     * sondeo del navegador (ver initImportStatusPolling en app.js) siga
     * indefinidamente si el job muere sin escribir nunca el resultado final
     * — el aviso de "esto está tardando más de lo normal" (pasados los
     * SLOW_IMPORT_WARNING_MS del JS) es lo que informa al usuario en ese
     * caso, en vez de dejarlo sondeando en silencio. Aquí solo se comprueba
     * que el marcado existe y empieza oculto; la lógica de cuándo mostrarlo
     * vive en JS, sin cobertura de tests (el proyecto no tiene runner JS).
     */
    public function test_import_page_includes_a_hidden_slow_import_warning(): void
    {
        $user = User::factory()->create();
        $csv = "Título\r\nCeleste\r\n";

        $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        $response = $this->get('/games/import');

        $response->assertOk();
        $response->assertSee('id="import-status-slow-warning" class="hidden', false);
    }

    /**
     * Regresión (#119): con el TTL de una hora de antes, una importación que
     * tardara más en pasar por la cola (servidor cargado, colección real de
     * 1000+ juegos) perdía su entrada de caché mientras seguía en curso de
     * verdad, y el sondeo veía un 404 indistinguible de un fallo real. El
     * TTL ahora es de un día (ver GameImportController::cacheTtl()).
     */
    public function test_import_status_still_reports_the_result_more_than_an_hour_after_finishing(): void
    {
        $user = User::factory()->create();
        $csv = "Título\r\nCeleste\r\n";

        // QUEUE_CONNECTION=sync (phpunit.xml) deja el job ya terminado —y su
        // resultado en caché con el nuevo TTL— para cuando llega aquí.
        $response = $this->actingAs($user)->post('/games/import', ['file' => $this->csvFile($csv)]);

        Carbon::setTestNow(now()->addHours(2));

        try {
            $this->importStatus($response)
                ->assertOk()
                ->assertJsonPath('done', true)
                ->assertJsonPath('imported', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_import_status_returns_404_for_an_unknown_import_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/games/import/status/does-not-exist')
            ->assertNotFound();
    }

    public function test_import_status_is_not_visible_to_another_user(): void
    {
        $owner = User::factory()->create();
        $csv = "Título\r\nCeleste\r\n";

        $response = $this->actingAs($owner)->post('/games/import', ['file' => $this->csvFile($csv)]);
        $importId = $response->getSession()->get('importId');

        $this->actingAs(User::factory()->create())
            ->getJson("/games/import/status/{$importId}")
            ->assertNotFound();
    }

    public function test_import_status_requires_authentication(): void
    {
        $this->getJson('/games/import/status/some-id')->assertUnauthorized();
    }

    public function test_preview_reports_matched_and_unmatched_columns_with_sample_rows(): void
    {
        $user = User::factory()->create();

        $csv = "Título,Plataforma\r\nCeleste,Nintendo Switch\r\nHollow Knight,Nintendo Switch\r\n";

        $response = $this->actingAs($user)->postJson('/games/import/preview', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('matchedColumns', ['Título', 'Plataforma']);
        $this->assertContains('EAN', $response->json('unmatchedColumns'));
        $this->assertCount(2, $response->json('rows'));
        $response->assertJsonPath('rows.0.titulo', 'Celeste');
        $response->assertJsonPath('rows.0.plataforma', 'Nintendo Switch');
    }

    public function test_preview_does_not_import_anything(): void
    {
        $user = User::factory()->create();

        $csv = "Título,Plataforma\r\nCeleste,Nintendo Switch\r\n";

        $this->actingAs($user)->postJson('/games/import/preview', ['file' => $this->csvFile($csv)])
            ->assertOk();

        $this->assertDatabaseCount('games', 0);
        $this->assertDatabaseCount('platforms', 0);
    }

    public function test_preview_requires_a_title_column(): void
    {
        $user = User::factory()->create();

        $csv = "Plataforma\r\nNintendo Switch\r\n";

        $this->actingAs($user)->postJson('/games/import/preview', ['file' => $this->csvFile($csv)])
            ->assertStatus(422);
    }
}
