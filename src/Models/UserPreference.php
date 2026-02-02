<?php
namespace Src\Models;

class UserPreference
{
    public function __construct(
        public int $id,
        public string $userType,
        public int $userId,
        public string $uiLanguage,
        public string $createdAt,
        public ?string $updatedAt = null
    ) {
    }
}
