<?php

namespace App\Cron\Imap;

/**
 * Minimal IMAP4rev1 client covering exactly what wp-monitor needs: login, select a mailbox,
 * search for unseen messages, fetch a message's size/subject/message-id cheaply, fetch a
 * message's full source on demand, and mark a message Seen.
 *
 * This intentionally is not a general-purpose IMAP library (no mailbox listing, no attachment
 * handling, no international mailbox names, ...) — it replaces webklex/php-imap, which pulled in
 * Laravel's illuminate/* and Symfony components for basic collections and, more importantly, had
 * a body-decoding path (iconv with //IGNORE) known to exhaust memory on some messages regardless
 * of their size. Keeping this small and owned means we control decoding ourselves instead.
 */
final class ImapClient
{
    /** @var resource */
    private $stream;
    private int $tagCounter = 0;

    public function connect(string $host, int $port, string $encryption): void
    {
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';

        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $stream = @stream_socket_client(
            "$scheme://$host:$port",
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($stream === false) {
            throw new \RuntimeException("Could not connect to {$host}:{$port} — {$errstr}");
        }

        $this->stream = $stream;
        stream_set_timeout($this->stream, 30);

        $this->readRawLine(); // server greeting

        if ($encryption === 'tls') {
            $this->runCommand('STARTTLS');
            if (!stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('IMAP STARTTLS negotiation failed');
            }
        }
    }

    public function login(string $user, string $password): void
    {
        $this->runCommand('LOGIN ' . $this->quote($user) . ' ' . $this->quote($password));
    }

    public function selectMailbox(string $mailbox): void
    {
        $this->runCommand('SELECT ' . $this->quote($mailbox));
    }

    /** @return int[] */
    public function searchUnseen(): array
    {
        $result = $this->runCommand('UID SEARCH UNSEEN');

        foreach ($result['lines'] as $line) {
            if (preg_match('/^\* SEARCH(.*)\r?\n$/i', $line, $m)) {
                return array_map('intval', array_filter(preg_split('/\s+/', trim($m[1])) ?: []));
            }
        }

        return [];
    }

    /** @return array{size: int, header: string} */
    public function fetchHeaderInfo(int $uid): array
    {
        $result = $this->runCommand("UID FETCH {$uid} (RFC822.SIZE BODY.PEEK[HEADER.FIELDS (SUBJECT MESSAGE-ID)])");

        $size = 0;
        foreach ($result['lines'] as $line) {
            if (preg_match('/RFC822\.SIZE (\d+)/', $line, $m)) {
                $size = (int) $m[1];
                break;
            }
        }

        return ['size' => $size, 'header' => $result['literal'] ?? ''];
    }

    public function fetchFullMessage(int $uid): string
    {
        $result = $this->runCommand("UID FETCH {$uid} (BODY.PEEK[])");

        return $result['literal'] ?? '';
    }

    public function markSeen(int $uid): void
    {
        $this->runCommand("UID STORE {$uid} +FLAGS (\\Seen)");
    }

    public function logout(): void
    {
        try {
            $this->runCommand('LOGOUT');
        } catch (\Throwable) {
            // best effort
        }

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /** @return array{lines: string[], literal: ?string} */
    private function runCommand(string $command): array
    {
        $tag = sprintf('A%04d', ++$this->tagCounter);
        fwrite($this->stream, "{$tag} {$command}\r\n");

        $lines = [];
        $literal = null;

        while (true) {
            $line = $this->readRawLine();
            $lines[] = $line;

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $literal = $this->readBytes((int) $m[1]);
                continue;
            }

            if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b(.*)$/i', $line, $m)) {
                if (strtoupper($m[1]) !== 'OK') {
                    throw new \RuntimeException("IMAP command failed ({$command}): " . trim($m[2]));
                }
                break;
            }
        }

        return ['lines' => $lines, 'literal' => $literal];
    }

    private function readRawLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            throw new \RuntimeException('IMAP connection closed unexpectedly');
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('IMAP connection closed while reading a literal');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
