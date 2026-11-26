<?php

it('renders the product landing page', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('One source of truth for stock across every channel.')
        ->assertSee(config('app.name'))
        ->assertDontSee('A foundation, not a finished product.');
});
