<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;

class PanelController extends Controller
{
    /**
     * Punto de entrada único para tareas que antes vivían sueltas por el
     * sidebar (importar, papelera): importar/exportar la colección, la
     * papelera de reciclaje y el perfil del usuario.
     */
    public function index()
    {
        $trashedCount = Game::onlyTrashed()->where('user_id', auth()->id())->count();

        return view('panel.index', compact('trashedCount'));
    }
}
