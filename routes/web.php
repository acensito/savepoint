<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CommissionController;
use App\Http\Controllers\Web\EditionController;
use App\Http\Controllers\Web\ForSaleController;
use App\Http\Controllers\Web\GameBulkActionController;
use App\Http\Controllers\Web\GameController;
use App\Http\Controllers\Web\GameCoverLookupController;
use App\Http\Controllers\Web\GameExportController;
use App\Http\Controllers\Web\GameImportController;
use App\Http\Controllers\Web\GameTrashController;
use App\Http\Controllers\Web\IgdbController;
use App\Http\Controllers\Web\ManufacturerController;
use App\Http\Controllers\Web\PanelController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\SalesController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\StatsController;
use App\Http\Controllers\Web\TwoFactorController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas (solo invitados)
|--------------------------------------------------------------------------
| El middleware 'guest' evita que alguien ya logueado vuelva al formulario
| de acceso. La ruta DEBE llamarse 'login': es a donde Laravel redirige
| automáticamente cuando el middleware 'auth' bloquea una petición.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('web.login.attempt');

    // Registro público de nuevos usuarios
    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:registration')
        ->name('web.register.attempt');

    // Desafío de 2FA por email: al que login()/register() redirigen cuando
    // la cuenta lo tiene activo. Va en el grupo 'guest' porque, llegados
    // aquí, el usuario todavía NO tiene sesión iniciada de verdad (ver
    // AuthController::login()/RegisterController::register()).
    Route::get('/login/verify', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/login/verify', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:two-factor-verify')
        ->name('two-factor.verify');
    Route::post('/login/verify/resend', [TwoFactorController::class, 'resend'])
        ->middleware('throttle:two-factor-resend')
        ->name('two-factor.resend');

    // Recuperación de contraseña: pedir enlace por email y consumirlo con un token de un solo uso.
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren sesión iniciada)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('web.logout');

    // Colección (con búsqueda opcional por título/EAN vía ?q=)
    Route::get('/', [GameController::class, 'index'])->name('web.games.index');

    // Estadísticas de la colección
    Route::get('/stats', [StatsController::class, 'index'])->name('web.stats.index');

    // Panel de control: punto de entrada único a importar/exportar la
    // colección, la papelera de reciclaje y el perfil del usuario (antes
    // sueltos como iconos propios del sidebar).
    Route::get('/panel', [PanelController::class, 'index'])->name('web.panel.index');
    Route::get('/panel/settings', [PanelController::class, 'settings'])->name('web.panel.settings');
    Route::put('/panel/settings', [PanelController::class, 'updateSettings'])->name('web.panel.settings.update');
    // AJAX fire-and-forget desde el icono de tema y los botones de vista de la
    // colección (ver initThemeToggle/initGamesViewToggle en app.js): no son
    // parte del formulario de Ajustes, tienen su propio control ya existente.
    Route::patch('/panel/settings/display', [PanelController::class, 'updateDisplay'])->name('web.panel.settings.display');
    // Mismo patrón que la de arriba: fire-and-forget desde cada toggle switch
    // de Ajustes (ver x-toggle e initSettingsToggles en app.js), efecto y
    // persistencia inmediatos sin pasar por "Guardar ajustes".
    Route::patch('/panel/settings/toggles', [PanelController::class, 'updateToggle'])->name('web.panel.settings.toggles');

    // Gestión de usuarios de la plataforma (solo admin, ver UserPolicy):
    // crear/editar/borrar cuentas a mano, sin tirar de tinker, y decidir si
    // el registro público (/register) está abierto o no (registration,
    // más abajo). Ruta estática /panel/users/create antes de
    // /panel/users/{user} por el mismo motivo que /games/create.
    Route::get('/panel/users/create', [UserController::class, 'create'])->name('web.panel.users.create');
    Route::post('/panel/users', [UserController::class, 'store'])->name('web.panel.users.store');
    Route::get('/panel/users', [UserController::class, 'index'])->name('web.panel.users.index');
    Route::get('/panel/users/{user}/edit', [UserController::class, 'edit'])->name('web.panel.users.edit');
    Route::put('/panel/users/{user}', [UserController::class, 'update'])->name('web.panel.users.update');
    Route::delete('/panel/users/{user}', [UserController::class, 'destroy'])->name('web.panel.users.destroy');
    Route::patch('/panel/registration', [UserController::class, 'updateRegistration'])->name('web.panel.registration.update');

    // Lista de deseos: juegos con Propiedad = "wishlist", en su propia página
    // (nunca en la colección principal, ver GameController::index). Ruta
    // estática /wishlist/create antes de la resource-like /wishlist por el
    // mismo motivo que las de /games más abajo.
    Route::get('/wishlist/create', [WishlistController::class, 'create'])->name('web.wishlist.create');
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('web.wishlist.store');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('web.wishlist.index');

    // Encargos: juegos que un amigo compra/envía al usuario o viceversa,
    // fuera de la colección hasta que se marcan recibidos (ver
    // CommissionController::resolve()). Ruta estática /commissions/create
    // antes de la resource-like /commissions/{commission} por el mismo
    // motivo que el resto de rutas de este fichero.
    Route::get('/commissions/create', [CommissionController::class, 'create'])->name('web.commissions.create');
    Route::post('/commissions', [CommissionController::class, 'store'])->name('web.commissions.store');
    Route::get('/commissions', [CommissionController::class, 'index'])->name('web.commissions.index');
    Route::get('/commissions/{commission}/edit', [CommissionController::class, 'edit'])->name('web.commissions.edit');
    Route::put('/commissions/{commission}', [CommissionController::class, 'update'])->name('web.commissions.update');
    Route::post('/commissions/{commission}/resolve', [CommissionController::class, 'resolve'])->name('web.commissions.resolve');
    Route::delete('/commissions/{commission}', [CommissionController::class, 'destroy'])->name('web.commissions.destroy');

    // En venta: juegos con for_sale=true (GameController::quickUpdate), en su
    // propia página para su mantenimiento (ver ForSaleController). No son un
    // "estado" de Propiedad aparte como wishlist/vendido: siguen en la
    // colección, solo se les da una vista dedicada.
    Route::get('/for-sale', [ForSaleController::class, 'index'])->name('web.for-sale.index');

    // Ventas: histórico por año de los juegos marcados como vendidos (ver
    // SalesController::markAsSold), fuera de la colección principal.
    Route::get('/sales', [SalesController::class, 'index'])->name('web.sales.index');
    Route::post('/sales/{id}/restore', [SalesController::class, 'restore'])->name('web.sales.restore');

    // Búsqueda rápida global (Ctrl+K): unos pocos resultados en vivo por título/EAN.
    // throttle:external-search-cex: consulta CexGameLookupService, que usa
    // una clave Algolia de instancia (no por cuenta) — sin límite, un solo
    // usuario podría agotarla o hacer que CEX la bloquee, afectando a todos.
    Route::get('/search/quick', [SearchController::class, 'quick'])
        ->middleware('throttle:external-search-cex')
        ->name('web.search.quick');

    // OJO con el orden: las rutas estáticas van ANTES que las que llevan
    // un parámetro {game}, o '/games/create' acabaría entrando por
    // '/games/{game}' y buscando un juego con id "create".
    Route::get('/games/create', [GameController::class, 'create'])->name('web.games.create');

    // Importación masiva desde CSV (volcado inicial de la colección).
    Route::get('/games/import', [GameImportController::class, 'create'])->name('web.games.import');
    Route::post('/games/import', [GameImportController::class, 'store'])->name('web.games.import.store');
    Route::post('/games/import/preview', [GameImportController::class, 'preview'])->name('web.games.import.preview');
    Route::get('/games/import/template', [GameImportController::class, 'template'])->name('web.games.import.template');
    Route::get('/games/import/status/{importId}', [GameImportController::class, 'importStatus'])->name('web.games.import.status');

    // Papelera, exportación imprimible/PDF y CSV, acciones en bloque y
    // búsqueda de carátula en CEX: controladores propios, separados de
    // GameController (ver README, "Mejoras técnicas") — mismo criterio que
    // ya separó IgdbController: acciones propias de un concepto, no CRUD
    // del juego en sí.
    //
    // Exportación imprimible/PDF y a CSV del listado filtrado (antes de
    // '/games/{game}' por la misma razón que '/games/create': si no,
    // '/games/print' o '/games/export' entrarían por ahí y buscarían un
    // juego con id "print"/"export").
    Route::get('/games/print', [GameExportController::class, 'print'])->name('web.games.print');
    Route::get('/games/export', [GameExportController::class, 'export'])->name('web.games.export');

    // Buscar carátula/EAN en CEX durante el alta: el juego todavía no existe,
    // así que no hay {game} al que atar esta ruta. Misma razón de orden que
    // las de arriba: antes de '/games/{game}' (show), si no "cover-lookup"
    // entraría por ahí y buscaría un juego con ese id.
    // Mismo throttle que /search/quick (misma clave CEX de instancia).
    Route::get('/games/cover-lookup', [GameCoverLookupController::class, 'coverLookupForNew'])
        ->middleware('throttle:external-search-cex')
        ->name('web.games.cover-lookup.new');

    Route::get('/games/trash', [GameTrashController::class, 'index'])->name('web.games.trash');
    Route::post('/games/{id}/restore', [GameTrashController::class, 'restore'])->name('web.games.restore');
    Route::delete('/games/{id}/force-delete', [GameTrashController::class, 'forceDelete'])->name('web.games.force-delete');

    // Acciones en bloque sobre varios juegos a la vez desde el listado.
    Route::post('/games/bulk-delete', [GameBulkActionController::class, 'destroy'])->name('web.games.bulk-delete');
    Route::post('/games/bulk-play-status', [GameBulkActionController::class, 'updatePlayStatus'])->name('web.games.bulk-play-status');

    Route::post('/games', [GameController::class, 'store'])->name('web.games.store');
    Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('web.games.edit');
    Route::patch('/games/{game}/quick-update', [GameController::class, 'quickUpdate'])->name('web.games.quick-update');
    Route::get('/games/{game}/cover-lookup', [GameCoverLookupController::class, 'coverLookup'])
        ->middleware('throttle:external-search-cex')
        ->name('web.games.cover-lookup');

    // Enriquecimiento con IGDB (desarrollador, fecha de lanzamiento, géneros
    // en inglés, nota agregada) desde la ficha de detalle: igdb-search solo
    // lista candidatos (AJAX), igdb-apply guarda el elegido y redirige de
    // vuelta a la ficha. Controlador propio (IgdbController), separado de
    // GameController (ver README, "Mejoras técnicas").
    // throttle:external-search-igdb: credenciales por cuenta (ver
    // AppServiceProvider::register()), así que el impacto de abusar de esto
    // se queda en la propia cuota Twitch del atacante — límite más laxo que
    // el de CEX arriba.
    Route::get('/games/{game}/igdb-search', [IgdbController::class, 'search'])
        ->middleware('throttle:external-search-igdb')
        ->name('web.games.igdb-search');
    Route::post('/games/{game}/igdb-apply', [IgdbController::class, 'apply'])->name('web.games.igdb-apply');
    Route::get('/games/{game}/igdb-artworks', [IgdbController::class, 'artworks'])
        ->middleware('throttle:external-search-igdb')
        ->name('web.games.igdb-artworks');
    Route::post('/games/{game}/igdb-background', [IgdbController::class, 'setBackground'])->name('web.games.igdb-background');
    Route::put('/games/{game}', [GameController::class, 'update'])->name('web.games.update');
    Route::post('/games/{game}/mark-sold', [SalesController::class, 'markAsSold'])->name('web.games.mark-sold');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('web.games.destroy');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('web.games.show');

    // Panel de catálogo: fabricantes, plataformas y ediciones (normal/especial/coleccionista/...)
    Route::resource('manufacturers', ManufacturerController::class)->except('show')->names('web.manufacturers');
    Route::resource('platforms', PlatformController::class)->except('show')->names('web.platforms');
    Route::resource('editions', EditionController::class)->except('show')->names('web.editions');

    // Perfil del usuario: datos de la cuenta y cambio de contraseña
    Route::get('/profile', [ProfileController::class, 'edit'])->name('web.profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateInfo'])->name('web.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('web.profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('web.profile.destroy');
});
