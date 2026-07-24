# SavePoint 🎮

**SavePoint** es una aplicación open-source para gestionar tu colección personal de videojuegos. Incluye un catálogo global, búsqueda inteligente en tiendas externas (Xtralife, CeX), y tracking completo de estado, precio y condición de cada copia.

## 🏗️ Arquitectura

Este es un **monorepo** con tres componentes independientes:

- **Backend**: Laravel 11 + PostgreSQL (API REST con Sanctum)
- **Mobile**: Flutter + Riverpod (iOS/Android)
- **Web**: Inertia.js + Vue (interfaz web, fase 2)
- **Infrastructure**: Docker Compose para desarrollo y producción

## 📁 Estructura de Carpetas

```
savepoint/
├── backend/          # API REST (Laravel 11)
├── mobile/           # Aplicación móvil (Flutter)
├── docker/           # Configuración Docker
├── docs/             # Documentación
└── docker-compose.yml
```

## 🚀 Inicio Rápido

### Requisitos
- Docker & Docker Compose
- Git
- (Opcional) Flutter SDK para desarrollo mobile

### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/savepoint.git
cd savepoint
```

### 2. Levantar la infraestructura
```bash
# Copiar variables de entorno
cp backend/.env.example backend/.env

# Levantar servicios (PostgreSQL + Laravel)
docker-compose up -d

# Esperar a que PostgreSQL esté listo
sleep 10

# Instalar dependencias y migrar DB
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```

### 3. Verificar
- Backend API: `http://localhost/api/health`
- Base de datos: conectar a `postgres://savepoint:secreto123@localhost:5432/savepoint`

### 4. Desarrollo Mobile (Flutter)
```bash
cd mobile
flutter pub get
flutter run
```

## 📚 Documentación

- [**SETUP.md**](docs/SETUP.md) - Guía de instalación completa
- [**API.md**](docs/API.md) - Endpoints y autenticación
- [**ARCHITECTURE.md**](docs/ARCHITECTURE.md) - Diseño técnico
- [**CONTRIBUTING.md**](docs/CONTRIBUTING.md) - Cómo contribuir

## 🔧 Comandos útiles

```bash
# Backend
docker-compose exec app php artisan tinker
docker-compose exec app php artisan migrate:fresh --seed
docker-compose logs -f app

# Base de datos
docker-compose exec postgres psql -U savepoint -d savepoint

# Mobile
cd mobile && flutter clean && flutter pub get
```

## 📝 Features

✅ **Implementado**
- Autenticación (Laravel Sanctum)
- Búsqueda global (Xtralife + CeX)
- Gestión de colección personal
- Clean Architecture en Flutter

🚧 **En desarrollo**
- CRUD completo de juegos
- Dashboard web
- Sincronización móvil-web
- API de catálogo global

## 📄 Licencia

MIT

## 👤 Autor

Felipe - [@tu_github](https://github.com/tu_github)

---

## ¿Preguntas?

Abre un issue o contacta a través de las discussions.