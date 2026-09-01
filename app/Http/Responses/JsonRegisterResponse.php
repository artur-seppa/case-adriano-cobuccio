<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;

class JsonRegisterResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'requires_email_verification' => is_null($user->email_verified_at),
        ], 201);
    }
}
