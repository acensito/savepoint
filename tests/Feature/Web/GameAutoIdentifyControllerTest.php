<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\GameAutoIdentifyController;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GameAutoIdentifyControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * QUEUE_CONNECTION=sync (phpunit.xml) deja Jobs\IdentifyMissingGameCovers
     * ya terminado para cuando llega aquí, igual que ImportGamesFromCsv en
     * GameImportControllerTest.
     */
    private function batchStatus(TestResponse $storeResponse): TestResponse
    {
        $batchId = $storeResponse->getSession()->get('batchId');

        return $this->getJson(route('web.games.auto-identify.status', $batchId));
    }

    public function test_guest_cannot_access_the_form(): void
    {
        $this->get('/games/auto-identify')->assertRedirect('/login');
    }

    public function test_form_only_lists_platforms_with_games_missing_a_cover(): void
    {
        $user = User::factory()->create();
        $withGap = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $complete = Platform::factory()->create(['name' => 'PlayStation 4']);

        Game::factory()->for($user)->create(['platform_id' => $withGap->id, 'cover' => null]);
        Game::factory()->for($user)->create(['platform_id' => $complete->id, 'cover' => 'covers/x.jpg']);

        $response = $this->actingAs($user)->get('/games/auto-identify');

        $response->assertOk();
        // No con assertDontSee('PlayStation 4'): el layout inyecta TODAS las
        // plataformas en el diálogo del buscador rápido (Ctrl+K, ver
        // AppServiceProvider::boot()) en cada página autenticada, así que el
        // nombre aparecería igual en esa lista aunque el <select> de aquí la
        // excluya bien — hay que mirar los datos de la vista, no el HTML.
        $response->assertViewHas('platforms', function ($platforms) use ($withGap, $complete) {
            $ids = $platforms->pluck('platform.id');

            return $ids->contains($withGap->id) && ! $ids->contains($complete->id);
        });
    }

    public function test_store_finds_a_candidate_by_ean_when_the_game_already_has_one(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Hollow Knight',
                    'boxId' => '5060146467315',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/hk_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($user)->create([
            'platform_id' => $platform->id,
            'title' => 'Hollow Knight',
            'ean' => '5060146467315',
            'cover' => null,
        ]);

        $response = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);

        $status = $this->batchStatus($response);
        $status->assertJsonPath('done', true);
        $status->assertJsonPath('candidates.0.game_id', $game->id);
        $status->assertJsonPath('candidates.0.proposed_cover_url', 'https://es.static.webuy.com/hk_l.jpg');
        $status->assertJsonPath('candidates.0.current_ean', '5060146467315');
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=5060146467315'));
    }

    public function test_store_finds_a_candidate_by_title_when_the_game_has_no_ean(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Celeste',
                    'boxId' => '0812872018012',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/celeste_l.jpg'],
                    'categoryFriendlyName' => 'Switch Juegos',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Nintendo Switch']);
        $game = Game::factory()->for($user)->create([
            'platform_id' => $platform->id,
            'title' => 'Celeste',
            'ean' => null,
            'cover' => null,
        ]);

        $response = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);

        $status = $this->batchStatus($response);
        $status->assertJsonPath('candidates.0.game_id', $game->id);
        $status->assertJsonPath('candidates.0.proposed_ean', '0812872018012');
        $status->assertJsonPath('candidates.0.proposed_cover_url', 'https://es.static.webuy.com/celeste_l.jpg');
        Http::assertSent(fn ($request) => str_contains($request['params'], 'query=Celeste'));
    }

    public function test_store_leaves_an_ambiguous_title_match_without_any_candidate(): void
    {
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [
                    ['boxName' => 'Ambiguous Game Collection', 'boxId' => '1', 'imageUrls' => ['large' => 'https://es.static.webuy.com/a.jpg']],
                    ['boxName' => 'Ambiguous Game Anthology', 'boxId' => '2', 'imageUrls' => ['large' => 'https://es.static.webuy.com/b.jpg']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        Game::factory()->for($user)->create(['platform_id' => $platform->id, 'title' => 'Ambiguous Game', 'ean' => null, 'cover' => null]);

        $response = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);

        $status = $this->batchStatus($response);
        $status->assertJsonPath('total', 1);
        $this->assertCount(0, $status->json('candidates'));
    }

    public function test_store_only_processes_games_of_the_chosen_platform_that_are_missing_a_cover(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $otherPlatform = Platform::factory()->create();

        Game::factory()->for($user)->create(['platform_id' => $platform->id, 'cover' => 'covers/already.jpg']);
        Game::factory()->for($user)->create(['platform_id' => $otherPlatform->id, 'cover' => null]);
        Game::factory()->for($user)->create(['platform_id' => $platform->id, 'cover' => null]);

        $response = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);

        $this->batchStatus($response)->assertJsonPath('total', 1);
    }

    public function test_confirm_downloads_the_cover_and_fills_the_missing_ean(): void
    {
        Storage::fake('public');
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Celeste',
                    'boxId' => '0812872018012',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/celeste_l.jpg'],
                ]],
            ], 200),
            'es.static.webuy.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($user)->create([
            'platform_id' => $platform->id,
            'title' => 'Celeste',
            'ean' => null,
            'cover' => null,
        ]);

        $storeResponse = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);
        $batchId = $storeResponse->getSession()->get('batchId');

        $this->actingAs($user)
            ->post(route('web.games.auto-identify.confirm', $batchId), ['game_ids' => [$game->id]])
            ->assertRedirect(route('web.games.auto-identify'));

        $game->refresh();
        $this->assertSame('0812872018012', $game->ean);
        $this->assertNotNull($game->cover);
        Storage::disk('public')->assertExists($game->cover);
    }

    public function test_confirm_ignores_unselected_candidates(): void
    {
        Storage::fake('public');
        Http::fake([
            'search.webuy.io/*' => Http::response([
                'hits' => [[
                    'boxName' => 'Celeste',
                    'boxId' => '0812872018012',
                    'imageUrls' => ['large' => 'https://es.static.webuy.com/celeste_l.jpg'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($user)->create(['platform_id' => $platform->id, 'title' => 'Celeste', 'ean' => null, 'cover' => null]);

        $storeResponse = $this->actingAs($user)->post('/games/auto-identify', ['platform_id' => $platform->id]);
        $batchId = $storeResponse->getSession()->get('batchId');

        $this->actingAs($user)->post(route('web.games.auto-identify.confirm', $batchId), ['game_ids' => []]);

        $game->refresh();
        $this->assertNull($game->ean);
        $this->assertNull($game->cover);
    }

    public function test_confirm_does_not_apply_a_candidate_belonging_to_another_user(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $platform = Platform::factory()->create();
        $game = Game::factory()->for($owner)->create(['platform_id' => $platform->id, 'cover' => null]);

        $batchId = (string) Str::uuid();
        Cache::put(GameAutoIdentifyController::cacheKey($batchId), [
            'user_id' => $owner->id,
            'done' => true,
            'total' => 1,
            'candidates' => [[
                'game_id' => $game->id,
                'title' => $game->title,
                'current_ean' => null,
                'proposed_ean' => '123',
                'proposed_cover_url' => 'https://es.static.webuy.com/x.jpg',
                'matched_title' => $game->title,
                'matched_platform' => null,
            ]],
        ], now()->addDay());

        $attacker = User::factory()->create();
        $this->actingAs($attacker)
            ->post(route('web.games.auto-identify.confirm', $batchId), ['game_ids' => [$game->id]])
            ->assertNotFound();

        $game->refresh();
        $this->assertNull($game->cover);
    }

    public function test_status_is_not_visible_to_another_user(): void
    {
        Http::fake(['search.webuy.io/*' => Http::response(['hits' => []], 200)]);

        $owner = User::factory()->create();
        $platform = Platform::factory()->create();
        Game::factory()->for($owner)->create(['platform_id' => $platform->id, 'cover' => null]);

        $response = $this->actingAs($owner)->post('/games/auto-identify', ['platform_id' => $platform->id]);
        $batchId = $response->getSession()->get('batchId');

        $this->actingAs(User::factory()->create())
            ->getJson(route('web.games.auto-identify.status', $batchId))
            ->assertNotFound();
    }

    public function test_status_returns_404_for_an_unknown_batch_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/games/auto-identify/status/does-not-exist')
            ->assertNotFound();
    }

    public function test_store_requires_an_existing_platform(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/games/auto-identify', ['platform_id' => 999999])
            ->assertSessionHasErrors('platform_id');
    }
}
