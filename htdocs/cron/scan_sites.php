<?php

require __DIR__ . '/../api/bootstrap.php';

use App\Core\Logger;
use App\Cron\OutageTracker;
use App\Cron\ReportMailer;
use App\Cron\SignatureComparer;
use App\Cron\SiteScanner;
use App\Models\Site;

// A very large page could be memory-heavy to parse; same headroom as check_mail.php.
ini_set('memory_limit', getenv('CRON_MEMORY_LIMIT') ?: '512M');

$logger = Logger::get();

try {
    $scanner = new SiteScanner();
    $comparer = new SignatureComparer();
    $reportMailer = new ReportMailer();
    $outageTracker = new OutageTracker();

    $sites = Site::all();
    $logger->info('scan_sites run started', ['site_count' => count($sites)]);

    $concurrency = (int) (getenv('SCAN_CONCURRENCY') ?: 10);
    $fetchResults = $scanner->fetchMany(array_column($sites, 'url'), $concurrency);

    foreach ($sites as $site) {
        // Whole per-site body in one try/catch — same reasoning as check_mail.php: one site's
        // problem (fetch failure, a DB hiccup while recording it, ...) must not abort scanning
        // the rest of the batch.
        try {
            $logger->debug('Scanning site', ['site_id' => $site['id'], 'url' => $site['url']]);

            $fetchResult = $fetchResults[$site['url']] ?? ['content' => null, 'error' => 'no fetch result'];

            if ($fetchResult['error'] !== null) {
                $logger->error('Site fetch failed', [
                    'site_id' => $site['id'],
                    'url' => $site['url'],
                    'error' => $fetchResult['error'],
                ]);
                $outageTracker->recordFailure($site, new \RuntimeException($fetchResult['error']));
                fwrite(STDOUT, "[{$site['url']}] fetch failed: {$fetchResult['error']}\n");
                continue;
            }

            $content = $fetchResult['content'];
            $outageTracker->recordSuccess($site);

            $result = $comparer->compareAndStore($site, $content, 'cron');

            if ($result['tampered']) {
                $reportMailer->sendTamperReport($site, $result);
                fwrite(STDOUT, "[{$site['url']}] TAMPERED (similarity {$result['similarity']})\n");
            } elseif ($result['drift']) {
                $reportMailer->sendDriftReport($site, $result);
                fwrite(STDOUT, "[{$site['url']}] ok, drift accepted (similarity {$result['similarity']})\n");
            } else {
                fwrite(STDOUT, "[{$site['url']}] ok (similarity {$result['similarity']})\n");
            }
        } catch (\Throwable $e) {
            $logger->error('Failed to process site, will retry next run', [
                'site_id' => $site['id'],
                'url' => $site['url'],
                'error' => $e->getMessage(),
            ]);
            fwrite(STDOUT, "[{$site['url']}] failed: {$e->getMessage()}\n");
        }
    }

    $logger->info('scan_sites run finished', ['site_count' => count($sites)]);
} catch (\Throwable $e) {
    $logger->error('scan_sites run failed', ['error' => $e->getMessage(), 'exception' => $e]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
