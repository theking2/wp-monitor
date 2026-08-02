<?php

namespace App\Cron;

use App\Core\Logger;
use App\Cron\Imap\ImapClient;
use App\Cron\Imap\ImapMessage;

final class MailReader
{
    private ImapClient $client;

    public function __construct()
    {
        $this->client = new ImapClient();
    }

    /**
     * Headers/size only — deliberately does NOT fetch message bodies, so one huge or
     * oddly-encoded message can't cost memory before it's even been looked at. Call
     * fetchBody() per message instead, after checking getSize().
     *
     * @return ImapMessage[]
     */
    public function fetchUnseen(): array
    {
        $mailbox = \getenv('IMAP_MAILBOX') ?: 'INBOX';
        Logger::get()->debug('Connecting to IMAP mailbox', ['host' => \getenv('IMAP_HOST') ?: '', 'mailbox' => $mailbox]);

        $this->client->connect(
            \getenv('IMAP_HOST') ?: '',
            (int) (\getenv('IMAP_PORT') ?: 993),
            \getenv('IMAP_ENCRYPTION') ?: 'ssl'
        );
        $this->client->login(\getenv('IMAP_USER') ?: '', \getenv('IMAP_PASSWORD') ?: '');
        $this->client->selectMailbox($mailbox);

        $messages = [];
        foreach ($this->client->searchUnseen() as $uid) {
            $info = $this->client->fetchHeaderInfo($uid);
            $messages[] = new ImapMessage($uid, $info['size'], $info['header']);
        }

        Logger::get()->debug('IMAP fetch complete', ['unseen_count' => \count($messages)]);

        return $messages;
    }

    /** Re-fetches a single message with its body populated. */
    public function fetchBody(ImapMessage $message): ImapMessage
    {
        return $message->withRawMessage($this->client->fetchFullMessage($message->getUid()));
    }

    public function markSeen(ImapMessage $message): void
    {
        $this->client->markSeen($message->getUid());
    }

    public function disconnect(): void
    {
        $this->client->logout();
    }
}
