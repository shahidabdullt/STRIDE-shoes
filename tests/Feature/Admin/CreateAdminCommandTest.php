<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-password-1')
            ->expectsQuestion('Confirm password', 'a-long-password-1')
            ->expectsOutputToContain('boss@example.com created')
            ->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('Boss', $user->name);
        $this->assertTrue(Hash::check('a-long-password-1', $user->password));
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
    }

    public function test_it_rejects_mismatched_passwords(): void
    {
        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-password-1')
            ->expectsQuestion('Confirm password', 'a-different-password')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
    }

    public function test_it_refuses_an_existing_email(): void
    {
        User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('app:create-admin', ['email' => 'boss@example.com', '--name' => 'Boss'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-password-1')
            ->expectsQuestion('Confirm password', 'a-long-password-1')
            ->assertFailed();

        $this->assertFalse(User::where('email', 'boss@example.com')->firstOrFail()->is_admin);
    }
}
