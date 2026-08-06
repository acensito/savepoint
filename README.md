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
- Búsqueda dentro de la propia colección por **título** o **EAN**: buscador grande y centrado, que filtra en vivo según se escribe. Un icono "Avanzado" despliega el resto de filtros (**plataforma**, **estado de juego**, **propiedad**, orden y tamaño de página) para cuando hacen falta.
- **Ficha de detalle de solo lectura** con toda la información del juego, para "solo mirar" sin abrir el formulario de edición.
- **Edición rápida** de la conservación directamente desde la tarjeta o la estantería (clic en una estrella), sin abrir el formulario completo.
- Edición de un juego existente, incluida la opción de reemplazar o quitar la carátula.
- Al dar de alta o editar un juego con un EAN que ya tienes registrado, se avisa antes de guardar en vez de duplicarlo sin más (con opción de "guardar de todos modos" para el caso legítimo de tener dos copias físicas).
- Baja de un juego mediante **papelera de reciclaje**: panel dedicado para ver los juegos borrados, restaurarlos o eliminarlos definitivamente. El aviso de borrado lleva un botón "Deshacer" que restaura el juego sin salir de la colección.
- **Importación masiva** desde un CSV: solo el título es obligatorio, cada fila se procesa de forma independiente (una fila con error no bloquea al resto) y las plataformas/ediciones que el CSV mencione y no existan todavía en el catálogo se crean automáticamente. Antes de importar se muestra una vista previa de lo que se va a crear, con una plantilla de ejemplo descargable.
- **Buscar carátula y EAN en CEX** (webuy.com) desde el propio formulario de edición de un juego ya guardado: busca por EAN o título en su catálogo, muestra los resultados con carátula/EAN/plataforma para elegir con confianza, y rellena ambos campos al elegir uno. Si la búsqueda automática no encuentra nada, se puede repetir a mano con otras palabras.
- Panel de gestión de ediciones (normal/especial/coleccionista...) asociadas a una o varias plataformas; si la que necesitas no existe todavía, se puede crear al vuelo desde el propio formulario de alta/edición de juego sin perder lo ya rellenado.

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

### API REST
- CRUD de juegos (`GET/POST/PUT/DELETE /api/games`) protegido con `auth:sanctum`.
- El listado (`index`) pagina: 20 juegos por página por defecto, admite `?per_page=` con tope de 100. Admite los mismos filtros que el listado web: `?q=` (título o EAN), `?platform_id=`, `?play_status=` y `?status=`.
- Respuestas transformadas con `GameResource` (aplana la plataforma a su nombre, expone URL de carátula, etc.).
- Validación de entrada separada en `StoreGameRequest` / `UpdateGameRequest`.

### Estadísticas
- Panel (`/stats`) con total de juegos, gasto total y conservación media, reparto de juegos por plataforma (barra por plataforma), y reparto por estado de juego y por propiedad (barras apiladas con leyenda).
- Evolución del gasto por mes de compra (gráfico de barras, últimos 12 meses con datos), top de géneros más repetidos en la colección, reparto por década de lanzamiento (`release_date`, orden cronológico) y destacados (juego más caro y mejor valorado, con enlace a su ficha).

### Exportación imprimible / PDF
- Exportación de la colección completa a una vista imprimible, lista para guardar como PDF desde el propio diálogo de impresión del navegador — sin generar nada en el servidor.

### Panel de control
- Página (`/panel`, enlazada desde el sidebar) que agrupa tareas que no son del día a día con la colección: importar/exportar, la papelera de reciclaje (con el nº de juegos que contiene) y el perfil del usuario. Sustituye a los iconos "Importar" y "Papelera" que antes vivían sueltos en el sidebar (siguen accesibles por URL directa, y el icono del panel se resalta como activo también en esas páginas).

### Seguridad de datos
- Cada juego pertenece a un usuario (`user_id`), asignado siempre al usuario autenticado (`auth()->id()` / `$request->user()->id`) al crearlo, tanto en web como en API.
- Listados y búsqueda (web y API) filtrados por `user_id`: cada usuario solo ve su propia colección.
- `GamePolicy` aplicada con `Gate::authorize()` en editar, borrar, restaurar y eliminar definitivamente (web y API), para que nadie pueda tocar un juego ajeno aunque adivine su ID por URL.
- Login (web y API) con protección contra fuerza bruta: bloqueo de 60 segundos tras 5 intentos fallidos, con clave email+IP (`ThrottlesLogins`), así que un atacante no puede bloquear a otros usuarios que compartan su misma IP.
- Botón de cerrar sesión ("Salir") en la navegación.

### Interfaz
- Sidebar plegable a solo iconos en escritorio, y drawer deslizante con botón hamburguesa en móvil.
- La colección se ve como tarjetas en móvil y como tabla en escritorio, con edición/borrado siempre desde la ficha de detalle del juego, nunca con iconos sueltos en cada fila.
- Feedback de acciones consistente en toda la app: toasts flotantes para confirmaciones (con "Deshacer" cuando aplica) y un diálogo propio para confirmar acciones destructivas, en vez del `confirm()` nativo del navegador.
- Tema claro/oscuro, persistido y aplicado antes del primer pintado para que no haya parpadeo al cargar o navegar.
- Orden del listado por título, precio, conservación o fecha de compra; atajo de teclado `/` para enfocar el buscador, como en GitHub o Gmail.
- Acciones en bloque: seleccionar varios juegos a la vez para enviarlos a la papelera o cambiarles el estado de golpe.
- Botón flotante de "Añadir juego" en móvil, para no tener que volver arriba al hacer scroll por una colección larga.
- Tres formas de ver la colección: la habitual, una tabla compacta y una estantería de carátulas grandes.
- Barra de estado discreta con el total de juegos y el gasto invertido en toda la colección, siempre visible.
- **Búsqueda rápida global** (`Ctrl+K`/`Cmd+K`): resultados en vivo por título o EAN mientras se escribe. Si el juego no está en tu colección, se ofrecen además **sugerencias de CEX** (webuy.com) con EAN y carátula reales para rellenar el alta con un clic.
- **Escaneo de código de barras** con la cámara desde la propia búsqueda rápida: detecta el EAN y lo vuelca en el buscador, enlazando con las sugerencias de CEX si el juego todavía no está en tu colección. Necesita HTTPS para acceder a la cámara fuera de `localhost` (ver [Desplegar para uso propio](#desplegar-para-uso-propio)).

## Stack técnico

- **Backend:** Laravel 13 / PHP 8.3
- **Base de datos:** PostgreSQL (usa `JSONB` para campos como `genres`)
- **Autenticación web:** sesiones con guard `web` (login por email/contraseña)
- **Autenticación API:** Laravel Sanctum (tokens Bearer)
- **Frontend web:** Blade + Tailwind CSS + Vite.
- **Localización:** interfaz y mensajes de validación en español (`APP_LOCALE=es`, `lang/es/`). Laravel 11+ no publica estos archivos por defecto; se generaron y tradujeron a mano para que los errores de formulario no muestren la clave sin traducir (p. ej. `validation.required`).

## Desplegar para uso propio

### Arranque rápido con Docker

Todo lo que hace falta es Docker y Docker Compose. El stack (`docker-compose.yml`) levanta cinco contenedores: `postgres`, `redis`, `app` (PHP-FPM), `queue` (worker de Redis) y `nginx`.

```bash
git clone <url-del-repo> savepoint && cd savepoint
docker compose up -d --build

docker compose exec app npm install
docker compose exec app npm run build      # o "npm run dev" si expones el puerto 5173 para hot-reload
```

Sin más pasos: no hace falta ni copiar un `.env` a mano. La app queda disponible en **`http://localhost:8081`**, ya con la base de datos migrada y dos usuarios de prueba (`admin@savepoint.test` / `test@example.com`, contraseña `password`). Todo eso (instalar dependencias de Composer, generar la clave de la app, migrar y sembrar la base de datos, enlazar el almacenamiento de las carátulas) lo hace automáticamente `docker/entrypoint.sh` en cada arranque del contenedor `app` — solo `npm install`/`npm run build` quedan fuera, porque compilar los assets en cada arranque sería lento y no hace falta salvo que cambies CSS/JS.

Tras cualquier cambio en las vistas Blade basta con recargar el navegador (no hay paso de build). Tras un cambio en CSS/JS (`resources/css`, `resources/js`) hace falta repetir `docker compose exec app npm run build` para que Tailwind/Vite regeneren el bundle.

### Personalizar puertos y credenciales

Por defecto todo funciona con los valores de `.env.example` (contraseña `secreto123`, puertos 5432/6379/8081/8043). Si algún puerto ya lo tienes ocupado, o vas a hacer un despliegue real y quieres cambiar la contraseña de Postgres, copia `.env.example` a `.env` en la raíz del proyecto (si no lo has hecho ya) y edita lo que necesites — es el **único** `.env` del proyecto: el mismo fichero que lee Docker Compose para las credenciales de Postgres/Redis y los puertos es, directamente, el que carga Laravel. Tras cambiarlo, `docker compose up -d --build` para que se aplique.

### Exponer la app fuera de `localhost`

Por defecto la app sirve por HTTP plano en el puerto 8081, sin TLS — de sobra para usarla en `localhost` o dentro de tu propia red local desde un ordenador. Para acceder desde el móvil hace falta además HTTPS: el escaneo de código de barras usa la cámara del navegador (`getUserMedia`), que solo se permite en "contextos seguros" (HTTPS, o `localhost`).

**Si ya tienes tus propios certificados de dominio** (comprados, de tu proveedor, de un reverse proxy delante que te los facilite...), nginx puede servir HTTPS directamente, sin publicar ningún puerto HTTP:

1. Coloca tus certificados en `./certs/fullchain.pem` y `./certs/privkey.pem` (la carpeta está en `.gitignore`, nunca se commitean).
2. En tu `.env`, cambia `COMPOSE_PROFILES=dev` a `COMPOSE_PROFILES=prod`.
3. `docker compose up -d --build`.

Esto arranca `nginx-prod` (`docker/nginx.prod.conf`, HTTPS en el puerto `HTTPS_PORT`, 8443 por defecto) **en vez de** `nginx` (el de desarrollo, HTTP en `HTTP_PORT`) — son dos servicios distintos, nunca los dos a la vez, así que no queda ningún puerto HTTP publicado. El resto de servicios (`postgres`, `redis`, `app`, `queue`) son los mismos en los dos perfiles.

**Si no tienes certificados propios todavía**, otras opciones razonables:

- **Cloudflare Tunnel** o **Tailscale Funnel**: HTTPS gratuito sin tocar la configuración de nginx; cómodo para acceder desde fuera de tu red local.
- **mkcert + nginx**: certificado local de confianza, si el acceso es solo dentro de tu LAN.
- **Caddy** como reverse proxy delante de nginx: certificados Let's Encrypt automáticos si tienes un dominio propio.

Para un despliegue "en serio" en un servidor, además de lo anterior:

- Ajusta en tu `.env` `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con el dominio final.
- Cambia `DB_PASSWORD` por una contraseña real (`openssl rand -base64 24`, por ejemplo) — la de `.env.example` es solo para desarrollo local. Esto **solo tiene efecto en un volumen de Postgres nuevo**: si vas a reutilizar un volumen que ya tenía otra contraseña, Postgres la guarda en sus propios datos y no se actualiza sola al cambiar el `.env`. Para que coincidan, cambia también la contraseña real: `docker compose exec postgres psql -U savepoint -d savepoint -c "ALTER USER savepoint WITH PASSWORD 'nueva_contraseña';"`.

<details>
<summary>Detalle técnico: por qué el arranque automático usa <code>migrate</code> y no <code>migrate:fresh</code></summary>

`docker/entrypoint.sh` ejecuta `php artisan migrate --seed` en cada arranque del contenedor `app`, nunca `migrate:fresh`. En una base de datos vacía (primer arranque de verdad) el resultado es el mismo que `fresh` — crea todas las tablas desde cero —, pero en cualquier arranque posterior con datos ya cargados `migrate` solo aplica lo pendiente y **nunca borra nada**, mientras que `migrate:fresh` habría hecho `DROP` de todas las tablas en cada reinicio del contenedor. `--seed` es seguro de repetir siempre porque `DatabaseSeeder` usa `updateOrCreate` en todas partes. Ver el CHANGELOG del 2026-08-06 para el porqué de este detalle tan concreto.

</details>

## Tests

```bash
docker compose exec app php artisan test
```

El entorno de test es independiente del de desarrollo: `phpunit.xml` fuerza `APP_ENV=testing`, SQLite en memoria, sesión/caché en array, etc., así que correr los tests nunca toca la base Postgres real ni Redis.

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
- Sin HTTPS en el despliegue actual: bloquea el escaneo de código de barras desde el móvil fuera de `localhost` (ver [Desplegar para uso propio](#desplegar-para-uso-propio)).

### Ideas de interfaz/funcionalidad sin priorizar

Grandes:
- Verificación de email / 2FA: el modelo `User` ya tiene `MustVerifyEmail` comentado en el código, pero deliberadamente no se activa — la app la usa una sola persona para su propia colección, así que no aporta nada de seguridad real hoy. Se deja preparado (comentado, no borrado) por si el proyecto se hace público/se forkea y alguien añade más usuarios o lo despliega en abierto.

Medianas:
- Ampliar `App\Services\GameLookup\GameLookupInterface` más allá de CEX (que hoy solo aporta EAN/carátula/plataforma, sin desarrollador ni fecha de lanzamiento en su índice) con un proveedor tipo **IGDB** o **RAWG** para autocompletar también desarrollador y año/fecha de lanzamiento al escanear o buscar.

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