<?php

namespace App\Cron;

/**
 * Best-effort heuristic parser for WordPress plugin/version update notification emails.
 * WordPress core, hosts, and plugins all phrase these differently, so this recognizes the
 * common "X was updated to Y" / bullet-list-of-plugin-and-version shapes rather than one
 * fixed template. Extend the patterns here as real-world formats show up unparsed.
 */
final class PluginUpdateParser
{
    public function looksLikePluginUpdate(string $subject, string $body): bool
    {
        $haystack = strtolower($subject . ' ' . $body);

        return (str_contains($haystack, 'plugin') || str_contains($haystack, 'auto-update'))
            && (str_contains($haystack, 'update') || str_contains($haystack, 'upgraded'));
    }

    /** @return array<int, array{plugin: string, version: string}> */
    public function extractUpdates(string $body): array
    {
        $updates = [];

        if (preg_match_all(
            '/([A-Za-z0-9][\w .\-\/]{1,80}?)\s+(?:was|were|has been|have been)?\s*updated(?: successfully)?(?: from [\w.\-]+)? to ([\w.\-]+)/i',
            $body,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $updates[] = ['plugin' => trim($match[1]), 'version' => trim($match[2])];
            }
        }

        if ($updates === []) {
            foreach (preg_split('/\r?\n/', $body) as $line) {
                if (preg_match('/^[\-\*\s]*([A-Za-z0-9][\w .\-\/]{1,80}?)\s+(\d+(?:\.\d+){1,3})\s*$/', trim($line), $m)) {
                    $updates[] = ['plugin' => trim($m[1]), 'version' => trim($m[2])];
                }
            }
        }

        return $updates;
    }

    public function extractSiteUrl(string $body): ?string
    {
        if (preg_match('#https?://[^\s<>"\']+#i', $body, $m)) {
            return rtrim($m[0], '.,;)');
        }

        return null;
    }
}
