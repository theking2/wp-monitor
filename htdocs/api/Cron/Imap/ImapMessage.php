<?php

namespace App\Cron\Imap;

/**
 * A single email, lazily upgradeable from "headers only" to "full source". Deliberately does its
 * own small MIME walk and charset conversion (via mb_convert_encoding, never iconv's `//IGNORE`
 * form) rather than depend on a library for it — see ImapClient's docblock for why.
 */
final class ImapMessage
{
    /** @var array<string, string> */
    private array $headers;
    private ?string $textBody = null;
    private ?string $htmlBody = null;
    private bool $bodyParsed = false;

    public function __construct(
        private readonly int $uid,
        private readonly int $size,
        string $headerBlock,
        private readonly ?string $rawMessage = null
    ) {
        $this->headers = self::parseHeaders($headerBlock);
    }

    public function withRawMessage(string $rawMessage): self
    {
        return new self($this->uid, $this->size, self::splitHeaderAndBody($rawMessage)[0], $rawMessage);
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMessageId(): string
    {
        return \trim($this->headers['message-id'] ?? '');
    }

    public function getSubject(): string
    {
        $raw = $this->headers['subject'] ?? '';

        return $raw === '' ? '' : self::decodeMimeHeader($raw);
    }

    public function getTextBody(): string
    {
        $this->ensureBodyParsed();

        return $this->textBody ?? '';
    }

    public function getHTMLBody(): string
    {
        $this->ensureBodyParsed();

        return $this->htmlBody ?? '';
    }

    private function ensureBodyParsed(): void
    {
        if ($this->bodyParsed) {
            return;
        }

        $this->bodyParsed = true;

        if ($this->rawMessage === null) {
            return;
        }

        [$headerBlock, $body] = self::splitHeaderAndBody($this->rawMessage);
        $topHeaders = self::parseHeaders($headerBlock);

        $this->walkPart(
            $topHeaders['content-type'] ?? 'text/plain',
            $topHeaders['content-transfer-encoding'] ?? '7bit',
            $body
        );
    }

    private function walkPart(string $contentType, string $encoding, string $body): void
    {
        if ($this->textBody !== null && $this->htmlBody !== null) {
            return;
        }

        if (\preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
            foreach (self::splitByBoundary($body, $m[1]) as $part) {
                [$partHeaderBlock, $partBody] = self::splitHeaderAndBody($part);
                $partHeaders = self::parseHeaders($partHeaderBlock);

                $this->walkPart(
                    $partHeaders['content-type'] ?? 'text/plain',
                    $partHeaders['content-transfer-encoding'] ?? '7bit',
                    $partBody
                );

                if ($this->textBody !== null && $this->htmlBody !== null) {
                    return;
                }
            }

            return;
        }

        $decoded = self::convertToUtf8(
            self::decodeContent($body, $encoding),
            self::extractCharset($contentType)
        );

        if ($this->textBody === null && \stripos($contentType, 'text/plain') !== false) {
            $this->textBody = $decoded;
        } elseif ($this->htmlBody === null && \stripos($contentType, 'text/html') !== false) {
            $this->htmlBody = $decoded;
        } elseif ($this->textBody === null && $this->htmlBody === null && \stripos($contentType, 'text/') === 0) {
            // No Content-Type at all is technically "text/plain us-ascii" per RFC 2045
            $this->textBody = $decoded;
        }
    }

    /** @return array{0: string, 1: string} */
    private static function splitHeaderAndBody(string $raw): array
    {
        $raw = \ltrim($raw, "\r\n");

        $pos = \strpos($raw, "\r\n\r\n");
        $sepLength = 4;
        if ($pos === false) {
            $pos = \strpos($raw, "\n\n");
            $sepLength = 2;
        }

        if ($pos === false) {
            return [$raw, ''];
        }

        return [\substr($raw, 0, $pos), \substr($raw, $pos + $sepLength)];
    }

    /** @return array<string, string> */
    private static function parseHeaders(string $block): array
    {
        $block = \preg_replace("/\r?\n[ \t]+/", ' ', $block) ?? $block; // unfold continuation lines

        $headers = [];
        foreach (\preg_split('/\r?\n/', $block) ?: [] as $line) {
            if (!\str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = \explode(':', $line, 2);
            $key = \strtolower(\trim($name));
            if (!isset($headers[$key])) {
                $headers[$key] = \trim($value);
            }
        }

        return $headers;
    }

    private static function extractCharset(string $contentType): string
    {
        if (\preg_match('/charset="?([^";\s]+)"?/i', $contentType, $m)) {
            return \strtoupper($m[1]);
        }

        return 'US-ASCII';
    }

    private static function decodeContent(string $body, string $encoding): string
    {
        return match (\strtolower(\trim($encoding))) {
            'base64' => \base64_decode(\preg_replace('/\s+/', '', $body) ?? $body) ?: '',
            'quoted-printable' => \quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Deliberately mb_convert_encoding, not iconv(...'//IGNORE'...): the latter has a known
     * glibc bug that can exhaust memory converting even a tiny string with a mismatched
     * declared charset. A body-decoding quirk must never be able to take the whole cron down.
     */
    private static function convertToUtf8(string $str, string $charset): string
    {
        if ($str === '' || \in_array($charset, ['UTF-8', 'US-ASCII', 'ASCII'], true)) {
            return $str;
        }

        try {
            $converted = @\mb_convert_encoding($str, 'UTF-8', $charset);
            return $converted !== false ? $converted : $str;
        } catch (\Throwable) {
            return $str;
        }
    }

    /**
     * RFC 2047 encoded-word ("=?charset?B|Q?text?=") decoder for headers, replacing
     * mb_decode_mimeheader() — deprecated since PHP 8.2 and, confirmed in production, prone to
     * mojibake (misreads the decoded bytes' encoding) and to leaving a stray space between two
     * adjacent encoded-words that a long subject got split across, which RFC 2047 says must be
     * removed when rejoining (that gap is line-folding whitespace, not real content).
     */
    private static function decodeMimeHeader(string $header): string
    {
        $decoded = \preg_replace_callback(
            '/=\?([^?]+)\?([BbQq])\?([^?]*)\?=(?:\s+(?==\?))?/',
            static function (array $m): string {
                $bytes = \strtoupper($m[2]) === 'B'
                    ? (\base64_decode($m[3]) ?: '')
                    : \quoted_printable_decode(\str_replace('_', ' ', $m[3]));

                return self::convertToUtf8($bytes, \strtoupper($m[1]));
            },
            $header
        );

        return $decoded ?? $header;
    }

    /** @return string[] */
    private static function splitByBoundary(string $body, string $boundary): array
    {
        $parts = \preg_split('/--' . \preg_quote($boundary, '/') . '(--)?\r?\n/', $body) ?: [];

        \array_shift($parts); // preamble before the first boundary
        if ($parts !== []) {
            \array_pop($parts); // epilogue after the closing boundary
        }

        return $parts;
    }
}
