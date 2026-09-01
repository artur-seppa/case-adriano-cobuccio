<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

/**
 * One JSON 204 response bound to every Fortify action contract that has no
 * meaningful body in a headless API (login, logout, password reset/update,
 * profile update, email verification).
 */
class JsonNoContentResponse implements LoginResponse, LogoutResponse, PasswordResetResponse, PasswordUpdateResponse, ProfileInformationUpdatedResponse, VerifyEmailResponse
{
    public function toResponse($request)
    {
        return response()->noContent();
    }
}
