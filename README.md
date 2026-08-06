# Savepoint

Savepoint es una aplicación para catalogar y gestionar una colección personal de videojuegos: qué juegos tienes, en qué plataforma, su estado de conservación, si los has terminado o no, precio de compra, etc.

El proyecto está construido como backend Laravel que sirve tanto una interfaz web (Blade) como una API REST (Sanctum) pensada para un futuro cliente externo (p. ej. app móvil).

Historial de cambios en [`CHANGELOG.md`](CHANGELOG.md).

## Índice

- [Stack técnico](#stack-técnico)
- [Características](#características)
- [Despliegue](#despliegue)
- [Tests](#tests)
- [Pendiente / en curso](#pendiente--en-curso)
- [Contribuir](#contribuir)
- [Licencia](#licencia)

## Stack técnico

- **Backend:** Laravel 13 / PHP 8.3
- **Base de datos:** PostgreSQL (usa `JSONB` para campos como `genres`)
- **Autenticación web:** sesiones con guard `web` (login por email/contraseña)
- **Autenticación API:** Laravel Sanctum (tokens Bearer)
- **Frontend web:** Blade + Tailwind CSS + Vite.
- **Localización:** interfaz y mensajes de validación en español (`APP_LOCALE=es`, `lang/es/`). Laravel 11+ no publica estos archivos por defecto; se generaron y tradujeron a mano para que los errores de formulario no muestren la clave sin traducir (p. ej. `validation.required`).

## Características

### Gestión de la colección de juegos
- Alta de un juego mediante un único formulario directo — sin pasos intermedios de búsqueda previa, ya que no hay scraping de fuentes externas. Cubre prácticamente todo el modelo: título, EAN, desarrollador, plataforma, fecha de lanzamiento, géneros, propiedad (en colección/lista de deseos/vendido, "en colección" por defecto), estado de juego, conservación, precio y lugar/fecha de compra, manual (con manual/sin manual/folleto), región, clasificación por edad y notas.
- **Carátula**: se sube desde el propio formulario (JPG/PNG/WEBP, máx. 1MB) con vista previa en vivo que respeta la proporción real de la imagen — ancho fijo y alto automático, así que una portada cuadrada (caja de PC/CD) sale cuadrada y una portrait (la mayoría de cajas de consola) crece en alto sin recortarse. Si el juego no tiene carátula, se muestra un recuadro con las iniciales del título en su lugar (tanto en el listado como en el formulario), generado con `Game::coverInitials()`.
- Listado de la colección (página principal) con miniatura, título, plataforma, edición, región, manual, conservación (estrellas), precio y fecha de compra, paginado (tamaño de página configurable desde el propio filtro: 10/20/50/100). La tabla de escritorio no incluye columna de estado de juego (pendiente/jugando/terminado), para que quepa entera sin scroll horizontal en portátiles 1080p; sigue disponible como filtro y en la ficha de detalle. En las tarjetas de móvil, la plataforma se muestra como etiqueta en la esquina superior derecha y el resto de datos (valoración, fecha de compra, precio) se reparte en filas propias en vez de amontonarse a la izquierda; tocar cualquier punto de la tarjeta (fuera de sus controles) abre la ficha de detalle. Editar y borrar un juego se hace siempre desde su ficha de detalle — ni la tabla de escritorio ni las tarjetas de móvil llevan iconos de acción propios.
- Búsqueda dentro de la propia colección por **título** o **EAN**: buscador grande y centrado (protagonista de la página, no un campo más), que filtra en vivo según se escribe (AJAX, sin recargar la página) e igual de visible en cualquier tamaño de pantalla; un icono "Avanzado" junto a él despliega el resto de filtros (**plataforma**, **estado de juego**, **propiedad**, orden y tamaño de página) para cuando hacen falta.
- La consulta del listado solo trae las columnas y relaciones que la vista pinta (evita cargar `notes`/`data`/`genres` innecesariamente y N+1 en `platform`/`edition`).
- **Ficha de detalle de solo lectura** (`/games/{id}`) con toda la información del juego, para "solo mirar" sin abrir el formulario de edición. El título de cada juego en el listado (tabla, tarjetas o estantería) enlaza aquí, y es también el único sitio desde el que se edita o se borra.
- **Edición rápida** de la conservación directamente desde la tarjeta del listado en móvil o la estantería (clic en una estrella), por AJAX, sin abrir el formulario completo. En la tabla de escritorio la conservación es de solo lectura a propósito: se cambia desde el formulario de edición.
- Edición de un juego existente, incluida la opción de reemplazar o quitar la carátula.
- Al dar de alta o editar un juego con un EAN que ya tienes registrado, se avisa antes de guardar en vez de duplicarlo sin más (con opción de "guardar de todos modos" para el caso legítimo de tener dos copias físicas). Los juegos sin EAN nunca activan el aviso entre sí.
- Baja de un juego mediante **papelera de reciclaje** (soft delete): panel dedicado (`/games/trash`, con su propio buscador por título/EAN y filtro por plataforma) para ver los juegos borrados, restaurarlos o eliminarlos definitivamente (esto último borra también el fichero de la carátula). El toast que aparece al borrar un juego lleva un botón "Deshacer" que lo restaura sin salir de la colección.
- **Importación masiva** (`/games/import`) desde un CSV (coma o punto y coma, con o sin BOM de Excel): solo el título es obligatorio, cada fila se procesa de forma independiente (una fila con error no bloquea al resto) y las plataformas/ediciones que el CSV mencione y no existan todavía en el catálogo se crean automáticamente. Al elegir el fichero se muestra antes una **vista previa** (columnas reconocidas/no reconocidas y las primeras filas) sin importar nada todavía. Hay una plantilla de ejemplo descargable desde la propia página. Tras importar se muestra un resumen (juegos importados, plataformas/ediciones creadas, filas con incidencias).
- Al editar/reemplazar la carátula, el fichero anterior se borra del disco (`storage/app/public/covers`) para no dejar huérfanos.
- Panel de gestión de ediciones (`/editions`) para dar de alta ediciones (normal/especial/coleccionista/...) asociadas a una o varias plataformas, con un botón "Seleccionar todas"/"Ninguna" para no marcarlas una a una; el campo `edition_id` del juego se filtra según la plataforma elegida en el formulario. Si la edición que necesitas no existe todavía, se puede crear al vuelo desde el propio formulario de alta/edición de juego (modal + AJAX) sin perder lo ya rellenado.

### Lista de deseos
- Página propia (`/wishlist`, enlazada desde el sidebar) para los juegos con Propiedad = "Lista de deseos". **Nunca aparecen en la colección principal** (`GameController::index` los excluye siempre, incluso si se manipula la URL con `?status=wishlist`; la opción se ha quitado del filtro "Propiedad" de la colección) ni cuentan en sus totales (barra de estado del pie), porque todavía no son parte de "tu colección".
- **Alta reducida** (`/wishlist/create`): a diferencia del alta normal, solo pide título, plataforma y edición — el resto de campos del juego (precio, conservación, manual...) no tienen sentido todavía. Propiedad se fija a "Lista de deseos" internamente, sin mostrar el campo.
- Cada juego admite **prioridad** (alta/media/baja), **precio estimado** y **dónde comprarlo**, editables desde el propio formulario de edición completo (sección "Lista de deseos"). Buscador por título/EAN y orden por prioridad (por defecto, alta primero), título o precio estimado.
- Acción **"Pasar a la colección"** en cada juego: abre el formulario de edición completo de siempre, con los datos ya insertados, pero con Propiedad y fecha de compra preseleccionadas a "En colección"/hoy para no tener que cambiarlas a mano (`?convert_to_owned=1`, ver `GameController::edit`). El usuario completa precio y el resto de detalles y guarda como cualquier edición normal.

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
- Panel (`/stats`) con total de juegos, gasto total y conservación media, reparto de juegos por plataforma (barra por plataforma), y reparto por estado de juego y por propiedad (barras apiladas con leyenda).
- Evolución del gasto por mes de compra (gráfico de barras, últimos 12 meses con datos), top de géneros más repetidos en la colección, y destacados (juego más caro y mejor valorado, con enlace a su ficha).

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar, borrar, restaurar y eliminar definitivamente (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Login (web y API) con protección contra fuerza bruta: bloqueo de 60 segundos tras 5 intentos fallidos, con clave email+IP (`ThrottlesLogins`), así que un atacante no puede bloquear a otros usuarios que compartan su misma IP.
- Botón de cerrar sesión ("Salir") en la navegación.

### Interfaz
- Sidebar plegable (escritorio): un botón en su cabecera lo contrae a solo iconos (de 15rem a 4.5rem) para aprovechar el ancho en pantallas 1080p; cada enlace muestra su nombre como tooltip nativo mientras está colapsado. La preferencia se guarda en `localStorage` y se aplica antes del primer pintado (vía un script bloqueante en el `<head>`) para no parpadear al navegar entre páginas. Implementado en JS vanilla (sin Alpine ni otra dependencia).
- Navegación móvil (< 768px): el sidebar deja de ocupar ancho fijo y pasa a ser un drawer superpuesto que entra desde la izquierda, con botón hamburguesa en el header y fondo oscuro; se cierra tocando fuera, el botón de cerrar o cualquier enlace del menú.
- Colección en móvil: el listado se muestra como tarjetas (carátula, plataforma, estado, conservación y precio) en vez de la tabla de escritorio; editar y borrar se hacen desde la ficha de detalle del juego, igual que en la tabla — la tarjeta no lleva iconos de acción propios. El buscador simple es igual de grande y visible que en escritorio, arriba del todo (no vive detrás de ningún acordeón); solo los filtros "Avanzado" (plataforma/estado/orden/paginación) se pliegan tras su icono, igual que en escritorio.
- Paneles de catálogo en móvil (fabricantes, plataformas, ediciones): igual que la colección, pasan de la tabla con scroll horizontal a tarjetas con la misma información y acciones de editar/borrar como botones de icono.
- Formularios (alta/edición de juego, perfil, fabricantes, plataformas) apilan sus campos en una sola columna en pantallas estrechas en vez de apretarlos en el mismo grid que escritorio.
- Feedback de acciones: los mensajes de éxito se muestran como un toast flotante que se desvanece solo (antes eran banners fijos, duplicados en cada vista, y en la colección principal directamente no se mostraban), con fondo sólido y posicionado siempre bajo la cabecera. Las acciones destructivas (borrar juego, plataforma, fabricante, edición, o eliminar definitivamente desde la papelera) piden confirmación en un `<dialog>` propio con el nombre del elemento afectado, en vez del `confirm()` del navegador. Ambos son un único componente compartido en el layout, reutilizado desde cualquier vista. El `<dialog>` se centra en pantalla vía `margin: auto` (se declara explícitamente en `app.css` porque el preflight de Tailwind resetea el margin por defecto de todos los elementos, y sin él el navegador no puede centrar un `<dialog>` abierto con `showModal()`).
- Tema claro/oscuro: botón en el header (y en las pantallas de login/recuperar contraseña) que alterna entre los dos, persistido en `localStorage` y aplicado antes del primer pintado (mismo mecanismo que el sidebar). Técnicamente no se tocó ninguna vista: cada plantilla sigue usando las mismas clases de Tailwind (`bg-slate-900`, `text-slate-400`...) de siempre, y lo que cambia con la clase `light` en `<html>` es a qué color apunta cada variable de la paleta (`app.css`), así que da igual cuántas vistas nuevas se añadan en el futuro, heredan el tema sin tocar nada.
- Orden del listado de la colección (`?sort=`, `?dir=`): por título, precio, conservación o fecha de compra, ascendente o descendente, combinable con la búsqueda y los filtros.
- Atajo de teclado `/` para enfocar el buscador de la colección, como en GitHub o Gmail.
- Acciones en bloque en la colección: casillas de selección (una por fila/tarjeta, más "seleccionar todo" en la tabla de escritorio) para enviar varios juegos a la papelera o cambiarles el estado de juego de golpe, sin repetir la acción uno a uno. Las casillas están ocultas por defecto: solo aparecen al activar el "modo selección" (icono en la esquina superior derecha, justo encima de la tabla/tarjetas), para no ocupar espacio permanentemente en cada fila/tarjeta.
- Botón flotante ("Añadir juego") fijo en la esquina inferior derecha en móvil, para no tener que volver arriba al hacer scroll por una colección larga.
- Tres formas de ver la colección, alternables con los iconos de la esquina superior derecha (persistido en `localStorage`, igual mecanismo que el tema): la habitual (tarjetas en móvil, tabla en escritorio), una tabla compacta (filas más bajas, mismos datos) y una estantería (grid de carátulas grandes). La compacta es la que se usa por defecto la primera vez que se entra sin preferencia guardada.
- Barra de estado discreta al pie de la colección (pegada al fondo del área de scroll): número total de juegos en la colección y gasto total invertido, sin depender de los filtros activos en cada momento.
- Búsqueda rápida global (`Ctrl+K`/`Cmd+K`, botón de lupa en el header): abre un `<dialog>` centrado con resultados en vivo por título o EAN mientras se escribe; Enter abre el primer resultado, click abre cualquier otro. Si el juego no está en la colección, en vez de solo proponer darlo de alta a mano, se ofrecen además **sugerencias de CEX** (webuy.com) con su EAN y carátula reales: al elegir una se muestra una ficha para comprobar los datos y, si concuerda, un botón "Dar de alta" que abre el formulario de siempre con título/EAN/carátula ya prellenados (el resto se rellena a mano). No es una API oficial de CEX: reutiliza el índice de Algolia de su propia web con una clave pública de solo-búsqueda; vive detrás de `App\Services\GameLookup\GameLookupInterface` (implementada hoy por `CexGameLookupService`, con la configuración en `config('services.cex')`) precisamente porque puede dejar de funcionar o cambiar de proveedor sin aviso — si eso pasa, el buscador simplemente deja de mostrar sugerencias externas (no rompe nada) y basta con cambiar el binding de la interfaz en `AppServiceProvider`, sin tocar el controlador ni la vista. La carátula sugerida no se descarga hasta que se guarda el alta, y solo desde hosts en una lista blanca (`CEX_IMAGE_HOSTS`) para evitar que ese campo se use como proxy hacia una URL arbitraria (SSRF).
- Escaneo de código de barras (EAN) desde la propia búsqueda rápida: el icono de cámara abre un `<dialog>` con la cámara (vía `@zxing/library`, no la `BarcodeDetector` nativa del navegador porque no funciona en Safari/iOS; cargada solo al pulsar el botón para no meterla en el bundle inicial) y, al detectar un código, lo vuelca en el buscador —si el juego ya está en la colección aparece como resultado, si no, entran en juego las sugerencias de CEX de arriba, buscando directamente por ese EAN—. **Necesita HTTPS en producción** para que el navegador dé acceso a la cámara (en local con `localhost` no hay problema); ver [Despliegue en producción](#producción).

## Despliegue

### Desarrollo con Docker

El stack (`docker-compose.yml`) levanta `postgres`, `redis`, `app` (PHP-FPM), `queue` (worker de Redis) y `nginx`. La imagen de `app`/`queue` (`docker/Dockerfile`) incluye Composer y Node.js 22 + npm (copiados desde las imágenes oficiales `composer:latest` y `node:22-alpine`) para poder compilar los assets de Vite dentro del propio contenedor.

Primer arranque:

```bash
cp backend/.env.example backend/.env   # ya trae los valores del docker-compose.yml (host/usuario/password de postgres y redis); solo falta APP_KEY
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build       # o npm run dev si se expone el puerto 5173 para HMR

docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed  # usuarios de prueba: felipe@savepoint.test / test@example.com, contraseña "password"
docker compose exec app php artisan storage:link
```

La app queda disponible en `http://localhost:8081`.

Tras cualquier cambio en las vistas Blade basta con recargar (no hay build). Tras un cambio en CSS/JS (`resources/css`, `resources/js`) hace falta `docker compose exec app npm run build` para que Tailwind/Vite regeneren el bundle — si no, la clase o el script nuevo no aparece aunque el código esté bien.

### Producción

El `nginx.conf` del repo (`docker/nginx.conf`) sirve la app por HTTP plano (puerto 8081); no hay terminación TLS configurada todavía. Esto es suficiente para uso en `localhost` o en la propia red local desde un navegador de escritorio, pero **bloquea una función concreta desde el móvil**: el escaneo de código de barras necesita `getUserMedia`, que los navegadores solo exponen en "contextos seguros" (HTTPS, con la única excepción de `localhost`). Fuera de `localhost` y sin HTTPS, el botón de cámara falla silenciosamente (no llega a pedir permiso).

Antes de exponer la app fuera de `localhost` (dominio propio, acceso desde el móvil, etc.), hace falta HTTPS delante de nginx. Opciones razonables según el caso:
- **Cloudflare Tunnel** o **Tailscale Funnel**: HTTPS gratuito sin tocar la config de nginx, cómodo si se accede desde fuera de la red local.
- **mkcert + nginx**: certificado local de confianza, si el acceso es solo dentro de la LAN de casa.
- **Caddy** como reverse proxy delante de nginx: gestiona certificados Let's Encrypt automáticamente si hay un dominio propio.

Fuera de esto, el despliegue en un servidor sigue el mismo `docker-compose.yml` que en desarrollo: clonar, copiar `.env` con los valores de producción (`APP_ENV=production`, `APP_DEBUG=false`, credenciales reales de Postgres/Redis, `APP_URL` con el dominio final), y los mismos pasos de `composer install` / `npm run build` / `migrate`.

## Tests

```bash
docker compose exec app php artisan test
```

El entorno de test es independiente del de desarrollo: `phpunit.xml` fuerza `APP_ENV=testing`, SQLite en memoria, sesión/caché en array, etc., así que correr los tests nunca toca la base Postgres real ni Redis (el `docker-compose.yml` deliberadamente no pasa `backend/.env` como `env_file` de `app`/`queue` para que esto funcione).

Cobertura actual:
- `Tests\Feature\Auth\WebAuthTest`: login/logout, credenciales inválidas, redirect a la página originalmente solicitada, protección de rutas para invitados, bloqueo por fuerza bruta.
- `Tests\Feature\Api\AuthTest`: login/logout vía Sanctum (emisión y revocación de token), `/api/user` protegido, bloqueo por fuerza bruta.
- `Tests\Feature\Api\GameControllerTest`: CRUD completo de la API, paginación (tamaño por defecto, `per_page` a medida y con tope), filtros (`q`, `platform_id`, `play_status`, `status`), scoping por usuario y `GamePolicy` bloqueando acceso a juegos ajenos (403 en view/update/delete).
- `Tests\Feature\Web\GameControllerTest`: alta y edición de juegos con subida/reemplazo de carátula real, validación, aviso de EAN duplicado (con y sin confirmar), `GamePolicy` aplicada en las rutas web, la ficha de detalle, la edición rápida (conservación/estado) por AJAX, el fragmento que devuelve `index()` para peticiones AJAX, y la papelera (listar/restaurar/eliminar definitivamente, buscador/filtro propio, con scoping por usuario).
- `Tests\Feature\Web\GameImportControllerTest`: importación desde CSV (con/sin BOM, separador coma o punto y coma), creación automática de plataformas/ediciones que no existían, filas sin título omitidas y reportadas como incidencia, validación del fichero subido, y la vista previa (columnas reconocidas/no reconocidas, filas de ejemplo, que no importa nada).
- `Tests\Feature\Web\ManufacturerControllerTest` / `PlatformControllerTest` / `EditionControllerTest`: CRUD de cada panel de catálogo, validaciones propias (colores en formato hex, nombre único de fabricante, colores obligatorios solo si se sobrescriben en una plataforma) y que borrar un registro deja en `null` la relación en juegos/plataformas en vez de arrastrar el borrado.
- `Tests\Feature\Web\ProfileControllerTest`: actualización de nombre/email (con email único), cambio de contraseña exigiendo la actual y confirmación.
- `Tests\Feature\Web\StatsControllerTest`: los totales y repartos (por plataforma, estado de juego, propiedad, gasto por mes, top de géneros y destacados) solo consideran los juegos del usuario autenticado.
- `Tests\Feature\Web\PasswordResetTest`: envío del enlace de reset (mismo mensaje exista o no el email), reset con token válido/inválido.
- `Tests\Unit\Models\GameTest` / `PlatformTest`: iniciales y URL de carátula, resolución de colores/etiqueta de chip con fallback a fabricante.

## Pendiente / en curso

- Exportación de la colección (la importación desde CSV ya está implementada, ver "Importación masiva").
- Sin backups automatizados de Postgres (ni `pg_dump` programado ni snapshot del volumen).
- Sin HTTPS en el despliegue actual: bloquea el escaneo de código de barras desde el móvil fuera de `localhost` (ver [Despliegue en producción](#producción)).

### Ideas de interfaz/funcionalidad sin priorizar

Grandes:
- Verificación de email / 2FA: el modelo `User` ya tiene `MustVerifyEmail` comentado en el código, pero deliberadamente no se activa — la app la usa una sola persona para su propia colección, así que no aporta nada de seguridad real hoy. Se deja preparado (comentado, no borrado) por si el proyecto se hace público/se forkea y alguien añade más usuarios o lo despliega en abierto.

## Contribuir

Savepoint es, hoy, un proyecto de uso personal (una sola persona catalogando su propia colección), así que no hay un proceso de contribución formal ni CI configurado. Aun así, si se bifurca o alguien quiere proponer un cambio:

- **Commits**: se usa el prefijo convencional del tipo de cambio en el mensaje (`feat:`, `fix:`, `docs:`, `test:`), en español y explicando el *por qué* del cambio, no solo el *qué* — se puede ver el patrón en `git log`.
- **Tests**: cualquier cambio de comportamiento (no solo visual) debería llevar test y pasar `docker compose exec app php artisan test` en verde antes de proponerlo.
- **Idioma**: la interfaz, los mensajes de validación y la documentación del proyecto están en español; se mantiene así para no mezclar idiomas a medias.
- **Documentación**: los cambios de comportamiento visible (nueva función, fix de UI, etc.) se documentan como una entrada nueva en [`CHANGELOG.md`](CHANGELOG.md) con la fecha del día, y si añaden o cambian una característica ya descrita, la sección correspondiente de "Características" en este README se actualiza a la vez para no dejarla desincronizada.