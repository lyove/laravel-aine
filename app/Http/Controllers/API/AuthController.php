<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight session auth helpers for the blog frontend (same-origin SPA):
 * the frontend shares the platform login (web session) with the admin area,
 * so `me` just returns the currently authenticated user.
 */
class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
