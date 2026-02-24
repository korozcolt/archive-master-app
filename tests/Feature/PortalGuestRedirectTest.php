<?php

use Illuminate\Support\Facades\Route;

test('guest visiting portal is redirected to login route', function () {
    expect(Route::has('login'))->toBeTrue();

    $response = $this->get('/portal');

    $response->assertRedirect(route('login'));
});
