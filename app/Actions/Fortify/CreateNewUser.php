<?php

namespace App\Actions\Fortify;

use App\Domain\Wallet\Actions\RegisterUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user together with their wallet.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        return app(RegisterUser::class)($input);
    }
}
