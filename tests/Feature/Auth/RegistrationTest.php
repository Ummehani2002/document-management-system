<?php

test('registration screen is not publicly available', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration is rejected', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
