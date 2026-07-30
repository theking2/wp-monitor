<?php

namespace App\Cron;

use App\Core\Logger;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

final class MailReader
{
    private ClientManager $manager;

    public function __construct()
    {
        $this->manager = new ClientManager([
            'default' => 'default',
            'accounts' => [
                'default' => [
                    'host' => getenv('IMAP_HOST') ?: '',
                    'port' => (int) (getenv('IMAP_PORT') ?: 993),
                    'encryption' => getenv('IMAP_ENCRYPTION') ?: 'ssl',
                    'validate_cert' => true,
                    'username' => getenv('IMAP_USER') ?: '',
                    'password' => getenv('IMAP_PASSWORD') ?: '',
                    'protocol' => 'imap',
                ],
            ],
        ]);
    }

    /** @return Message[] */
    public function fetchUnseen(): array
    {
        $mailbox = getenv('IMAP_MAILBOX') ?: 'INBOX';
        Logger::get()->debug('Connecting to IMAP mailbox', ['host' => getenv('IMAP_HOST') ?: '', 'mailbox' => $mailbox]);

        $client = $this->manager->account('default');
        $client->connect();

        $folder = $client->getFolder($mailbox);

        $messages = $folder->messages()
            ->whereUnseen()
            ->leaveUnread()
            ->setFetchOrder('asc')
            ->get()
            ->all();

        Logger::get()->debug('IMAP fetch complete', ['unseen_count' => count($messages)]);

        return $messages;
    }

    public function markSeen(Message $message): void
    {
        $message->setFlag('Seen');
    }
}
