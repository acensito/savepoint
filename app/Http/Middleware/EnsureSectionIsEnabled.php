<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea con 404 el acceso directo por URL a una sección opcional que el
 * usuario haya desactivado desde /panel/settings (ver PanelController::TOGGLE_FIELDS
 * y el sidebar de layouts/app.blade.php, issue #32): igual que si la ruta no
 * existiera, sin distinguir "desactivada" de "no encontrada" de cara al
 * usuario. Los datos de la sección nunca se tocan, solo el acceso.
 */
class EnsureSectionIsEnabled
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        abort_unless($request->user()->{"section_{$section}_enabled"}, 404);

        return $next($request);
    }
}
