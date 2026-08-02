# Savepoint

Savepoint es una aplicación para catalogar y gestionar una colección personal de videojuegos: qué juegos tienes, en qué plataforma, su estado de conservación, si los has terminado o no, valoración, precio de compra, etc.

El proyecto está construido como backend Laravel que sirve tanto una interfaz web (Blade) como una API REST (Sanctum) pensada para un futuro cliente externo (p. ej. app móvil).

## Stack técnico

- **Backend:** Laravel 13 / PHP 8.3
- **Base de datos:** PostgreSQL (usa `JSONB` para campos como `genres`)
- **Autenticación web:** sesiones con guard `web` (login por email/contraseña)
- **Autenticación API:** Laravel Sanctum (tokens Bearer)
- **Frontend web:** Blade + Tailwind CSS + Vite, iconos con `blade-heroicons`
- **Localización:** interfaz y mensajes de validación en español (`APP_LOCALE=es`, `lang/es/`). Laravel 11+ no publica estos archivos por defecto; se generaron y tradujeron a mano para que los errores de formulario no muestren la clave sin traducir (p. ej. `validation.required`).

## Requisitos funcionales realizados

### Gestión de la colección de juegos
- Alta de un juego mediante un único formulario directo — sin pasos intermedios de búsqueda previa, ya que no hay scraping de fuentes externas. Cubre prácticamente todo el modelo: título, EAN, desarrollador, plataforma, fecha de lanzamiento, géneros, propiedad (en colección/lista de deseos/vendido, "en colección" por defecto), estado de juego, valoración, precio y lugar/fecha de compra, manual (con manual/sin manual/folleto), región, clasificación por edad y notas.
- **Carátula**: se sube desde el propio formulario (JPG/PNG/WEBP, máx. 1MB) con vista previa en vivo que respeta la proporción real de la imagen — ancho fijo y alto automático, así que una portada cuadrada (caja de PC/CD) sale cuadrada y una portrait (la mayoría de cajas de consola) crece en alto sin recortarse. Si el juego no tiene carátula, se muestra un recuadro con las iniciales del título en su lugar (tanto en el listado como en el formulario), generado con `Game::coverInitials()`.
- Listado de la colección (página principal) con miniatura, título, plataforma, edición, estado, región, manual, valoración (estrellas), precio y fecha de compra, paginado.
- Búsqueda dentro de la propia colección por **título** o **EAN**, más filtros por **plataforma**, **estado de juego** y **propiedad**, todo combinable desde la página principal.
- La consulta del listado solo trae las columnas y relaciones que la vista pinta (evita cargar `notes`/`data`/`genres` innecesariamente y N+1 en `platform`/`edition`).
- Edición de un juego existente, incluida la opción de reemplazar o quitar la carátula.
- Baja de un juego mediante **papelera de reciclaje** (soft delete): panel dedicado (`/games/trash`) para ver los juegos borrados, restaurarlos o eliminarlos definitivamente (esto último borra también el fichero de la carátula).
- **Importación masiva** (`/games/import`) desde un CSV (coma o punto y coma, con o sin BOM de Excel): solo el título es obligatorio, cada fila se procesa de forma independiente (una fila con error no bloquea al resto) y las plataformas/ediciones que el CSV mencione y no existan todavía en el catálogo se crean automáticamente. Hay una plantilla de ejemplo descargable desde la propia página. Tras importar se muestra un resumen (juegos importados, plataformas/ediciones creadas, filas con incidencias).
- Al editar/reemplazar la carátula, el fichero anterior se borra del disco (`storage/app/public/covers`) para no dejar huérfanos.
- Panel de gestión de ediciones (`/editions`) para dar de alta ediciones (normal/especial/coleccionista/...) asociadas a una o varias plataformas, con un botón "Seleccionar todas"/"Ninguna" para no marcarlas una a una; el campo `edition_id` del juego se filtra según la plataforma elegida en el formulario. Si la edición que necesitas no existe todavía, se puede crear al vuelo desde el propio formulario de alta/edición de juego (modal + AJAX) sin perder lo ya rellenado.

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
- **Recuperación de contraseña** (`/forgot-password`, `/reset-password/{token}`): flujo estándar de Laravel (token de un solo uso, expira a los 60 minutos, mismo mensaje de éxito exista o no el email para no revelar qué cuentas están registradas). El email se envía por el canal configurado en `MAIL_MAILER` (`log` por defecto en desarrollo, así que el enlace aparece en `storage/logs/laravel.log`).

### API REST
- CRUD de juegos (`GET/POST/PUT/DELETE /api/games`) protegido con `auth:sanctum`.
- El listado (`index`) pagina: 20 juegos por página por defecto, admite `?per_page=` con tope de 100. Admite los mismos filtros que el listado web: `?q=` (título o EAN), `?platform_id=`, `?play_status=` y `?status=`.
- Respuestas transformadas con `GameResource` (aplana la plataforma a su nombre, expone URL de carátula, etc.).
- Validación de entrada separada en `StoreGameRequest` / `UpdateGameRequest`.

### Estadísticas
- Panel (`/stats`) con total de juegos, gasto total y valoración media, reparto de juegos por plataforma (barra por plataforma), y reparto por estado de juego y por propiedad (barras apiladas con leyenda).

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar, borrar, restaurar y eliminar definitivamente (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Login (web y API) con protección contra fuerza bruta: bloqueo de 60 segundos tras 5 intentos fallidos, con clave email+IP (`ThrottlesLogins`), así que un atacante no puede bloquear a otros usuarios que compartan su misma IP.
- Botón de cerrar sesión ("Salir") en la navegación.

### Interfaz
- Sidebar plegable (escritorio): un botón en su cabecera lo contrae a solo iconos (de 15rem a 4.5rem) para aprovechar el ancho en pantallas 1080p; cada enlace muestra su nombre como tooltip nativo mientras está colapsado. La preferencia se guarda en `localStorage` y se aplica antes del primer pintado (vía un script bloqueante en el `<head>`) para no parpadear al navegar entre páginas. Implementado en JS vanilla (sin Alpine ni otra dependencia).
- Navegación móvil (< 768px): el sidebar deja de ocupar ancho fijo y pasa a ser un drawer superpuesto que entra desde la izquierda, con botón hamburguesa en el header y fondo oscuro; se cierra tocando fuera, el botón de cerrar o cualquier enlace del menú.
- Colección en móvil: el listado se muestra como tarjetas (carátula, plataforma, estado, valoración y precio, con acciones de editar/borrar como botones de icono) en vez de la tabla de escritorio. El buscador y los filtros quedan colapsados detrás de un botón "Buscar y filtrar" (con contador de filtros activos, y abierto automáticamente si ya venías con alguno aplicado) para no ocupar la pantalla principal; usa `<details>` nativo, sin JS adicional.
- Paneles de catálogo en móvil (fabricantes, plataformas, ediciones): igual que la colección, pasan de la tabla con scroll horizontal a tarjetas con la misma información y acciones de editar/borrar como botones de icono.
- Formularios (alta/edición de juego, perfil, fabricantes, plataformas) apilan sus campos en una sola columna en pantallas estrechas en vez de apretarlos en el mismo grid que escritorio.
- Feedback de acciones: los mensajes de éxito se muestran como un toast flotante que se desvanece solo (antes eran banners fijos, duplicados en cada vista, y en la colección principal directamente no se mostraban). Las acciones destructivas (borrar juego, plataforma, fabricante, edición, o eliminar definitivamente desde la papelera) piden confirmación en un `<dialog>` propio con el nombre del elemento afectado, en vez del `confirm()` del navegador. Ambos son un único componente compartido en el layout, reutilizado desde cualquier vista. El `<dialog>` se centra en pantalla vía `margin: auto` (se declara explícitamente en `app.css` porque el preflight de Tailwind resetea el margin por defecto de todos los elementos, y sin él el navegador no puede centrar un `<dialog>` abierto con `showModal()`).
- Tema claro/oscuro: botón en el header (y en las pantallas de login/recuperar contraseña) que alterna entre los dos, persistido en `localStorage` y aplicado antes del primer pintado (mismo mecanismo que el sidebar). Técnicamente no se tocó ninguna vista: cada plantilla sigue usando las mismas clases de Tailwind (`bg-slate-900`, `text-slate-400`...) de siempre, y lo que cambia con la clase `light` en `<html>` es a qué color apunta cada variable de la paleta (`app.css`), así que da igual cuántas vistas nuevas se añadan en el futuro, heredan el tema sin tocar nada.
- Orden del listado de la colección (`?sort=`, `?dir=`): por título, precio, valoración o fecha de compra, ascendente o descendente, combinable con la búsqueda y los filtros.
- Atajo de teclado `/` para enfocar el buscador de la colección, como en GitHub o Gmail.
- Acciones en bloque en la colección: casillas de selección (una por fila/tarjeta, más "seleccionar todo" en la tabla de escritorio) para enviar varios juegos a la papelera o cambiarles el estado de juego de golpe, sin repetir la acción uno a uno.
- Botón flotante ("Añadir juego") fijo en la esquina inferior derecha en móvil, para no tener que volver arriba al hacer scroll por una colección larga.
- Vista de estantería: alternativa en grid de carátulas grandes al listado habitual (tabla/tarjetas), con el mismo botón de alternancia persistido en `localStorage` que el tema.

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
- `Tests\Feature\Auth\WebAuthTest`: login/logout, credenciales inválidas, redirect a la página originalmente solicitada, protección de rutas para invitados, bloqueo por fuerza bruta.
- `Tests\Feature\Api\AuthTest`: login/logout vía Sanctum (emisión y revocación de token), `/api/user` protegido, bloqueo por fuerza bruta.
- `Tests\Feature\Api\GameControllerTest`: CRUD completo de la API, paginación (tamaño por defecto, `per_page` a medida y con tope), filtros (`q`, `platform_id`, `play_status`, `status`), scoping por usuario y `GamePolicy` bloqueando acceso a juegos ajenos (403 en view/update/delete).
- `Tests\Feature\Web\GameControllerTest`: alta y edición de juegos con subida/reemplazo de carátula real, validación, `GamePolicy` aplicada en las rutas web, y la papelera (listar/restaurar/eliminar definitivamente, con scoping por usuario).
- `Tests\Feature\Web\GameImportControllerTest`: importación desde CSV (con/sin BOM, separador coma o punto y coma), creación automática de plataformas/ediciones que no existían, filas sin título omitidas y reportadas como incidencia, validación del fichero subido.
- `Tests\Feature\Web\ManufacturerControllerTest` / `PlatformControllerTest` / `EditionControllerTest`: CRUD de cada panel de catálogo, validaciones propias (colores en formato hex, nombre único de fabricante, colores obligatorios solo si se sobrescriben en una plataforma) y que borrar un registro deja en `null` la relación en juegos/plataformas en vez de arrastrar el borrado.
- `Tests\Feature\Web\ProfileControllerTest`: actualización de nombre/email (con email único), cambio de contraseña exigiendo la actual y confirmación.
- `Tests\Feature\Web\StatsControllerTest`: los totales y repartos (por plataforma, estado de juego y propiedad) solo consideran los juegos del usuario autenticado.
- `Tests\Feature\Web\PasswordResetTest`: envío del enlace de reset (mismo mensaje exista o no el email), reset con token válido/inválido.
- `Tests\Unit\Models\GameTest` / `PlatformTest`: iniciales y URL de carátula, resolución de colores/etiqueta de chip con fallback a fabricante.

## Pendiente / en curso

- Exportación de la colección (la importación desde CSV ya está implementada, ver "Importación masiva").
- Sin backups automatizados de Postgres (ni `pg_dump` programado ni snapshot del volumen).

### Ideas de interfaz/funcionalidad sin priorizar

Rápidas:
- Aviso de EAN duplicado al dar de alta un juego. Ojo: hay juegos antiguos sin EAN, así que la comprobación solo puede saltar cuando el EAN introducido no está vacío y ya existe en la colección del usuario — dos juegos sin EAN nunca deben marcarse como duplicados entre sí.
- Buscador y filtro por plataforma en la papelera (`/games/trash`), igual que ya tiene el listado principal.
- Paginación configurable en el listado web (la API ya admite `?per_page=`, la web no).
- Botón "Deshacer" en el toast al enviar un juego a la papelera, sin tener que ir a `/games/trash`.
- Vista compacta de la tabla de la colección (filas más bajas/densas), como alternativa a la actual junto a tarjetas y estantería.
- Mejorar la estética de las tarjetas de la colección en móvil (el diseño actual es funcional pero mejorable).
- Las casillas de selección de la colección (acciones en bloque) no deberían estar siempre visibles: que aparezcan solo al entrar en un "modo selección" (p. ej. con un botón "Seleccionar"), no todo el rato ocupando espacio en cada fila/tarjeta.
- Barra de estado discreta en la parte inferior de la colección, con el nº de juegos y algún dato más que pueda interesar (gasto total, algo así), sin llamar la atención.

Medias:
- Ficha de detalle de solo lectura por juego (`/games/{id}`), en vez de tener que abrir el formulario de edición para "solo mirar".
- Edición rápida (valoración/estado de juego) desde la propia fila del listado, por AJAX.
- Vista previa del CSV antes de importar: primeras filas y a qué columna se ha mapeado cada una, antes de confirmar.
- Rediseño del buscador/filtro de la colección: por defecto un buscador simple que filtra según se escribe (sin recargar la página), con un enlace "Avanzado" dentro del propio recuadro para desplegar el resto de filtros (plataforma, estado, orden...) cuando se necesiten. Implica filtrado en vivo (AJAX o similar), no solo un cambio visual.

Grandes:
- Buscador global tipo "Cmd+K" (paleta de comandos) para saltar a un juego/plataforma/sección sin ratón.
- Estadísticas más ricas: evolución del gasto por mes/año, top de géneros, juego más caro/mejor valorado.
- Verificación de email / 2FA (el modelo `User` ya tiene `MustVerifyEmail` comentado en el código); opcional para uso personal, interesante si la cuenta se comparte con más gente.

## Changelog

### 2026-08-02
- Filtros en la API (`GET /api/games`): mismos parámetros que el listado web (`q`, `platform_id`, `play_status`, `status`), para desbloquear el futuro cliente móvil.
- Recuperación de contraseña (`/forgot-password`, `/reset-password/{token}`): flujo estándar de Laravel con token de un solo uso, mismo mensaje de éxito exista o no el email.
- Importación masiva de la colección desde CSV (`/games/import`): solo el título es obligatorio, fila a fila (una fila con error no bloquea el resto), plataformas/ediciones nuevas se crean automáticamente, plantilla de ejemplo descargable y resumen de resultado tras importar.
- Paneles de catálogo (fabricantes, plataformas, ediciones) con vista de tarjetas en móvil, igual que el listado principal, en vez de solo scroll horizontal en la tabla.
- Tests: paneles de catálogo, perfil de usuario, estadísticas, recuperación de contraseña, importación CSV y los nuevos filtros de la API.
- Arreglado el centrado de los `<dialog>` (confirmación de borrado, alta rápida de edición): salían pegados a la esquina superior izquierda por un choque entre el preflight de Tailwind y el centrado nativo del navegador.
- Tema claro/oscuro con botón en el header (y en login/recuperar contraseña), persistido en `localStorage`.
- Orden del listado de la colección por título, precio, valoración o fecha de compra.
- Atajo de teclado `/` para enfocar el buscador de la colección.
- Acciones en bloque en la colección: seleccionar varios juegos y enviarlos a la papelera o cambiarles el estado de juego de golpe.
- Botón flotante de "Añadir juego" en móvil.
- Vista de estantería (grid de carátulas grandes) como alternativa al listado habitual.
- Tests de las acciones en bloque y de la ordenación del listado (110 tests en total).

### 2026-08-01
- Papelera de reciclaje con interfaz (`/games/trash`): restaurar o eliminar definitivamente un juego borrado (con limpieza de la carátula en disco).
- Paginación en `GET /api/games` (20 por página, `?per_page=` hasta 100).
- Toast flotante para los mensajes de éxito (sustituye los banners fijos repetidos por vista, que en la colección principal ni siquiera existían) y modal de confirmación propio para las acciones destructivas (sustituye el `confirm()` nativo del navegador en juegos, papelera, plataformas, fabricantes y ediciones).
- Protección contra fuerza bruta en el login (web y API): bloqueo de 60s tras 5 intentos fallidos, por email+IP, con contador compartido vía `ThrottlesLogins`.
- Alta de juego: "Propiedad" renombrada a "En colección" (valor por defecto al crear), retirado el campo "Condición física", nuevos valores de "Manual" (Con Manual/Sin Manual/Folleto, con color según si falta o no) y añadida la región PAL-EU.
- Carátulas: el preview y el listado respetan la proporción real de la imagen (ancho fijo, alto automático) en vez de recortar a cuadrado; arreglada la Content-Security-Policy de nginx, que bloqueaba el preview en vivo del alta.
- Creación de ediciones al vuelo desde el propio formulario de alta/edición de juego (modal + AJAX), sin perder los datos ya rellenados; botones "Seleccionar todas"/"Ninguna" en el panel de ediciones.
- Mensajes de validación traducidos al español (antes mostraban la clave sin traducir, p. ej. `validation.required`).

### 2026-07-31
- Interfaz responsive/móvil: navegación en drawer con hamburguesa, listado principal en tarjetas con buscador/filtros colapsables, formularios apilados en pantallas estrechas.
- Cobertura de tests: autenticación web y API (Sanctum), CRUD de la API con `GamePolicy`, alta/edición de juegos con carátula real.
- Corregido el entorno de tests: por un problema de configuración de Docker (`env_file` duplicando lo que ya carga Laravel) llegó a ejecutarse contra la base de datos real de desarrollo en vez de SQLite en memoria.
