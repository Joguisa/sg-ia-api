<?php
namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\PasswordResetToken;
use Src\Repositories\Interfaces\PasswordResetRepositoryInterface;

final class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function create(int $adminId, string $token, int $expiryMinutes = 15): PasswordResetToken
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

        $st = $this->db->pdo()->prepare(
            "INSERT INTO password_reset_tokens (admin_id, token, expires_at) 
             VALUES (:aid, :tok, :exp)"
        );
        $st->execute([
            ':aid' => $adminId,
            ':tok' => $token,
            ':exp' => $expiresAt
        ]);

        $id = (int) $this->db->pdo()->lastInsertId();

        return new PasswordResetToken(
            $id,
            $adminId,
            $token,
            $expiresAt,
            null,
            date('Y-m-d H:i:s')
        );
    }

    public function findByToken(string $token): ?PasswordResetToken
    {
        $st = $this->db->pdo()->prepare(
            "SELECT * FROM password_reset_tokens WHERE token = :tok LIMIT 1"
        );
        $st->execute([':tok' => $token]);
        $r = $st->fetch();

        if (!$r)
            return null;

        return new PasswordResetToken(
            (int) $r['id'],
            (int) $r['admin_id'],
            $r['token'],
            $r['expires_at'],
            $r['used_at'],
            $r['created_at']
        );
    }

    public function markAsUsed(string $token): bool
    {
        $st = $this->db->pdo()->prepare(
            "UPDATE password_reset_tokens 
             SET used_at = CURRENT_TIMESTAMP 
             WHERE token = :tok AND used_at IS NULL"
        );
        $st->execute([':tok' => $token]);
        return $st->rowCount() > 0;
    }

    public function deleteByAdmin(int $adminId): int
    {
        $st = $this->db->pdo()->prepare(
            "DELETE FROM password_reset_tokens WHERE admin_id = :aid"
        );
        $st->execute([':aid' => $adminId]);
        return $st->rowCount();
    }

    public function cleanExpired(): int
    {
        $st = $this->db->pdo()->prepare(
            "DELETE FROM password_reset_tokens WHERE expires_at < CURRENT_TIMESTAMP"
        );
        $st->execute();
        return $st->rowCount();
    }
}
