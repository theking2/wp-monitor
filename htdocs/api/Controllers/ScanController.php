<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Cron\OutageTracker;
use App\Cron\ReportMailer;
use App\Cron\SignatureComparer;
use App\Cron\SiteScanner;
use App\Models\Site;

final class ScanController
{
    public function scan(Request $request, array $params): void
    {
        $logger = Logger::get();

        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        $logger->debug('Manual scan requested', ['site_id' => $site['id'], 'url' => $site['url']]);

        try {
            $content = (new SiteScanner())->fetch($site['url']);
        } catch (\Throwable $e) {
            $logger->error('Manual scan fetch failed', [
                'site_id' => $site['id'],
                'url' => $site['url'],
                'error' => $e->getMessage(),
            ]);
            try {
                (new OutageTracker())->recordFailure($site, $e);
            } catch (\Throwable $trackerError) {
                $logger->error('Failed to record outage', ['site_id' => $site['id'], 'error' => $trackerError->getMessage()]);
            }
            Response::error('Could not fetch site: ' . $e->getMessage(), 502);
            return;
        }

        try {
            (new OutageTracker())->recordSuccess($site);
        } catch (\Throwable $e) {
            $logger->error('Failed to record recovery', ['site_id' => $site['id'], 'error' => $e->getMessage()]);
        }

        $result = (new SignatureComparer())->compareAndStore($site, $content, 'manual');

        if ($result['tampered']) {
            try {
                (new ReportMailer())->sendTamperReport($site, $result);
            } catch (\Throwable $e) {
                $logger->warning('Tamper report email failed to send', [
                    'site_id' => $site['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($result['drift']) {
            try {
                (new ReportMailer())->sendDriftReport($site, $result);
            } catch (\Throwable $e) {
                $logger->warning('Drift report email failed to send', [
                    'site_id' => $site['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Response::json($result);
    }
}
