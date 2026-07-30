<?php

namespace App\Cron;

use PHPMailer\PHPMailer\PHPMailer;

abstract class Mailer
{
    protected function newMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: '';
        $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER') ?: '';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom(getenv('SMTP_USER') ?: 'wp-monitor@localhost', 'wp-monitor');

        return $mail;
    }
}
