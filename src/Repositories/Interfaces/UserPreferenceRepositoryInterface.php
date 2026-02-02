<?php
namespace Src\Repositories\Interfaces;

use Src\Models\UserPreference;

interface UserPreferenceRepositoryInterface
{
    public function get(string $userType, int $userId): ?UserPreference;
    public function setLanguage(string $userType, int $userId, string $uiLanguage): UserPreference;
    public function delete(string $userType, int $userId): bool;
}
