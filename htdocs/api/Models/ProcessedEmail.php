<?php

namespace App\Models;

use App\Core\Database;

final class ProcessedEmail
{
    public static function isProcessed(string $messageId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM processed_emails WHERE message_id = :id');
        $stmt->execute(['id' => $messageId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function markProcessed(string $messageId, ?string $subject, string $classification): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT OR IGNORE INTO processed_emails (message_id, subject, classification)
             VALUES (:id, :subject, :classification)'
        );
        $stmt->execute(['id' => $messageId, 'subject' => $subject, 'classification' => $classification]);
    }
}
