<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\Request;

class EditionController extends Controller
{
    public function index()
    {
        $editions = Edition::with('platforms')
            ->paginate(50);

        return response()->json($editions, 200);
    }

    public function show(Edition $edition)
    {
        return response()->json($edition->load('platforms'), 200);
    }

    public function store(Request $request)
    {
        // Solo para admins
        abort(403);
    }

    public function update(Request $request, Edition $edition)
    {
        // Solo para admins
        abort(403);
    }

    public function destroy(Request $request, Edition $edition)
    {
        // Solo para admins
        abort(403);
    }
}