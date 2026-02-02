<?php
namespace Src\Controllers;

use Src\Services\PreferenceService;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class PreferenceController
{
    public function __construct(
        private PreferenceService $service
    ) {
    }

    public function getLanguage(array $params): void
    {
        $userType = $params['userType'] ?? '';
        $userId = (int) ($params['userId'] ?? 0);

        if ($userId <= 0 || !in_array($userType, ['admin', 'player'])) {
            Response::json(['ok' => false, 'error' => 'Invalid parameters'], 400);
        }

        $lang = $this->service->getUserLanguage($userType, $userId);
        Response::json(['ok' => true, 'language' => $lang]);
    }

    public function setLanguage(array $params): void
    {
        $userType = $params['userType'] ?? '';
        $userId = (int) ($params['userId'] ?? 0);

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $newLang = $data['language'] ?? '';

            if (empty($newLang)) {
                throw new \InvalidArgumentException('Language is required');
            }

            $result = $this->service->setUserLanguage($userType, $userId, $newLang);

            // Responder usando el NUEVO idioma para confirmar el cambio
            $msg = Translations::get('language_updated', $newLang);

            Response::json(['ok' => true, 'message' => $msg, 'data' => $result]);

        } catch (\InvalidArgumentException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function resetLanguage(array $params): void
    {
        $userType = $params['userType'] ?? '';
        $userId = (int) ($params['userId'] ?? 0);

        // Detectar idioma actual antes de borrar para responder
        $currentLang = LanguageDetector::detect();

        if ($this->service->resetUserLanguage($userType, $userId)) {
            Response::json([
                'ok' => true,
                'message' => Translations::get('language_reset', $currentLang)
            ]);
        } else {
            // No había preferencia guardada, es lo mismo que éxito
            Response::json([
                'ok' => true,
                'message' => Translations::get('language_reset', $currentLang)
            ]);
        }
    }
}
