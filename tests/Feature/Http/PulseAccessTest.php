<?php

use App\Models\User;

it('serves the Pulse dashboard in non-production', function () {
    $this->actingAs(User::factory()->create())->get('/pulse')->assertOk();
});
