<?php

namespace App\Cron;

use App\Core\Logger;

/**
 * Runs a single-site integrity check end to end: fetch, outage tracking, signature comparison,
 * and tamper/drift report emails. Shared by the manual "scan now" action and any other trigger
 * that checks one site outside of the batched scan_sites.php cron run.
 */
final class SiteChecker
{
    public function __construct(
        private readonly SiteScanner $scanner = new SiteScanner(),
        private readonly SignatureComparer $comparer = new SignatureComparer(),
        private readonly OutageTracker $outageTracker = new OutageTracker(),
        private readonly ReportMailer $reportMailer = new ReportMailer(),
    ) {
    }

    /**
     * @param array<string, mixed> $site
     * @return array<string, mixed> the comparison result, or an error shape on fetch failure
     */
    public function check(array $site, string $triggeredBy): array
    {
        $logger = Logger::get();

        try {
            $content = $this->scanner->fetch($site['url']);
        } catch (\Throwable $e) {
            $logger->error('Site fetch failed', [
                'site_id' => $site['id'],
                'url' => $site['url'],
                'triggered_by' => $triggeredBy,
                'error' => $e->getMessage(),
            ]);
            try {
                $this->outageTracker->recordFailure($site, $e);
            } catch (\Throwable $trackerError) {
                $logger->error('Failed to record outage', ['site_id' => $site['id'], 'error' => $trackerError->getMessage()]);
            }

            return ['error' => $e->getMessage()];
        }

        try {
            $this->outageTracker->recordSuccess($site);
        } catch (\Throwable $e) {
            $logger->error('Failed to record recovery', ['site_id' => $site['id'], 'error' => $e->getMessage()]);
        }

        $result = $this->comparer->compareAndStore($site, $content, $triggeredBy);

        if ($result['tampered']) {
            try {
                $this->reportMailer->sendTamperReport($site, $result);
            } catch (\Throwable $e) {
                $logger->warning('Tamper report email failed to send', ['site_id' => $site['id'], 'error' => $e->getMessage()]);
            }
        } elseif ($result['drift']) {
            try {
                $this->reportMailer->sendDriftReport($site, $result);
            } catch (\Throwable $e) {
                $logger->warning('Drift report email failed to send', ['site_id' => $site['id'], 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }
}
