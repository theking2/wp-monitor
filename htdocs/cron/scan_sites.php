<?php

require __DIR__ . '/../api/bootstrap.php';

use App\Core\Logger;
use App\Cron\ReportMailer;
use App\Cron\SignatureComparer;
use App\Cron\SiteScanner;
use App\Models\Site;

$logger = Logger::get();

try {
    $scanner = new SiteScanner();
    $comparer = new SignatureComparer();
    $reportMailer = new ReportMailer();

    $sites = Site::all();
    $logger->info('scan_sites run started', ['site_count' => count($sites)]);

    foreach ($sites as $site) {
        $logger->debug('Scanning site', ['site_id' => $site['id'], 'url' => $site['url']]);

        try {
            $content = $scanner->fetch($site['url']);
        } catch (\Throwable $e) {
            $logger->error('Site fetch failed', [
                'site_id' => $site['id'],
                'url' => $site['url'],
                'error' => $e->getMessage(),
            ]);
            fwrite(STDERR, "[{$site['url']}] fetch failed: {$e->getMessage()}\n");
            continue;
        }

        $result = $comparer->compareAndStore($site, $content, 'cron');

        if ($result['tampered']) {
            $reportMailer->sendTamperReport($site, $result);
            fwrite(STDOUT, "[{$site['url']}] TAMPERED (similarity {$result['similarity']})\n");
        } else {
            fwrite(STDOUT, "[{$site['url']}] ok (similarity {$result['similarity']})\n");
        }
    }

    $logger->info('scan_sites run finished', ['site_count' => count($sites)]);
} catch (\Throwable $e) {
    $logger->error('scan_sites run failed', ['error' => $e->getMessage(), 'exception' => $e]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
