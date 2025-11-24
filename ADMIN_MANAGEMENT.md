# 👤 Gestión de Administradores

Guía para crear, actualizar y gestionar credenciales de admin en el sistema.

---

## 1. Crear Nuevo Admin

### Método 1: SQL Directo

```sql
-- 1. Generar hash de la contraseña (ver script PHP abajo)
-- 2. Ejecutar INSERT

INSERT INTO admins (email, password_hash)
VALUES (
  'nuevo-admin@dominio.com',
  '$2y$10$...'  -- Hash aquí
);
```

### Método 2: Usar Script PHP

```php
<?php
// script: generate_admin.php
require_once __DIR__ . '/vendor/autoload.php';

$email = $argv[1] ?? 'admin@example.com';
$password = $argv[2] ?? 'password123';

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Email: {$email}\n";
echo "Password: {$password}\n";
echo "Hash: {$hash}\n\n";
echo "SQL:\n";
echo "INSERT INTO admins (email, password_hash) VALUES ('{$email}', '{$hash}');\n";
?>
```

**Uso**:
```bash
php generate_admin.php nuevo-admin@sg-ia.com mi-contraseña-segura
```

**Output**:
```
Email: nuevo-admin@sg-ia.com
Password: mi-contraseña-segura
Hash: $2y$10$abcdef123456...

SQL:
INSERT INTO admins (email, password_hash) VALUES ('nuevo-admin@sg-ia.com', '$2y$10$abcdef123456...');
```

---

## 2. Cambiar Contraseña de Existente

### Opción A: Directamente en BD (SQL)

```sql
-- 1. Generar nuevo hash (ver abajo)
-- 2. Actualizar registro

UPDATE admins
SET password_hash = '$2y$10$NUEVO_HASH_AQUI'
WHERE email = 'admin@sg-ia.com';
```

### Opción B: Script de CLI (Recomendado)

```php
<?php
// Script: change_admin_password.php
require_once __DIR__ . '/vendor/autoload.php';

use Src\Database\Connection;

$email = $argv[1] ?? null;
$newPassword = $argv[2] ?? null;

if (!$email || !$newPassword) {
  echo "Uso: php change_admin_password.php <email> <nueva-contraseña>\n";
  exit(1);
}

$dbCfg = require __DIR__ . '/config/database.php';
$conn = new Connection($dbCfg);
$pdo = $conn->pdo();

// Generar nuevo hash
$newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

// Actualizar en BD
$stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
$result = $stmt->execute([$newHash, $email]);

if ($result && $stmt->rowCount() > 0) {
  echo "✓ Contraseña actualizada para: {$email}\n";
  exit(0);
} else {
  echo "✗ Admin no encontrado: {$email}\n";
  exit(1);
}
?>
```

**Uso**:
```bash
php change_admin_password.php admin@sg-ia.com nueva-contraseña-123
```

---

## 3. Generar Hash Bcrypt Manualmente

### En Terminal (PHP CLI)

```bash
php -r "echo password_hash('mi-contraseña', PASSWORD_BCRYPT, ['cost' => 10]);"
```

### Online (Solo para testing)

Usar https://www.bcryptcalculator.com/ (⚠️ Nunca en producción)

### En Código

```php
<?php
$password = 'mi-contraseña-segura';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
echo $hash;
?>
```

---

## 4. Listar Todos los Admins

```sql
SELECT id, email, created_at, updated_at FROM admins;
```

---

## 5. Eliminar Admin

```sql
DELETE FROM admins WHERE email = 'admin-a-eliminar@dominio.com';
```

⚠️ **Cuidado**: Sin confirmación adicional

---

## 6. Resetear Contraseña de Admin por Defecto

Si olvidaste la contraseña del admin por defecto:

```sql
UPDATE admins
SET password_hash = '$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y'
WHERE email = 'admin@sg-ia.com';
```

Esto restaura:
- Email: `admin@sg-ia.com`
- Password: `admin123`

---

## 7. Validar Contraseña Bcrypt

```php
<?php
$password = 'admin123';
$hash = '$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y';

if (password_verify($password, $hash)) {
  echo "✓ Contraseña válida\n";
} else {
  echo "✗ Contraseña inválida\n";
}
?>
```

---

## 8. Mejores Prácticas

### Seguridad de Contraseñas

```
✅ DO:
- Mínimo 12 caracteres
- Mezclar mayúsculas, minúsculas, números, símbolos
- Usar contraseñas únicas por admin
- Cambiar periódicamente (cada 90 días)
- Nunca compartir en texto plano

❌ DON'T:
- Usar contraseñas simples (password123, admin123)
- Reutilizar contraseñas
- Guardar en código/comments
- Enviar por email sin encripción
- Usar MD5 o SHA1 (usar bcrypt)
```

### Ejemplo de Contraseña Fuerte

```
P@ssW0rd_2025_Seg!
```

---

## 9. Auditoría de Cambios

Para rastrear quién cambió qué:

```sql
-- Ver cambios recientes de admins
SELECT * FROM admins ORDER BY updated_at DESC LIMIT 10;

-- Ver logs administrativos (si existen)
SELECT * FROM admin_system_logs
WHERE entity_table = 'admins'
ORDER BY logged_at DESC
LIMIT 20;
```

---

## 10. Recuperación de Contraseña (Futuro)

Para implementar flujo de "olvidé mi contraseña":

1. **Endpoint**: `POST /auth/forgot-password`
   - Recibe email
   - Genera token temporal
   - Envía email con link

2. **Endpoint**: `POST /auth/reset-password`
   - Recibe token temporal + nueva contraseña
   - Valida token
   - Actualiza contraseña

---

## 11. Tabla admins - Estructura

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

---

## 12. Troubleshooting

| Problema | Causa | Solución |
|----------|-------|----------|
| `Duplicate entry for email` | Email ya existe | Usar email único o UPDATE en lugar de INSERT |
| `password_verify` retorna false | Hash incorrecto | Regenerar hash con password_hash() |
| `Syntax error in SQL` | SQL malformado | Verificar comillas y caracteres especiales |
| `Admin no encontrado` | Email no existe | Verificar email exacto en BD |
| `Hash no funciona` | Costo bcrypt diferente | Usar `['cost' => 10]` estándar |

---

## 13. Script Completo de Gestión

```php
<?php
/**
 * admin_manager.php
 * CLI tool para gestión de admins
 */

require_once __DIR__ . '/vendor/autoload.php';

use Src\Database\Connection;

$command = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

function showUsage() {
  echo <<<USAGE
Uso: php admin_manager.php <comando> [email] [contraseña]

Comandos:
  create <email> <contraseña>    - Crear nuevo admin
  change <email> <contraseña>    - Cambiar contraseña
  list                            - Listar todos los admins
  delete <email>                  - Eliminar admin
  reset                           - Resetear admin por defecto

Ejemplos:
  php admin_manager.php create nuevo-admin@sg-ia.com MiContraseña123!
  php admin_manager.php change admin@sg-ia.com nueva-contraseña
  php admin_manager.php list
  php admin_manager.php reset

USAGE;
}

function getConnection() {
  $dbCfg = require __DIR__ . '/config/database.php';
  return new Connection($dbCfg);
}

switch($command) {
  case 'create':
    if (!$email || !$password) {
      echo "Error: Email y contraseña requeridos\n";
      showUsage();
      exit(1);
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $conn = getConnection();
    $stmt = $conn->pdo()->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    if ($stmt->execute([$email, $hash])) {
      echo "✓ Admin creado: {$email}\n";
    } else {
      echo "✗ Error al crear admin\n";
      exit(1);
    }
    break;

  case 'change':
    if (!$email || !$password) {
      echo "Error: Email y contraseña requeridos\n";
      exit(1);
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $conn = getConnection();
    $stmt = $conn->pdo()->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
    if ($stmt->execute([$hash, $email]) && $stmt->rowCount() > 0) {
      echo "✓ Contraseña actualizada: {$email}\n";
    } else {
      echo "✗ Admin no encontrado: {$email}\n";
      exit(1);
    }
    break;

  case 'list':
    $conn = getConnection();
    $stmt = $conn->pdo()->query('SELECT id, email, created_at, updated_at FROM admins');
    $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($admins)) {
      echo "No hay admins\n";
    } else {
      echo "\nAdmins registrados:\n";
      echo str_repeat("─", 60) . "\n";
      foreach ($admins as $admin) {
        echo "ID: {$admin['id']} | Email: {$admin['email']}\n";
        echo "  Creado: {$admin['created_at']}\n";
        echo "  Actualizado: {$admin['updated_at']}\n";
      }
      echo str_repeat("─", 60) . "\n";
    }
    break;

  case 'delete':
    if (!$email) {
      echo "Error: Email requerido\n";
      exit(1);
    }
    $conn = getConnection();
    $stmt = $conn->pdo()->prepare('DELETE FROM admins WHERE email = ?');
    if ($stmt->execute([$email]) && $stmt->rowCount() > 0) {
      echo "✓ Admin eliminado: {$email}\n";
    } else {
      echo "✗ Admin no encontrado: {$email}\n";
      exit(1);
    }
    break;

  case 'reset':
    $conn = getConnection();
    $hash = '$2y$10$7n3Lj5mK9xK8pQrLxZvN3O8qQ9r8sK7jL4mN6oP7qR8sT9uV0wX1y';
    $stmt = $conn->pdo()->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
    if ($stmt->execute([$hash, 'admin@sg-ia.com'])) {
      echo "✓ Admin por defecto reseteado\n";
      echo "  Email: admin@sg-ia.com\n";
      echo "  Password: admin123\n";
    } else {
      echo "✗ Error al resetear admin\n";
      exit(1);
    }
    break;

  default:
    showUsage();
    exit(1);
}
?>
```

**Uso**:
```bash
php admin_manager.php create nuevo@sg-ia.com contraseña
php admin_manager.php list
php admin_manager.php change admin@sg-ia.com nueva-pass
php admin_manager.php delete antiguo@sg-ia.com
php admin_manager.php reset
```

---

## 14. Entorno de Producción

### Recomendaciones de Seguridad

```bash
# .env (nunca commitar)
JWT_SECRET=5KR9x2p8Q1mK7nL9vH3sT6uY2zW4aB8cF1eG5jP9qX3dL7hM2sN6vC4wR8yZ1t5
ADMIN_EMAIL=admin-seguro@dominio.com
```

### Cambio de Contraseña Post-Deploy

```bash
# 1. Generar nueva contraseña
php -r "echo password_hash('nueva-contraseña-fuerte-123', PASSWORD_BCRYPT, ['cost' => 10]);"

# 2. Copiar el hash y ejecutar
mysql -u usuario -p < update_admin.sql
```

**update_admin.sql**:
```sql
UPDATE admins
SET password_hash = '$2y$10/HASH_AQUI'
WHERE email = 'admin@sg-ia.com';
```

---

**Última actualización**: 2025-11-23
