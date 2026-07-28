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
- Alta de un juego mediante un único formulario directo (título, plataforma, estado de juego, valoración) — sin pasos intermedios de búsqueda previa, ya que no hay scraping de fuentes externas.
- Listado de la colección (página principal) con título, plataforma, estado (pendiente/jugando/terminado) y valoración, paginado.
- Búsqueda dentro de la propia colección por **título** o **EAN**, integrada en la página principal (los filtros adicionales quedan para más adelante).
- Edición de un juego existente.
- Baja de un juego mediante **papelera de reciclaje** (soft delete, no se pierde el registro).
- Modelo de datos preparado para bastante más detalle del que hoy se edita desde el formulario: EAN, carátula, desarrollador, géneros, condición física, edición, notas, precio y lugar de compra, fecha de compra, estado del manual, región y clasificación por edad.

### Catálogo (fabricantes y plataformas)
- Panel de gestión (`/manufacturers`, `/platforms`) para dar de alta, editar y borrar fabricantes y plataformas propias, en vez de depender de un catálogo precargado fijo.
- Cada **fabricante** define un color de marca para el chip (fondo, letras y borde) que heredan todas sus plataformas.
- Cada **plataforma** puede personalizar sus propios colores en lugar de heredar los del fabricante, y tiene una **etiqueta abreviada** editable para el chip (p. ej. "PS5"); si no se define, se usa el nombre completo.
- El chip de plataforma (colores + etiqueta) se muestra en el listado de la colección mediante un componente Blade reutilizable (`<x-platform-chip>`).
- Relación fabricante → plataforma → edición → juego modelada con Eloquent.

### Autenticación
- **Web:** login/logout con sesión (regenera el ID de sesión al iniciar sesión para evitar session fixation; redirige a la página original tras el login).
- **API:** login/logout con emisión y revocación de token Sanctum, pensado para un cliente externo (app móvil).

### API REST
- CRUD de juegos (`GET/POST/PUT/DELETE /api/games`) protegido con `auth:sanctum`.
- Respuestas transformadas con `GameResource` (aplana la plataforma a su nombre, expone URL de carátula, etc.).
- Validación de entrada separada en `StoreGameRequest` / `UpdateGameRequest`.

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar y borrar (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Botón de cerrar sesión ("Salir") en la navegación.

## Pendiente / en curso

- El formulario de alta y de edición solo cubren título, plataforma, estado y valoración; el resto de campos del modelo (EAN, condición, precio, notas, etc.) todavía no tienen UI.
- La búsqueda de la colección todavía no tiene filtros (por plataforma, estado, etc.) más allá del texto libre por título/EAN.
