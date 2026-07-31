<?php

/**
 * One-time maintenance script: creates a `sites` row for every distinct `site_url` already
 * recorded in `plugin_updates` that doesn't have one yet. Needed because Site::firstOrCreateByUrl()
 * was only wired into check_mail.php after some plugin-update emails were already processed — those
 * historical rows never got a matching site, and the source emails are already marked Seen, so
 * they won't be reprocessed on their own. Safe to run more than once (idempotent).
 *
 * Usage: php cron/backfill_sites_from_plugin_updates.php
 */

require __DIR__ . '/../api/bootstrap.php';

use App\Core\Database;
use App\Models\Site;

$urls = Database::connection()
    ->query("SELECT DISTINCT site_url FROM plugin_updates WHERE site_url IS NOT NULL AND site_url != ''")
    ->fetchAll(PDO::FETCH_COLUMN);

$created = 0;
$existing = 0;

foreach ($urls as $url) {
    // Same cleanup as PluginUpdateParser::extractSiteUrl() — some historical rows were stored
    // with trailing junk (confirmed real case: a stray trailing "\") from before that was fixed.
    $url = rtrim($url, "/.,;)\\");
    $before = Site::findByUrl($url);

    $site = Site::firstOrCreateByUrl($url, $url);

    if ($before === null) {
        $created++;
        echo "created: {$site['url']}\n";
    } else {
        $existing++;
    }
}

echo "\n{$created} site(s) created, {$existing} already existed, " . count($urls) . " distinct URL(s) checked.\n";
