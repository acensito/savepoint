# 🚀 SavePoint - Guía de Instalación Completa

## Requisitos previos

- **Docker Desktop** (Windows/Mac) o Docker + Docker Compose (Linux)
- **Git**
- **Flutter SDK** (solo si desarrollas la app móvil)

## Paso 1: Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/savepoint.git
cd savepoint
```

## Paso 2: Configurar variables de entorno

```bash
# Copiar plantilla .env
cp backend/.env.example backend/.env

# Ver el contenido (opcional)
cat backend/.env
```

**Archivos .env key:**
- `backend/.env` - Configuración de Laravel
- `docker-compose.yml` - Variables Docker (DB_PASSWORD, etc)

## Paso 3: Levantar infraestructura Docker

```bash
# Construir imágenes y levantar servicios
docker-compose up -d

# Ver estado
docker-compose ps
```

Esto inicia:
- **PostgreSQL** (puerto 5432)
- **PHP-FPM** (puerto 9000, interno)
- **Nginx** (puerto 80, http://localhost)

## Paso 4: Instalar Laravel

```bash
# Entrar en el contenedor app
docker-compose exec app bash

# Dentro del contenedor:
composer install
php artisan key:generate
php artisan migrate
```

## Paso 5: Verificar instalación

```bash
# Probar la API
curl http://localhost/api/health

# Ver logs
docker-compose logs -f app
```

Deberías ver:
```json
{"status":"ok"}
```

## Paso 6: Base de datos

### Conectar a PostgreSQL

```bash
# Desde tu host
docker-compose exec postgres psql -U savepoint -d savepoint

# O con pgAdmin (opcional)
docker-compose exec postgres psql -U savepoint -d savepoint < backup.sql
```

### Ver tablas creadas

```bash
# Dentro de psql:
\dt

# Ver estructura de tabla games
\d games
```

## Paso 7: Flutter (opcional - para desarrollo mobile)

```bash
cd mobile

# Descargar dependencias
flutter pub get

# Ejecutar en emulador/dispositivo
flutter run

# Build APK
flutter build apk --release
```

## Desarrollar localmente

### Backend (Laravel)

```bash
# Ejecutar migrations nuevas
docker-compose exec app php artisan migrate

# Crear seeders (datos de prueba)
docker-compose exec app php artisan db:seed

# Consulta SQL
docker-compose exec postgres psql -U savepoint -d savepoint
```

### Mobile (Flutter)

```bash
cd mobile

# Hot reload
flutter run

# Cambiar API endpoint (en .env o config)
# Apunta a http://localhost:80 (tu máquina host)
```

### Web (Inertia - fase 2)

```bash
cd backend
npm install
npm run dev
```

## 🐛 Troubleshooting

### Error: "PostgreSQL no inicia"
```bash
docker-compose logs postgres
docker-compose down -v  # Borra volúmenes
docker-compose up -d postgres
```

### Error: "Port 80 already in use"
```bash
# Cambiar puerto en docker-compose.yml:
ports:
  - "8080:80"  # Nueva: 8080:80

# Luego acceder a http://localhost:8080
```

### Error: "Composer not found"
```bash
# Reconstruir imagen
docker-compose build --no-cache
docker-compose up -d
docker-compose exec app composer install
```

### Flutter no conecta a API
```bash
# Verificar que la API funciona
curl http://localhost/api/health

# En Flutter, usar IP de host real (no localhost)
# Si estás en emulador Android: 10.0.2.2:80
```

## 📦 Producción

Para desplegar en tu servidor:

```bash
# En servidor (con Docker)
git clone https://github.com/tu-usuario/savepoint.git
cd savepoint

# Crear .env de producción
cp backend/.env.example backend/.env
# Editar valores (DB_PASSWORD, APP_URL, etc)

# Levantar con Nginx real
docker-compose -f docker-compose.prod.yml up -d
```

## ✅ Checklist final

- [ ] Docker funcionando (`docker --version`)
- [ ] Proyecto clonado en `~/savepoint`
- [ ] `docker-compose ps` muestra 3 servicios running
- [ ] `curl http://localhost/api/health` retorna `{"status":"ok"}`
- [ ] Base de datos PostgreSQL con tablas creadas
- [ ] Flutter app compila sin errores
- [ ] Puedo hacer login en la API

## 🆘 ¿Necesitas ayuda?

- Revisa `docs/API.md` para endpoints
- Lee `docs/ARCHITECTURE.md` para entender el flujo
- Abre un issue en GitHub

Happy coding! 🎮