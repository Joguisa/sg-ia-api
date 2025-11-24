# Sistema de Autenticación JWT - Documentación Técnica

## 1. Resumen Ejecutivo

Se ha implementado un **sistema de autenticación basado en JWT (JSON Web Tokens)** para proteger los endpoints administrativos de la API. El sistema utiliza la librería `firebase/php-jwt` para la generación y validación de tokens seguros.

### Características Principales
- ✅ **Autenticación con JWT**: Tokens con firma HS256
- ✅ **Expiración de tokens**: 24 horas (configurable)
- ✅ **Password hashing**: Bcrypt (password_verify)
- ✅ **Middleware de protección**: Validación automática de headers Authorization
- ✅ **Endpoint público de login**: POST `/auth/login`
- ✅ **Rutas administrativas protegidas**: Todas las rutas `/admin/*`
- ✅ **Tests completos**: Script para validar todo el flujo

---

## 2. Componentes Implementados

### 2.1 AuthService (`src/Services/AuthService.php`)

**Responsabilidad**: Gestionar la lógica de autenticación y tokens JWT

```php
public function login(string $email, string $password): array
```
- Busca el admin en la tabla `admins`
- Verifica el password con `password_verify()`
- Genera un JWT con payload: `{sub, email, role, iat, exp}`
- Retorna el token o error

```php
public function validateToken(string $token): array
```
- Decodifica y valida la firma del token
- Verifica la expiración automáticamente
- Retorna el payload descodificado o error

**Configuración**:
- `JWT_SECRET`: Variable de entorno (fallback a valor por defecto)
- `tokenExpiry`: 86400 segundos (24 horas) por defecto

### 2.2 AuthController (`src/Controllers/AuthController.php`)

**Responsabilidad**: Exponer el endpoint de login HTTP

**Endpoint**: `POST /auth/login`

**Formato de Solicitud**:
```json
{
  "email": "admin@sg-ia.com",
  "password": "admin123"
}
```

**Respuesta Exitosa** (200):
```json
{
  "ok": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Respuesta Error** (401):
```json
{
  "ok": false,
  "error": "Invalid credentials"
}
```

### 2.3 AuthMiddleware (`src/Middleware/AuthMiddleware.php`)

**Responsabilidad**: Validar JWT en requests a rutas protegidas

**Flujo de Validación**:
1. Lee header `Authorization`
2. Extrae el token del formato `Bearer <token>`
3. Valida la firma y expiración
4. Almacena payload en `$_SERVER['ADMIN']`
5. Detiene ejecución con 401 si es inválido

**Respuesta Error** (401):
```json
{
  "ok": false,
  "error": "Missing Authorization header"
}
```

### 2.4 Router Actualizado (`src/Utils/Router.php`)

**Cambios**:
- Método `add()` ahora acepta parámetro opcional `$middleware`
- El middleware se ejecuta ANTES del handler de ruta
- Si el middleware llama `exit`, no se ejecuta el handler

**Uso**:
```php
$router->add('PUT', '/admin/questions/{id}',
  fn($p) => $adminCtrl->updateQuestion($p),
  fn() => $authMiddleware->validate()
);
```

---

## 3. Arquitectura de Protección

### 3.1 Rutas Protegidas (Requieren JWT)
```
PUT   /admin/questions/{id}
PATCH /admin/questions/{id}/verify
```

### 3.2 Rutas Públicas (Sin JWT)
```
GET   /
POST  /auth/login
POST  /players
GET   /players
POST  /games/start
GET   /games/next
POST  /games/{id}/answer
GET   /questions/{id}
GET   /stats/session/{id}
```

---

## 4. Tabla de Base de Datos

Se utiliza la tabla `admins` creada en la migración 003:

```sql
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Admin por defecto** (seeded en 003_update_requirements.sql):
- **Email**: `admin@sg-ia.com`
- **Password**: `admin123`
- **Hash**: `$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y`

---

## 5. Estructura del JWT

### 5.1 Header
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

### 5.2 Payload
```json
{
  "sub": 1,
  "email": "admin@sg-ia.com",
  "role": "admin",
  "iat": 1700000000,
  "exp": 1700086400
}
```

### 5.3 Signature
- Algoritmo: HS256 (HMAC-SHA256)
- Secret: `JWT_SECRET` (variable de entorno)

---

## 6. Flujo de Autenticación

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. POST /auth/login {email, password}                          │
└─────────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. AuthController valida entrada y llama AuthService::login()   │
└─────────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. AuthService busca admin en BD y verifica password            │
└─────────────────────────────────────────────────────────────────┘
                        ↓
          ┌─────────────┴────────────────┐
          ↓                              ↓
    [Inválido]                      [Válido]
     401 Error                  Generar JWT
          ↓                              ↓
    {"error": "..."}      {"token": "..."}
          ↓                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. Cliente almacena el token en localStorage/sessionStorage     │
└─────────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. PUT /admin/questions/{id} con Authorization: Bearer <token>  │
└─────────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. AuthMiddleware valida el token                               │
└─────────────────────────────────────────────────────────────────┘
          ↓
  ┌───────┴──────────┐
  ↓                  ↓
[Inválido]      [Válido]
401 Error      Procesar
  ↓              Solicitud
Salir             ↓
             200 OK
```

---

## 7. Test Suite (`tests/test_auth_flow.php`)

Script completo para validar el sistema. Ejecutar con:

```bash
php tests/test_auth_flow.php
```

o con variable de entorno personalizada:

```bash
API_URL=http://localhost:8080 php tests/test_auth_flow.php
```

### Tests Incluidos

1. **Acceso sin token** ✓ Rechaza con 401
2. **Login exitoso** ✓ Retorna JWT válido
3. **Acceso con token** ✓ Autoriza con 200
4. **Credenciales incorrectas** ✓ Rechaza con 401
5. **Token inválido** ✓ Rechaza con 401

---

## 8. Configuración en Producción

### 8.1 Variables de Entorno Recomendadas

```env
JWT_SECRET=tu-secreto-super-seguro-de-256-bits-minimo
JWT_EXPIRY=86400
APP_ENV=production
APP_DEBUG=false
```

### 8.2 Cambiar Admin por Defecto

Para generar un nuevo hash bcrypt:

```php
<?php
$password = 'nueva-contraseña';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
echo $hash; // Usar en UPDATE admins SET password_hash = '...'
?>
```

### 8.3 Seguridad del JWT_SECRET

- ✅ Mínimo 256 bits (32 caracteres) de entropía
- ✅ Cambiar frecuentemente en producción
- ✅ Usar valores únicos por ambiente
- ✅ Nunca commitear en git

---

## 9. Ejemplo de Uso desde Cliente

### JavaScript/Fetch

```javascript
// 1. Login
const loginResponse = await fetch('http://api.local/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'admin@sg-ia.com',
    password: 'admin123'
  })
});

const { token } = await loginResponse.json();
localStorage.setItem('authToken', token);

// 2. Usar token en solicitudes protegidas
const updateResponse = await fetch('http://api.local/admin/questions/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    statement: 'Nueva pregunta',
    difficulty: 3
  })
});

const result = await updateResponse.json();
```

### cURL

```bash
# 1. Login
curl -X POST http://localhost:8000/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@sg-ia.com","password":"admin123"}' \
  | jq '.token' > token.txt

# 2. Usar token
TOKEN=$(cat token.txt | tr -d '"')
curl -X PUT http://localhost:8000/admin/questions/1 \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"statement":"Test","difficulty":2}'
```

---

## 10. Troubleshooting

### Error: "Missing Authorization header"
- Verificar que el header `Authorization` esté presente
- Formato correcto: `Authorization: Bearer <token>`

### Error: "Invalid token"
- Token expirado (24 horas)
- Token corrupto o malformado
- Secret incorrecto

### Error: "Database error"
- Verificar conexión a BD
- Verificar tabla `admins` existe
- Verificar credenciales de conexión

### Error: "Invalid credentials"
- Email no existe
- Password incorrecto
- Tabla admins vacía

---

## 11. Próximos Pasos Recomendados

1. **Rate Limiting**: Implementar límite de intentos de login fallidos
2. **Refresh Tokens**: Agregar tokens de refresco para mayor seguridad
3. **Roles y Permisos**: Expandir a roles (editor, viewer, admin)
4. **Logout**: Endpoint para invalidar tokens (blacklist)
5. **Auditoría**: Registrar intentos de login en logs
6. **MFA**: Autenticación de dos factores

---

## 12. Referencias

- **firebase/php-jwt**: https://github.com/firebase/php-jwt
- **JWT.io**: https://jwt.io/
- **OWASP**: https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html

---

**Fecha**: 2025-11-23
**Versión**: 1.0
**Status**: ✅ Implementación Completa
