<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Src\Database\Connection;
use Src\Repositories\Implementations\AdminRepository;
use Src\Services\AuthService;
use Dotenv\Dotenv;

// Load Env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Config
$config = require __DIR__ . '/../config/database.php';

// Setup
echo "--- Starting Admin CRUD Integration Test ---\n";
try {
    $conn = new Connection($config);
    $pdo = $conn->pdo();
    $adminRepo = new AdminRepository($conn);
    $authService = new AuthService($pdo, $_ENV['JWT_SECRET'] ?? 'secret_test');

    $testEmail = 'test_crud_' . time() . '@sg-ia.com';
    $testPass = 'SecurePass123!';
    $testRole = 'admin';

    // 1. Create Admin
    echo "[TEST 1] Creating Admin... ";
    $hash = password_hash($testPass, PASSWORD_BCRYPT);
    $admin = $adminRepo->create($testEmail, $hash, $testRole);
    if ($admin && $admin->id > 0 && $admin->email === $testEmail) {
        echo "OK (ID: {$admin->id})\n";
    } else {
        throw new Exception("Failed to create admin");
    }

    // 2. Verify Database & Password Hash
    echo "[TEST 2] Verifying DB Hash... ";
    $stmt = $pdo->prepare("SELECT password_hash, is_active FROM admins WHERE id = ?");
    $stmt->execute([$admin->id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($testPass, $row['password_hash'])) {
        echo "OK (Hash matches)\n";
    } else {
        throw new Exception("Hash mismatch");
    }

    // 3. Test Login (Active)
    echo "[TEST 3] Testing Login (Active)... ";
    $login = $authService->login($testEmail, $testPass);
    if ($login['ok'] === true) {
        echo "OK (Token received)\n";
    } else {
        throw new Exception("Login failed: " . ($login['error'] ?? 'Unknown'));
    }

    // 4. Test Update
    echo "[TEST 4] Updating Admin Role... ";
    $updateOk = $adminRepo->update($admin->id, ['role' => 'superadmin']);
    $updatedAdmin = $adminRepo->find($admin->id);
    if ($updateOk && $updatedAdmin->role === 'superadmin') {
        echo "OK\n";
    } else {
        throw new Exception("Update failed");
    }

    // 5. Test Find by Email (Uniqueness check logic often handled in Controller/DB unique index, testing retrieval here)
    echo "[TEST 5] Find by Email... ";
    $found = $adminRepo->findByEmail($testEmail);
    if ($found && $found->id === $admin->id) {
        echo "OK\n";
    } else {
        throw new Exception("Find by email failed");
    }

    // 6. Test Logical Delete
    echo "[TEST 6] Logical Deletion (Deactivate)... ";
    $deleteOk = $adminRepo->delete($admin->id);
    $deletedAdmin = $adminRepo->find($admin->id);
    if ($deleteOk && $deletedAdmin->isActive === false) {
        echo "OK\n";
    } else {
        throw new Exception("Logical delete failed (isActive is " . ($deletedAdmin->isActive ? 'true' : 'false') . ")");
    }

    // 7. Test Login (Inactive) - Should Fail
    echo "[TEST 7] Testing Login (Inactive)... ";
    $loginInactive = $authService->login($testEmail, $testPass);
    if ($loginInactive['ok'] === false && strpos($loginInactive['error'], 'Invalid credentials') !== false) {
        // AuthService returns "Invalid credentials" generic error, but distinct from "Database error"
        // Since we checked credentials work in TEST 3, this failure must be due to is_active=0 check
        echo "OK (Login blocked)\n";
    } else {
        throw new Exception("Inactive login should have failed but got: " . json_encode($loginInactive));
    }

    // 8. Test Toggle Status (Re-activate)
    echo "[TEST 8] Re-activating... ";
    $adminRepo->updateStatus($admin->id, true);
    $reactivated = $adminRepo->find($admin->id);
    if ($reactivated->isActive === true) {
        echo "OK\n";
    } else {
        throw new Exception("Re-activation failed");
    }
    
    // Cleanup
    echo "[CLEANUP] Removing test user... ";
    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$admin->id]);
    echo "OK\n";

    echo "\nAll Admin CRUD tests passed successfully! ✅\n";

} catch (Exception $e) {
    echo "\nFAILED ❌: " . $e->getMessage() . "\n";
    if (isset($admin)) {
        // Attempt cleanup
        $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$admin->id]);
    }
    exit(1);
}
