# Cobertura de tests

Detalle de qué prueba cada clase de test de Savepoint, controlador a controlador — separado del
[README](../README.md#tests) para no alargarlo. Se actualiza en la misma PR que añade o cambia tests.

- `Tests\Feature\Auth\WebAuthTest`: login/logout, credenciales inválidas, redirect a la página originalmente solicitada,
  protección de rutas para invitados, bloqueo por fuerza bruta (por email+IP y, rotando de IP en cada intento, por el
  límite adicional solo por email), que el enlace "Regístrate" aparece o no según el registro público esté abierto o
  cerrado, y que el tema (incluido "automático") se mantiene entre las pantallas de login/registro/2FA y la app ya
  autenticada.
- `Tests\Feature\Auth\RegisterTest`: alta con datos válidos (evento `Registered`, contraseña hasheada, cuenta creada con
  2FA activo y sin autenticar todavía), que completar el desafío de 2FA es lo que autentica de verdad y respeta la
  página originalmente solicitada, validación (nombre obligatorio, email válido y único, contraseña con mínimo 8
  caracteres y mayúscula/minúscula/número/símbolo, confirmación), que no se puede escalar a admin desde el formulario,
  invitado vs. usuario ya autenticado, límite de 5 registros/minuto por IP, que el 2FA se puede desactivar después desde
  Ajustes, el enlace desde el login, que el formulario y el propio endpoint quedan bloqueados (con redirect a
  `/login` y aviso) cuando un admin cierra el registro público, que un fallo al enviar el código de 2FA deshace el
  registro en vez de dejar una cuenta huérfana, y que el formulario desactiva el autorrelleno de cuentas guardadas y
  muestra los requisitos de la contraseña.
- `Tests\Feature\Auth\TwoFactorTest`: login con 2FA desactivado sin cambios, login con 2FA activo redirige al desafío en
  vez de autenticar, código correcto/incorrecto/caducado, límites de verificación y reenvío, que reenviar invalida el
  código anterior, "recordar dispositivo" crea la cookie/fila y un login posterior con ella se salta el desafío (uno con
  una cookie desconocida sigue pidiéndolo), el email censurado que muestra la pantalla del desafío, y de seguridad: que
  la cookie de dispositivo de confianza de una cuenta no sirve para saltarse el desafío de otra, y que un `user_id`
  colado a mano en el body de `two-factor.verify` no tiene ningún efecto (siempre sale de la sesión); y que un fallo al
  enviar el código (login o reenvío) muestra un aviso claro en vez de un 500 sin manejar.
- `Tests\Feature\Api\AuthTest`: login/logout vía Sanctum (emisión y revocación de token), `/api/user` protegido (incluye
  que la respuesta nunca expone campos ocultos como `password` o `igdb_client_secret`), bloqueo por fuerza bruta,
  expiración de token (rechazado pasado el límite configurado, aceptado justo antes), token manipulado/de usuario
  borrado rechazado, mensaje de error de login idéntico exista o no el email (sin enumeración de usuarios), email con
  sintaxis de inyección SQL rechazado por validación sin error 500; y el desafío de 2FA completo: `/api/login` no emite
  token en una cuenta con 2FA activo (solo `two_factor_token`), `/api/login/verify-2fa` lo completa con el código
  correcto y lo rechaza con uno incorrecto/caducado o un `two_factor_token` desconocido, un `user_id` colado en el body
  no tiene efecto, `/api/login/resend-2fa` invalida el código anterior, y ambas rutas tienen su propio límite de
  intentos.
- `Tests\Feature\Api\TokenAbilityTest`: cada ruta de la API rechaza un token sin la ability que le corresponde
  (`games:read`/`games:write`/`profile:read`), y `/logout` acepta cualquier token válido.
- `Tests\Feature\SessionCookieSecurityTest`: la cookie de sesión no lleva `Secure` por HTTP plano, pero sí en cuanto la
  petición llega con `X-Forwarded-Proto: https` (simula el proxy inverso de producción).
- `Tests\Feature\Api\GameControllerTest`: CRUD completo de la API, paginación (tamaño por defecto, `per_page` a medida,
  con tope, y tratado como el valor por defecto si no es un entero positivo), filtros (`q`, `platform_id`,
  `play_status`, `status`) incluida una búsqueda con caracteres de inyección SQL sin error ni fuga entre usuarios,
  scoping por usuario y `GamePolicy` bloqueando acceso a juegos ajenos (403 en view/update/delete) o inexistentes (404),
  un `user_id` colado en el payload de alta/edición sin ningún efecto (siempre manda el usuario autenticado), y que
  `status` no admite `sold` (estado derivado, solo asignable desde `SalesController`).
- `Tests\Feature\Web\GameControllerTest`: alta y edición de juegos con subida/reemplazo de carátula real, validación,
  aviso de EAN duplicado (con y sin confirmar), `GamePolicy` aplicada en las rutas web, la ficha de detalle, la edición
  rápida (estado/en venta) por AJAX y por formulario normal, el filtro "en venta", el fragmento que devuelve `index()`
  para peticiones AJAX, el orden/paginación/región/edición por defecto de Ajustes (aplicados solo cuando la URL o el
  formulario no traen un valor explícito), y el autoasignado de fondo desde IGDB al dar de alta con ese ajuste activo;
  el filtro por conservación, el botón "Guardar igualmente" del propio aviso de EAN duplicado, el resaltado en amarillo
  de juegos en mal estado (y que se puede desactivar desde Ajustes), y la vista "solo texto" (botón visible, clase en
  `<html>` según la preferencia guardada).
- `Tests\Feature\Web\GameExportControllerTest`: exportación imprimible/PDF y a CSV, mismos filtros que el listado,
  scoping por usuario, y de regresión, que la vista imprimible es un documento autocontenido sin el layout de la app.
- `Tests\Feature\Web\GameTrashControllerTest`: papelera (listar/restaurar/eliminar definitivamente, buscador/filtro
  propio, con scoping por usuario) y que excluye los juegos vendidos.
- `Tests\Feature\Web\GameBulkActionControllerTest`: acciones en bloque (borrar, cambiar estado de juego, marcar como
  vendido con precio/fecha compartidos) acotadas al usuario autenticado, con validación de los IDs seleccionados.
- `Tests\Feature\Web\GameAutoIdentifyControllerTest`: lanzamiento del job de identificación acotado a plataformas con
  juegos sin carátula, cola de revisión con candidatos por EAN/título, confirmación en bloque que aplica solo lo
  marcado, y aislamiento entre usuarios en cada paso.
- `Tests\Feature\Web\GameCoverLookupControllerTest`: búsqueda de carátula/EAN en CEX tanto para un juego ya guardado
  (por su EAN o su título, o por una búsqueda manual) como para el alta (sin resultados ni llamada a CEX sin `q`,
  requiere sesión iniciada).
- `Tests\Feature\Web\GameImportControllerTest`: importación desde CSV (con/sin BOM, separador coma o punto y coma),
  creación automática de plataformas/ediciones que no existían, filas sin título omitidas y reportadas como incidencia,
  validación del fichero subido, y la vista previa (columnas reconocidas/no reconocidas, filas de ejemplo, que no
  importa nada).
- `Tests\Feature\Web\ManufacturerControllerTest` / `PlatformControllerTest` / `EditionControllerTest`: CRUD de cada
  panel de catálogo acotado a la cuenta autenticada (`PlatformPolicy`/`EditionPolicy`/`ManufacturerPolicy` con 403 en
  vez de 404 al tocar un registro ajeno, listados/desplegables que no enseñan catálogo de otra cuenta), validaciones
  propias (colores en formato hex, nombre único de fabricante por cuenta, colores obligatorios solo si se sobrescriben
  en una plataforma), que borrar un registro deja en `null` la relación en juegos/plataformas en vez de arrastrar el
  borrado, que la edición "Normal" de cada cuenta existe sin ninguna plataforma asociada (disponible para cualquiera),
  y el formato de edición (disco por defecto si no se indica, alta/edición con cualquiera de los subtipos físicos,
  formato inválido — incluido el antiguo "físico" genérico, ya no válido — rechazado). `Tests\Unit\Models\EditionTest`
  cubre la etiqueta/icono de cada formato y el fallback de uno desconocido.
- `Tests\Feature\CatalogPerUserBackfillTest`: la migración que reparte el catálogo compartido en una copia privada por
  cuenta reasigna cada juego y el ajuste de edición por defecto de cada usuario sin dejar ninguna FK rota.
  `Tests\Feature\Services\Catalog\SeedCatalogCopierTest`: la copia del catálogo base a una cuenta nueva es idempotente
  (repetirla no duplica filas) e independiente entre cuentas.
- `Tests\Feature\Web\SearchControllerTest`: búsqueda rápida por título/EAN acotada al usuario autenticado, filtros de
  plataforma/estado de juego/propiedad, sugerencias externas de CEX (no antes de 3 caracteres) tanto sin coincidencia
  local como junto a coincidencias ya existentes (recortadas a un par, sin repetir títulos ya poseídos), el aviso de
  "afina la búsqueda" cuando no hay nada en ningún sitio, y que la lista de deseos aparece o no según el ajuste
  correspondiente.
- `Tests\Feature\Web\PanelControllerTest`: enlaces del panel y contador de la papelera por usuario, y la página de
  Ajustes — guardar cada grupo de preferencias (incluido dejar un valor en blanco para volver al comportamiento por
  defecto), que no afectan a otros usuarios, y el endpoint AJAX de tema/vista de la colección; la Zona de peligro
  (vaciar una plataforma concreta o toda la colección, con y sin confirmación correcta, aislamiento entre usuarios,
  invitados sin acceso).
- `Tests\Feature\Web\ProfileControllerTest`: actualización de nombre/email (con email único), subida/reemplazo/
  eliminación de avatar (con limpieza del fichero anterior en disco), validación del avatar (tipo/tamaño), y cambio de
  contraseña exigiendo la actual y confirmación.
- `Tests\Feature\Web\UserControllerTest`: invitados y usuarios no-admin bloqueados (redirect/403) en todas las rutas de
  gestión de usuarios, listado con nº de juegos por cuenta, alta con contraseña hasheada, validación (email único,
  contraseña mínima y confirmada), edición de nombre/email/rol, cambio de contraseña opcional (en blanco no la toca),
  que un admin no puede quitarse el rol ni borrarse a sí mismo, que no se puede borrar una cuenta con juegos, que el
  registro público está abierto por defecto, y que un admin puede cerrarlo/reabrirlo.
- `Tests\Feature\Web\StatsControllerTest`: los totales y repartos (por plataforma, estado de juego, propiedad, gasto por
  mes y por plataforma, top de géneros, destacados, conservación total y por plataforma, y ventas por año) solo
  consideran los juegos del usuario autenticado.
- `Tests\Feature\Web\SalesControllerTest`: histórico de ventas agrupado por año con sus totales/rendimiento, scoping por
  usuario, deshacer una venta (el juego vuelve a la colección sin datos de venta) y `GamePolicy` bloqueando la
  restauración de una venta ajena, marcar un juego como vendido (validación, envío a la papelera, `GamePolicy`).
- `Tests\Feature\Web\PasswordResetTest`: envío del enlace de reset (mismo mensaje exista o no el email), reset con token
  válido/inválido.
- `Tests\Unit\Models\GameTest` / `PlatformTest`: iniciales y URL de carátula, resolución de colores/etiqueta de chip con
  fallback a fabricante; el color del placeholder de carátula (heredado de la plataforma, o determinista por título sin
  ella) y qué juegos cuentan como "mal estado" para el aviso amarillo.
