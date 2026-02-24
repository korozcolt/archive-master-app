<?php

test('home page shows portal and admin entry points', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Ir al Portal')
        ->assertSee('Ir a Administrador');
});

test('portal login page shows link to admin login', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Ir a Administrador');
});

test('admin login page shows link to portal login', function () {
    $response = $this->get('/admin/login');

    $response->assertOk()
        ->assertSee('Ir a Portal');
});
