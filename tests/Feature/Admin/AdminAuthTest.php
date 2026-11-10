<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Admin sign in');
    }

    public function test_non_admin_user_gets_403_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('Products');
    }

    public function test_admin_can_sign_in_and_the_session_id_changes(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'correct-horse-battery']);

        $this->get('/admin/login');
        $before = session()->getId();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_non_admin_cannot_sign_in_even_with_the_right_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_with_the_same_message(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_login_validates_input(): void
    {
        $this->post('/admin/login', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $payload = ['email' => 'nobody@example.com', 'password' => 'wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', $payload)->assertSessionHasErrors('email');
        }

        $this->post('/admin/login', $payload)->assertTooManyRequests();
    }

    public function test_admin_can_sign_out(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_signed_in_guest_only_routes_redirect_away(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/login')->assertRedirect();
    }
}
