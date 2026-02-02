<?php
namespace Src\Repositories\Interfaces;

use Src\Models\PasswordResetToken;

interface PasswordResetRepositoryInterface
{
    public function create(int $adminId, string $token, int $expiryMinutes = 15): PasswordResetToken;
    public function findByToken(string $token): ?PasswordResetToken;
    public function markAsUsed(string $token): bool;
    public function deleteByAdmin(int $adminId): int;
    public function cleanExpired(): int;
}
