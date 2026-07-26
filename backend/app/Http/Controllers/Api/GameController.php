<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $games = $request->user()->games()
            ->with(['platform.manufacturer', 'edition'])
            ->paginate(20);

        return response()->json($games, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'platform_id' => 'required|exists:platforms,id',
            'ean' => 'nullable|string',
            'developer' => 'nullable|string',
            'release_date' => 'nullable|date',
            'genres' => 'nullable|array',
            'status' => 'nullable|in:owned,wishlist,preordered,borrowed',
            'play_status' => 'nullable|in:finished,playing,abandoned,backlog',
            'condition' => 'nullable|in:New,Excellent,Good,Fair,Poor',
            'edition_id' => 'nullable|exists:editions,id',
            'rating' => 'nullable|integer|min:0|max:10',
            'price_paid' => 'nullable|numeric|min:0',
            'purchase_place' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'manual_status' => 'nullable|in:included,missing,none,leaflet',
            'region' => 'nullable|in:PAL-ES,PAL-EU,PAL-UK,PAL-FR,PAL-DE,NTSC-U,NTSC-J,Other',
            'age_rating' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $game = $request->user()->games()->create($validated);

        return response()->json([
            'message' => 'Game created successfully',
            'game' => $game->load(['platform.manufacturer', 'edition']),
        ], 201);
    }

    public function show(Game $game, Request $request)
    {
        if ($game->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($game->load(['platform.manufacturer', 'edition']), 200);
    }

    public function update(Request $request, Game $game)
    {
        if ($game->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string',
            'platform_id' => 'nullable|exists:platforms,id',
            'ean' => 'nullable|string',
            'developer' => 'nullable|string',
            'release_date' => 'nullable|date',
            'genres' => 'nullable|array',
            'status' => 'nullable|in:owned,wishlist,preordered,borrowed',
            'play_status' => 'nullable|in:finished,playing,abandoned,backlog',
            'condition' => 'nullable|in:New,Excellent,Good,Fair,Poor',
            'edition_id' => 'nullable|exists:editions,id',
            'rating' => 'nullable|integer|min:0|max:10',
            'price_paid' => 'nullable|numeric|min:0',
            'purchase_place' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'manual_status' => 'nullable|in:included,missing,none,leaflet',
            'region' => 'nullable|in:PAL-ES,PAL-EU,PAL-UK,PAL-FR,PAL-DE,NTSC-U,NTSC-J,Other',
            'age_rating' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $game->update($validated);

        return response()->json([
            'message' => 'Game updated successfully',
            'game' => $game->load(['platform.manufacturer', 'edition']),
        ], 200);
    }

    public function destroy(Request $request, Game $game)
    {
        if ($game->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $game->delete();

        return response()->json(['message' => 'Game deleted successfully'], 200);
    }
}