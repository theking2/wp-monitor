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
        $haystack = \strtolower($subject . ' ' . $body);

        // "aktualisi" stems both "aktualisiert" (updated) and "Aktualisierung" (update, noun) —
        // WordPress auto-update notifications are frequently sent in the site's own language.
        $mentionsUpdate = \str_contains($haystack, 'update') || \str_contains($haystack, 'upgraded') || \str_contains($haystack, 'aktualisi');

        // Core-update notifications ("wurde automatisch auf WordPress 6.8.7 aktualisiert") don't
        // say "plugin"/"auto-update" at all, so a "WordPress <version>" mention is an alternative gate.
        $mentionsCoreVersion = \preg_match('/wordpress\s+\d+(?:\.\d+){1,3}/', $haystack) === 1;

        return $mentionsUpdate
            && (\str_contains($haystack, 'plugin') || \str_contains($haystack, 'auto-update') || $mentionsCoreVersion);
    }

    /**
     * A single notification email can report BOTH a "these failed" and a "these succeeded"
     * list in the same body (confirmed real-world case: a plugin fails, is rolled back, and a
     * different plugin succeeds, both listed under their own section headers with otherwise
     * identical "Plugin (von Version X auf Y)" formatting) — so status has to be determined by
     * which section a match falls in, not from the email as a whole.
     *
     * @return array<int, array{plugin: string, version: string, status: string}>
     */
    public function extractUpdates(string $body): array
    {
        $body = self::normalizeWhitespace($body);

        [$failedSection, $successSection] = self::splitBySection($body);

        $updates = self::extractFromSection($successSection ?? $body, 'success');

        if ($failedSection !== null) {
            \array_push($updates, ...self::extractFromSection($failedSection, 'failed'));
        }

        return $updates;
    }

    /**
     * @return array{0: ?string, 1: ?string} [failedSection, successSection] — either may be
     * null if that section's marker wasn't found. When neither is found, both are null and the
     * caller falls back to treating the whole body as one (assumed-success) section.
     */
    private static function splitBySection(string $body): array
    {
        $failedMarkers = ['konnten nicht aktualisiert werden', 'could not be updated', 'failed to update'];
        $successMarkers = ['sind jetzt auf dem neuesten stand', 'up to date now', 'have been updated'];

        $lower = \strtolower($body);

        $failedPos = self::firstMatchPosition($lower, $failedMarkers);
        $successPos = self::firstMatchPosition($lower, $successMarkers);

        if ($failedPos !== null && $successPos !== null) {
            return $failedPos < $successPos
                ? [\substr($body, $failedPos, $successPos - $failedPos), \substr($body, $successPos)]
                : [\substr($body, $failedPos), \substr($body, $successPos, $failedPos - $successPos)];
        }

        if ($failedPos !== null) {
            return [\substr($body, $failedPos), null];
        }

        if ($successPos !== null) {
            return [null, \substr($body, $successPos)];
        }

        return [null, null];
    }

    /**
     * German typography (and some mail clients) glue a number to its preceding word with a
     * non-breaking space rather than a plain one — invisible when read, but our patterns match
     * a literal " " so "Version\u{A0}4.2.3" silently fails to match. Collapse every non-breaking
     * space variant (U+00A0, U+202F) and stray tabs down to a plain space before matching;
     * newlines are left alone since section-splitting depends on them.
     */
    private static function normalizeWhitespace(string $text): string
    {
        return \str_replace(["\u{00A0}", "\u{202F}", "\t"], ' ', $text);
    }

    /** @param string[] $markers */
    private static function firstMatchPosition(string $haystack, array $markers): ?int
    {
        foreach ($markers as $marker) {
            $pos = \strpos($haystack, $marker);
            if ($pos !== false) {
                return $pos;
            }
        }

        return null;
    }

    /** @return array<int, array{plugin: string, version: string, status: string}> */
    private static function extractFromSection(string $text, string $status): array
    {
        $updates = [];

        if (\preg_match_all(
            '/([A-Za-z0-9][\w .\-\/]{1,80}?)\s+(?:was|were|has been|have been)?\s*updated(?: successfully)?(?: from [\w.\-]+)? to ([\w.\-]+)/i',
            $text,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $updates[] = ['plugin' => \trim($match[1]), 'version' => \trim($match[2]), 'status' => $status];
            }
        }

        // "Elementor Pro (von Version 4.2.0 auf 4.2.1)" — confirmed real-world format from a
        // German WordPress auto-update notification. Group 2 is the version it's actually still
        // on (relevant for a "failed" section); group 3 is the version it moved to on success.
        if ($updates === [] && \preg_match_all(
            '/([A-Za-z0-9][\w .\-\/]{1,80}?)\s*\(von Version ([\w.\-]+) auf ([\w.\-]+)\)/i',
            $text,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $version = $status === 'failed' ? \trim($match[2]) : \trim($match[3]);
                $updates[] = ['plugin' => \trim($match[1]), 'version' => $version, 'status' => $status];
            }
        }

        if ($updates === [] && \preg_match_all(
            '/([A-Za-z0-9][\w .\-\/]{1,80}?)\s+(?:wurde|wurden)\s+(?:von [\w.\-]+ )?auf ([\w.\-]+)\s+aktualisiert/i',
            $text,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $updates[] = ['plugin' => \trim($match[1]), 'version' => \trim($match[2]), 'status' => $status];
            }
        }

        // Core-update notifications name the product inline with the version ("auf WordPress
        // 6.8.7 aktualisiert" / "updated to WordPress 6.8.7") — the generic patterns above miss
        // this because their version group excludes spaces, and "WordPress" isn't a plugin name.
        // Anchored on "aktualisiert"/"updated to" right next to it so a merely-*available* newer
        // version mentioned elsewhere in the mail ("WordPress 7.0.3 ist ebenfalls verfügbar")
        // isn't picked up as if it had been installed.
        if ($updates === [] && \preg_match('/auf\s+wordpress\s+(\d+(?:\.\d+){1,3})\s+aktualisiert/i', $text, $m)) {
            $updates[] = ['plugin' => 'WordPress', 'version' => $m[1], 'status' => $status];
        } elseif ($updates === [] && \preg_match('/updated\s+to\s+wordpress\s+(\d+(?:\.\d+){1,3})/i', $text, $m)) {
            $updates[] = ['plugin' => 'WordPress', 'version' => $m[1], 'status' => $status];
        }

        if ($updates === []) {
            foreach (\preg_split('/\r?\n/', $text) as $line) {
                if (\preg_match('/^[\-\*\s]*([A-Za-z0-9][\w .\-\/]{1,80}?)\s+(\d+(?:\.\d+){1,3})\s*$/', \trim($line), $m)) {
                    $updates[] = ['plugin' => \trim($m[1]), 'version' => \trim($m[2]), 'status' => $status];
                }
            }
        }

        return $updates;
    }

    public function extractSiteUrl(string $body): ?string
    {
        // Trailing punctuation/backslash picked up from surrounding text/line-wrap artifacts
        // (confirmed real case: a trailing "\" on an otherwise-correct URL) isn't part of the URL.
        if (\preg_match('#https?://[^\s<>"\']+#i', $body, $m)) {
            return \rtrim($m[0], ".,;)\\");
        }

        return null;
    }

    /** A "[Site Name] ..." subject prefix is a common site-nickname convention for these notifications. */
    public function extractSiteName(string $subject): ?string
    {
        if (\preg_match('/^\[([^\]]+)\]/', \trim($subject), $m)) {
            return \trim($m[1]);
        }

        return null;
    }
}
