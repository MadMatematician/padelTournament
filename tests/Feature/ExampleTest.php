<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_radice_manda_gli_ospiti_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
