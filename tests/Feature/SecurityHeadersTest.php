<?php

it('sends the browser hardening headers and allows the microphone', function () {
    $this->get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');
});

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
