<?php

namespace App\Cron;

final class SiteScanner
{
    /**
     * Fetches a page and reduces it to the normalized text content used for signature comparison.
     */
    public function fetch(string $url): string
    {
        $ch = $this->newHandle($url);
        $html = curl_exec($ch);

        if ($html === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error ?: 'request failed');
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException("unexpected HTTP status {$status}");
        }

        return $this->extractRelevantContent((string) $html);
    }

    /**
     * Fetches multiple URLs concurrently and reduces each to normalized text content.
     *
     * @param string[] $urls
     * @return array<string, array{content: ?string, error: ?string}> keyed by URL
     */
    public function fetchMany(array $urls, int $concurrency = 10): array
    {
        $results = [];
        $queue = $urls;
        $multi = curl_multi_init();
        $active = [];

        $start = function (string $url) use (&$active, $multi): void {
            $ch = $this->newHandle($url);
            curl_multi_add_handle($multi, $ch);
            $active[(int) $ch] = ['handle' => $ch, 'url' => $url];
        };

        while (count($active) < $concurrency && $queue !== []) {
            $start(array_shift($queue));
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);

            while ($info = curl_multi_info_read($multi)) {
                $ch = $info['handle'];
                $id = (int) $ch;
                $url = $active[$id]['url'];

                $html = curl_multi_getcontent($ch);
                $error = curl_error($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($error !== '') {
                    $results[$url] = ['content' => null, 'error' => $error];
                } elseif ($status >= 400) {
                    $results[$url] = ['content' => null, 'error' => "unexpected HTTP status {$status}"];
                } else {
                    $results[$url] = ['content' => $this->extractRelevantContent((string) $html), 'error' => null];
                }

                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
                unset($active[$id]);

                if ($queue !== []) {
                    $start(array_shift($queue));
                    $running = 1;
                }
            }
        } while ($running > 0 || $active !== []);

        curl_multi_close($multi);

        return $results;
    }

    /**
     * @return \CurlHandle
     */
    private function newHandle(string $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => (int) (getenv('SITE_SCANNER_TIMEOUT') ?: 10),
            CURLOPT_USERAGENT => 'wp-monitor/1.0 (+site integrity check)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        return $ch;
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
