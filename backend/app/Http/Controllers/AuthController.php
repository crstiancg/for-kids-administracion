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
    /**
     * Mismo contrato que muni-asis-sitra / sistema-botica: `permisos` junta los
     * directos del usuario y los heredados de sus roles, sin repetidos.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->makeHidden(['roles', 'permissions']),
            'roles' => $user->getRoleNames(),
            'permisos' => $user->getAllPermissions()->pluck('name')->unique()->values(),
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
