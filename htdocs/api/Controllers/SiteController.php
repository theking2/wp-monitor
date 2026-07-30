<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Alert;
use App\Models\Site;
use App\Models\Snapshot;

final class SiteController
{
    public function index(Request $request): void
    {
        Response::json(Site::all());
    }

    public function show(Request $request, array $params): void
    {
        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        $site['snapshots'] = Snapshot::forSite((int) $params['id']);
        $site['alerts'] = Alert::forSite((int) $params['id']);

        Response::json($site);
    }

    public function store(Request $request): void
    {
        $url = trim((string) ($request->body['url'] ?? ''));
        $name = trim((string) ($request->body['name'] ?? ''));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            Response::error('A valid url is required', 422);
            return;
        }

        if (Site::findByUrl($url) !== null) {
            Response::error('That site is already being monitored', 409);
            return;
        }

        $site = Site::create($name !== '' ? $name : $url, $url);
        Logger::get()->info('Site added for monitoring', ['site_id' => $site['id'], 'url' => $url]);

        Response::json($site, 201);
    }
}
