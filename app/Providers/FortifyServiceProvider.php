<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\EmailVerifiedResponse;
use App\Http\Responses\JsonNoContentResponse;
use App\Http\Responses\JsonRegisterResponse;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse;
use Laravel\Fortify\Contracts\ProfileInformationUpdatedResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Don't let Fortify register its full route file — see routes/fortify.php.
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Headless API: every Fortify action returns JSON, never a redirect/view.
        $this->app->singleton(LoginResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(LogoutResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(PasswordResetResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(PasswordUpdateResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(ProfileInformationUpdatedResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(RegisterResponse::class, JsonRegisterResponse::class);

        // The email-verification link is opened in a browser from an email — a
        // small confirmation page instead of a blank 204.
        $this->app->singleton(VerifyEmailResponse::class, EmailVerifiedResponse::class);

        // Our curated Fortify routes (Fortify's own file is ignored above).
        Route::domain(config('fortify.domain'))
            ->prefix(config('fortify.prefix'))
            ->middleware(config('fortify.middleware', ['web']))
            ->group(base_path('routes/fortify.php'));

        // With `fortify.views = false` there is no `password.reset` GET route, so
        // the stock ResetPassword notification's `route('password.reset', ...)`
        // throws a RouteNotFoundException (→ 500 on every forgot-password). The
        // reset link belongs in the SPA anyway.
        ResetPassword::createUrlUsing(function (CanResetPassword $user, string $token) {
            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return $frontend.'/reset-password?token='.$token
                .'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
