# 🎮 SG-IA API: Serious Game con Inteligencia Artificial Adaptativa

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-00758F?logo=mysql&logoColor=white)
![Google Gemini](https://img.shields.io/badge/Google%20Gemini-API-4285F4?logo=google&logoColor=white)

> Backend RESTful para alfabetización sanitaria sobre cáncer de colon mediante gamificación adaptativa con IA generativa.

**Proyecto de Integración Curricular**
*Universidad de Guayaquil · Facultad de Ciencias Matemáticas y Físicas · Carrera Ingeniería en Software · 2025-2026*

---

## 📋 Descripción

Sistema backend que adapta dinámicamente la dificultad de preguntas educativas según el desempeño del jugador, integrando Google Gemini para generación procedimental de contenido médico sobre cáncer de colon.

**Características:**
- Motor adaptativo que ajusta dificultad (1.0-5.0) en tiempo real
- Generación de preguntas con IA Generativa (Gemini 1.5 Flash)
- Autenticación JWT para administradores
- API RESTful con endpoints para jugadores, sesiones y estadísticas
- Arquitectura limpia sin frameworks pesados (PHP vanilla + PDO)

---

## 🚀 Instalación Rápida

### Requisitos
- PHP 8.1+ (extensiones: pdo_mysql, curl, mbstring, json)
- MySQL 8.0+
- Composer 2.0+

### Pasos

```bash
# 1. Clonar repositorio
git clone https://github.com/Joguisa/sg-ia-api.git
cd sg-ia-api

# 2. Instalar dependencias
composer install --optimize-autoloader

# 3. Copiar configuración
cp .env.example .env

# 4. Crear base de datos
mysql -u root -p -e "CREATE DATABASE sg_ia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p sg_ia_db < db/schema/001_sg-ia-db.sql

# 5. Configurar .env (editar con tus valores)
# Campos requeridos: DB_USER, DB_PASS, GEMINI_API_KEY, JWT_SECRET

# 6. Iniciar servidor
php -S localhost:8000 -t public
```

Verificar instalación:
```bash
curl http://localhost:8000/
# Response: {"ok":true,"service":"sg-ia-api","time":"..."}
```

### Troubleshooting

| Error | Solución |
|-------|----------|
| `Class not found` | `composer dump-autoload` |
| Conexión BD | Verificar `DB_USER`, `DB_PASS`, `DB_NAME` en `.env` |
| Puerto 8000 en uso | `php -S localhost:8001 -t public` |
| Gemini API falla | Validar `GEMINI_API_KEY` en `.env` |

---

## 🔑 Variables de Entorno

```bash
# Base de datos
DB_DSN=mysql:host=127.0.0.1;dbname=sg_ia_db;charset=utf8mb4
DB_USER=root
DB_PASS=

# IA Generativa
GEMINI_API_KEY=your_api_key_here
GEMINI_ENABLED=true

# Autenticación JWT
JWT_SECRET=your_secure_random_string_min_32_chars
JWT_EXPIRY=86400

# CORS
CORS_ORIGIN=http://localhost:4200
```

Para generar `JWT_SECRET` seguro:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## 📊 Endpoints API

### Resumen Rápido

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/auth/login` | Obtener token JWT (admin) |
| POST | `/players` | Registrar jugador |
| GET | `/players` | Listar jugadores |
| POST | `/games/start` | Iniciar sesión de juego |
| GET | `/games/next` | Obtener siguiente pregunta |
| POST | `/games/{id}/answer` | Enviar respuesta |
| GET | `/stats/session/{id}` | Estadísticas de sesión |
| GET | `/stats/player/{id}` | Perfil del jugador |
| GET | `/stats/leaderboard` | Top 10 jugadores |
| GET | `/admin/dashboard` | Dashboard (protegido) |
| POST | `/admin/generate-batch` | Generar preguntas con IA (protegido) |

### Ejemplo: Obtener token JWT

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Response:
```json
{
  "ok": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 3600
}
```

### Ejemplo: Iniciar juego

```bash
curl -X POST http://localhost:8000/games/start \
  -H "Content-Type: application/json" \
  -d '{"player_id":1,"initial_difficulty":1.0}'
```

Response:
```json
{
  "ok": true,
  "session": {
    "id": 128,
    "player_id": 1,
    "current_difficulty": 1.0,
    "score": 0,
    "lives": 3,
    "status": "active"
  }
}
```

---

## 🧠 Motor de IA Adaptativo

**Concepto:** Ajusta dificultad según velocidad y precisión de respuestas para mantener al jugador en "zona de flujo" (desafiante pero alcanzable).

**Lógica simple:**
- Respuesta correcta rápida (< 3s) → +0.50 dificultad
- Respuesta correcta moderada (3-6s) → +0.25 dificultad
- Respuesta correcta lenta (> 6s) → +0.10 dificultad
- Respuesta incorrecta → -0.25 dificultad

**Puntuación:**
- Correcta < 3s: 20 puntos
- Correcta < 6s: 15 puntos
- Correcta > 6s: 10 puntos
- Incorrecta: 0 puntos

---

## 🏗️ Arquitectura

**Capas:**
```
Controllers (HTTP) → Services (Lógica) → Repositories (Datos) → BD (MySQL)
```

**Estructura de carpetas:**
```
src/
├── Controllers/        # Manejadores de HTTP (6 controladores)
├── Services/          # GameService, AIEngine, AuthService
├── Repositories/      # Data Access Objects (5 repositorios)
├── Models/            # DTOs y Entidades
├── Middleware/        # Auth JWT y CORS
├── Database/          # Conexión PDO
└── Utils/             # Router y Response helpers
```

---

## 🧪 Testing

```bash
# Validar motor de IA adaptativo (6 casos de prueba)
php tests/test_ai_logic.php

# Flujo completo con IA activa (registro → sesión → pregunta generada → respuesta)
php tests/test_auth_flow.php

# Características administrativas (generación de preguntas, validación)
php tests/test_admin_features.php
```

**Descripción de tests:**
- `test_ai_logic.php` - Valida cálculo de dificultad: respuestas rápidas (+0.50), moderadas (+0.25), lentas (+0.10), incorrectas (-0.25), límites [1.0-5.0]
- `test_auth_flow.php` - Flujo integral: creación de jugador, sesión, generación con Gemini, respuesta educativa
- `test_admin_features.php` - Verifica acceso a prompts de IA en BD y generación de preguntas complejas

---

## 🔐 Seguridad

- ✅ Queries preparadas (protección SQL injection)
- ✅ Validación estricta de entrada (type casting)
- ✅ JWT con expiración (24 horas)
- ✅ Contraseñas hasheadas con bcrypt
- ✅ CORS whitelist configurado
- ✅ .env no versionado en Git

---

## 📄 Licencia

MIT License - Ver archivo LICENSE

**Proyecto de Tesis**
Universidad de Guayaquil · 2025-2026

---

**Stack:** PHP 8.1 | MySQL 8.0 | Google Gemini | JWT | Guzzle HTTP
