<?php

use App\Http\Requests\Api\StoreUserRequest;
use Illuminate\Http\Request;

it('uses bogota as application default timezone', function () {
    expect(config('app.timezone'))->toBe('America/Bogota');
});

it('defaults new api users to bogota timezone when omitted', function () {
    $baseRequest = Request::create('/api/users', 'POST', [
        'name' => 'Demo User',
        'email' => 'demo@example.com',
        'password' => 'Password123',
    ]);

    $request = StoreUserRequest::createFromBase($baseRequest);

    $reflectedMethod = new ReflectionMethod(StoreUserRequest::class, 'prepareForValidation');
    $reflectedMethod->setAccessible(true);
    $reflectedMethod->invoke($request);

    expect($request->input('timezone'))->toBe('America/Bogota')
        ->and($request->input('language'))->toBe('es')
        ->and($request->input('is_active'))->toBeTrue();
});
