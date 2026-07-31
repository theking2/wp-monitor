<?php

namespace App\Cron;

use App\Core\Logger;
use App\Cron\Imap\ImapMessage;

final class MailForwarder extends Mailer
{
    public function forward(ImapMessage $message): void
    {
        $to = getenv('FORWARD_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $subject = (string) $message->getSubject();

        $mail = $this->newMailer();
        $mail->addAddress($to);
        $mail->Subject = '[Fwd] ' . $subject;

        $html = $message->getHTMLBody();
        if ($html !== '') {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $message->getTextBody() ?: strip_tags($html);
        } else {
            $mail->isHTML(false);
            $mail->Body = $message->getTextBody() ?: '(no body)';
        }

        Logger::get()->info('Forwarding email', ['to' => $to, 'subject' => $subject]);
        $mail->send();
    }
}
