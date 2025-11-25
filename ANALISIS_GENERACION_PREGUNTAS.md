# Análisis: Capacidad de Generación de Banco de Preguntas

## 📋 Estado Actual del Sistema

### Cómo funciona actualmente (On-Demand):
1. Cliente llama `GET /games/next?category_id=X&difficulty=Y`
2. Backend busca pregunta existente: `getActiveByDifficulty()`
3. Si existe pregunta verificada por admin → la retorna
4. Si NO existe → genera una nueva con IA (si está configurada)
5. La pregunta se guarda con `admin_verified = 0` (sin verificar)

**Línea relevante en GameService.php:65**
```php
return $this->generateAndSaveQuestion($categoryId, $difficulty);
```

---

## ⚠️ PROBLEMAS DETECTADOS

### 1. **Filtro de Verificación Admin (CRÍTICO)**
```php
// QuestionRepository.php:13
WHERE q.admin_verified = 1  // ← Solo retorna preguntas VERIFICADAS
```

**Impacto:**
- Preguntas generadas por IA se guardan con `admin_verified = 0`
- El siguiente request NO encuentra la pregunta recién creada
- Se genera OTRA pregunta (duplicada/redundante)
- Se desperdician llamadas a IA y recursos

**Síntoma:** Usuario ve preguntas nuevas cada vez, nunca se reutilizan

---

### 2. **Falta de Validación de Integridad de Opciones**
La IA genera las opciones, pero no hay re-verificación en backend:
```php
// GeminiAIService.php:265
if (!is_array($json['options']) || count($json['options']) !== 4) {
```

Problema: Si Gemini retorna opciones malformadas ocasionalmente, se guardan sin recurso.

---

### 3. **Límite de Tasa API (Rate Limiting)**
- Gemini tiene límites de tasa (depende del plan)
- Sin caché, cada pregunta = 1 llamada API
- Para generar banco de N preguntas: N llamadas API costosas
- Timeout: 30 segundos por pregunta

---

### 4. **Generación en Batch: Limitaciones**
```php
// AdminController.php:297-349
POST /admin/generate-batch
{
  "quantity": 5,      // ← Max 50
  "category_id": 1,
  "difficulty": 3
}
```

**Problemas:**
- Secuencial: genera 1 pregunta, espera, genera la siguiente
- SIN verificación de duplicados
- SIN transaction: si falla a mitad, deja datos inconsistentes
- Lentitud: 50 preguntas × 30s = 25 minutos
- Requiere admin authentication

---

## ✅ DIAGNÓSTICO: ¿Está listo para banco completo?

### Respuesta: **PARCIALMENTE NO**

| Aspecto | Estado | Severidad |
|---------|--------|-----------|
| Arquitectura de almacenamiento | ✅ Listo | - |
| Generación de preguntas | ⚠️ Funcional pero ineficiente | Media |
| Reutilización de preguntas | ❌ No funciona bien | **ALTA** |
| Batch generation | ⚠️ Existe pero lento | Media |
| Validación de integridad | ⚠️ Parcial | Baja |
| Manejo de errores | ⚠️ Básico | Media |
| Control de duplicados | ❌ No existe | **ALTA** |

---

## 🛠️ RECOMENDACIONES POR PRIORIDAD

### 🔴 ALTA PRIORIDAD (Implementar antes de usar)

#### 1. **Corregir Filtro de Búsqueda de Preguntas**
**Ubicación:** `QuestionRepository.php:10-26`

**Cambio recomendado:**
```php
// OPCIÓN A: Aceptar preguntas no verificadas en gameplay
public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
            ORDER BY q.id DESC LIMIT 1";
    // Eliminar: AND q.admin_verified=1
    // ...
}

// OPCIÓN B: Priorizar verificadas, aceptar no verificadas como fallback
public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
            ORDER BY q.admin_verified DESC, q.id DESC LIMIT 1";
    // Prioriza verificadas, pero acepta IA si no hay verificadas
    // ...
}
```

**Impacto:** Reutilización automática de preguntas generadas

---

#### 2. **Agregar Detección de Duplicados**
**Nueva función en QuestionRepository:**
```php
public function countByDifficulty(int $categoryId, int $difficulty): int {
    $sql = "SELECT COUNT(*) as total FROM questions
            WHERE is_active=1 AND category_id=:c AND difficulty=:d";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':c' => $categoryId, ':d' => $difficulty]);
    return (int)$st->fetch()['total'];
}
```

**Uso en AdminController (generateBatch):**
```php
// Antes de generar batch, verificar stock actual
$existing = $this->questions->countByDifficulty($categoryId, $difficulty);
if ($existing >= 20) { // Ej: máximo 20 por dificultad
    Response::json(['ok' => false, 'error' => 'Ya existen preguntas suficientes'], 400);
}
```

---

#### 3. **Mejorar Batch Generation (Transacciones)**
**Ubicación:** `AdminController.php:297-349`

```php
public function generateBatch(): void {
    // ... validaciones iniciales ...

    $pdo = $this->questions->getPdo();
    try {
        $pdo->beginTransaction();

        $generated = [];
        $failed = 0;

        for ($i = 0; $i < $quantity; $i++) {
            $question = $this->gameService->generateAndSaveQuestion($categoryId, $difficulty);

            if ($question) {
                $generated[] = $question;
                // Log cada éxito
                error_log("✓ Pregunta #{$question['id']} generada");
            } else {
                $failed++;
                error_log("✗ Pregunta #{$i} falló");
            }
        }

        $pdo->commit(); // ← Confirmar solo si todo OK

        Response::json([
            'ok' => true,
            'generated' => count($generated),
            'failed' => $failed,
            'questions' => $generated,
            'message' => "Generated " . count($generated) . " questions successfully"
        ], 201);
    } catch (\Throwable $e) {
        $pdo->rollBack(); // ← Revertir si hay error
        Response::json(['ok'=>false,'error'=> $e->getMessage()], 500);
    }
}
```

---

### 🟡 MEDIA PRIORIDAD (Mejoras importantes)

#### 4. **Implementar Caché de Preguntas**
Para evitar regenerar la misma pregunta:
```php
// Antes de llamar a Gemini
$cacheKey = "question_{$categoryId}_{$difficulty}";
if (apcu_exists($cacheKey)) {
    return apcu_fetch($cacheKey);
}

// Generar...
$result = $this->generativeAi->generateQuestion($categoryName, $difficulty);

// Guardar en caché 1 hora
apcu_store($cacheKey, $result, 3600);
```

---

#### 5. **Agregar Rate Limiting**
```php
// En GameController::next()
$rateLimitKey = "api_calls_" . $_SERVER['REMOTE_ADDR'];
$calls = apcu_fetch($rateLimitKey) ?? 0;

if ($calls > 100) { // 100 requests por hora
    Response::json(['ok' => false, 'error' => 'Rate limit exceeded'], 429);
    return;
}

apcu_store($rateLimitKey, $calls + 1, 3600);
```

---

#### 6. **Validar Opciones Antes de Guardar**
```php
// En GameService::generateAndSaveQuestion()
$generatedData = $this->generativeAi->generateQuestion($categoryName, $difficulty);

// Validar integridad
if (!$this->validateQuestionIntegrity($generatedData)) {
    error_log("Pregunta generada con formato inválido, reintentando...");
    // Reintentar máximo 3 veces
}
```

---

### 🟢 BAJA PRIORIDAD (Optimizaciones futuras)

#### 7. **Generar en Paralelo (Async)**
Usar workers/queues para generación asincrónica:
- Job Queue: Redis + PHP Worker
- No bloquear request HTTP
- Procesar en background

#### 8. **Implementar Audit Log**
Rastrear cada pregunta generada, quién la verificó, cuándo...

---

## 📊 PLAN DE ACCIÓN RECOMENDADO

### Fase 1: Crítica (1-2 horas)
```
[ ] 1. Corregir filtro admin_verified
[ ] 2. Añadir transacciones en batch generation
[ ] 3. Crear endpoint GET /admin/questions/stats para ver cobertura
```

### Fase 2: Importante (2-4 horas)
```
[ ] 4. Implementar detección de duplicados
[ ] 5. Agregar validación mejorada de integridad
[ ] 6. Optimizar batch generation (paralelo si es posible)
```

### Fase 3: Optimización (4+ horas)
```
[ ] 7. Implementar caché
[ ] 8. Agregar rate limiting
[ ] 9. Sistema de audit
```

---

## 🧪 PRUEBA RECOMENDADA

Ejecuta en Postman:

```postman
// 1. Generar batch de 10 preguntas
POST /admin/generate-batch
Authorization: Bearer {{token}}
{
  "quantity": 10,
  "category_id": 1,
  "difficulty": 3
}

// 2. Llamar /games/next 5 veces
GET /games/next?category_id=1&difficulty=3
GET /games/next?category_id=1&difficulty=3
GET /games/next?category_id=1&difficulty=3
GET /games/next?category_id=1&difficulty=3
GET /games/next?category_id=1&difficulty=3

// Esperado: Recibir las MISMAS 5 preguntas (reutilización)
// Actual: Probablemente diferentes (sin reutilización)
```

---

## 💡 CONCLUSIÓN

**¿Está listo para producción con banco completo?**

| Escenario | Recomendación |
|-----------|---|
| **Pruebas de desarrollo** | ✅ Sí, pero espera lentitud |
| **Pruebas QA con usuarios reales** | ⚠️ Necesita Fase 1 |
| **Producción** | ❌ Necesita Fases 1 + 2 |

**Después de implementar Fase 1:** Sistema será estable y eficiente para banco de hasta 500-1000 preguntas.

