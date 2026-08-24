# Contribuir a Savepoint

Savepoint es, hoy, un proyecto de uso personal (una sola persona catalogando su propia colección), así que no hay un
proceso de contribución formal ni CI configurado. Aun así, si se bifurca o alguien quiere proponer un cambio, esta guía
recoge cómo está organizado el trabajo.

## Antes de empezar

- Revisa la sección [Pendiente / en curso](README.md#pendiente--en-curso) del README y las
  [issues abiertas](../../issues) — es donde vive el trabajo ya identificado. Si quieres coger una, coméntalo en la
  issue antes de ponerte para evitar trabajo duplicado.
- Para algo que no esté ya recogido ahí (bug, propuesta de función), abre una issue describiendo el problema o la
  propuesta antes de mandar un PR, salvo que sea un cambio trivial (typo, fix menor).

## Entorno de desarrollo

El proyecto se levanta con Docker Compose — ver [Desplegar para uso propio](README.md#desplegar-para-uso-propio) en el
README para el arranque completo. Resumen rápido:

```bash
git clone <url-del-repo> savepoint && cd savepoint
cp .env.example .env
docker compose up -d --build
```

Tras cambios en Blade/PHP basta con recargar el navegador (no hay build gracias al volumen). Tras cambios en
CSS/JS (`resources/css`, `resources/js`):

```bash
docker compose exec app npm run build
```

## Tests

Cualquier cambio de comportamiento (no solo visual) debería llevar test y pasar en verde antes de proponerlo:

```bash
docker compose exec app php artisan test
```

El entorno de test es independiente del de desarrollo (SQLite en memoria vía `phpunit.xml`), así que correr los tests
nunca toca la base Postgres real ni Redis. **Nunca uses `artisan tinker` contra el contenedor `app` para generar datos
de prueba** — apunta a la base de desarrollo real, no a la de testing; si necesitas datos, escribe un test.

## Estilo de código

- **Commits**: prefijo convencional del tipo de cambio (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`...), en español y
  explicando el *por qué* del cambio, no solo el *qué* — revisa `git log` para ver el patrón.
- **Idioma**: la interfaz, los mensajes de validación y la documentación del proyecto están en español; se mantiene así
  para no mezclar idiomas a medias.
- **Formato PHP**: el proyecto usa `laravel/pint`. Actívalo una vez por clon con el hook de pre-commit incluido en
  [`.githooks/pre-commit`](.githooks/pre-commit):

  ```bash
  git config core.hooksPath .githooks
  ```

  A partir de ahí, cada commit formatea solo los `.php` en stage automáticamente. Requiere el contenedor `app`
  levantado (`docker compose up -d`); si Pint falla o el contenedor no está arriba, el hook aborta el commit con un
  mensaje en vez de dejarlo pasar sin formatear. No hace falta correr Pint a mano.

## Documentación

Los cambios de comportamiento visible (nueva función, fix de UI, etc.) llevan:

- Una entrada nueva en [`CHANGELOG.md`](CHANGELOG.md) con la fecha del día.
- Si añaden o cambian una característica ya descrita, actualizar la sección correspondiente de
  [Características](README.md#características) en el README a la vez, para no dejarla desincronizada.

## Enviar el cambio

1. Abre un PR contra `main` con una descripción breve del *por qué*.
2. Asegúrate de que los tests pasan (`docker compose exec app php artisan test`) y de que Pint no deja nada sin
   formatear.
3. Enlaza la issue relacionada si existe.

## Licencia

El proyecto usa [PolyForm Noncommercial 1.0.0](LICENSE): cualquier contribución se distribuye bajo la misma licencia.
