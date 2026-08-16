# Savepoint

Savepoint es una aplicación para catalogar y gestionar una colección personal de videojuegos: qué juegos tienes, en qué plataforma, su estado de conservación, si los has terminado o no, precio de compra, y mucho más — con importación masiva, búsqueda de carátulas y datos en catálogos externos, estadísticas de la colección y exportación imprimible, entre otras cosas.

Construida como backend Laravel que sirve tanto una interfaz web (Blade + Tailwind) como una API REST (Sanctum) pensada para un futuro cliente externo (p. ej. app móvil). Pensada para desplegarse fácilmente con Docker en tu propio servidor o máquina — ver [Desplegar para uso propio](#desplegar-para-uso-propio).

Historial de cambios en [`CHANGELOG.md`](CHANGELOG.md).

## Índice

- [Características](#características)
- [Stack técnico](#stack-técnico)
- [Desplegar para uso propio](#desplegar-para-uso-propio)
- [Tests](#tests)
- [Pendiente / en curso](#pendiente--en-curso)
- [Contribuir](#contribuir)
- [Licencia](#licencia)

## Características

### Gestión de la colección de juegos
- Alta de un juego mediante un único formulario directo, sin pasos intermedios. Cubre prácticamente todo el modelo: título, EAN, desarrollador, plataforma, fecha de lanzamiento, géneros, propiedad (en colección/lista de deseos/vendido), estado de juego, conservación, precio y lugar/fecha de compra, manual, región, clasificación por edad y notas.
- **Carátula**: se sube desde el propio formulario (JPG/PNG/WEBP, máx. 1MB) con vista previa en vivo que respeta la proporción real de la imagen, sin recortarla. Si el juego no tiene carátula, se muestran las iniciales del título en su lugar.
- Listado de la colección con miniatura, título, plataforma, edición, región, manual, conservación (estrellas), precio y fecha de compra, paginado. En móvil se muestra como tarjetas en vez de tabla; tocar cualquier punto de una tarjeta abre la ficha de detalle del juego, que es el único sitio desde el que se edita o se borra.
- La colección se busca por **título**/**EAN** desde el mismo buscador rápido de toda la app (`Ctrl+K`, ver Interfaz): ya no hay un buscador de texto aparte en la propia página, solo un botón con su misma pinta que lo abre, precargado con la búsqueda activa si la hay. Un icono "Avanzado" en la página sigue desplegando los filtros de **plataforma**, **estado de juego**, **propiedad**, orden y tamaño de página, que gobiernan el listado paginado (el buscador rápido no pagina).
- **Ficha de detalle de solo lectura** con toda la información del juego, para "solo mirar" sin abrir el formulario de edición.
- **Enriquecimiento automático con IGDB**: la primera vez que se abre la ficha de un juego, se busca en IGDB por título (acotado por plataforma si hay match) y se completan desarrollador/fecha de lanzamiento si estaban vacíos, más géneros (en inglés, aparte de los que se escriben a mano) y la nota agregada — sin ninguna acción del usuario. Un botón "Corregir coincidencia" permite buscar y elegir otro resultado a mano si el automático no es el correcto (remaster, plataforma equivocada...). Requiere credenciales de IGDB (ver `IGDB_CLIENT_ID`/`IGDB_CLIENT_SECRET` en `.env.example`); sin ellas, este enriquecimiento simplemente no ocurre.
- **Fondo de la ficha con arte de IGDB**: botón "Elegir fondo" que muestra una muestra del arte promocional disponible en IGDB para elegir uno (o ninguno) como cabecera de la ficha del juego. Con el ajuste "Fondo automático desde IGDB" (ver Panel de control) activado, el primer arte disponible se fija solo al dar de alta el juego; desactivado (por defecto), sigue siendo siempre una elección explícita.
- **Edición rápida** de la conservación directamente desde la tarjeta o la estantería (clic en una estrella), sin abrir el formulario completo.
- Edición de un juego existente, incluida la opción de reemplazar o quitar la carátula.
- Al dar de alta o editar un juego con un EAN que ya tienes registrado, se avisa antes de guardar en vez de duplicarlo sin más (con opción de "guardar de todos modos" para el caso legítimo de tener dos copias físicas).
- Baja de un juego mediante **papelera de reciclaje**: panel dedicado para ver los juegos borrados, restaurarlos o eliminarlos definitivamente. El aviso de borrado lleva un botón "Deshacer" que restaura el juego sin salir de la colección.
- **Importación masiva** desde un CSV: solo el título es obligatorio, cada fila se procesa de forma independiente (una fila con error no bloquea al resto) y las plataformas/ediciones que el CSV mencione y no existan todavía en el catálogo se crean automáticamente. Antes de importar se muestra una vista previa de lo que se va a crear, con una plantilla de ejemplo descargable.
- **Buscar carátula y EAN en CEX** (webuy.com) desde el propio formulario de edición de un juego ya guardado: busca por EAN o título en su catálogo, muestra los resultados con carátula/EAN/plataforma para elegir con confianza, y rellena ambos campos al elegir uno. Si la búsqueda automática no encuentra nada, se puede repetir a mano con otras palabras.
- Panel de gestión de ediciones (normal/especial/coleccionista...) asociadas a una o varias plataformas — o a ninguna, lo que la deja disponible para cualquier plataforma, presente o futura. Una edición **"Normal"** con ese criterio viene creada de fábrica y es la que se preselecciona por defecto al dar de alta un juego (configurable desde Ajustes, junto con la región por defecto). Si la edición que necesitas no existe todavía, se puede crear al vuelo desde el propio formulario de alta/edición de juego sin perder lo ya rellenado. Cada edición tiene además un **formato** (físico/digital/CIAB, físico por defecto) marcado con icono en la gestión de ediciones, la ficha del juego y el listado de la colección.
- **Marcar un juego como "en venta"**: etiqueta independiente del estado de Propiedad (un juego sigue en tu colección y además puede estar en venta), con badge en las tres vistas de la colección (tarjetas, tabla, estantería) y su propio filtro. Se activa desde la ficha de detalle del juego o desde el propio formulario de alta/edición.
- **Vender un juego**: desde su ficha de detalle, un botón "Marcar como vendido" pide precio y fecha de venta (y permite ajustar las notas) y envía el juego a la papelera — deja de aparecer en tu colección, pero recuperable como cualquier borrado. Una página aparte, **Ventas** (`/sales`), reúne el histórico agrupado por año (título, plataforma, edición, región, precio de compra/venta, rendimiento, notas), con opción de deshacer una venta y que el juego vuelva a la colección.

### Lista de deseos
- Página propia para los juegos que todavía no tienes: **nunca aparecen en la colección principal** ni cuentan en sus totales.
- **Alta reducida**: a diferencia del alta normal, solo pide título, plataforma y edición — el resto de campos (precio, conservación, manual...) no tienen sentido todavía.
- Cada juego admite **prioridad** (alta/media/baja), **precio estimado** y **dónde comprarlo**. Buscador por título/EAN y orden por prioridad, título o precio estimado.
- Acción **"Pasar a la colección"**: abre el formulario de edición completo con los datos ya insertados y Propiedad/fecha de compra preseleccionadas, para no tener que rellenar todo de nuevo cuando por fin compras un juego de tu lista.

### Catálogo (fabricantes y plataformas)
- Panel de gestión para dar de alta, editar y borrar tus propios fabricantes y plataformas, en vez de depender de un catálogo precargado fijo.
- Cada **fabricante** define un color de marca para su chip que heredan todas sus plataformas; cada **plataforma** puede personalizar el suyo y tiene una **etiqueta abreviada** editable (p. ej. "PS5").

### Autenticación
- **Web:** login/logout con sesión (regenera el ID de sesión al iniciar sesión para evitar session fixation; redirige a la página original tras el login).
- **API:** login/logout con emisión y revocación de token Sanctum, pensado para un cliente externo (app móvil).
- **Perfil** (`/profile`): el usuario puede actualizar su nombre/email y cambiar su contraseña (pide la contraseña actual para confirmarla).
- **Recuperación de contraseña** (`/forgot-password`, `/reset-password/{token}`): flujo estándar de Laravel (token de un solo uso, expira a los 60 minutos, mismo mensaje de éxito exista o no el email para no revelar qué cuentas están registradas). El email se envía por el canal configurado en `MAIL_MAILER` (`log` por defecto en desarrollo, así que el enlace aparece en `storage/logs/laravel.log`).
- **Sin alta pública**: no hay ningún `/register`, las cuentas se crean solo desde la gestión de usuarios de abajo (o, para el primer arranque, por el seeder).
- **Gestión de usuarios** (`/panel/users`, solo cuentas con el rol **admin**): listar todas las cuentas de la plataforma con su nº de juegos, dar de alta cuentas nuevas (nombre/email/contraseña puesta a mano, rol admin opcional), editarlas y borrarlas. Un admin no puede quitarse el rol a sí mismo ni borrar su propia cuenta, y no se puede borrar una cuenta que todavía tenga juegos (evita el borrado en cascada real de toda su colección a nivel de base de datos). No hay ningún admin por defecto "de fábrica": la migración que añadió el rol marcó como admin a todas las cuentas que ya existían en ese momento.

### API REST
- CRUD de juegos (`GET/POST/PUT/DELETE /api/games`) protegido con `auth:sanctum`.
- El listado (`index`) pagina: 20 juegos por página por defecto, admite `?per_page=` con tope de 100. Admite los mismos filtros que el listado web: `?q=` (título o EAN), `?platform_id=`, `?play_status=` y `?status=`.
- Respuestas transformadas con `GameResource` (aplana la plataforma a su nombre, expone URL de carátula, etc.).
- Validación de entrada separada en `StoreGameRequest` / `UpdateGameRequest`.
- Tokens Sanctum con expiración global de 30 días desde su emisión (`SANCTUM_TOKEN_EXPIRATION_MINUTES` en `.env`, `config/sanctum.php`): pasado ese tiempo dejan de autenticar aunque no se hayan revocado a mano, así que un token filtrado no queda válido para siempre.

### Estadísticas
- Panel (`/stats`) con total de juegos, gasto total y conservación media, reparto de juegos por plataforma (barra por plataforma), y reparto por estado de juego y por propiedad (barras apiladas con leyenda).
- Evolución del gasto por mes de compra (gráfico de barras, últimos 12 meses con datos), top de géneros más repetidos en la colección, reparto por década de lanzamiento (`release_date`, orden cronológico) y destacados (juego más caro y mejor valorado, con enlace a su ficha).
- **Ventas por año**: nº de ventas, invertido, obtenido y rendimiento (beneficio y %) de los juegos vendidos, con enlace al histórico completo en `/sales`.

### Exportación
- **A CSV**: descarga toda la colección (respeta los filtros activos del listado) con las mismas columnas que reconoce la importación, así que el fichero se puede editar y volver a importar tal cual.
- **Imprimible / PDF**: vista independiente con la colección completa, lista para guardar como PDF desde el propio diálogo de impresión del navegador — sin generar nada en el servidor.

### Panel de control
- Página (`/panel`, enlazada desde el sidebar) que agrupa tareas que no son del día a día con la colección: importar/exportar, la papelera de reciclaje (con el nº de juegos que contiene), el perfil del usuario y los ajustes de comportamiento (ver debajo). Sustituye a los iconos "Importar" y "Papelera" que antes vivían sueltos en el sidebar (siguen accesibles por URL directa, y el icono del panel se resalta como activo también en esas páginas).
- **Ajustes** (`/panel/settings`): comportamiento de la app configurable por cuenta, no de instancia (esta app no tiene concepto de administrador global).
  - **Fondo automático desde IGDB**: si está activo, al dar de alta un juego se intenta identificar en IGDB y, si tiene arte disponible, se fija el primero como fondo de la ficha sin ninguna acción del usuario — se puede seguir cambiando a mano entre el resto de opciones, igual que siempre. Desactivado, el fondo se queda vacío hasta elegirlo a mano, como hasta ahora. Desactivado por defecto.
  - **Orden y tamaño de página por defecto** con los que arranca el listado de la colección, y **región y edición por defecto** que se preseleccionan al dar de alta un juego: un filtro o una elección explícita en el momento siguen ganando siempre a estos valores por defecto.
  - **Excluir la lista de deseos** de los resultados del buscador rápido (Ctrl+K), para quien prefiera no verla mezclada con la colección ahí (incluida por defecto, como hasta ahora).
  - Tema claro/oscuro y la vista de la colección elegida (ver Interfaz) también se guardan aquí, aunque se cambian desde sus propios controles (icono del header, botones de vista), no desde este formulario.

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar, borrar, restaurar y eliminar definitivamente (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Login (web y API) con protección contra fuerza bruta: bloqueo de 60 segundos tras 5 intentos fallidos, con clave email+IP (`ThrottlesLogins`), así que un atacante no puede bloquear a otros usuarios que compartan su misma IP. Además, un segundo límite más laxo (10 intentos / 5 minutos) solo por email frena a quien rota de IP en cada intento para saltarse el primero.
- Botón de cerrar sesión ("Salir") en la navegación.

### Interfaz
- Sidebar plegable a solo iconos en escritorio, y drawer deslizante con botón hamburguesa en móvil.
- La colección se ve como tarjetas en móvil y como tabla en escritorio, con edición/borrado siempre desde la ficha de detalle del juego, nunca con iconos sueltos en cada fila.
- Feedback de acciones consistente en toda la app: toasts flotantes para confirmaciones (con "Deshacer" cuando aplica) y un diálogo propio para confirmar acciones destructivas, en vez del `confirm()` nativo del navegador.
- Tema claro/oscuro y vista de la colección (línea de abajo) son ajustes de **cuenta**, no solo del navegador (antes vivían solo en `localStorage`): se pintan server-side desde el primer HTML, sin parpadeo al cargar o navegar, y te siguen a cualquier dispositivo donde inicies sesión.
- Orden del listado por título, precio, conservación o fecha de compra (con un valor por defecto configurable desde Ajustes); atajo de teclado `/` abre el buscador rápido, igual que `Ctrl+K`.
- Acciones en bloque: seleccionar varios juegos a la vez para enviarlos a la papelera o cambiarles el estado de golpe.
- Botón flotante de "Añadir juego" en móvil, para no tener que volver arriba al hacer scroll por una colección larga.
- Tres formas de ver la colección: la habitual, una tabla compacta y una estantería de carátulas grandes.
- Barra de estado discreta con el total de juegos y el gasto invertido en toda la colección, siempre visible.
- **Buscador único de la app** (`Ctrl+K`/`Cmd+K`, también la tecla `/` y el botón-buscador de la propia colección): resultados en vivo por título o EAN mientras se escribe, con filtros opcionales de plataforma/estado de juego/propiedad y un enlace para ver todos los resultados en la colección paginada cuando hace falta más que eso. Si el juego no está en tu colección, se ofrecen además **sugerencias de CEX** (webuy.com) con EAN y carátula reales para rellenar el alta con un clic; la lista de deseos se puede excluir de estos resultados desde Ajustes.
- **Escaneo de código de barras** con la cámara desde la propia búsqueda rápida: detecta el EAN y lo vuelca en el buscador, enlazando con las sugerencias de CEX si el juego todavía no está en tu colección. Necesita HTTPS para acceder a la cámara fuera de `localhost` (ver [Desplegar para uso propio](#desplegar-para-uso-propio)).
- **Instalable como PWA**: manifest (`public/manifest.json`) y service worker (`public/sw.js`, cache-first solo para los assets versionados de Vite) para poder "añadir a pantalla de inicio" en móvil y abrirla como app aparte. Igual que el escaneo de código de barras, el service worker solo se registra en HTTPS o `localhost`.

## Stack técnico

- **Backend:** Laravel 13 / PHP 8.3
- **Base de datos:** PostgreSQL (usa `JSONB` para campos como `genres`)
- **Autenticación web:** sesiones con guard `web` (login por email/contraseña)
- **Autenticación API:** Laravel Sanctum (tokens Bearer)
- **Frontend web:** Blade + Tailwind CSS + Vite.
- **Localización:** interfaz y mensajes de validación en español (`APP_LOCALE=es`, `lang/es/`). Laravel 11+ no publica estos archivos por defecto; se generaron y tradujeron a mano para que los errores de formulario no muestren la clave sin traducir (p. ej. `validation.required`).

## Desplegar para uso propio

### Arranque rápido con Docker

Todo lo que hace falta es Docker y Docker Compose. El stack (`docker-compose.yml`) levanta cinco contenedores: `postgres`, `redis`, `app` (PHP-FPM), `queue` (worker de Redis) y `nginx`. La imagen de `app`/`queue` (`docker/Dockerfile`) solo prepara el entorno (PHP + extensiones, Composer, Node/npm) — no instala ni configura la app, eso se hace a mano, un paso cada vez, para que quede claro qué hace falta y qué está pasando en cada momento:

```bash
git clone <url-del-repo> savepoint && cd savepoint
cp .env.example .env   # valores por defecto ya funcionan sin tocar nada; ver "Personalizar puertos y credenciales"

docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build       # o "npm run dev" si expones el puerto 5173 para hot-reload

docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed   # usuarios de prueba: admin@savepoint.test / test@example.com, contraseña "password"
docker compose exec app php artisan storage:link

# storage/ y bootstrap/cache vienen del bind-mount del host: si el repo se
# clonó como root (habitual en un servidor), PHP-FPM no puede escribir ahí al
# atender peticiones web (corre como www-data, no como root) y cualquier
# página da Error 500 ("tempnam(): file created in the system's temporary
# directory") aunque `artisan migrate`/`db:seed` de arriba hayan ido bien —
# esos sí corren como root porque "docker compose exec" usa root por defecto.
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

La app queda disponible en **`http://localhost:8081`**.

Tras cualquier cambio en las vistas Blade basta con recargar el navegador (no hay paso de build). Tras un cambio en CSS/JS (`resources/css`, `resources/js`) hace falta repetir `docker compose exec app npm run build`. Tras añadir una migración nueva, `docker compose exec app php artisan migrate`.

### Personalizar puertos y credenciales

Por defecto todo funciona con los valores de `.env.example` (contraseña `secreto123`, puertos 5432/6379/8081/8043). Si algún puerto ya lo tienes ocupado, o vas a hacer un despliegue real y quieres cambiar la contraseña de Postgres, copia `.env.example` a `.env` en la raíz del proyecto (si no lo has hecho ya) y edita lo que necesites — es el **único** `.env` del proyecto: el mismo fichero que lee Docker Compose para las credenciales de Postgres/Redis y los puertos es, directamente, el que carga Laravel. Tras cambiarlo, `docker compose up -d --build` para que se aplique.

### Exponer la app fuera de `localhost`

Por defecto la app sirve por HTTP plano en el puerto 8081, sin TLS — de sobra para usarla en `localhost` o dentro de tu propia red local desde un ordenador. Para acceder desde el móvil hace falta además HTTPS: el escaneo de código de barras usa la cámara del navegador (`getUserMedia`), que solo se permite en "contextos seguros" (HTTPS, o `localhost`). nginx en sí no gestiona certificados — sirve siempre HTTP plano; el TLS lo pone un **proxy inverso** delante:

- **Cloudflare Tunnel** o **Tailscale Funnel**: HTTPS gratuito sin tocar la configuración de nginx; cómodo para acceder desde fuera de tu red local.
- **mkcert + nginx**: certificado local de confianza, si el acceso es solo dentro de tu LAN.
- **Caddy** como reverse proxy delante de nginx: certificados Let's Encrypt automáticos si tienes un dominio propio.

En `docker-compose.yml`, el servicio `nginx` publica su puerto con dos líneas alternativas (una comentada): la de desarrollo/testeo (`HTTP_PORT`, 8081 por defecto) y la de producción (`HTTPS_PORT`, 8443 por defecto — el puerto al que apunta tu proxy inverso, que sigue hablando HTTP normal con nginx por detrás). Comenta una y descomenta la otra según toque, y `docker compose up -d --build` para que se aplique.

La cookie de sesión no necesita ningún ajuste aparte para llevar el flag `Secure` en este escenario: `bootstrap/app.php` confía en las cabeceras `X-Forwarded-*` de cualquier origen (`trustProxies(at: '*')`, seguro aquí porque el proxy inverso es el único punto de entrada), así que en cuanto este manda `X-Forwarded-Proto: https`, Laravel detecta la petición como segura y marca la cookie sola — sin tocar `SESSION_SECURE_COOKIE` en `.env` (se deja sin definir a propósito, ver comentario en `.env.example`).

Para un despliegue "en serio" en un servidor, además de lo anterior:

- Ajusta en tu `.env` `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con el dominio final.
- Cambia `DB_PASSWORD` por una contraseña real (`openssl rand -base64 24`, por ejemplo) — la de `.env.example` es solo para desarrollo local. Esto **solo tiene efecto en un volumen de Postgres nuevo**: si vas a reutilizar un volumen que ya tenía otra contraseña, Postgres la guarda en sus propios datos y no se actualiza sola al cambiar el `.env`. Para que coincidan, cambia también la contraseña real: `docker compose exec postgres psql -U savepoint -d savepoint -c "ALTER USER savepoint WITH PASSWORD 'nueva_contraseña';"`.

⚠️ Para aplicar migraciones nuevas usa siempre `php artisan migrate`, **nunca `migrate:fresh`** en un entorno con datos reales: `migrate:fresh` hace `DROP` de todas las tablas antes de recrearlas. `migrate` a secas solo aplica lo pendiente y no borra nada — el resultado en una base de datos vacía es el mismo que `fresh` de todas formas, así que no hay ninguna razón para usar `fresh` salvo que quieras borrarlo todo a propósito. Esto ya causó una pérdida de datos real durante el desarrollo del proyecto (ver CHANGELOG del 2026-08-06).

## Tests

```bash
docker compose exec app php artisan test
```

El entorno de test es independiente del de desarrollo: `phpunit.xml` fuerza `APP_ENV=testing`, SQLite en memoria, sesión/caché en array, etc., así que correr los tests nunca toca la base Postgres real ni Redis.

Cobertura actual:
- `Tests\Feature\Auth\WebAuthTest`: login/logout, credenciales inválidas, redirect a la página originalmente solicitada, protección de rutas para invitados, bloqueo por fuerza bruta (por email+IP y, rotando de IP en cada intento, por el límite adicional solo por email).
- `Tests\Feature\Api\AuthTest`: login/logout vía Sanctum (emisión y revocación de token), `/api/user` protegido, bloqueo por fuerza bruta, expiración de token (rechazado pasado el límite configurado, aceptado justo antes).
- `Tests\Feature\SessionCookieSecurityTest`: la cookie de sesión no lleva `Secure` por HTTP plano, pero sí en cuanto la petición llega con `X-Forwarded-Proto: https` (simula el proxy inverso de producción).
- `Tests\Feature\Api\GameControllerTest`: CRUD completo de la API, paginación (tamaño por defecto, `per_page` a medida y con tope), filtros (`q`, `platform_id`, `play_status`, `status`), scoping por usuario y `GamePolicy` bloqueando acceso a juegos ajenos (403 en view/update/delete).
- `Tests\Feature\Web\GameControllerTest`: alta y edición de juegos con subida/reemplazo de carátula real, validación, aviso de EAN duplicado (con y sin confirmar), `GamePolicy` aplicada en las rutas web, la ficha de detalle, la edición rápida (conservación/estado/en venta) por AJAX y por formulario normal, el filtro "en venta", marcar un juego como vendido (validación, envío a la papelera, `GamePolicy`) y que la papelera excluye los juegos vendidos, el fragmento que devuelve `index()` para peticiones AJAX, la papelera (listar/restaurar/eliminar definitivamente, buscador/filtro propio, con scoping por usuario), el orden/paginación/región/edición por defecto de Ajustes (aplicados solo cuando la URL o el formulario no traen un valor explícito) y el autoasignado de fondo desde IGDB al dar de alta con ese ajuste activo.
- `Tests\Feature\Web\GameImportControllerTest`: importación desde CSV (con/sin BOM, separador coma o punto y coma), creación automática de plataformas/ediciones que no existían, filas sin título omitidas y reportadas como incidencia, validación del fichero subido, y la vista previa (columnas reconocidas/no reconocidas, filas de ejemplo, que no importa nada).
- `Tests\Feature\Web\ManufacturerControllerTest` / `PlatformControllerTest` / `EditionControllerTest`: CRUD de cada panel de catálogo, validaciones propias (colores en formato hex, nombre único de fabricante, colores obligatorios solo si se sobrescriben en una plataforma), que borrar un registro deja en `null` la relación en juegos/plataformas en vez de arrastrar el borrado, que la edición "Normal" existe por defecto sin ninguna plataforma asociada (disponible para cualquiera), y el formato de edición (físico por defecto si no se indica, alta/edición con un formato concreto, formato inválido rechazado).
- `Tests\Feature\Web\SearchControllerTest`: búsqueda rápida por título/EAN acotada al usuario autenticado, filtros de plataforma/estado de juego/propiedad, sugerencias externas de CEX solo cuando no hay coincidencia local (y no antes de 3 caracteres), y que la lista de deseos aparece o no según el ajuste correspondiente.
- `Tests\Feature\Web\PanelControllerTest`: enlaces del panel y contador de la papelera por usuario, y la página de Ajustes — guardar cada grupo de preferencias (incluido dejar un valor en blanco para volver al comportamiento por defecto), que no afectan a otros usuarios, y el endpoint AJAX de tema/vista de la colección.
- `Tests\Feature\Web\ProfileControllerTest`: actualización de nombre/email (con email único), cambio de contraseña exigiendo la actual y confirmación.
- `Tests\Feature\Web\UserControllerTest`: invitados y usuarios no-admin bloqueados (redirect/403) en todas las rutas de gestión de usuarios, listado con nº de juegos por cuenta, alta con contraseña hasheada, validación (email único, contraseña mínima y confirmada), edición de nombre/email/rol, cambio de contraseña opcional (en blanco no la toca), que un admin no puede quitarse el rol ni borrarse a sí mismo, y que no se puede borrar una cuenta con juegos.
- `Tests\Feature\Web\StatsControllerTest`: los totales y repartos (por plataforma, estado de juego, propiedad, gasto por mes, top de géneros, destacados y ventas por año) solo consideran los juegos del usuario autenticado.
- `Tests\Feature\Web\SalesControllerTest`: histórico de ventas agrupado por año con sus totales/rendimiento, scoping por usuario, deshacer una venta (el juego vuelve a la colección sin datos de venta) y `GamePolicy` bloqueando la restauración de una venta ajena.
- `Tests\Feature\Web\PasswordResetTest`: envío del enlace de reset (mismo mensaje exista o no el email), reset con token válido/inválido.
- `Tests\Unit\Models\GameTest` / `PlatformTest`: iniciales y URL de carátula, resolución de colores/etiqueta de chip con fallback a fabricante.

## Pendiente / en curso

- Sin backups automatizados de Postgres (ni `pg_dump` programado ni snapshot del volumen).
- Sin HTTPS en el despliegue actual: bloquea el escaneo de código de barras desde el móvil fuera de `localhost` (ver [Desplegar para uso propio](#desplegar-para-uso-propio)).
- **2FA por email en el login**: la app está expuesta a internet, así que un password filtrado/reusado en otro sitio (credential stuffing) es un vector real, no solo teórico. El modelo `User` ya tiene `MustVerifyEmail` comentado en el código como punto de partida. Falta: configurar un mailer real (`MAIL_MAILER` está en `log`, no envía nada todavía), columnas en `users` para código + expiración, pantalla de verificación tras el login por password, y reenvío de código con cooldown.

### Ideas de interfaz/funcionalidad sin priorizar

Pequeñas (estética):
- Estados vacíos ilustrados en colección, lista de deseos y papelera cuando no hay elementos, en vez del texto plano actual.
- Skeleton loaders en el buscador AJAX de la colección y en la búsqueda rápida (Ctrl+K) mientras llegan los resultados, en vez del salto brusco al reemplazarlos.
- Insignia visual de "completado" (icono de trofeo) en los juegos con estado de juego = terminado: en la vista de estantería, en la esquina de la carátula; en la vista de tarjetas (móvil), en la propia tarjeta.
- Transición suave al cambiar entre las tres vistas de la colección (tarjetas/tabla/estantería), hoy instantánea y algo brusca.

## Contribuir

Savepoint es, hoy, un proyecto de uso personal (una sola persona catalogando su propia colección), así que no hay un proceso de contribución formal ni CI configurado. Aun así, si se bifurca o alguien quiere proponer un cambio:

- **Commits**: se usa el prefijo convencional del tipo de cambio en el mensaje (`feat:`, `fix:`, `docs:`, `test:`), en español y explicando el *por qué* del cambio, no solo el *qué* — se puede ver el patrón en `git log`.
- **Tests**: cualquier cambio de comportamiento (no solo visual) debería llevar test y pasar `docker compose exec app php artisan test` en verde antes de proponerlo.
- **Idioma**: la interfaz, los mensajes de validación y la documentación del proyecto están en español; se mantiene así para no mezclar idiomas a medias.
- **Documentación**: los cambios de comportamiento visible (nueva función, fix de UI, etc.) se documentan como una entrada nueva en [`CHANGELOG.md`](CHANGELOG.md) con la fecha del día, y si añaden o cambian una característica ya descrita, la sección correspondiente de "Características" en este README se actualiza a la vez para no dejarla desincronizada.

## Licencia

Código abierto bajo [PolyForm Noncommercial 1.0.0](LICENSE): puedes usarlo, modificarlo y bifurcarlo libremente, pero no para ningún uso comercial. Como el autor es el único titular de los derechos, puede conceder aparte una licencia comercial bajo petición (esquema de licencia dual, igual que MySQL o Qt) — abre un issue o contacta directamente si es tu caso.