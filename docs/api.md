# Documentación de la API REST de Savepoint

Savepoint expone una API REST construida sobre Laravel 13 y autenticada mediante Laravel Sanctum. Esta API está diseñada
para permitir el consumo de la colección de videojuegos desde clientes externos (como aplicaciones móviles, scripts de
automatización o clientes de escritorio).

---

## Índice

1. [Información general](#1-información-general)
    - [URL base](#url-base)
    - [Cabeceras requeridas](#cabeceras-requeridas)
    - [Autenticación y ciclo de vida del token](#autenticación-y-ciclo-de-vida-del-token)
2. [Endpoints de Autenticación](#2-endpoints-de-autenticación)
    - [Iniciar sesión (`POST /api/login`)](#iniciar-sesión-post-apilogin)
    - [Verificar el código de 2FA (`POST /api/login/verify-2fa`)](#verificar-el-código-de-2fa-post-apiloginverify-2fa)
    - [Reenviar el código de 2FA (`POST /api/login/resend-2fa`)](#reenviar-el-código-de-2fa-post-apiloginresend-2fa)
    - [Cerrar sesión (`POST /api/logout`)](#cerrar-sesión-post-apilogout)
3. [Usuario autenticado (`GET /api/user`)](#3-usuario-autenticado-get-apiuser)
4. [Gestión de Juegos (`/api/games`)](#4-gestión-de-juegos-apigames)
    - [Listar juegos con filtros y paginación (`GET /api/games`)](#listar-juegos-con-filtros-y-paginación-get-apigames)
    - [Ver detalle de un juego (`GET /api/games/{id}`)](#ver-detalle-de-un-juego-get-apigamesid)
    - [Crear un juego (`POST /api/games`)](#crear-un-juego-post-apigames)
    - [Actualizar un juego (`PUT/PATCH /api/games/{id}`)](#actualizar-un-juego-putpatch-apigamesid)
    - [Eliminar un juego (`DELETE /api/games/{id}`)](#eliminar-un-juego-delete-apigamesid)
5. [Estructura del recurso `GameResource`](#5-estructura-del-recurso-gameresource)
6. [Códigos de estado y respuestas de error](#6-códigos-de-estado-y-respuestas-de-error)
7. [Notas técnicas y particularidades](#7-notas-técnicas-y-particularidades)

---

## 1. Información general

### URL base

Todas las rutas de la API están prefijadas con `/api`:

```text
http://localhost:8000/api
https://tu-dominio-savepoint.com/api
```

### Cabeceras requeridas

Todas las peticiones a la API deben incluir las siguientes cabeceras HTTP:

| Cabecera        | Valor                   | Descripción                                                  |
|:----------------|:------------------------|:-------------------------------------------------------------|
| `Accept`        | `application/json`      | Obligatoria para recibir siempre respuestas en formato JSON. |
| `Content-Type`  | `application/json`      | Requerida en peticiones con cuerpo (`POST`, `PUT`, `PATCH`). |
| `Authorization` | `Bearer <access_token>` | Requerida en todas las rutas protegidas.                     |

> **Nota:** La aplicación configura el renderizado del contrato de errores en `bootstrap/app.php` únicamente para
> peticiones con prefijo `/api/*`. Las rutas web mantienen sus respuestas HTML y su comportamiento habitual.

### Autenticación y ciclo de vida del token

La autenticación utiliza tokens de acceso personal de **Laravel Sanctum** emitidos con el nombre `MobileApp`.

- **Emisión:** Al realizar un login satisfactorio en `/api/login` (o, si la cuenta tiene 2FA activo, al completar el
  desafío en `/api/login/verify-2fa`).
- **Expiración:** Los tokens tienen una duración configurada en `config/sanctum.php` mediante la variable de entorno
  `SANCTUM_TOKEN_EXPIRATION_MINUTES` (por defecto **30 días** / 43.200 minutos). Transcurrido este tiempo sin renovar,
  cualquier petición devolverá `401 Unauthorized`.
- **Revocación:** Al llamar a `/api/logout`, se revoca y elimina exclusivamente el token utilizado en la petición
  actual.

### Límite general de peticiones

Todas las rutas `/api/*` están sujetas a un límite de **120 peticiones/minuto** (`throttle:api`, activado en
`bootstrap/app.php`), por usuario autenticado o por IP mientras no se tenga token todavía. Superarlo devuelve
`429 Too Many Requests`. `/api/login` y el desafío de 2FA tienen además sus propios límites, más estrictos y específicos
(ver sus secciones).

---

## 2. Endpoints de Autenticación

### Iniciar sesión (`POST /api/login`)

Autentica las credenciales de un usuario. Es una ruta pública.

- **Si la cuenta no tiene 2FA activo:** devuelve el token de acceso Bearer directamente (200 OK, ver más abajo).
- **Si la cuenta tiene 2FA por email activo** (toda cuenta nueva lo lleva activo desde el registro): **no** emite ningún
  token todavía. Manda un código de 6 dígitos por email y devuelve un `two_factor_token` de un solo uso (válido 10
  minutos) que hay que canjear junto con el código en `POST /api/login/verify-2fa` para completar el login y recibir el
  token de acceso.

#### Control de intentos y protección de fuerza bruta (Rate Limiting)

El endpoint implementa limitación de tasa por partida doble:

1. **Límite por Email + IP:** Máximo **5 intentos fallidos en 60 segundos**.
2. **Límite global por Email:** Máximo **10 intentos fallidos en 300 segundos** (evita ataques distribuidos rotando de
   IP).

Superar cualquiera de estos umbrales bloquea peticiones posteriores y devuelve `429 Too Many Requests`.

#### Parámetros del cuerpo (JSON)

| Campo      | Tipo     | Obligatorio | Descripción                                            |
|:-----------|:---------|:------------|:-------------------------------------------------------|
| `email`    | `string` | **Sí**      | Correo electrónico del usuario (formato email válido). |
| `password` | `string` | **Sí**      | Contraseña del usuario.                                |

#### Ejemplo de petición

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@ejemplo.com",
    "password": "mi_password_segura"
  }'
```

#### Respuestas

##### 200 OK — Login exitoso (cuenta sin 2FA)

```json
{
    "message": "Login exitoso",
    "access_token": "1|q6jK9x7gZ8K...plainTextToken...",
    "token_type": "Bearer"
}
```

##### 200 OK — 2FA requerido (cuenta con 2FA activo)

Sin `access_token`: hay que completar el desafío en `/api/login/verify-2fa`.

```json
{
    "message": "Te hemos enviado un código de verificación por email.",
    "two_factor_required": true,
    "two_factor_token": "K9x7gZ8K...64 caracteres aleatorios..."
}
```

##### 401 Unauthorized — Credenciales incorrectas

```json
{
    "code": "INVALID_CREDENTIALS",
    "status": 401,
    "message": "Credenciales incorrectas"
}
```

##### 422 Unprocessable Content — Error de validación

Los mensajes de validación se localizan según `APP_LOCALE` (`es` por defecto en `.env.example`).

```json
{
    "code": "VALIDATION_ERROR",
    "status": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
        "email": [
            "El campo email es obligatorio."
        ],
        "password": [
            "El campo contraseña es obligatorio."
        ]
    }
}
```

##### 429 Too Many Requests — Límite de intentos superado

```json
{
    "code": "RATE_LIMIT_EXCEEDED",
    "status": 429,
    "message": "Demasiados intentos de acceso. Inténtalo de nuevo en 58 segundos."
}
```

##### 503 Service Unavailable — Fallo al enviar el código de 2FA

Solo si la cuenta tiene 2FA activo y el envío del email falla (SMTP caído, credenciales mal puestas...). No se devuelve
ningún `two_factor_token`: hay que reintentar `/api/login` más tarde.

```json
{
    "code": "SERVICE_UNAVAILABLE",
    "status": 503,
    "message": "Error. Por favor, inténtalo más tarde y, si el problema persiste, comunícaselo al administrador."
}
```

---

### Verificar el código de 2FA (`POST /api/login/verify-2fa`)

Canjea el `two_factor_token` devuelto por `/api/login` junto con el código de 6 dígitos recibido por email, para
completar el login y emitir el token de acceso. Es una ruta pública (todavía no hay usuario autenticado en este punto).

#### Control de intentos (Rate Limiting)

Máximo **5 intentos en 10 minutos**, por cuenta (no por `two_factor_token`: pedir `/api/login` otra vez no reinicia el
contador). Superarlo devuelve `429 Too Many Requests`.

#### Parámetros del cuerpo (JSON)

| Campo              | Tipo     | Obligatorio | Descripción                                         |
|:-------------------|:---------|:------------|:----------------------------------------------------|
| `two_factor_token` | `string` | **Sí**      | El devuelto por `/api/login` al iniciar el desafío. |
| `code`             | `string` | **Sí**      | Código de 6 dígitos recibido por email.             |

#### Ejemplo de petición

```bash
curl -X POST http://localhost:8000/api/login/verify-2fa \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
    "two_factor_token": "K9x7gZ8K...64 caracteres aleatorios...",
    "code": "123456"
  }'

```

#### Respuestas

##### 200 OK — Login completado

Misma forma que el login exitoso sin 2FA:

```json
{
    "message": "Login exitoso",
    "access_token": "1|q6jK9x7gZ8K...plainTextToken...",
    "token_type": "Bearer"
}
```

##### 401 Unauthorized — Código incorrecto o caducado

```json
{
    "code": "INVALID_TWO_FACTOR_CODE",
    "status": 401,
    "message": "Código incorrecto o caducado."
}
```

##### 401 Unauthorized — `two_factor_token` desconocido, caducado o ya usado

```json
{
    "code": "TWO_FACTOR_CHALLENGE_EXPIRED",
    "status": 401,
    "message": "La verificación ha caducado o no es válida. Vuelve a iniciar sesión."
}
```

##### 429 Too Many Requests — Límite de intentos superado

```json
{
    "code": "RATE_LIMIT_EXCEEDED",
    "status": 429,
    "message": "Demasiados intentos de acceso. Inténtalo de nuevo más tarde."
}
```

---

### Reenviar el código de 2FA (`POST /api/login/resend-2fa`)

Genera un código nuevo (invalida el anterior) y lo reenvía, para el mismo desafío pendiente. Ruta pública.

#### Control de intentos (Rate Limiting)

Máximo **3 intentos en 5 minutos**, por cuenta (mismo criterio que verify-2fa).

#### Parámetros del cuerpo (JSON)

| Campo              | Tipo     | Obligatorio | Descripción                                         |
|:-------------------|:---------|:------------|:----------------------------------------------------|
| `two_factor_token` | `string` | **Sí**      | El devuelto por `/api/login` al iniciar el desafío. |

#### Ejemplo de petición

```bash
curl -X POST http://localhost:8000/api/login/resend-2fa \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"two_factor_token": "K9x7gZ8K...64 caracteres aleatorios..."}'
```

#### Respuestas

##### 200 OK

```json
{
    "message": "Te hemos enviado un código nuevo."
}
```

##### 401 Unauthorized — `two_factor_token` desconocido o caducado

```json
{
    "code": "TWO_FACTOR_CHALLENGE_EXPIRED",
    "status": 401,
    "message": "La verificación ha caducado o no es válida. Vuelve a iniciar sesión."
}
```

##### 429 Too Many Requests — Límite de intentos superado

```json
{
    "code": "RATE_LIMIT_EXCEEDED",
    "status": 429,
    "message": "Demasiados intentos de acceso. Inténtalo de nuevo más tarde."
}
```

---

### Cerrar sesión (`POST /api/logout`)

Revoca y destruye el token de acceso personal con el que se realizó la petición. Requiere autenticación
(`auth:sanctum`).

#### Ejemplo de petición

```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..."
```

#### Respuestas

##### 200 OK — Sesión cerrada

```json
{
    "message": "Sesión cerrada correctamente"
}
```

##### 401 Unauthorized — Token no proporcionado o inválido

```json
{
    "code": "UNAUTHENTICATED",
    "status": 401,
    "message": "No autenticado."
}
```

---

## 3. Usuario autenticado (`GET /api/user`)

Devuelve la información y preferencias de la cuenta del usuario autenticado. Requiere autenticación (`auth:sanctum`).

> **Nota sobre el formato:** Este endpoint devuelve el modelo Eloquent `User` serializado directamente (no utiliza un
> API Resource). Los campos confidenciales como `password`, `remember_token`, `igdb_client_secret` y `two_factor_code`
> están ocultos. El accessor `avatarUrl()` no está presente en `$appends`, por lo que se devuelve la ruta relativa en
> `avatar_path`.

#### Ejemplo de petición

```bash
curl -X GET http://localhost:8000/api/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..."
```

#### Respuesta 200 OK

```json
{
    "id": 1,
    "name": "Andrés Podadera",
    "email": "andres@ejemplo.com",
    "is_admin": true,
    "theme": "dark",
    "games_view": "grid",
    "navbar_color": "#1e293b",
    "default_sort": "title",
    "default_dir": "asc",
    "default_per_page": 20,
    "default_region": "PAL-ESP",
    "default_edition_id": 1,
    "quick_search_exclude_wishlist": false,
    "hide_for_sale_from_collection": false,
    "auto_igdb_background": true,
    "igdb_enabled": true,
    "igdb_client_id": "mi_client_id_igdb",
    "avatar_path": "avatars/user_1.png",
    "two_factor_enabled": false,
    "two_factor_code_expires_at": null,
    "email_verified_at": "2026-08-01T12:00:00.000000Z",
    "created_at": "2026-08-01T10:00:00.000000Z",
    "updated_at": "2026-08-24T18:30:00.000000Z"
}
```

---

## 4. Gestión de Juegos (`/api/games`)

Todos los endpoints de juegos están protegidos con `auth:sanctum` y aplican aislamiento por usuario: las operaciones
solo actúan sobre los juegos propiedad del usuario autenticado.

---

### Listar juegos con filtros y paginación (`GET /api/games`)

Devuelve una colección paginada de los juegos pertenecientes al usuario autenticado, ordenados por fecha de creación
descendente (`latest()`). Carga de forma impaciente (*eager loading*) la relación con `platform`.

#### Parámetros de consulta (Query Parameters — Opcionales)

| Parámetro     | Tipo      | Descripción                                                                                                            |
|:--------------|:----------|:-----------------------------------------------------------------------------------------------------------------------|
| `q`           | `string`  | Búsqueda parcial e insensible a mayúsculas en el título (`title`), o coincidencia exacta por código de barras (`ean`). |
| `platform_id` | `integer` | Filtra por el ID de la plataforma.                                                                                     |
| `play_status` | `string`  | Filtra por estado de juego: `pending`, `playing`, `finished`.                                                          |
| `status`      | `string`  | Filtra por estado en la colección: `owned` (en colección), `wishlist` (en lista de deseos).                            |
| `per_page`    | `integer` | Número de resultados por página. Por defecto `20`, tope máximo de `100`.                                               |
| `page`        | `integer` | Número de página a consultar (por defecto `1`).                                                                        |

#### Ejemplo de petición

```bash
curl -X GET "http://localhost:8000/api/games?q=Knight&play_status=playing&per_page=10" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..."
```

#### Respuesta 200 OK

```json
{
    "data": [
        {
            "id": 42,
            "title": "Hollow Knight",
            "ean": "0814249018440",
            "cover_url": "http://localhost:8000/storage/covers/hollow_knight.jpg",
            "status": "owned",
            "for_sale": false,
            "play_status": "playing",
            "platform": "Nintendo Switch",
            "genres": [
                "Metroidvania",
                "Plataformas",
                "Aventura"
            ],
            "rating": 5,
            "release_date": "2018-06-12"
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/games?page=1",
        "last": "http://localhost:8000/api/games?page=3",
        "prev": null,
        "next": "http://localhost:8000/api/games?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "links": [
            {
                "url": null,
                "label": "&laquo; Anterior",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/games?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": "http://localhost:8000/api/games?page=2",
                "label": "2",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/games?page=3",
                "label": "3",
                "active": false
            },
            {
                "url": "http://localhost:8000/api/games?page=2",
                "label": "Siguiente &raquo;",
                "active": false
            }
        ],
        "path": "http://localhost:8000/api/games",
        "per_page": 10,
        "to": 10,
        "total": 25
    }
}
```

---

### Ver detalle de un juego (`GET /api/games/{id}`)

Obtiene el detalle de un juego concreto. Carga la relación con `platform`.

#### Autorización y control de acceso

- **Propietario:** Puede consultar el juego (200 OK).
- **Otro usuario:** Devuelve `403 Forbidden` (`No autorizado para realizar esta acción.`).
- **Inexistente o eliminado (Soft Delete):** Devuelve `404 Not Found`.

#### Ejemplo de petición

```bash
curl -X GET http://localhost:8000/api/games/42 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..."
```

#### Respuesta 200 OK

```json
{
    "data": {
        "id": 42,
        "title": "Hollow Knight",
        "ean": "0814249018440",
        "cover_url": "http://localhost:8000/storage/covers/hollow_knight.jpg",
        "status": "owned",
        "for_sale": false,
        "play_status": "playing",
        "platform": "Nintendo Switch",
        "genres": [
            "Metroidvania",
            "Plataformas",
            "Aventura"
        ],
        "rating": 5,
        "release_date": "2018-06-12"
    }
}
```

---

### Crear un juego (`POST /api/games`)

Registra un nuevo juego en la colección del usuario autenticado (`user_id` asignado automáticamente en el servidor).

#### Parámetros del cuerpo (JSON — Validados por `StoreGameRequest`)

| Campo          | Tipo      | Obligatorio   | Reglas y valores permitidos                                                                                            |
|:---------------|:----------|:--------------|:-----------------------------------------------------------------------------------------------------------------------|
| `title`        | `string`  | **Sí**        | Máximo 255 caracteres. Mensaje de error personalizado si falta: *"El título del juego es obligatorio."*                |
| `platform_id`  | `integer` | **Sí**        | Debe existir en la tabla `platforms`. Mensaje personalizado si no existe: *"La plataforma seleccionada no es válida."* |
| `status`       | `string`  | No (nullable) | Valores permitidos: `owned`, `wishlist`.                                                                               |
| `play_status`  | `string`  | No (nullable) | Valores permitidos: `pending`, `playing`, `finished`.                                                                  |
| `release_date` | `string`  | No (nullable) | Fecha válida (`YYYY-MM-DD`).                                                                                           |
| `genres`       | `array`   | No (nullable) | Array de strings (nombres de géneros).                                                                                 |
| `rating`       | `integer` | No (nullable) | Valor entero entre `1` y `5` (`Game::RATING_MIN` y `Game::RATING_MAX`).                                                |
| `price_paid`   | `numeric` | No (nullable) | Número decimal mayor o igual a `0`. *(Ver nota: no se devuelve en la respuesta).*                                      |

#### Ejemplo de petición

```bash
curl -X POST http://localhost:8000/api/games \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..." \
  -d '{
    "title": "Celeste",
    "platform_id": 3,
    "status": "owned",
    "play_status": "finished",
    "release_date": "2018-01-25",
    "genres": ["Plataformas", "Indie"],
    "rating": 5,
    "price_paid": 19.99
  }'
```

#### Respuestas

##### 201 Created

> **Nota:** La respuesta de creación no carga la relación `platform`, por lo que el atributo `platform` no aparecerá en
> el objeto `data`.

```json
{
    "data": {
        "id": 43,
        "title": "Celeste",
        "ean": null,
        "cover_url": null,
        "status": "owned",
        "for_sale": false,
        "play_status": "finished",
        "genres": [
            "Plataformas",
            "Indie"
        ],
        "rating": 5,
        "release_date": "2018-01-25"
    }
}
```

##### 422 Unprocessable Content — Error de validación

```json
{
    "code": "VALIDATION_ERROR",
    "status": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
        "title": [
            "El título del juego es obligatorio."
        ],
        "platform_id": [
            "La plataforma seleccionada no es válida."
        ]
    }
}
```

---

### Actualizar un juego (`PUT/PATCH /api/games/{id}`)

Actualiza uno o varios campos de un juego existente. Permite actualización parcial (los campos no enviados no se
modifican).

#### Autorización y control de acceso

- **Propietario:** Puede modificar el juego.
- **Otro usuario:** Devuelve `403 Forbidden` (`No autorizado para realizar esta acción.`).
- **Inexistente o eliminado:** Devuelve `404 Not Found`.

#### Parámetros del cuerpo (JSON — Validados por `UpdateGameRequest`)

Las reglas son idénticas a la creación, pero `title` y `platform_id` aplican la regla `sometimes|required`, permitiendo
enviar únicamente los campos a modificar.

| Campo          | Tipo      | Obligatorio            | Reglas y valores permitidos                           |
|:---------------|:----------|:-----------------------|:------------------------------------------------------|
| `title`        | `string`  | Opcional (`sometimes`) | Requerido si se incluye. Máx. 255 caracteres.         |
| `platform_id`  | `integer` | Opcional (`sometimes`) | Requerido si se incluye. Debe existir en `platforms`. |
| `status`       | `string`  | No (nullable)          | `owned`, `wishlist`.                                  |
| `play_status`  | `string`  | No (nullable)          | `pending`, `playing`, `finished`.                     |
| `release_date` | `string`  | No (nullable)          | Fecha válida (`YYYY-MM-DD`).                          |
| `genres`       | `array`   | No (nullable)          | Array de strings.                                     |
| `rating`       | `integer` | No (nullable)          | Entero entre `1` y `5`.                               |
| `price_paid`   | `numeric` | No (nullable)          | Número decimal `>= 0`.                                |

#### Ejemplo de petición

```bash
curl -X PATCH http://localhost:8000/api/games/43 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..." \
  -d '{
    "play_status": "playing",
    "rating": 4
  }'
```

#### Respuesta 200 OK

> **Nota:** La respuesta no vuelve a cargar la relación `platform`, por lo que el atributo `platform` no aparecerá en el
> objeto `data`.

```json
{
    "data": {
        "id": 43,
        "title": "Celeste",
        "ean": null,
        "cover_url": null,
        "status": "owned",
        "for_sale": false,
        "play_status": "playing",
        "genres": [
            "Plataformas",
            "Indie"
        ],
        "rating": 4,
        "release_date": "2018-01-25"
    }
}
```

---

### Eliminar un juego (`DELETE /api/games/{id}`)

Aplica un borrado lógico (*Soft Delete*) sobre el juego (`deleted_at` toma la fecha actual). El registro no se borra
físicamente de la base de datos PostgreSQL, pero deja de ser accesible en todas las consultas de la API.

#### Autorización y control de acceso

- **Propietario:** Puede eliminar el juego.
- **Otro usuario:** Devuelve `403 Forbidden` (`No autorizado para realizar esta acción.`).
- **Inexistente o ya eliminado:** Devuelve `404 Not Found`.

#### Ejemplo de petición

```bash
curl -X DELETE http://localhost:8000/api/games/43 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|q6jK9x7gZ8K..."
```

#### Respuesta 200 OK

```json
{
    "message": "Juego eliminado correctamente"
}
```

---

## 5. Estructura del recurso `GameResource`

El objeto `GameResource` transforma el modelo `Game` en la representación JSON pública:

| Campo          | Tipo           | Nullable    | Descripción                                                                                                                                                    |
|:---------------|:---------------|:------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `id`           | `integer`      | No          | Identificador único del juego.                                                                                                                                 |
| `title`        | `string`       | No          | Título del juego.                                                                                                                                              |
| `ean`          | `string`       | **Sí**      | Código de barras europeo (EAN) o null.                                                                                                                         |
| `cover_url`    | `string` (URL) | **Sí**      | URL completa hacia la imagen de portada almacenada en el disco público (`url('storage/' . cover)`), o `null` si no tiene portada.                              |
| `status`       | `string`       | **Sí**      | Estado en la colección: `'owned'` (en colección) o `'wishlist'` (en lista de deseos).                                                                          |
| `for_sale`     | `boolean`      | No          | Indica si el juego está marcado para la venta (`true`/`false`).                                                                                                |
| `play_status`  | `string`       | **Sí**      | Estado de juego: `'pending'`, `'playing'`, `'finished'` o `null`.                                                                                              |
| `platform`     | `string`       | Condicional | Nombre de la plataforma (ej. `"Nintendo Switch"`, `"PlayStation 5"`). **Solo se incluye cuando la relación `platform` ha sido cargada** (en `index` y `show`). |
| `genres`       | `array`        | **Sí**      | Lista de nombres de géneros como array de strings, o `null`.                                                                                                   |
| `rating`       | `integer`      | **Sí**      | Calificación personal del 1 al 5 (`Game::RATING_MIN` a `Game::RATING_MAX`), o `null`.                                                                          |
| `release_date` | `string`       | **Sí**      | Fecha de lanzamiento con formato `'YYYY-MM-DD'`, o `null`.                                                                                                     |

---

## 6. Códigos de estado y respuestas de error

Todas las respuestas de error de `/api/*` tienen esta forma común:

```json
{
    "code": "VALIDATION_ERROR",
    "status": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
        "email": [
            "El campo email es obligatorio."
        ]
    }
}
```

`errors` solo aparece en errores de validación. Los códigos públicos posibles son:
`UNAUTHENTICATED`, `INVALID_CREDENTIALS`, `TWO_FACTOR_CHALLENGE_EXPIRED`, `INVALID_TWO_FACTOR_CODE`,
`FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `HTTP_ERROR`, `VALIDATION_ERROR`, `RATE_LIMIT_EXCEEDED`,
`SERVICE_UNAVAILABLE` e `INTERNAL_ERROR`.

Los errores 429 por validación de login y por el middleware de throttle usan `RATE_LIMIT_EXCEEDED`, no incluyen `errors`
y conservan cabeceras como `Retry-After` cuando Laravel las proporciona.

La API utiliza los códigos de estado estándar de HTTP:

| Código                          | Significado            | Causa común                                                                                                                 |
|:--------------------------------|:-----------------------|:----------------------------------------------------------------------------------------------------------------------------|
| **`200 OK`**                    | Petición exitosa       | Lectura, actualización o eliminación completada con éxito.                                                                  |
| **`201 Created`**               | Recurso creado         | Alta de un nuevo juego mediante `POST /api/games`.                                                                          |
| **`401 Unauthorized`**          | No autenticado         | Token ausente, revocado, caducado (>30 días) o credenciales incorrectas en login.                                           |
| **`403 Forbidden`**             | Acción no autorizada   | Intento de consultar, editar o borrar un juego perteneciente a otro usuario.                                                |
| **`404 Not Found`**             | Recurso no encontrado  | El ID del juego no existe o ha sido eliminado (*Soft Delete*).                                                              |
| **`422 Unprocessable Content`** | Error de validación    | Los datos enviados en el cuerpo no cumplen las reglas de validación.                                                        |
| **`429 Too Many Requests`**     | Límite de intentos     | Límite general de la API (120/min), de intentos de login en `/api/login`, o del desafío de 2FA (`verify-2fa`/`resend-2fa`). |
| **`500 Internal Server Error`** | Error interno          | Error no controlado en el servidor.                                                                                         |
| **`503 Service Unavailable`**   | Envío de email fallido | El código de 2FA no se pudo enviar (`/api/login`, `/api/login/resend-2fa`).                                                 |

### Ejemplos de estructuras de error

#### 401 Unauthorized (Falta o invalidez de token)

```json
{
    "code": "UNAUTHENTICATED",
    "status": 401,
    "message": "No autenticado."
}
```

#### 403 Forbidden (Intento de acceso a recurso ajeno)

```json
{
    "code": "FORBIDDEN",
    "status": 403,
    "message": "No autorizado para realizar esta acción."
}
```

#### 404 Not Found (Recurso no encontrado)

```json
{
    "code": "NOT_FOUND",
    "status": 404,
    "message": "Recurso no encontrado."
}
```

#### 422 Unprocessable Content (Errores de validación)

```json
{
    "code": "VALIDATION_ERROR",
    "status": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
        "title": [
            "El título del juego es obligatorio."
        ],
        "platform_id": [
            "La plataforma seleccionada no es válida."
        ]
    }
}
```

---

## 7. Notas técnicas y particularidades

1. **Presencia condicional del campo `platform`:**
   En `GameResource`, el nombre de la plataforma se expone mediante
   `$this->whenLoaded('platform', fn () => $this->platform->name)`. Por tanto:
    - En `GET /api/games` (`index`) y `GET /api/games/{id}` (`show`), la relación se carga explícitamente y el campo
      `platform` está presente como un `string`.
    - En `POST /api/games` (`store`) y `PUT/PATCH /api/games/{id}` (`update`), el modelo se devuelve sin cargar la
      relación, por lo que la clave `platform` **no está presente** en la respuesta JSON. Si el cliente necesita el
      nombre de la plataforma tras crear o editar, puede consultar nuevamente el endpoint `show` o resolverlo localmente
      a partir del `platform_id` enviado.

2. **Campo `price_paid` (Solo Escritura):**
   `StoreGameRequest` y `UpdateGameRequest` aceptan y validan el campo `price_paid` (precio de compra) para almacenarlo
   en la base de datos, pero `GameResource` **no lo incluye** en la serialización pública.

3. **Comportamiento de Soft Delete:**
   El modelo `Game` utiliza el trait `Illuminate\Database\Eloquent\SoftDeletes`. Al eliminar un juego vía
   `DELETE /api/games/{id}`, la fila permanece en la base de datos con `deleted_at` informado. Cualquier consulta
   posterior a dicho `id` devolverá automáticamente `404 Not Found`.

4. **Idioma de los mensajes de respuesta:**
   La API emite sus mensajes en español, localizados mediante `APP_LOCALE` (`es` por defecto en `.env.example`), los
   callbacks de renderizado de excepciones en `bootstrap/app.php`, el mapa de atributos de `lang/es/validation.php`
   y los mensajes propios de controladores y requests.
