# JWT Authentication - Quick Start Guide

## 🚀 5 Minutos para Empezar

### Prerequisitos
- API corriendo en `http://localhost:8000` (o tu URL)
- BD migrada con `003_update_requirements.sql`
- PHP 8.1+
- cURL instalado

---

## Opción 1: Pruebas desde Terminal (cURL)

### 1️⃣ Obtener el Token (Login)

```bash
curl -X POST http://localhost:8000/auth/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@sg-ia.com",
    "password": "admin123"
  }'
```

**Respuesta esperada**:
```json
{
  "ok": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsImVtYWlsIjoiYWRtaW5Ac2ctaWEuY29tIiwicm9sZSI6ImFkbWluIiwiaWF0IjoxNzAwMDAwMDAwLCJleHAiOjE3MDAwODY0MDB9...."
}
```

### 2️⃣ Usar el Token para Acceder a Ruta Protegida

Reemplaza `YOUR_TOKEN_HERE` con el token recibido:

```bash
TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

curl -X PUT http://localhost:8000/admin/questions/1 \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "statement": "Pregunta actualizada",
    "difficulty": 3
  }'
```

**Respuesta esperada**: 200 OK

### 3️⃣ Verificar Que Sin Token Se Rechaza

```bash
curl -X PUT http://localhost:8000/admin/questions/1 \
  -H 'Content-Type: application/json' \
  -d '{"statement": "Test"}'
```

**Respuesta esperada**: 401 Unauthorized
```json
{
  "ok": false,
  "error": "Missing Authorization header"
}
```

---

## Opción 2: Pruebas Automatizadas (PHP)

### Ejecutar Script de Prueba Completo

```bash
# Usa localhost por defecto
php tests/test_auth_flow.php

# O especifica una URL diferente
API_URL=http://api.mi-dominio.com php tests/test_auth_flow.php
```

**Output esperado**:
```
=== TEST 1: Acceder a ruta admin SIN token ===
HTTP Code: 401
Response: {"ok":false,"error":"Missing Authorization header"}
[✓ PASS] Acceso sin token rechazado

=== TEST 2: Login correcto ===
HTTP Code: 200
Response: {"ok":true,"token":"eyJ..."}
[✓ PASS] Login exitoso y token generado
Token recibido: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJz...

=== TEST 3: Acceder a ruta admin CON token ===
HTTP Code: 200
Response: {"ok":true,"data":{...}}
[✓ PASS] Acceso con token autorizado

=== TEST 4: Login con credenciales incorrectas ===
HTTP Code: 401
Response: {"ok":false,"error":"Invalid credentials"}
[✓ PASS] Login rechazado con credenciales incorrectas

=== TEST 5: Token inválido ===
HTTP Code: 401
Response: {"ok":false,"error":"Invalid token..."}
[✓ PASS] Token inválido rechazado

=== RESUMEN DE PRUEBAS ===
Pruebas pasadas: 5/5
✓ ¡TODOS LOS TESTS PASARON!
```

---

## Opción 3: Desde Postman/Insomnia

### 1. POST /auth/login

- **URL**: `http://localhost:8000/auth/login`
- **Method**: POST
- **Headers**:
  - `Content-Type: application/json`
- **Body** (JSON):
```json
{
  "email": "admin@sg-ia.com",
  "password": "admin123"
}
```

### 2. Copiar Token de la Respuesta

Busca en la respuesta:
```json
{
  "ok": true,
  "token": "eyJhbGciOi..." ← COPIAR ESTO
}
```

### 3. Usar Token en Rutas Protegidas

- **URL**: `http://localhost:8000/admin/questions/1`
- **Method**: PUT
- **Headers**:
  - `Content-Type: application/json`
  - `Authorization: Bearer eyJhbGciOi...` ← PEGAR AQUÍ
- **Body** (JSON):
```json
{
  "statement": "Nueva pregunta",
  "difficulty": 2
}
```

---

## Credenciales por Defecto

```
Email:    admin@sg-ia.com
Password: admin123
```

⚠️ **Cambiar en Producción**: Ver `JWT_AUTH_IMPLEMENTATION.md` sección 8.2

---

## Estructura del Token JWT

Puedes decodificar el token en https://jwt.io/ para ver:

```json
{
  "sub": 1,           // ID del admin
  "email": "admin@sg-ia.com",
  "role": "admin",
  "iat": 1700000000,  // Emitido en
  "exp": 1700086400   // Expira en (24 horas después)
}
```

---

## Errores Comunes

| Error | Causa | Solución |
|-------|-------|----------|
| `Missing Authorization header` | No envías el header | Agrega `Authorization: Bearer <token>` |
| `Invalid Authorization header format` | Formato incorrecto | Usa `Bearer <token>` (con espacio) |
| `Invalid token` | Token corrupto o expirado | Login de nuevo para obtener nuevo token |
| `Invalid credentials` | Email/password incorrectos | Usa `admin@sg-ia.com` / `admin123` |
| `Database error` | BD no conecta | Verifica conexión y migración 003 |

---

## Variables de Entorno (Producción)

Crear archivo `.env`:

```env
JWT_SECRET=tu-secreto-de-32-caracteres-minimo
JWT_EXPIRY=86400
```

O exportar en shell:

```bash
export JWT_SECRET="tu-secreto-de-32-caracteres-minimo"
php tests/test_auth_flow.php
```

---

## Endpoints Disponibles

### Públicos (Sin JWT)
```
GET    /                          # Health check
POST   /auth/login               # Login
POST   /players                  # Crear jugador
GET    /players                  # Listar jugadores
POST   /games/start              # Iniciar sesión
GET    /games/next               # Siguiente pregunta
POST   /games/{id}/answer        # Enviar respuesta
GET    /questions/{id}           # Obtener pregunta
GET    /stats/session/{id}       # Estadísticas
```

### Protegidos (Requieren JWT)
```
PUT    /admin/questions/{id}         # Actualizar pregunta
PATCH  /admin/questions/{id}/verify  # Verificar pregunta
```

---

## Soporte

Para más detalles técnicos, ver `JWT_AUTH_IMPLEMENTATION.md`
