<?php

namespace App\Cron;

final class SiteScanner
{
    /**
     * Fetches a page and reduces it to the normalized text content used for signature comparison.
     */
    public function fetch(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'wp-monitor/1.0 (+site integrity check)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $html = curl_exec($ch);
        if ($html === false) {
            $error = curl_error($ch);
            throw new \RuntimeException($error ?: 'request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status >= 400) {
            throw new \RuntimeException("unexpected HTTP status {$status}");
        }

        return $this->extractRelevantContent((string) $html);
    }

    private function extractRelevantContent(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//script | //style | //noscript | //comment()') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $title = $dom->getElementsByTagName('title')->item(0)?->textContent ?? '';
        $body = $dom->getElementsByTagName('body')->item(0)?->textContent ?? $dom->textContent;

        return $this->normalize($title . "\n" . $body);
    }

    /**
     * Collapses whitespace and masks tokens (hashes, timestamps, dates) that legitimately change
     * on every request, so those don't get flagged as tampering by themselves.
     */
    private function normalize(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $text = preg_replace('/\b[0-9a-f]{32,64}\b/i', '[hash]', $text) ?? $text;
        $text = preg_replace('/\b\d{1,2}:\d{2}(:\d{2})?\b/', '[time]', $text) ?? $text;
        $text = preg_replace('/\b(19|20)\d{2}-\d{2}-\d{2}\b/', '[date]', $text) ?? $text;

        return $text;
    }
}
