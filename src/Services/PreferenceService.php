<?php
namespace Src\Services;

use Src\Repositories\Interfaces\UserPreferenceRepositoryInterface;

final class PreferenceService
{
    public function __construct(
        private UserPreferenceRepositoryInterface $repo
    ) {
    }

    public function getUserLanguage(string $userType, int $userId): string
    {
        $pref = $this->repo->get($userType, $userId);
        return $pref ? $pref->uiLanguage : 'es'; // Default system language
    }

    public function setUserLanguage(string $userType, int $userId, string $language): array
    {
        if (!in_array($language, ['es', 'en'])) {
            throw new \InvalidArgumentException("Invalid language. Must be 'es' or 'en'");
        }

        if (!in_array($userType, ['admin', 'player'])) {
            throw new \InvalidArgumentException("Invalid user type. Must be 'admin' or 'player'");
        }

        $pref = $this->repo->setLanguage($userType, $userId, $language);

        return [
            'user_type' => $pref->userType,
            'user_id' => $pref->userId,
            'ui_language' => $pref->uiLanguage,
            'updated_at' => $pref->updatedAt ?? $pref->createdAt
        ];
    }

    public function resetUserLanguage(string $userType, int $userId): bool
    {
        return $this->repo->delete($userType, $userId);
    }
}
