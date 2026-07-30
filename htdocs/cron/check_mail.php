<?php

require __DIR__ . '/../api/bootstrap.php';

use App\Core\Logger;
use App\Cron\MailForwarder;
use App\Cron\MailReader;
use App\Cron\PluginUpdateParser;
use App\Models\PluginUpdate;
use App\Models\ProcessedEmail;

$logger = Logger::get();

try {
    $reader = new MailReader();
    $parser = new PluginUpdateParser();
    $forwarder = new MailForwarder();

    $messages = $reader->fetchUnseen();
    $logger->info('check_mail run started', ['unseen_count' => count($messages)]);

    foreach ($messages as $message) {
        $messageId = (string) $message->getMessageId();
        if ($messageId === '') {
            $messageId = 'uid-' . $message->getUid();
        }

        if (ProcessedEmail::isProcessed($messageId)) {
            $logger->debug('Skipping already-processed message', ['message_id' => $messageId]);
            $reader->markSeen($message);
            continue;
        }

        $subject = (string) $message->getSubject();
        $body = $message->getTextBody() ?: strip_tags($message->getHTMLBody());

        if ($parser->looksLikePluginUpdate($subject, $body)) {
            $updates = $parser->extractUpdates($body);
            $siteUrl = $parser->extractSiteUrl($body);

            if ($updates === []) {
                // Recognized as an update notification but the specifics didn't match a known
                // pattern — keep a raw record instead of silently dropping it.
                $logger->warning('Recognized plugin-update email but could not parse specifics', [
                    'message_id' => $messageId,
                    'subject' => $subject,
                ]);
                PluginUpdate::create('(unparsed)', null, $siteUrl, mb_substr($body, 0, 2000));
            } else {
                $logger->info('Parsed plugin update notification', [
                    'message_id' => $messageId,
                    'count' => count($updates),
                ]);
                foreach ($updates as $update) {
                    PluginUpdate::create($update['plugin'], $update['version'], $siteUrl, mb_substr($body, 0, 2000));
                }
            }

            ProcessedEmail::markProcessed($messageId, $subject, 'plugin_update');
        } else {
            $logger->debug('Forwarding non-plugin-update email', ['message_id' => $messageId, 'subject' => $subject]);
            $forwarder->forward($message);
            ProcessedEmail::markProcessed($messageId, $subject, 'forwarded');
        }

        $reader->markSeen($message);
    }

    $logger->info('check_mail run finished', ['processed' => count($messages)]);
    echo count($messages) . " message(s) processed\n";
} catch (\Throwable $e) {
    $logger->error('check_mail run failed', ['error' => $e->getMessage(), 'exception' => $e]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
