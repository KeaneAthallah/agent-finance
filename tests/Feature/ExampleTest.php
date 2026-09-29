<?php

it('redirects the root URL to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});
