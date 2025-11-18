<?php
declare(strict_types=1);

/**
 * DATABASE SEEDER
 * Imports seed data with proper constraint handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Database\Connection;

$config = require __DIR__ . '/../config/database.php';
$conn = new Connection($config);
$pdo = $conn->pdo();

echo "Database Seeder\n";
echo "═══════════════════════════════════════════════\n\n";

try {
    // Disable foreign key checks
    echo "Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Clear tables in order
    $tables = [
        'player_answers',
        'game_sessions',
        'question_explanations',
        'question_options',
        'questions',
        'content_sources',
        'question_categories'
    ];

    foreach ($tables as $table) {
        echo "Truncating {$table}...";
        $pdo->exec("TRUNCATE TABLE {$table}");
        echo " \n";
    }

    // Re-enable foreign key checks
    echo "\nRe-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Insert categories
    echo "\nInserting categories...\n";
    $pdo->exec("INSERT INTO question_categories (id, name, description) VALUES
    (1, 'Epidemiología y Generalidades', 'Datos globales, regionales y tasas de supervivencia.'),
    (2, 'Factores de Riesgo', 'Factores modificables (dieta, tabaco) y no modificables.'),
    (3, 'Tamizaje y Detección', 'Métodos de diagnóstico y edades recomendadas.'),
    (4, 'Prevención y Estilos de Vida', 'Hábitos saludables y autocuidado.')");
    echo "Categories inserted\n";

    // Insert sources
    echo "\nInserting content sources...\n";
    $pdo->exec("INSERT INTO content_sources (id, citation, url) VALUES
    (1, 'Guías de Salud Pública y Literatura Científica (Resumen Propuesta)', NULL),
    (2, 'Estadísticas Globales de Cáncer Colorrectal', NULL)");
    echo "Sources inserted\n";

    // Insert questions with options and explanations
    echo "\nInserting questions (11 total)...\n";

    $questions = [
        [
            'id' => 1,
            'statement' => '¿El cáncer de colon es una enfermedad que se puede prevenir con hábitos saludables?',
            'difficulty' => 1,
            'category_id' => 4,
            'source_id' => 1,
            'options' => [['Sí', 1], ['No', 0]],
            'explanation' => 'Correcto. El cáncer de colon es altamente prevenible mediante la adopción de estilos de vida saludables y chequeos regulares.'
        ],
        [
            'id' => 2,
            'statement' => '¿La inactividad física aumenta el riesgo de desarrollar cáncer de colon?',
            'difficulty' => 1,
            'category_id' => 2,
            'source_id' => 1,
            'options' => [['Verdadero', 1], ['Falso', 0]],
            'explanation' => 'Correcto. La inactividad física es un factor de riesgo modificable clave identificado en la literatura científica.'
        ],
        [
            'id' => 3,
            'statement' => 'Según las guías de salud pública actuales, ¿a qué edad se recomienda generalmente iniciar el tamizaje para cáncer de colon?',
            'difficulty' => 2,
            'category_id' => 3,
            'source_id' => 1,
            'options' => [['A los 20 años', 0], ['A los 45 años', 1], ['A los 65 años', 0], ['Cuando aparezcan síntomas graves', 0]],
            'explanation' => 'Correcto. Las recomendaciones actuales sugieren iniciar el tamizaje a los 45 años para una detección temprana efectiva.'
        ],
        [
            'id' => 4,
            'statement' => '¿Cuál de los siguientes alimentos se asocia con un mayor riesgo de cáncer de colon si se consume en exceso?',
            'difficulty' => 2,
            'category_id' => 2,
            'source_id' => 1,
            'options' => [['Frutas y verduras', 0], ['Pescado azul', 0], ['Carnes rojas y procesadas', 1], ['Granos integrales', 0]],
            'explanation' => 'Correcto. El consumo excesivo de carne roja y procesada es un factor de riesgo modificable reconocido.'
        ],
        [
            'id' => 5,
            'statement' => 'Además de la colonoscopía, ¿qué otra prueba se utiliza comúnmente para el tamizaje inicial?',
            'difficulty' => 3,
            'category_id' => 3,
            'source_id' => 1,
            'options' => [['Radiografía de tórax', 0], ['Prueba inmunoquímica fecal', 1], ['Examen de orina', 0], ['Electrocardiograma', 0]],
            'explanation' => 'Correcto. La prueba inmunoquímica fecal es un método de tamizaje validado y menos invasivo para la detección inicial.'
        ],
        [
            'id' => 6,
            'statement' => '¿Por qué es crítica la detección temprana del cáncer de colon?',
            'difficulty' => 3,
            'category_id' => 1,
            'source_id' => 1,
            'options' => [['Porque no existe tratamiento en etapas avanzadas', 0], ['Porque la supervivencia disminuye drásticamente entre la Etapa I y la IV', 1], ['Porque afecta solo a personas jóvenes', 0]],
            'explanation' => 'Correcto. Existe una gran diferencia en la supervivencia dependiendo de la etapa en que se detecte la enfermedad.'
        ],
        [
            'id' => 7,
            'statement' => 'Según la evidencia científica, ¿cuál es la tasa aproximada de supervivencia si el cáncer se detecta en la Etapa I?',
            'difficulty' => 4,
            'category_id' => 1,
            'source_id' => 1,
            'options' => [['Alrededor del 50%', 0], ['Aproximadamente el 91%', 1], ['Cerca del 14%', 0], ['El 100% siempre', 0]],
            'explanation' => 'Correcto. La detección en Etapa I ofrece una tasa de supervivencia muy alta, cercana al 91%.'
        ],
        [
            'id' => 8,
            'statement' => 'En contraste, ¿a cuánto desciende la tasa de supervivencia si el cáncer se detecta tardíamente en la Etapa IV?',
            'difficulty' => 4,
            'category_id' => 1,
            'source_id' => 1,
            'options' => [['Baja al 60%', 0], ['Baja drásticamente al 14%', 1], ['Se mantiene en el 80%', 0], ['Es del 5%', 0]],
            'explanation' => 'Correcto. La supervivencia en Etapa IV cae significativamente al 14%, lo que resalta la importancia del tamizaje.'
        ],
        [
            'id' => 9,
            'statement' => 'En términos estadísticos de riesgo (Odds Ratio - OR), ¿cuál es el impacto de tener historia familiar de cáncer de colon?',
            'difficulty' => 5,
            'category_id' => 2,
            'source_id' => 1,
            'options' => [['OR de 1.57 (Riesgo moderado)', 0], ['OR de 5.90 (Riesgo muy alto)', 1], ['OR de 1.44 (Riesgo leve)', 0], ['No tiene impacto estadístico', 0]],
            'explanation' => 'Correcto. La historia familiar es un factor de riesgo no modificable crítico, con un Odds Ratio elevado de 5.90.'
        ],
        [
            'id' => 10,
            'statement' => 'Según la literatura validada, ¿cuál es el Odds Ratio (OR) asociado al tabaquismo como factor de riesgo?',
            'difficulty' => 5,
            'category_id' => 2,
            'source_id' => 1,
            'options' => [['OR 1.44', 1], ['OR 5.90', 0], ['OR 0.35', 0], ['OR 2.50', 0]],
            'explanation' => 'Correcto. El tabaquismo presenta un OR de 1.44, lo que indica una asociación significativa con el aumento del riesgo.'
        ],
        [
            'id' => 11,
            'statement' => '¿Qué valor de Odds Ratio (OR) se atribuye a la obesidad como factor de riesgo para cáncer de colon?',
            'difficulty' => 5,
            'category_id' => 2,
            'source_id' => 1,
            'options' => [['OR 1.57', 1], ['OR 3.00', 0], ['OR 0.90', 0], ['OR 10.5', 0]],
            'explanation' => 'Correcto. La obesidad es un factor de riesgo modificable con un OR de 1.57 según los estudios referenciados.'
        ]
    ];

    foreach ($questions as $q) {
        // Insert question
        $stmt = $pdo->prepare(
            "INSERT INTO questions (id, statement, difficulty, category_id, source_id)
            VALUES (:id, :statement, :difficulty, :category_id, :source_id)"
        );
        $stmt->execute([
            ':id' => $q['id'],
            ':statement' => $q['statement'],
            ':difficulty' => $q['difficulty'],
            ':category_id' => $q['category_id'],
            ':source_id' => $q['source_id']
        ]);

        // Insert options
        foreach ($q['options'] as [$content, $isCorrect]) {
            $stmt = $pdo->prepare(
                "INSERT INTO question_options (question_id, content, is_correct)
                VALUES (:question_id, :content, :is_correct)"
            );
            $stmt->execute([
                ':question_id' => $q['id'],
                ':content' => $content,
                ':is_correct' => $isCorrect
            ]);
        }

        // Insert explanation
        $stmt = $pdo->prepare(
            "INSERT INTO question_explanations (question_id, text)
            VALUES (:question_id, :text)"
        );
        $stmt->execute([
            ':question_id' => $q['id'],
            ':text' => $q['explanation']
        ]);

        echo ".";
    }

    echo "\nQuestions inserted\n";

    echo "\n═══════════════════════════════════════════════\n";
    echo "Database seeded successfully!\n";
    echo "═══════════════════════════════════════════════\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}
