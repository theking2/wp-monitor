<?php

require __DIR__ . '/../api/bootstrap.php';

use App\Core\Logger;
use App\Cron\MailForwarder;
use App\Cron\MailReader;
use App\Cron\PluginUpdateParser;
use App\Models\PluginUpdate;
use App\Models\ProcessedEmail;
use App\Models\Site;

// A single oversized message (huge attachment/embedded image) can otherwise exhaust memory
// while being decoded; give ourselves headroom before that becomes the limiting factor.
ini_set('memory_limit', getenv('CRON_MEMORY_LIMIT') ?: '512M');

$maxMessageSize = (int) (getenv('IMAP_MAX_MESSAGE_SIZE') ?: 20 * 1024 * 1024);

$logger = Logger::get();

try {
    $reader = new MailReader();
    $parser = new PluginUpdateParser();
    $forwarder = new MailForwarder();

    $messages = $reader->fetchUnseen();
    $logger->debug('check_mail run started', ['unseen_count' => count($messages)]);

    foreach ($messages as $message) {
        // Everything for this one message lives in this try/catch — so literally nothing about 
        // a single message (bad headers, a dropped IMAP connection mid-batch, a failed forward, ...)
        // can abort processing of the rest of the batch.
        try {
            $messageId = (string) $message->getMessageId();
            if ($messageId === '') {
                $messageId = 'uid-' . $message->getUid();
            }

            if (ProcessedEmail::isProcessed($messageId)) {
                $logger->debug('Skipping already-processed message', ['message_id' => $messageId]);
                $reader->markSeen($message);
                continue;
            }

            $size = $message->getSize();
            if ($size > $maxMessageSize) {
                $logger->warning('Skipping oversized message without decoding it', [
                    'message_id' => $messageId,
                    'size' => $size,
                    'max_size' => $maxMessageSize,
                ]);
                ProcessedEmail::markProcessed($messageId, null, 'skipped_too_large');
                $reader->markSeen($message);
                continue;
            }

            $message = $reader->fetchBody($message);
            $subject = (string) $message->getSubject();
            // strip_tags() only removes markup — it leaves entities like "&nbsp;" as literal
            // text, which then fails to match a plain-space pattern in the parser below.
            $body = $message->getTextBody() ?: html_entity_decode(strip_tags($message->getHTMLBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($parser->looksLikePluginUpdate($subject, $body)) {
                $updates = $parser->extractUpdates($body);
                $siteUrl = $parser->extractSiteUrl($body);

                if ($siteUrl !== null) {
                    // This is the actual "sites get discovered from mail" mechanism
                    $siteName = $parser->extractSiteName($subject) ?? $siteUrl;
                    $site = Site::firstOrCreateByUrl(rtrim($siteUrl, '/'), $siteName);
                    $logger->debug('Site discovered/matched from mail', ['site_id' => $site['id'], 'url' => $site['url']]);
                }

                if ($updates === []) {
                    // Recognized as an update notification but the specifics didn't match a
                    // known pattern — keep a raw record instead of silently dropping it.
                    $logger->warning('Recognized plugin-update email but could not parse specifics', [
                        'message_id' => $messageId,
                        'subject' => $subject,
                    ]);
                    PluginUpdate::create('(unparsed)', null, $siteUrl, mb_substr($body, 0, 2000), 'unknown');
                } else {
                    $failedCount = count(array_filter($updates, static fn($u) => $u['status'] === 'failed'));
                    $logger->info('Parsed plugin update notification', [
                        'message_id' => $messageId,
                        'count' => count($updates),
                        'failed_count' => $failedCount,
                    ]);
                    if ($failedCount > 0) {
                        $logger->warning('One or more plugin updates failed', [
                            'message_id' => $messageId,
                            'site_url' => $siteUrl,
                            'failed_count' => $failedCount,
                        ]);
                    }
                    foreach ($updates as $update) {
                        PluginUpdate::create($update['plugin'], $update['version'], $siteUrl, mb_substr($body, 0, 2000), $update['status']);
                    }
                }

                ProcessedEmail::markProcessed($messageId, $subject, 'plugin_update');
            } else {
                $logger->debug('Forwarding non-plugin-update email', ['message_id' => $messageId, 'subject' => $subject]);
                $forwarder->forward($message);
                ProcessedEmail::markProcessed($messageId, $subject, 'forwarded');
            }

            $reader->markSeen($message);
        } catch (\Throwable $e) {
            $logger->error('Failed to process message, will retry next run', [
                'uid' => $message->getUid(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    $reader->disconnect();
    $forwarder->closeConnection();

    $logger->debug('check_mail run finished', ['processed' => count($messages)]);
    echo count($messages) . " message(s) processed\n";
} catch (\Throwable $e) {
    $logger->error('check_mail run failed', ['error' => $e->getMessage(), 'exception' => $e]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
