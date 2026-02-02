<?php
namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\UserPreference;
use Src\Repositories\Interfaces\UserPreferenceRepositoryInterface;

final class UserPreferenceRepository implements UserPreferenceRepositoryInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function get(string $userType, int $userId): ?UserPreference
    {
        $st = $this->db->pdo()->prepare(
            "SELECT * FROM user_preferences WHERE user_type = :type AND user_id = :id"
        );
        $st->execute([':type' => $userType, ':id' => $userId]);
        $r = $st->fetch();

        if (!$r)
            return null;

        return new UserPreference(
            (int) $r['id'],
            $r['user_type'],
            (int) $r['user_id'],
            $r['ui_language'],
            $r['created_at'],
            $r['updated_at']
        );
    }

    public function setLanguage(string $userType, int $userId, string $uiLanguage): UserPreference
    {
        $st = $this->db->pdo()->prepare(
            "INSERT INTO user_preferences (user_type, user_id, ui_language)
             VALUES (:type, :id, :lang)
             ON DUPLICATE KEY UPDATE ui_language = :lang_upd"
        );

        $st->execute([
            ':type' => $userType,
            ':id' => $userId,
            ':lang' => $uiLanguage,
            ':lang_upd' => $uiLanguage
        ]);

        return $this->get($userType, $userId);
    }

    public function delete(string $userType, int $userId): bool
    {
        $st = $this->db->pdo()->prepare(
            "DELETE FROM user_preferences WHERE user_type = :type AND user_id = :id"
        );
        $st->execute([':type' => $userType, ':id' => $userId]);
        return $st->rowCount() > 0;
    }
}
