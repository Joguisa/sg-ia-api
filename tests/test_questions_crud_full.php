<?php
/**
 * Test Full Questions CRUD
 * Valida: Creation (AI), Edit, Verify, Unverify, Delete
 */
declare(strict_types=1);

// Configuración
$baseUrl = 'http://localhost:8000';
$adminEmail = 'admin@sg-ia.com';
$adminPassword = 'admin123';

// Colores
$red = "\033[31m";
$green = "\033[32m";
$yellow = "\033[33m";
$cyan = "\033[36m";
$reset = "\033[0m";

echo "═══════════════════════════════════════════════════════════\n";
echo " FULL QUESTIONS CRUD VALIDATION (AI GENERATION)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['code' => 0, 'error' => $error];

    return ['code' => $code, 'body' => json_decode($response, true)];
}

function printResult($step, $res, $expectedCode = 200) {
    global $green, $red, $reset, $yellow;
    echo "[$step] ";
    if ($res['code'] === $expectedCode) {
        echo "{$green}OK ({$res['code']}){$reset}\n";
        return true;
    } else {
        echo "{$red}FAIL ({$res['code']}){$reset} - " . json_encode($res['body'] ?? $res['error']) . "\n";
        return false;
    }
}

// 1. Login
echo "1. Authenticating...\n";
$login = makeRequest('POST', "$baseUrl/auth/login", ['email' => $adminEmail, 'password' => $adminPassword]);
if (!printResult("Login", $login)) die("Cannot continue without auth.\n");
$token = $login['body']['token'];
echo "   Token acquired using $adminEmail\n";

// 2. Get Categories (to pick one)
echo "\n2. Fetching Categories...\n";
$cats = makeRequest('GET', "$baseUrl/admin/categories", null, $token);
if (!printResult("Get Categories", $cats) || empty($cats['body']['categories'])) die("Need categories to continue.\n");
$categoryId = $cats['body']['categories'][0]['id']; // Pick first one
echo "   Using Category ID: $categoryId\n";

// 3. Generate Batch (AI Creation)
echo "\n3. Testing Creation via AI (Batch Generation)...\n";
$genData = [
    'quantity' => 1,
    'category_id' => $categoryId,
    'difficulty' => 1,
    'language' => 'es'
];
// This might take time, so we set a timeout if we were using real curl, but makeRequest uses default
echo "   Sending request to generate 1 question...\n";
$batchRes = makeRequest('POST', "$baseUrl/admin/generate-batch", $genData, $token);

if (!printResult("Generate Batch", $batchRes, 201)) {
    echo "{$yellow}⚠️  AI Generation failed. This might be due to API keys not being set or quota limits.{$reset}\n";
    // Try to proceed checking if we can find an existing question to test other CRUD ops?
    // No, better to stop or maybe Create a manual question? 
    // The requirement was "Creation is via AI". So I should report this failure.
    // However, for the sake of validating the REST of the CRUD, maybe I should use an existing question?
    // Let's see if we can get a question list and pick one.
    
    echo "   Attempting to fetch latest question to continue testing...\n";
    $listRes = makeRequest('GET', "$baseUrl/admin/questions", null, $token);
    if ($listRes['code'] === 200 && !empty($listRes['body']['questions'])) {
         $question = $listRes['body']['questions'][0];
         $questionId = $question['id'];
         echo "   {$yellow}Using existing Question ID: $questionId for further tests.{$reset}\n";
    } else {
         die("{$red}Cannot proceed. No questions generated and no existing questions found.{$reset}\n");
    }
} else {
    $generated = $batchRes['body']['questions'] ?? [];
    if (empty($generated)) {
         echo "{$yellow}Batch created but no questions returned. Maybe partial failure?{$reset}\n";
         // Try to fetch unverified
         $unvRes = makeRequest('GET', "$baseUrl/admin/unverified", null, $token);
         if (!empty($unvRes['body'])) {
             $questionId = $unvRes['body'][0]['id'];
             echo "   Found unverified question ID: $questionId\n";
         } else {
             die("No questions available.\n");
         }
    } else {
        $questionId = $generated[0]['id'];
        echo "   {$green}Generated Question ID: $questionId{$reset}\n";
        echo "   Statement: " . substr($generated[0]['statement'], 0, 50) . "...\n";
    }
}

// 4. Verify
echo "\n4. Testing Verification...\n";
$verifyRes = makeRequest('PATCH', "$baseUrl/admin/questions/$questionId/verify", ['verified' => true], $token);
printResult("Verify Question", $verifyRes);
if (($verifyRes['body']['question']['admin_verified'] ?? false) === true) echo "   {$green}Verified status confirmed.{$reset}\n";

// 5. Unverify
echo "\n5. Testing Unverification...\n";
$unverifyRes = makeRequest('PATCH', "$baseUrl/admin/questions/$questionId/verify", ['verified' => false], $token);
printResult("Unverify Question", $unverifyRes);
if (($unverifyRes['body']['question']['admin_verified'] ?? true) === false) echo "   {$green}Unverified status confirmed.{$reset}\n";

// 6. Edit (Full Update)
echo "\n6. Testing Edit (Full Update)...\n";
// First get full details to keep options
$fullRes = makeRequest('GET', "$baseUrl/admin/questions/$questionId/full", null, $token);
$fullData = $fullRes['body']['question'] ?? [];
if (empty($fullData)) die("Could not fetch full data for edit test.\n");

$newStatement = $fullData['statement'] . " (EDITED)";
$editData = [
    'statement' => $newStatement,
    'difficulty' => $fullData['difficulty'],
    'category_id' => $fullData['category_id'],
    'options' => $fullData['options'], // Keep options
    'explanation_correct' => 'Buena respuesta (Edited)',
    'explanation_incorrect' => 'Mala respuesta (Edited)'
];

$editRes = makeRequest('PUT', "$baseUrl/admin/questions/$questionId/full", $editData, $token);
printResult("Edit Question", $editRes);
if (str_contains($editRes['body']['question']['statement'] ?? '', '(EDITED)')) echo "   {$green}Statement update confirmed.{$reset}\n";

// 7. Delete
echo "\n7. Testing Deletion...\n";
$delRes = makeRequest('DELETE', "$baseUrl/admin/questions/$questionId", null, $token);
printResult("Delete Question", $delRes);

// Verify deletion
$checkRes = makeRequest('GET', "$baseUrl/admin/questions/$questionId/full", null, $token);
if ($checkRes['code'] === 404) {
    echo "   {$green}Confirmation: Question 404 not found (Deleted).{$reset}\n";
} else {
    echo "   {$red}Warning: Question still accessible or other error.{$reset}\n";
}

echo "\nCompleted.\n";
