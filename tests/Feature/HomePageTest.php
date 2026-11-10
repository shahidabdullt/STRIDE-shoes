<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_the_layout(): void
    {
        $this->withoutVite();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1">', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('Skip to content')
            ->assertSee('Shoes for every stride')
            ->assertSee('Payments are simulated');
    }
}
