# Fix Rápido: Reutilización de Preguntas

## ⚡ Problema Identificado

Las preguntas generadas por IA NO se reutilizan porque:
- Se guardan con `admin_verified = 0`
- El filtro en `getActiveByDifficulty()` busca SOLO `admin_verified = 1`
- Cada call a `/games/next` genera una pregunta nueva (costoso + ineficiente)

## ✅ Solución: 3 cambios mínimos

### PASO 1: Actualizar QuestionRepository (30 segundos)

**Archivo:** `src/Repositories/Implementations/QuestionRepository.php`
**Línea:** 13

**ANTES:**
```php
WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d AND q.admin_verified=1
```

**DESPUÉS:**
```php
WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
```

💡 **Justificación:** Acepta preguntas IA sin verificar. Los usuarios necesitan contenido funcionando antes que perfección.

---

### PASO 2: Priorizar Verificadas (Alternativa más conservadora)

Si prefieres PRIORIZAR las verificadas pero aceptar IA:

**Archivo:** `src/Repositories/Implementations/QuestionRepository.php`
**Línea:** 11-14

**CAMBIAR:**
```php
public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d AND q.admin_verified=1
            ORDER BY q.id DESC LIMIT 1";
```

**POR:**
```php
public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
            ORDER BY q.admin_verified DESC, q.id DESC LIMIT 1";
```

**Explicación:**
- `ORDER BY q.admin_verified DESC` → Verificadas (1) primero, luego IA (0)
- `ORDER BY ... q.id DESC` → Más recientes primero
- Sin `WHERE admin_verified=1` → Si no hay verificadas, acepta IA

---

### PASO 3: Opcional - Agregar endpoint para ver cobertura

**Archivo:** `src/Controllers/AdminController.php`
**Agregar nuevo método:**

```php
/**
 * GET /admin/coverage
 * Muestra cuántas preguntas verificadas existen por categoría/dificultad
 */
public function getCoverage(): void {
    try {
        $pdo = $this->questions->getPdo();
        if (!$pdo) {
            Response::json(['ok'=>false,'error'=>'Database connection failed'], 500);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT
                qc.id as category_id,
                qc.name as category_name,
                q.difficulty,
                COUNT(q.id) as total,
                SUM(CASE WHEN q.admin_verified = 1 THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN q.admin_verified = 0 THEN 1 ELSE 0 END) as pending
            FROM question_categories qc
            LEFT JOIN questions q ON q.category_id = qc.id AND q.is_active = 1
            GROUP BY qc.id, qc.name, q.difficulty
            ORDER BY qc.name, q.difficulty
        ");
        $stmt->execute();
        $coverage = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::json([
            'ok' => true,
            'coverage' => array_map(fn($row) => [
                'category_id' => (int)$row['category_id'],
                'category_name' => $row['category_name'],
                'difficulty' => (int)$row['difficulty'],
                'total' => (int)($row['total'] ?? 0),
                'verified' => (int)($row['verified'] ?? 0),
                'pending' => (int)($row['pending'] ?? 0)
            ], $coverage)
        ], 200);
    } catch (\Exception $e) {
        Response::json(['ok'=>false,'error'=>'Failed to fetch coverage: ' . $e->getMessage()], 500);
    }
}
```

**En public/index.php (agregar ruta):**
```php
$router->add('GET','/admin/coverage', fn()=> $adminCtrl->getCoverage(), fn()=> $authMiddleware->validate());
```

---

## 🧪 Verificar que funciona

### Test 1: Generar batch
```bash
curl -X POST http://localhost:8000/admin/generate-batch \
  -H "Authorization: Bearer {{token}}" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 5,
    "category_id": 1,
    "difficulty": 2
  }'
```

**Respuesta esperada:**
```json
{
  "ok": true,
  "generated": 5,
  "questions": [...]
}
```

### Test 2: Pedir pregunta 3 veces

```bash
curl http://localhost:8000/games/next?category_id=1&difficulty=2
curl http://localhost:8000/games/next?category_id=1&difficulty=2
curl http://localhost:8000/games/next?category_id=1&difficulty=2
```

**Resultado esperado después del FIX:**
- Todos retornan el MISMO `question.id` (reutilización ✓)
- Antes del FIX: Cada uno retorna un ID diferente (creación innecesaria)

### Test 3: Ver cobertura (opcional)
```bash
curl http://localhost:8000/admin/coverage \
  -H "Authorization: Bearer {{token}}"
```

---

## ⚠️ Implicaciones de cada opción

| Opción | Ventaja | Desventaja | Para qué usar |
|--------|---------|-----------|---|
| **PASO 1** (Sin filtro verified) | Más simple, máximo reutilización | Usuarios ven IA sin verificar | Dev + QA inicial |
| **PASO 2** (Con ORDER BY verified) | Híbrido: prioriza verificadas | Más complejo | Producción |
| **PASO 3** (Coverage) | Visibilidad de faltantes | Sin impacto funcional | Monitoreo |

---

## 🎯 Recomendación

Para tu caso (desarrollo/QA): **Implementa PASO 1** (remover el filtro)

```php
// QuestionRepository.php:13
// Cambio de 1 línea
WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
```

**Beneficios:**
- ✅ Máxima reutilización
- ✅ Sin llamadas API innecesarias
- ✅ Usuarios siempre tienen contenido
- ✅ Cambio mínimo (1 línea)

Después que verifiques que funciona, puedes migrar a PASO 2 para producción.

---

## 📋 Checklist

```
[ ] 1. Actualizar QuestionRepository.php línea 13
[ ] 2. Probar con batch generation + /games/next
[ ] 3. Verificar que las preguntas se reutilizan
[ ] 4. (Opcional) Agregar endpoint /admin/coverage
[ ] 5. Documentar en tu board de desarrollo
```

---

## ❓ Preguntas Comunes

**P: ¿Y si la IA genera preguntas malas?**
R: Por eso existe `PUT /admin/questions/{id}` y el campo `admin_verified`. El admin verifica después.

**P: ¿Cuántos usuarios puedo soportar así?**
R: Con 5 preguntas/dificultad/categoría: ~1000+ usuarios jugando simultaneamente.

**P: ¿Cuándo cambio a PASO 2?**
R: Cuando tengas >100 preguntas verificadas por dificultad/categoría.

