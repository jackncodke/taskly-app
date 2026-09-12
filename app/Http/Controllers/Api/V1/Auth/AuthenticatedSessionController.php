<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedSessionController extends Controller
{
    /**
     * Issue an API token for the given credentials.
     *
     * The plain token is returned once and never again — only its hash is
     * stored — so a client that loses it has to log in for a new one.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = $request->retrieveUser();

        return response()->json([
            'token' => $user->createToken($request->deviceName())->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Revoke the token the request was made with.
     *
     * Only the current token is deleted, so signing out of one device leaves
     * the others signed in.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
