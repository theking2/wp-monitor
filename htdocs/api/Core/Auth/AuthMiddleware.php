<?php

namespace App\Core\Auth;

use App\Core\Request;

final class AuthMiddleware
{
    public static function check(Request $request): bool
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return false;
        }

        return JwtService::verify($token) !== null;
    }
}
