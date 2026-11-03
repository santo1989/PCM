<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Sign-up is admin-only: guests are sent to login, an admin adds users and stays logged in as themselves.
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_registration_screen()
    {
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_admin_can_add_a_user()
    {
        $admin = User::factory()->create(['role_id' => 1, 'mobile' => '0100', 'dob' => '1990-01-01']);

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'mobile' => '0111',
            'dob' => '1995-05-05',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role_id' => 2]);
    }
}
