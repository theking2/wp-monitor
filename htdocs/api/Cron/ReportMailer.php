<?php

namespace App\Cron;

use App\Core\Logger;

final class ReportMailer extends Mailer
{
    /** @param array<string, mixed> $site @param array<string, mixed> $result */
    public function sendTamperReport(array $site, array $result): void
    {
        $to = getenv('REPORT_TO_EMAIL') ?: '';
        if ($to === '') {
            return;
        }

        $mail = $this->newMailer();
        $mail->addAddress($to);
        $mail->Subject = "[wp-monitor] Possible tampering detected: {$site['name']}";
        $mail->isHTML(false);
        $mail->Body = sprintf(
            "Site: %s (%s)\nSimilarity to last known signature: %.1f%%\n\nDiff summary:\n%s\n",
            $site['name'],
            $site['url'],
            $result['similarity'] * 100,
            $result['diff_summary'] ?? '(none)'
        );

        Logger::get()->warning('Sending tamper report email', ['site_id' => $site['id'], 'to' => $to]);
        $mail->send();
    }
}
