<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_authenticated_client_visiting_home_is_redirected_to_book_service(): void
    {
        $client = User::factory()->create(['is_admin' => false]);

        $this->actingAs($client)
            ->get(route('home'))
            ->assertRedirect('/book-a-service');
    }

    public function test_authenticated_admin_visiting_home_is_redirected_to_bookings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertRedirect('/bookings');
    }
}
