<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Register a new user and issue their first API token.
     *
     * The form's RegisterRequest is reused as is: the rules, the email
     * canonicalization and the Portuguese messages are the same whichever door
     * the account is created through.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
        } catch (UniqueConstraintViolationException) {
            // Another request registered this email between validation and insert.
            throw ValidationException::withMessages([
                'email' => 'Este e-mail já está cadastrado.',
            ]);
        }

        event(new Registered($user));

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }
}
