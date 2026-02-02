<?php
namespace Src\Models;

class PasswordResetToken
{
    public function __construct(
        public int $id,
        public int $adminId,
        public string $token,
        public string $expiresAt,
        public ?string $usedAt,
        public string $createdAt
    ) {
    }

    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) < time();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
}
