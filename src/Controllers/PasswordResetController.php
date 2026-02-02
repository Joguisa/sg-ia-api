<?php
namespace Src\Controllers;

use Src\Services\PasswordResetService;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;
use Src\Services\ValidationService;

final class PasswordResetController
{
    public function __construct(
        private PasswordResetService $service
    ) {
    }

    public function requestReset(): void
    {
        $lang = LanguageDetector::detect();
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ValidationService::requireFields($data, ['email']);

            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(Translations::get('invalid_email_format', $lang));
            }

            $result = $this->service->requestReset($email, $lang);
            Response::json(['ok' => true] + $result);

        } catch (\InvalidArgumentException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Log real error
            error_log("Password reset request error: " . $e->getMessage());
            Response::json(['ok' => false, 'error' => Translations::get('internal_error', $lang)], 500);
        }
    }

    public function verifyCode(): void
    {
        $lang = LanguageDetector::detect();
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ValidationService::requireFields($data, ['code']);

            $code = trim($data['code']);
            $this->service->verifyCode($code);

            Response::json(['ok' => true, 'valid' => true]);

        } catch (\RuntimeException $e) {
            // Traducir el mensaje de excepción si es una clave conocida
            $msg = Translations::get($e->getMessage(), $lang);
            // Si no hay traducción (devuelve la misma key), mostrar mensaje genérico o el error original
            if ($msg === $e->getMessage()) {
                $msg = $e->getMessage();
            }
            Response::json(['ok' => false, 'error' => $msg], 400);
        } catch (\Exception $e) {
            Response::json(['ok' => false, 'error' => Translations::get('internal_error', $lang)], 500);
        }
    }

    public function resetPassword(): void
    {
        $lang = LanguageDetector::detect();
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ValidationService::requireFields($data, ['code', 'new_password']);

            $code = trim($data['code']);
            $newPassword = $data['new_password'];

            $this->service->resetPassword($code, $newPassword);

            Response::json([
                'ok' => true,
                'message' => Translations::get('password_reset_successful', $lang)
            ]);

        } catch (\InvalidArgumentException $e) {
            $msg = Translations::get($e->getMessage(), $lang);
            Response::json(['ok' => false, 'error' => $msg], 400);
        } catch (\RuntimeException $e) {
            $msg = Translations::get($e->getMessage(), $lang);
            Response::json(['ok' => false, 'error' => $msg], 400);
        } catch (\Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            Response::json(['ok' => false, 'error' => Translations::get('internal_error', $lang)], 500);
        }
    }
}
