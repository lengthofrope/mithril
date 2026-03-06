<?php

use App\Models\User;

test('the application returns a successful response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});
