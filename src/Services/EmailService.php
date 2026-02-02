<?php
namespace Src\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    public function sendPasswordResetCode(string $toEmail, string $code, string $language = 'es'): bool
    {
        $subject = $language === 'es' ? 'Código de recuperación de contraseña' : 'Password Reset Code';

        $messageEs = "Hola,<br><br>Tu código de verificación para restablecer tu contraseña es:<br><br><b>$code</b><br><br>Este código expirará en 15 minutos.<br><br>Si no solicitaste este cambio, ignora este correo.<br><br>SG-IA Sistema";
        $messageEn = "Hello,<br><br>Your verification code to reset your password is:<br><br><b>$code</b><br><br>This code will expire in 15 minutes.<br><br>If you did not request this change, please ignore this email.<br><br>SG-IA System";

        $body = $language === 'es' ? $messageEs : $messageEn;

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 465);

            // Recipients
            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@sg-ia.com',
                $_ENV['MAIL_FROM_NAME'] ?? 'SG-IA Sistema'
            );
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("[EmailService] Error sending email: {$mail->ErrorInfo}");
            return false;
        }
    }
}
