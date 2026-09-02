<?php

/*
 * Our curated subset of Fortify's routes. Fortify's own route file is disabled
 * (`Fortify::ignoreRoutes()` in FortifyServiceProvider) because it registers,
 * unconditionally, a password-confirmation ("sudo mode") pair and — behind
 * feature flags we don't set — 2FA and passkey routes. None of that is in scope,
 * so instead of hiding routes we declare exactly the auth surface this API has.
 *
 * Loaded inside a group with `prefix => api` and `middleware => [web, throttle:auth]`.
 */

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

$guest = 'guest:'.config('fortify.guard');
$auth = config('fortify.auth_middleware', 'auth').':'.config('fortify.guard');
$verificationLimiter = 'throttle:'.config('fortify.limiters.verification', '6,1');

// Session
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware($guest)->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware($auth)->name('logout');

// Registration (creates the user + wallet via App\Actions\Fortify\CreateNewUser)
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware($guest)->name('register.store');

// Password reset
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware($guest)->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware($guest)->name('password.update');

// Email verification
Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware([$auth, 'signed', $verificationLimiter])
    ->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware([$auth, $verificationLimiter])
    ->name('verification.send');

// Self-service profile / password
Route::put('/user/profile-information', [ProfileInformationController::class, 'update'])->middleware($auth)->name('user-profile-information.update');
Route::put('/user/password', [PasswordController::class, 'update'])->middleware($auth)->name('user-password.update');
