# Contribuir a Savepoint

El flujo de desarrollo de Savepoint usa Docker Compose. Cada worktree debe tener su propia identidad Compose y sus
propios puertos para que varios entornos puedan ejecutarse a la vez sin compartir contenedores, red ni volúmenes.

## Flujo canónico por worktree

1. Crea el worktree desde `main` y entra en él:

   ```bash
   git worktree add ../savepoint-mi-cambio -b mi-cambio main
   cd ../savepoint-mi-cambio
   ```

2. Prepara la configuración local. `.env` es privado y debe ser un fichero normal del worktree, no un enlace simbólico:

   ```bash
   cp .env.example .env
   ```

   Edita `.env` y asigna valores únicos para `COMPOSE_PROJECT_NAME`, `POSTGRES_FORWARD_PORT`, `REDIS_FORWARD_PORT` y
   `HTTP_PORT` (por ejemplo, `savepoint-mi-cambio`, `55432`, `56379` y `18081`). Mantén `DB_HOST=postgres`,
   `DB_PORT=5432`, `REDIS_HOST=redis` y `REDIS_PORT=6379`: son los nombres y puertos internos de la red Compose.
   `HTTPS_PORT` solo se usa si activas la publicación HTTPS alternativa de nginx.

3. Comprueba la configuración interpolada antes de arrancar:

   ```bash
   docker compose config
   ```

4. Levanta el entorno:

   ```bash
   docker compose up -d --build
   ```

5. Ejecuta los tests dentro del contenedor `app`:

   ```bash
   docker compose exec app php artisan test
   ```

6. Tras cambios en CSS o JavaScript, recompila los assets:

   ```bash
   docker compose exec app npm run build
   ```

   Los cambios PHP y Blade se reflejan mediante el volumen montado; no hace falta reconstruir para cada edición.

7. Detén el worktree al terminar, conservando sus volúmenes:

   ```bash
   docker compose down
   ```

8. Cuando el entorno y sus datos ya no sean necesarios, destrúyelos explícitamente:

   ```bash
   docker compose down -v --remove-orphans
   ```

   `-v` elimina los volúmenes nombrados de ese proyecto Compose. No lo uses si necesitas conservar los datos locales.

## Aislamiento y ficheros locales

- No reutilices el mismo `COMPOSE_PROJECT_NAME` ni los mismos puertos publicados en dos worktrees activos.
- No cambies los hosts de servicio `postgres` y `redis`, ni los puertos internos `5432` y `6379`.
- No añadas al repositorio `.env`, credenciales ni otros secretos.
- No crees enlaces simbólicos para `.env` ni para `vendor`; cada worktree debe tener su configuración y sus dependencias
  locales. Usa `cp .env.example .env` y el flujo Docker Compose.

## Tests y estilo

Cualquier cambio de comportamiento debe llevar test y pasar en verde antes de proponerlo. La suite local (paso 5) corre
contra SQLite en memoria por velocidad, pero en CI (GitHub Actions) también se ejecuta completa contra PostgreSQL real
para detectar diferencias de comportamiento entre motores; si quieres reproducir esa segunda tanda en local, usa
`docker compose exec app vendor/bin/phpunit --configuration=phpunit.pgsql.xml` contra una base de datos Postgres de
pruebas (no la de desarrollo).

Configura el hook de commits una vez por clon:

```bash
git config core.hooksPath .githooks
```

Los commits usan prefijos convencionales (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`) y explican el motivo del
cambio. La interfaz y la documentación del proyecto están en español.

## Enviar el cambio

Abre un PR contra `main`, enlaza la issue relacionada si existe y describe el porqué del cambio. Incluye el resultado de
los tests y confirma que no se han añadido secretos ni enlaces simbólicos de `.env` o `vendor`.

## Licencia

El proyecto usa [PolyForm Noncommercial 1.0.0](LICENSE): cualquier contribución se distribuye bajo la misma licencia.
