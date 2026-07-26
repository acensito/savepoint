<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function index()
    {
        $platforms = Platform::with('manufacturer')
            ->paginate(50);

        return response()->json($platforms, 200);
    }

    public function show(Platform $platform)
    {
        return response()->json($platform->load('manufacturer'), 200);
    }

    public function store(Request $request)
    {
        // Solo para admins (implementar después)
        abort(403);
    }

    public function update(Request $request, Platform $platform)
    {
        // Solo para admins
        abort(403);
    }

    public function destroy(Request $request, Platform $platform)
    {
        // Solo para admins
        abort(403);
    }
}