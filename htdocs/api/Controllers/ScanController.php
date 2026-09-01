<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Cron\SiteChecker;
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

        $result = (new SiteChecker())->check($site, 'manual');

        if (isset($result['error'])) {
            Response::error('Could not fetch site: ' . $result['error'], 502);
            return;
        }

        Response::json($result);
    }
}
