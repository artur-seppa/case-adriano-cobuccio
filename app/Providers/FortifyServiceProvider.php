<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\JsonNoContentResponse;
use App\Http\Responses\JsonRegisterResponse;
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
        //
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
        $this->app->singleton(VerifyEmailResponse::class, JsonNoContentResponse::class);
        $this->app->singleton(RegisterResponse::class, JsonRegisterResponse::class);
    }
}
