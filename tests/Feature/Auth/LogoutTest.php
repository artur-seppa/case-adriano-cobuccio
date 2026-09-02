<?php

use App\Models\User;

it('logs out and clears authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/logout')
        ->assertNoContent();

    $this->assertGuest();
});
