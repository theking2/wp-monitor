<?php

namespace App\Cron;

use App\Core\Logger;
use App\Models\Site;
use App\Models\SiteOutage;

/**
 * A fetch failure has no snapshot to attach an alert to (there's no content to compare), so this
 * is tracked separately from tampering. Alerts once per outage episode — not on every subsequent
 * failed cron run while a site stays down — and closes the episode out (plus a recovery email)
 * the moment a fetch succeeds again.
 */
final class OutageTracker
{
    private ReportMailer $reportMailer;

    public function __construct()
    {
        $this->reportMailer = new ReportMailer();
    }

    /** @param array<string, mixed> $site */
    public function recordFailure(array $site, \Throwable $e): void
    {
        Site::updateStatus($site['id'], 'unreachable');

        if (SiteOutage::findOpen($site['id']) !== null) {
            // Already open and already alerted on — this is a continuation, not a new episode.
            return;
        }

        SiteOutage::open($site['id'], $e->getMessage());
        Logger::get()->warning('Site marked unreachable', [
            'site_id' => $site['id'],
            'url' => $site['url'],
            'error' => $e->getMessage(),
        ]);

        try {
            $this->reportMailer->sendOutageReport($site, $e->getMessage());
        } catch (\Throwable $mailError) {
            Logger::get()->error('Failed to send outage report email', [
                'site_id' => $site['id'],
                'error' => $mailError->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $site */
    public function recordSuccess(array $site): void
    {
        $open = SiteOutage::findOpen($site['id']);
        if ($open === null) {
            return;
        }

        SiteOutage::resolve($open['id']);
        Logger::get()->info('Site recovered from outage', ['site_id' => $site['id'], 'outage_id' => $open['id']]);

        try {
            $this->reportMailer->sendRecoveryReport($site);
        } catch (\Throwable $mailError) {
            Logger::get()->error('Failed to send recovery report email', [
                'site_id' => $site['id'],
                'error' => $mailError->getMessage(),
            ]);
        }
    }
}
