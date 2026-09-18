<?php

namespace App\Cron;

use App\Core\Logger;

final class ReportMailer extends Mailer
{
    /** @param array<string, mixed> $site @param array<string, mixed> $result */
    public function sendTamperReport(array $site, array $result): void
    {
        $to = \getenv('REPORT_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $mail = $this->newMailer();
        $to_array = \explode(';', $to);
        $to_array = \array_map('trim', $to_array);
        foreach ($to_array as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->Subject = "[wp-monitor] Possible tampering detected: {$site['name']}";
        $mail->isHTML(false);
        $mail->Body = \sprintf(
            "Site: %s (%s)\nSimilarity to last known signature: %.1f%%\n\nDiff summary:\n%s\n",
            $site['name'],
            $site['url'],
            $result['similarity'] * 100,
            $result['diff_summary'] ?? '(none)'
        );

        Logger::get()->warning('Sending tamper report email', ['site_id' => $site['id'], 'to' => $to]);
        $mail->send();
        Logger::get()->info('Tamper report accepted by SMTP server', [
            'site_id' => $site['id'],
            'to' => $to,
            'smtp_response' => \trim($mail->getSMTPInstance()->getLastReply()),
        ]);
    }

    /** @param array<string, mixed> $site @param array<string, mixed> $result */
    public function sendDriftReport(array $site, array $result): void
    {
        $to = \getenv('REPORT_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $mail = $this->newMailer();
        $to_array = \explode(';', $to);
        $to_array = \array_map('trim', $to_array);
        foreach ($to_array as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->Subject = "[wp-monitor] Content updated: {$site['name']}";
        $mail->isHTML(false);
        $mail->Body = \sprintf(
            "Site: %s (%s)\nSimilarity to previous signature: %.2f%%\nThis was within tolerance and has been accepted as the new signature.\n\nDiff summary:\n%s\n",
            $site['name'],
            $site['url'],
            $result['similarity'] * 100,
            $result['diff_summary'] ?? '(none)'
        );

        Logger::get()->info('Sending drift report email', ['site_id' => $site['id'], 'to' => $to]);
        $mail->send();
        Logger::get()->info('Drift report accepted by SMTP server', [
            'site_id' => $site['id'],
            'to' => $to,
            'smtp_response' => \trim($mail->getSMTPInstance()->getLastReply()),
        ]);
    }

    /** @param array<string, mixed> $site */
    public function sendOutageReport(array $site, string $errorMessage): void
    {
        $to = \getenv('REPORT_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $mail = $this->newMailer();
        $to_array = \explode(';', $to);
        $to_array = \array_map('trim', $to_array);
        foreach ($to_array as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->Subject = "[wp-monitor] Site unreachable: {$site['name']}";
        $mail->isHTML(false);
        $mail->Body = \sprintf(
            "Site: %s (%s)\nCould not be reached: %s\n",
            $site['name'],
            $site['url'],
            $errorMessage
        );

        Logger::get()->warning('Sending outage report email', ['site_id' => $site['id'], 'to' => $to]);
        $mail->send();
        Logger::get()->info('Outage report accepted by SMTP server', [
            'site_id' => $site['id'],
            'to' => $to,
            'smtp_response' => \trim($mail->getSMTPInstance()->getLastReply()),
        ]);
    }

    /** @param array<string, mixed> $site */
    public function sendRecoveryReport(array $site): void
    {
        $to = \getenv('REPORT_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $mail = $this->newMailer();
        $to_array = \explode(';', $to);
        $to_array = \array_map('trim', $to_array);
        foreach ($to_array as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->Subject = "[wp-monitor] Site reachable again: {$site['name']}";
        $mail->isHTML(false);
        $mail->Body = \sprintf("Site: %s (%s)\nIs reachable again.\n", $site['name'], $site['url']);

        Logger::get()->info('Sending recovery report email', ['site_id' => $site['id'], 'to' => $to]);
        $mail->send();
        Logger::get()->info('Recovery report accepted by SMTP server', [
            'site_id' => $site['id'],
            'to' => $to,
            'smtp_response' => \trim($mail->getSMTPInstance()->getLastReply()),
        ]);
    }
}
