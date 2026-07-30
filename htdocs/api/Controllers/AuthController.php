<?php

namespace App\Controllers;

use App\Core\Auth\JwtService;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

final class AuthController
{
    public function login(Request $request): void
    {
        $username = (string) ($request->body['username'] ?? '');
        $password = (string) ($request->body['password'] ?? '');

        $expectedUser = getenv('ADMIN_USERNAME') ?: '';
        $expectedHash = getenv('ADMIN_PASSWORD_HASH') ?: '';

        $valid = $username !== ''
            && $expectedUser !== ''
            && hash_equals($expectedUser, $username)
            && $expectedHash !== ''
            && password_verify($password, $expectedHash);

        if (!$valid) {
            Logger::get()->warning('Failed login attempt', ['username' => $username]);
            Response::error('Invalid credentials', 401);
            return;
        }

        Logger::get()->info('Admin login succeeded', ['username' => $username]);
        Response::json(['token' => JwtService::issue($username)]);
    }
}
