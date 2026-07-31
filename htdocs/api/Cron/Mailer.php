<?php

namespace App\Cron;

use PHPMailer\PHPMailer\PHPMailer;

abstract class Mailer
{
    private ?PHPMailer $mailer = null;

    /**
     * Reuses one SMTP connection (SMTPKeepAlive) across every send() in the same run instead of
     * a fresh connect+STARTTLS+AUTH handshake per email — with a mailbox full of messages to
     * forward, that per-message handshake cost alone can add up to tens of seconds.
     */
    protected function newMailer(): PHPMailer
    {
        if ($this->mailer !== null) {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            return $this->mailer;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: '';
        $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER') ?: '';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        // Forcing STARTTLS regardless of port was a real mismatch risk (e.g. port 465 is
        // implicit TLS, not STARTTLS) — make it match whatever the account actually needs.
        $mail->SMTPSecure = match (strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls')) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'none', '' => '',
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
        $mail->SMTPKeepAlive = true;
        // PHPMailer's own default is 300s — an unreachable/firewalled/slow SMTP server would
        // otherwise block the whole cron run for up to 5 minutes on a single email before
        // failing. Fail fast instead; the caller's try/catch already handles a failed send.
        $mail->Timeout = (int) (getenv('SMTP_TIMEOUT') ?: 15);
        $mail->setFrom(getenv('SMTP_USER') ?: 'wp-monitor@localhost', 'wp-monitor');

        $this->mailer = $mail;

        return $mail;
    }

    public function closeConnection(): void
    {
        $this->mailer?->smtpClose();
    }
}
