<?php

use Illuminate\Support\Facades\Route;

it('does not register Telescope routes outside the local environment', function () {
    // APP_ENV=testing → provider não registra
    expect(Route::has('telescope'))->toBeFalse();
})->skip(fn () => app()->environment('local'), 'only meaningful when env != local');
