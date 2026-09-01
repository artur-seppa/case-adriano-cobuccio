<?php

use App\Domain\Wallet\Support\SystemWallets;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Integration tests run against the real migrated Postgres `wallet_test` DB.
// DatabaseTruncation (not RefreshDatabase, not DatabaseMigrations) is used:
// RefreshDatabase wraps each test in a never-committed transaction, so the
// DEFERRABLE INITIALLY DEFERRED constraint trigger (fires at COMMIT) could
// never run; DatabaseMigrations works but re-migrates per test (slow).
// DatabaseTruncation lets real commits happen (trigger fires) and truncates
// tables between tests (fast).
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(fn () => SystemWallets::externalWorld())
    ->in('Integration');

// Concurrency tests fork real OS processes (pcntl) that each open their own DB
// connection, so they can only observe COMMITTED rows. DatabaseTruncation (no
// wrapping transaction) lets the parent's setup writes commit before the fork;
// RefreshDatabase would hide them inside an uncommitted transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(fn () => SystemWallets::externalWorld())
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
