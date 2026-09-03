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
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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

        // The verification link is clicked from an email, in whatever browser the
        // user has. If it pointed straight at the API host (`APP_URL` / the proxy
        // target, often `127.0.0.1:8000`) the session cookie — set for `localhost`
        // by the SPA — would not be sent to a different host, and `auth:web` on the
        // route would 401. So build the link against the SPA origin (`frontend_url`)
        // and let the SPA's dev/prod proxy forward `/api/email/verify/...` to the
        // backend same-origin, cookie included. The signature is generated
        // `absolute: false` (path + query only) so it validates regardless of which
        // host actually terminates the request; the route uses `signed:relative`.
        VerifyEmail::createUrlUsing(function (MustVerifyEmail $notifiable) {
            $relative = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                absolute: false,
            );

            return rtrim((string) config('app.frontend_url'), '/').$relative;
        });
    }
}
