<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index()
    {
        $manufacturers = Manufacturer::paginate(50);

        return response()->json($manufacturers, 200);
    }

    public function show(Manufacturer $manufacturer)
    {
        return response()->json($manufacturer, 200);
    }

    public function store(Request $request)
    {
        // Solo para admins
        abort(403);
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        // Solo para admins
        abort(403);
    }

    public function destroy(Request $request, Manufacturer $manufacturer)
    {
        // Solo para admins
        abort(403);
    }
}