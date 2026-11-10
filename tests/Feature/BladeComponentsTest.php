<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeComponentsTest extends TestCase
{
    public function test_button_renders_as_a_button_or_a_link(): void
    {
        $this->blade('<x-button type="submit">Save</x-button>')
            ->assertSee('<button type="submit"', false)
            ->assertSee('Save');

        $this->blade('<x-button href="/shoes" variant="secondary">Browse</x-button>')
            ->assertSee('<a href="/shoes"', false)
            ->assertSee('Browse');
    }

    public function test_input_renders_a_labelled_field(): void
    {
        $this->withViewErrors([])
            ->blade('<x-input name="email" label="Email" type="email" value="a@example.com" />')
            ->assertSee('<label for="email"', false)
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('value="a@example.com"', false);
    }

    public function test_card_and_badge_render_their_content(): void
    {
        $this->blade('<x-card>Hello card</x-card>')->assertSee('Hello card');
        $this->blade('<x-badge variant="success">In stock</x-badge>')
            ->assertSee('In stock')
            ->assertSee('bg-emerald-100', false);
    }

    public function test_flash_shows_status_and_error_messages(): void
    {
        $this->withSession(['status' => 'Saved', 'error' => 'Nope'])
            ->blade('<x-flash />')
            ->assertSee('role="status"', false)
            ->assertSee('Saved')
            ->assertSee('role="alert"', false)
            ->assertSee('Nope');
    }
}
