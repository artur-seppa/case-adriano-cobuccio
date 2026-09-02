<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Enums\WalletType;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class RegisterUser
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        return $this->db->transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            Wallet::create([
                'type' => WalletType::User,
                'user_id' => $user->id,
                'currency' => 'BRL',
                'balance_cents' => 0,
                'entry_count' => 0,
            ]);

            return $user;
        });
    }
}
