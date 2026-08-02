<?php

namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query = [];
    public array $body;
    public array $headers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = \parse_url($uri, \PHP_URL_PATH) ?: '/';
        $this->path = $path === '/' ? '/' : \rtrim($path, '/');

        \parse_str($_SERVER['QUERY_STRING'] ?? '', $this->query);

        $this->headers = \function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $this->body = $this->parseBody();
    }

    private function parseBody(): array
    {
        $raw = \file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $contentType = $this->headers['Content-Type'] ?? $this->headers['content-type'] ?? '';
        if (\str_contains($contentType, 'application/json')) {
            return \json_decode($raw, true) ?: [];
        }

        \parse_str($raw, $parsed);
        return $parsed;
    }

    public function bearerToken(): ?string
    {
        // Some Apache/PHP-FPM setups strip the Authorization header before PHP sees it, or
        // rename it during an internal rewrite — check every place it might have landed.
        $auth = $this->headers['Authorization']
            ?? $this->headers['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (\preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return $m[1];
        }

        return null;
    }
}
