<?php

namespace App\Cron;

use App\Core\Logger;
use App\Cron\Imap\ImapMessage;

final class MailForwarder extends Mailer
{
    public function forward(ImapMessage $message): void
    {
        $to = \getenv('FORWARD_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $subject = (string) $message->getSubject();
        $from = $message->getFrom();

        $mail = $this->newMailer();
        $mail->addAddress($to);
        $mail->Subject = '[Fwd] ' . $subject;

        $html = $message->getHTMLBody();
        if ($html !== '') {
            $mail->isHTML(true);
            $mail->Body = self::withOriginalSenderHtml($from, $html);
            $mail->AltBody = self::withOriginalSenderText($from, $message->getTextBody() ?: \strip_tags($html));
        } else {
            $mail->isHTML(false);
            $mail->Body = self::withOriginalSenderText($from, $message->getTextBody() ?: '(no body)');
        }

        Logger::get()->info('Forwarding email', ['to' => $to, 'subject' => $subject]);
        $mail->send();
        Logger::get()->info('Forward accepted by SMTP server', [
            'to' => $to,
            'subject' => $subject,
            'smtp_response' => \trim($mail->getSMTPInstance()->getLastReply()),
        ]);
    }

    private static function withOriginalSenderText(string $from, string $body): string
    {
        if ($from === '') {
            return $body;
        }

        return "Original sender: {$from}\n\n{$body}";
    }

    private static function withOriginalSenderHtml(string $from, string $html): string
    {
        if ($from === '') {
            return $html;
        }

        $notice = '<p><strong>Original sender:</strong> ' . \htmlspecialchars($from, \ENT_QUOTES) . '</p>';

        return $notice . $html;
    }
}
