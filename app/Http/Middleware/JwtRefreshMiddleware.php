<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtRefreshMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            // Expired tokens are allowed through — the refresh call in the
            // controller will issue a new token and blacklist the old one.
        } catch (JWTException) {
            return response()->json(['message' => 'Token is invalid or missing.'], 401);
        }

        return $next($request);
    }
}
