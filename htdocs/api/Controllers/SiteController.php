<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Alert;
use App\Models\Site;
use App\Models\SiteOutage;
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
        $site['outages'] = SiteOutage::forSite((int) $params['id']);

        Response::json($site);
    }

    public function store(Request $request): void
    {
        $url = \trim((string) ($request->body['url'] ?? ''));
        $name = \trim((string) ($request->body['name'] ?? ''));

        if ($url === '' || \filter_var($url, \FILTER_VALIDATE_URL) === false) {
            Response::error('A valid url is required', 422);
            return;
        }

        if (Site::findByUrl($url) !== null) {
            Response::error('That site is already being monitored', 409);
            return;
        }

        $site = Site::create($name !== '' ? $name : ($this->fetchTitle($url) ?? $url), $url);
        Logger::get()->info('Site added for monitoring', ['site_id' => $site['id'], 'url' => $url]);

        Response::json($site, 201);
    }

    public function destroy(Request $request, array $params): void
    {
        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        Site::delete($site['id']);
        Logger::get()->info('Site removed from monitoring', ['site_id' => $site['id'], 'url' => $site['url']]);

        Response::json(['deleted' => true]);
    }

    /**
     * Best-effort <title> lookup for a freshly-added site with no name given; any failure
     * (unreachable host, malformed HTML, empty <title>) just falls back to the URL as the name.
     */
    private function fetchTitle(string $url): ?string
    {
        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_TIMEOUT => (int) (\getenv('SITE_SCANNER_TIMEOUT') ?: 10),
            \CURLOPT_USERAGENT => 'wp-monitor/1.0 (+site title lookup)',
            \CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = \curl_exec($ch);
        $error = \curl_error($ch);
        \curl_close($ch);

        if ($error !== '' || !\is_string($html) || $html === '') {
            return null;
        }

        \libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        \libxml_clear_errors();

        $title = \trim($dom->getElementsByTagName('title')->item(0)?->textContent ?? '');

        return $title !== '' ? $title : null;
    }

    public function rename(Request $request, array $params): void
    {
        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        $name = \trim((string) ($request->body['name'] ?? ''));
        if ($name === '') {
            Response::error('Name cannot be empty', 422);
            return;
        }

        Logger::get()->info('Site renamed', [
            'site_id' => $site['id'],
            'old_name' => $site['name'],
            'new_name' => $name,
        ]);

        Response::json(Site::updateName($site['id'], $name));
    }

    public function updateSettings(Request $request, array $params): void
    {
        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        $sanityCheckEnabled = \array_key_exists('sanity_check_enabled', $request->body)
            ? (bool) $request->body['sanity_check_enabled']
            : null;
        $wpCronEnabled = \array_key_exists('wp_cron_enabled', $request->body)
            ? (bool) $request->body['wp_cron_enabled']
            : null;

        Logger::get()->info('Site scan settings updated', [
            'site_id' => $site['id'],
            'sanity_check_enabled' => $sanityCheckEnabled,
            'wp_cron_enabled' => $wpCronEnabled,
        ]);

        Response::json(Site::updateSettings($site['id'], $sanityCheckEnabled, $wpCronEnabled));
    }

    public function confirmChanges(Request $request, array $params): void
    {
        $site = Site::find((int) $params['id']);
        if ($site === null) {
            Response::error('Site not found', 404);
            return;
        }

        if ($site['status'] !== 'tampered') {
            Response::error('Site is not currently flagged as tampered', 409);
            return;
        }

        $snapshot = Snapshot::latest($site['id']);
        if ($snapshot === null) {
            Response::error('No snapshot to confirm', 409);
            return;
        }

        Snapshot::promoteToSignature($snapshot['id']);
        Site::updateStatus($site['id'], 'ok');
        Alert::resolveOpenForSite($site['id']);

        Logger::get()->info('Tampered content confirmed as legitimate', [
            'site_id' => $site['id'],
            'snapshot_id' => $snapshot['id'],
        ]);

        Response::json(Site::find($site['id']));
    }
}
