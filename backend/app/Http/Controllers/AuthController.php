<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El login NO pasa por acá: el front pide el token directo a POST /oauth/token
 * (password grant de Passport). Este controlador cubre lo que viene después.
 */
class AuthController extends Controller
{
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->token();
        $token->refreshToken?->revoke();
        $token->revoke();

        return response()->noContent();
    }
}
