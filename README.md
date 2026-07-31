# Savepoint

Savepoint es una aplicación para catalogar y gestionar una colección personal de videojuegos: qué juegos tienes, en qué plataforma, su estado de conservación, si los has terminado o no, valoración, precio de compra, etc.

El proyecto está construido como backend Laravel que sirve tanto una interfaz web (Blade) como una API REST (Sanctum) pensada para un futuro cliente externo (p. ej. app móvil).

## Stack técnico

- **Backend:** Laravel 13 / PHP 8.3
- **Base de datos:** PostgreSQL (usa `JSONB` para campos como `genres`)
- **Autenticación web:** sesiones con guard `web` (login por email/contraseña)
- **Autenticación API:** Laravel Sanctum (tokens Bearer)
- **Frontend web:** Blade + Tailwind CSS + Vite, iconos con `blade-heroicons`

## Requisitos funcionales realizados

### Gestión de la colección de juegos
- Alta de un juego mediante un único formulario directo — sin pasos intermedios de búsqueda previa, ya que no hay scraping de fuentes externas. Cubre prácticamente todo el modelo: título, EAN, desarrollador, plataforma, fecha de lanzamiento, géneros, propiedad, estado de juego, condición física, valoración, precio y lugar/fecha de compra, manual, región, clasificación por edad y notas.
- **Carátula**: se sube desde el propio formulario (JPG/PNG/WEBP, máx. 1MB) con vista previa en vivo. Si el juego no tiene carátula, se muestra un recuadro con las iniciales del título en su lugar (tanto en el listado como en el formulario), generado con `Game::coverInitials()`.
- Listado de la colección (página principal) con miniatura, título, plataforma, edición, estado, región, manual, valoración (estrellas), precio y fecha de compra, paginado.
- Búsqueda dentro de la propia colección por **título** o **EAN**, más filtros por **plataforma**, **estado de juego** y **propiedad**, todo combinable desde la página principal.
- La consulta del listado solo trae las columnas y relaciones que la vista pinta (evita cargar `notes`/`data`/`genres` innecesariamente y N+1 en `platform`/`edition`).
- Edición de un juego existente, incluida la opción de reemplazar o quitar la carátula.
- Baja de un juego mediante **papelera de reciclaje** (soft delete, no se pierde el registro).
- Al editar/reemplazar la carátula, el fichero anterior se borra del disco (`storage/app/public/covers`) para no dejar huérfanos.
- Panel de gestión de ediciones (`/editions`) para dar de alta ediciones (normal/especial/coleccionista/...) asociadas a una o varias plataformas; el campo `edition_id` del juego se filtra según la plataforma elegida en el formulario.

### Catálogo (fabricantes y plataformas)
- Panel de gestión (`/manufacturers`, `/platforms`) para dar de alta, editar y borrar fabricantes y plataformas propias, en vez de depender de un catálogo precargado fijo.
- Cada **fabricante** define un color de marca para el chip (fondo, letras y borde) que heredan todas sus plataformas.
- Cada **plataforma** puede personalizar sus propios colores en lugar de heredar los del fabricante, y tiene una **etiqueta abreviada** editable para el chip (p. ej. "PS5"); si no se define, se usa el nombre completo.
- El chip de plataforma (colores + etiqueta) se muestra en el listado de la colección mediante un componente Blade reutilizable (`<x-platform-chip>`).
- Relación fabricante → plataforma → edición → juego modelada con Eloquent.

### Autenticación
- **Web:** login/logout con sesión (regenera el ID de sesión al iniciar sesión para evitar session fixation; redirige a la página original tras el login).
- **API:** login/logout con emisión y revocación de token Sanctum, pensado para un cliente externo (app móvil).
- **Perfil** (`/profile`): el usuario puede actualizar su nombre/email y cambiar su contraseña (pide la contraseña actual para confirmarla).

### API REST
- CRUD de juegos (`GET/POST/PUT/DELETE /api/games`) protegido con `auth:sanctum`.
- Respuestas transformadas con `GameResource` (aplana la plataforma a su nombre, expone URL de carátula, etc.).
- Validación de entrada separada en `StoreGameRequest` / `UpdateGameRequest`.

### Estadísticas
- Panel (`/stats`) con total de juegos, gasto total y valoración media, reparto de juegos por plataforma (barra por plataforma), y reparto por estado de juego y por propiedad (barras apiladas con leyenda).

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar y borrar (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Botón de cerrar sesión ("Salir") en la navegación.

### Interfaz
- Sidebar plegable: un botón en su cabecera lo contrae a solo iconos (de 15rem a 4.5rem) para aprovechar el ancho en pantallas 1080p; cada enlace muestra su nombre como tooltip nativo mientras está colapsado. La preferencia se guarda en `localStorage` y se aplica antes del primer pintado (vía un script bloqueante en el `<head>`) para no parpadear al navegar entre páginas. Implementado en JS vanilla (sin Alpine ni otra dependencia).

## Desarrollo con Docker

El stack (`docker-compose.yml`) levanta `postgres`, `redis`, `app` (PHP-FPM), `queue` (worker de Redis) y `nginx`. La imagen de `app`/`queue` (`docker/Dockerfile`) incluye Composer y Node.js 22 + npm (copiados desde las imágenes oficiales `composer:latest` y `node:22-alpine`) para poder compilar los assets de Vite dentro del propio contenedor.

Primer arranque:

```bash
cp backend/.env.example backend/.env   # y ajustar DB_HOST/REDIS_HOST a postgres/redis, DB_* a los del compose
docker compose up -d --build

docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build       # o npm run dev si se expone el puerto 5173 para HMR

docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed  # usuarios de prueba: felipe@savepoint.test / test@example.com, contraseña "password"
docker compose exec app php artisan storage:link
```

La app queda disponible en `http://localhost:8081`.

### Tests

```bash
docker compose exec app php artisan test
```

El entorno de test es independiente del de desarrollo: `phpunit.xml` fuerza `APP_ENV=testing`, SQLite en memoria, sesión/caché en array, etc., así que correr los tests nunca toca la base Postgres real ni Redis (el `docker-compose.yml` deliberadamente no pasa `backend/.env` como `env_file` de `app`/`queue` para que esto funcione).

Cobertura actual:
- `Tests\Feature\Auth\WebAuthTest`: login/logout, credenciales inválidas, redirect a la página originalmente solicitada, protección de rutas para invitados.
- `Tests\Feature\Api\AuthTest`: login/logout vía Sanctum (emisión y revocación de token), `/api/user` protegido.
- `Tests\Feature\Api\GameControllerTest`: CRUD completo de la API, scoping por usuario y `GamePolicy` bloqueando acceso a juegos ajenos (403 en view/update/delete).
- `Tests\Feature\Web\GameControllerTest`: alta y edición de juegos con subida/reemplazo de carátula real, validación, y `GamePolicy` aplicada en las rutas web.
- `Tests\Unit\Models\GameTest` / `PlatformTest`: iniciales y URL de carátula, resolución de colores/etiqueta de chip con fallback a fabricante.

## Pendiente / en curso

- Ampliar tests a lo que queda sin cubrir: paneles de catálogo (fabricantes/plataformas/ediciones), perfil de usuario y estadísticas.
- Versión web responsive/mobile de toda la interfaz (pensada como acceso de emergencia cuando la app móvil no esté disponible): listado en tarjetas en pantallas estrechas (el sidebar plegable de escritorio ya está hecho, ver "Interfaz").
- Importar/exportar la colección: de momento interesa sobre todo la **importación** (volcado inicial de datos desde la hoja Excel actual). Falta decidir formato de entrada (¿CSV/Excel con las columnas ya vistas en el listado?) y cómo mapear plataformas/ediciones existentes vs. crearlas sobre la marcha.
