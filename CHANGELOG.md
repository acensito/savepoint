# Changelog

Historial de cambios de Savepoint, más reciente primero. Antes vivía como una
sección al final de `README.md`; se separó a este fichero para que el README
pueda ser un documento de presentación del proyecto en vez de una lista que
crece sin parar.

## 2026-08-06
- **Toast flotante**: arreglado el solape con la cabecera — el contenedor usaba `top-4`, pero el header no es `position: fixed`, así que el toast quedaba encima de sus iconos (buscador, tema, perfil, salir) en vez de aparecer debajo. En móvil, donde el toast ocupa casi todo el ancho, esto lo hacía tapar directamente el menú hamburguesa y el logo. Reposicionado a `top-16` para que quede siempre bajo el header, en escritorio y en móvil.
- **Toast flotante**: cambiado el fondo translúcido (`bg-emerald-500/10` / `bg-red-500/10`) por uno sólido (`bg-slate-900`, la misma superficie que usa el resto de la app): el contenido de la página se transparentaba a través del aviso y lo volvía difícil de leer, sobre todo en móvil al solaparse con el título de la página.
- Quitados los iconos de editar/borrar de las **tarjetas de la colección en móvil**, mismo criterio aplicado ya a la tabla de escritorio (ver 2026-08-05): esas acciones quedan relegadas a la ficha de detalle de cada juego, que ya las tenía. La edición rápida de conservación/estado de juego (clic en una estrella o en el estado) se mantiene sin cambios.
- Documentación: separado el changelog del README a este fichero; el README se reorganiza como documento de presentación (descripción, características, despliegue, contribución) en vez de mezclar eso con el historial de cambios.

## 2026-08-05
- **Sugerencias de CEX en la búsqueda rápida**: cuando un juego escaneado/buscado no está en la colección, se consulta el catálogo de CEX (webuy.com, vía su índice de Algolia — no es una API oficial) y se ofrecen sus resultados con EAN y carátula reales; al elegir uno se ve una ficha de comprobación y un botón "Dar de alta" que prellena el formulario de siempre. Implementado detrás de `App\Services\GameLookup\GameLookupInterface` para poder cambiar de proveedor sin tocar el controlador ni la vista si CEX deja de funcionar. La carátula se descarga solo al guardar el alta y solo desde hosts en lista blanca (protección SSRF). Depurado un 403 intermitente: `search.webuy.io` está detrás de Cloudflare y bloqueaba el User-Agent por defecto de Guzzle/Laravel (identificado como librería HTTP genérica) aunque curl o un navegador sí pasaban; se soluciona mandando un User-Agent propio que identifica la app.
- Sección de **lista de deseos** (`/wishlist`, enlazada desde el sidebar): página propia para los juegos con Propiedad = "Lista de deseos", que **ya nunca aparecen en la colección principal** ni en sus totales. Alta reducida (`/wishlist/create`: solo título/plataforma/edición), prioridad/precio estimado/dónde comprarlo (nuevos campos del juego, editables desde el formulario de edición completo), buscador/orden propios y acción "Pasar a la colección" que abre el formulario de edición de siempre con Propiedad y fecha de compra preseleccionadas a "En colección"/hoy.
- Búsqueda rápida global (Ctrl+K / Cmd+K, `/search/quick`): abre un `<dialog>` centrado ("spotlight", más cerca de arriba que el resto de diálogos) con resultados en vivo por título/EAN mientras se escribe; Enter abre el primer resultado, click abre cualquier otro. Los resultados son enlaces normales a la ficha del juego.
- Rediseño visual de la ficha de detalle de un juego (`/games/{id}`): los campos pasan de una rejilla plana a "tarjetas" agrupadas en dos secciones (Detalles / Compra) con icono por campo, carátula más grande con sombra y notas en un bloque destacado.
- Arreglado un bug de aislamiento de tests que llegó a machacar la base de datos real de desarrollo (usuarios, colección y catálogo a cero): los contenedores `app`/`queue` habían quedado arrancados con variables de entorno de Postgres "grabadas" desde antes de quitar `env_file` de `docker-compose.yml` (Docker no relee `env_file` de un contenedor ya corriendo, solo al recrearlo), así que `php artisan test` seguía viendo `DB_CONNECTION=pgsql` pese a que `phpunit.xml` fuerza SQLite en memoria. Se soluciona recreando los contenedores (`docker compose up -d --force-recreate app queue`) tras cualquier cambio en `env_file`/`environment` del compose.
- Corregido `.env.example`: traía los valores por defecto del scaffold de Laravel (sqlite, sin locale es, sin redis) en vez de los que el proyecto necesita (Postgres/Redis del `docker-compose.yml`, `APP_LOCALE=es`, `QUEUE_CONNECTION=redis`).
- Escaneo de código de barras (EAN) con la cámara desde la búsqueda rápida (`@zxing/library`): si el código no coincide con ningún juego de la colección, propone darlo de alta con el EAN ya relleno en vez de solo decir "sin resultados".
- Quitada la columna "Estado" (pendiente/jugando/terminado) de la tabla de escritorio: en portátiles con pantalla 1080p no cabía entera y obligaba a hacer scroll horizontal. Sigue disponible como filtro, en las tarjetas de móvil y en la ficha de detalle.
- Reducidas las estrellas de conservación de la tabla de escritorio (13px por defecto → 12px → 10px, a gusto): era el único sitio de la app donde `<x-star-rating>` no fijaba un tamaño explícito.
- Quitada también la columna "Acciones" (Editar/Borrar) de la tabla de escritorio: esas acciones quedan relegadas a la ficha de detalle de cada juego, que ya las tenía.
- La conservación (estrellas) deja de ser editable con un clic en la tabla de escritorio: ahora es de solo lectura ahí, se cambia desde el formulario de edición. Las tarjetas de móvil y la estantería mantienen la edición rápida.

## 2026-08-02
- Campo "Valoración" renombrado a "Conservación" en toda la interfaz (tabla, tarjetas, orden, formulario de alta/edición, estadísticas) y en la importación CSV (columna de la plantilla y del importador): refleja mejor su uso real como estado físico de conservación de la copia, no una valoración subjetiva del juego. Las palabras que aparecían al pasar el ratón por las estrellas del formulario ("Excelente", "Aceptable"...) también se actualizaron a la misma escala (Malo/Regular/Bueno/Muy bueno/Nuevo o precintado).
- Rediseño del buscador de la colección: pasa a ser grande, a todo el ancho y visible siempre igual en cualquier tamaño de pantalla (antes, en móvil, vivía plegado tras un acordeón "Buscar y filtrar"); los iconos de cambio de vista y de modo selección se agrupan aparte, más pequeños, en la esquina superior derecha justo encima de la tabla/tarjetas.
- Vista compacta como vista por defecto de la colección la primera vez que se entra sin preferencia guardada (antes era la habitual de tarjetas/tabla).
- Arreglado un bug de variables CSS circulares en el tema claro (`app.css`): dos variables de la paleta que se referenciaban entre sí en direcciones opuestas quedaban invalidadas por CSS en vez de tomar el valor esperado, dejando el sidebar y los bordes de tarjetas/tablas transparentes (se veía el morado de fondo del `body` a través). De paso, se rebajó la saturación de indigo/red en el tema claro para que no resultara tan vivo sobre fondo blanco.
- Arreglado el botón de vista de estantería en móvil: al pulsarlo una segunda vez no volvía a las tarjetas (solo alternaba a estantería una y otra vez), porque los botones para volver a la vista habitual están ocultos en móvil.
- Filtros en la API (`GET /api/games`): mismos parámetros que el listado web (`q`, `platform_id`, `play_status`, `status`), para desbloquear el futuro cliente móvil.
- Recuperación de contraseña (`/forgot-password`, `/reset-password/{token}`): flujo estándar de Laravel con token de un solo uso, mismo mensaje de éxito exista o no el email.
- Importación masiva de la colección desde CSV (`/games/import`): solo el título es obligatorio, fila a fila (una fila con error no bloquea el resto), plataformas/ediciones nuevas se crean automáticamente, plantilla de ejemplo descargable y resumen de resultado tras importar.
- Paneles de catálogo (fabricantes, plataformas, ediciones) con vista de tarjetas en móvil, igual que el listado principal, en vez de solo scroll horizontal en la tabla.
- Tests: paneles de catálogo, perfil de usuario, estadísticas, recuperación de contraseña, importación CSV y los nuevos filtros de la API.
- Arreglado el centrado de los `<dialog>` (confirmación de borrado, alta rápida de edición): salían pegados a la esquina superior izquierda por un choque entre el preflight de Tailwind y el centrado nativo del navegador.
- Aviso de EAN duplicado al dar de alta o editar un juego, con opción de guardar de todos modos; nunca salta con juegos sin EAN.
- Buscador y filtro por plataforma en la papelera, paginación configurable en el listado web (10/20/50/100) y botón "Deshacer" en el toast al enviar un juego a la papelera.
- Vista compacta de la tabla (junto a tarjetas/tabla habitual y estantería) y estética renovada de las tarjetas de la colección en móvil.
- Casillas de selección de la colección ocultas por defecto, solo visibles en "modo selección" (botón junto a "Añadir Juego").
- Barra de estado discreta al pie de la colección con el total de juegos y el gasto invertido en toda la colección.
- Tests de todo lo anterior (122 tests en total).
- Tema claro/oscuro con botón en el header (y en login/recuperar contraseña), persistido en `localStorage`.
- Orden del listado de la colección por título, precio, valoración o fecha de compra.
- Atajo de teclado `/` para enfocar el buscador de la colección.
- Acciones en bloque en la colección: seleccionar varios juegos y enviarlos a la papelera o cambiarles el estado de juego de golpe.
- Botón flotante de "Añadir juego" en móvil.
- Vista de estantería (grid de carátulas grandes) como alternativa al listado habitual.
- Tests de las acciones en bloque y de la ordenación del listado (110 tests en total).
- Ficha de detalle de solo lectura por juego (`/games/{id}`), enlazada desde el título en cualquier vista de la colección.
- Edición rápida de valoración y estado de juego desde la propia fila/tarjeta, por AJAX.
- Vista previa del CSV antes de importar: columnas reconocidas/no reconocidas y primeras filas, sin importar nada todavía.
- Buscador de la colección rediseñado: simple y con filtrado en vivo por defecto (AJAX, sin recargar), con un botón "Avanzado" para desplegar plataforma/estado/orden/paginación cuando hacen falta.
- Estadísticas ampliadas: evolución del gasto por mes, top de géneros y destacados (juego más caro y mejor valorado).
- Tests de todo lo anterior (135 tests en total).

## 2026-08-01
- Papelera de reciclaje con interfaz (`/games/trash`): restaurar o eliminar definitivamente un juego borrado (con limpieza de la carátula en disco).
- Paginación en `GET /api/games` (20 por página, `?per_page=` hasta 100).
- Toast flotante para los mensajes de éxito (sustituye los banners fijos repetidos por vista, que en la colección principal ni siquiera existían) y modal de confirmación propio para las acciones destructivas (sustituye el `confirm()` nativo del navegador en juegos, papelera, plataformas, fabricantes y ediciones).
- Protección contra fuerza bruta en el login (web y API): bloqueo de 60s tras 5 intentos fallidos, por email+IP, con contador compartido vía `ThrottlesLogins`.
- Alta de juego: "Propiedad" renombrada a "En colección" (valor por defecto al crear), retirado el campo "Condición física", nuevos valores de "Manual" (Con Manual/Sin Manual/Folleto, con color según si falta o no) y añadida la región PAL-EU.
- Carátulas: el preview y el listado respetan la proporción real de la imagen (ancho fijo, alto automático) en vez de recortar a cuadrado; arreglada la Content-Security-Policy de nginx, que bloqueaba el preview en vivo del alta.
- Creación de ediciones al vuelo desde el propio formulario de alta/edición de juego (modal + AJAX), sin perder los datos ya rellenados; botones "Seleccionar todas"/"Ninguna" en el panel de ediciones.
- Mensajes de validación traducidos al español (antes mostraban la clave sin traducir, p. ej. `validation.required`).

## 2026-07-31
- Interfaz responsive/móvil: navegación en drawer con hamburguesa, listado principal en tarjetas con buscador/filtros colapsables, formularios apilados en pantallas estrechas.
- Cobertura de tests: autenticación web y API (Sanctum), CRUD de la API con `GamePolicy`, alta/edición de juegos con carátula real.
- Corregido el entorno de tests: por un problema de configuración de Docker (`env_file` duplicando lo que ya carga Laravel) llegó a ejecutarse contra la base de datos real de desarrollo en vez de SQLite en memoria.
