<?php
namespace Src\Services;

use Src\Repositories\Interfaces\AdminRepositoryInterface;
use Src\Repositories\Interfaces\PasswordResetRepositoryInterface;
use Src\Services\EmailService;
use Src\Utils\Translations;

final class PasswordResetService
{
    public function __construct(
        private AdminRepositoryInterface $adminRepo,
        private PasswordResetRepositoryInterface $tokenRepo,
        private EmailService $emailService
    ) {
    }

    public function requestReset(string $email, string $language = 'es'): array
    {
        // Buscar admin por email
        $admin = $this->adminRepo->findByEmail($email);

        // Si el admin no existe, retornamos éxito igual para no revelar existencia de emails (seguridad)
        if (!$admin) {
            // Fake delay para evitar timing attacks
            usleep(random_int(100000, 300000));
            return ['message' => Translations::get('password_reset_code_sent', $language)];
        }

        // Invalidar tokens anteriores
        $this->tokenRepo->deleteByAdmin($admin->id);

        // Generar código de 6 dígitos
        $code = (string) random_int(100000, 999999);

        // Guardar token
        $this->tokenRepo->create($admin->id, $code);

        // Enviar email
        $this->emailService->sendPasswordResetCode($email, $code, $language);

        return ['message' => Translations::get('password_reset_code_sent', $language)];
    }

    public function verifyCode(string $code): array
    {
        $token = $this->tokenRepo->findByToken($code);

        if (!$token || !$token->isValid()) {
            throw new \RuntimeException('invalid_reset_code'); // Clave de traducción
        }

        return ['valid' => true];
    }

    public function resetPassword(string $code, string $newPassword): array
    {
        $token = $this->tokenRepo->findByToken($code);

        if (!$token || !$token->isValid()) {
            throw new \RuntimeException('invalid_reset_code');
        }

        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('password_too_short');
        }

        // Hashear password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Actualizar password del admin
        $this->adminRepo->update($token->adminId, ['password_hash' => $passwordHash]);

        // Marcar token como usado
        $this->tokenRepo->markAsUsed($code);

        return ['success' => true];
    }
}
