<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_submit_a_booking_request(): void
    {
        $this->fakeCloudinary();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('book-a-service.store'), [
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'design_picture' => UploadedFile::fake()->image('design.jpg'),
            'service_date' => now()->addWeek()->toDateString(),
            'price_range' => 2000,
        ]);

        $response->assertRedirect(route('book-a-service'));
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'price_range' => 2000,
            'status' => 'Pending',
        ]);

        $booking = Booking::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('booking-designs/abcdefghijklmno.jpg', $booking->design_picture);
        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return $request->method() === 'POST'
                && str_contains($request->url(), 'https://api.cloudinary.com/v1_1/test-cloud/image/upload')
                && str_contains($body, 'name="type"')
                && str_contains($body, 'authenticated')
                && str_contains($body, 'name="api_key"')
                && str_contains($body, 'test-key')
                && str_contains($body, 'name="public_id"')
                && ! str_contains($body, 'test-secret');
        });
    }

    public function test_cloudinary_design_picture_is_streamed_after_authorization(): void
    {
        $this->fakeCloudinary();

        $owner = User::factory()->create();
        $otherClient = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $owner->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'design_picture' => 'booking-designs/remote-design.png',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);

        $this->actingAs($otherClient)
            ->get(route('bookings.design-picture', $booking))
            ->assertForbidden();

        Http::assertNothingSent();

        $this->actingAs($owner)
            ->get(route('bookings.design-picture', $booking))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('remote-image', false);

        $this->actingAs($owner)
            ->get(route('bookings.design-picture.download', $booking))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=remote-design.png');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'https://api.cloudinary.com/v1_1/test-cloud/image/download')
                && str_contains($request->url(), 'public_id=booking-designs%2Fremote-design')
                && str_contains($request->url(), 'format=png')
                && str_contains($request->url(), 'type=authenticated')
                && str_contains($request->url(), 'api_key=test-key')
                && ! str_contains($request->url(), 'test-secret');
        });
    }

    public function test_design_picture_is_visible_only_to_the_booking_owner_or_an_admin(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('booking-designs/private-design.jpg', 'fake-image-content');

        $owner = User::factory()->create();
        $otherClient = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $booking = Booking::create([
            'user_id' => $owner->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'design_picture' => 'private-design.jpg',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);

        $this->actingAs($owner)
            ->get(route('bookings.design-picture', $booking))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($otherClient)
            ->get(route('bookings.design-picture', $booking))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('bookings.design-picture', $booking))
            ->assertOk();
    }

    public function test_admin_can_download_design_picture_but_other_clients_cannot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('booking-designs/private-design.jpg', 'fake-image-content');

        $owner = User::factory()->create();
        $otherClient = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $booking = Booking::create([
            'user_id' => $owner->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'design_picture' => 'private-design.jpg',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);

        $this->actingAs($otherClient)
            ->get(route('bookings.design-picture.download', $booking))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('bookings.design-picture.download', $booking))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=private-design.jpg');
    }

    public function test_booking_date_must_not_be_in_the_past(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('book-a-service.store'), [
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->subDay()->toDateString(),
            'price_range' => 5000,
        ])->assertSessionHasErrors('service_date');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_clients_cannot_book_a_date_reserved_by_another_pending_or_approved_booking(): void
    {
        $firstClient = User::factory()->create();
        $secondClient = User::factory()->create();
        $reservedDate = now()->addWeek()->toDateString();

        Booking::create([
            'user_id' => $firstClient->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => $reservedDate,
            'price_range' => 500,
        ]);

        $this->actingAs($secondClient)
            ->get(route('book-a-service'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('unavailableDates.0', $reservedDate));

        $this->actingAs($secondClient)->post(route('book-a-service.store'), [
            'detailed_address' => '99 Other Avenue',
            'contact_number' => '+639171234567',
            'service_date' => $reservedDate,
            'price_range' => 500,
        ])->assertSessionHasErrors('service_date');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_rejected_booking_does_not_keep_its_date_reserved(): void
    {
        $this->fakeCloudinary();
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $reservedDate = now()->addWeek()->toDateString();

        Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => $reservedDate,
            'price_range' => 500,
            'status' => 'Rejected',
        ]);

        $this->actingAs($otherClient)->post(route('book-a-service.store'), [
            'detailed_address' => '99 Other Avenue',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Upper arm',
            'design_picture' => UploadedFile::fake()->image('idea.png'),
            'service_date' => $reservedDate,
            'price_range' => 500,
        ])->assertRedirect(route('book-a-service'));

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_contact_number_must_match_the_requested_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('book-a-service.store'), [
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+63 917 123 4567',
            'body_parts' => 'Left forearm',
            'design_picture' => UploadedFile::fake()->image('design.jpg'),
            'service_date' => now()->addWeek()->toDateString(),
            'price_range' => 500,
        ])->assertSessionHasErrors('contact_number');
    }

    public function test_body_parts_are_limited_to_255_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('book-a-service.store'), [
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => str_repeat('a', 256),
            'design_picture' => UploadedFile::fake()->image('design.jpg'),
            'service_date' => now()->addWeek()->toDateString(),
            'price_range' => 500,
        ])->assertSessionHasErrors('body_parts');
    }

    public function test_design_picture_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('book-a-service.store'), [
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+1 (818)-123-1234',
            'body_parts' => 'Left forearm',
            'design_picture' => UploadedFile::fake()->create('design.pdf', 100, 'application/pdf'),
            'service_date' => now()->addWeek()->toDateString(),
            'price_range' => 500,
        ])->assertSessionHasErrors('design_picture');
    }

    public function test_price_range_must_be_between_twenty_and_two_thousand_dollars(): void
    {
        $user = User::factory()->create();

        foreach ([19, 2001] as $price) {
            $this->actingAs($user)->post(route('book-a-service.store'), [
                'detailed_address' => '12 Main Street, Quezon City',
                'contact_number' => '+639171234567',
                'service_date' => now()->addWeek()->toDateString(),
                'price_range' => $price,
            ])->assertSessionHasErrors('price_range');
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_list_is_searchable_and_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Booking::create([
            'user_id' => $user->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);
        Booking::create([
            'user_id' => $otherUser->id,
            'detailed_address' => '99 Other Avenue',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 800,
        ]);

        $this->actingAs($user)
            ->get(route('book-a-service', ['search' => 'Main Street']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('BookAService')
                ->where('filters.search', 'Main Street')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.detailed_address', '12 Main Street, Quezon City'));
    }

    public function test_booking_list_is_paginated(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 9) as $number) {
            Booking::create([
                'user_id' => $user->id,
                'detailed_address' => "{$number} Main Street, Quezon City",
                'contact_number' => '+639171234567',
                'service_date' => now()->addWeek(),
                'price_range' => 500,
            ]);
        }

        $this->actingAs($user)
            ->get(route('book-a-service'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('BookAService')
                ->has('bookings.data', 8)
                ->where('bookings.total', 9));

        $this->actingAs($user)
            ->get(route('book-a-service', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.current_page', 2));
    }

    public function test_completed_bookings_move_from_active_bookings_to_searchable_client_history(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $completedBooking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->subDay(),
            'price_range' => 500,
            'status' => 'Completed',
        ]);
        Booking::create([
            'user_id' => $otherClient->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->subDay(),
            'price_range' => 500,
            'status' => 'Completed',
        ]);

        $this->actingAs($client)
            ->get(route('book-a-service'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('BookAService')
                ->has('bookings.data', 0));

        $this->actingAs($client)
            ->get(route('history', ['search' => 'Main Street']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('History')
                ->where('filters.search', 'Main Street')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $completedBooking->id)
                ->where('bookings.data.0.status', 'Completed'));
    }

    public function test_admin_can_open_the_client_booking_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('book-a-service'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('BookAService'));
    }

    public function test_admin_can_view_all_bookings_with_client_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['name' => 'Booking Client']);
        $otherClient = User::factory()->create(['name' => 'Different Customer']);
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);
        Booking::create([
            'user_id' => $otherClient->id,
            'detailed_address' => '99 Other Avenue',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 800,
        ]);

        $this->actingAs($admin)
            ->get(route('bookings', ['search' => 'Booking Client']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings')
                ->has('bookings.data', 1)
                ->where('filters.search', 'Booking Client')
                ->where('bookings.data.0.id', $booking->id)
                ->where('bookings.data.0.user.name', 'Booking Client'));
    }

    public function test_admin_bookings_page_only_lists_pending_bookings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();

        foreach (['Pending', 'Approved', 'Completed'] as $status) {
            Booking::create([
                'user_id' => $client->id,
                'detailed_address' => "{$status} Street",
                'contact_number' => '+1 (818)-123-4567',
                'body_parts' => 'Forearm',
                'design_picture' => null,
                'service_date' => now()->addWeek(),
                'price_range' => 300,
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('bookings'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookings')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.status', 'Pending'));
    }

    public function test_admin_schedules_lists_approved_bookings_for_all_clients(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['name' => 'Scheduled Client']);
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '22 Schedule Street',
            'contact_number' => '+1 (818)-123-4567',
            'body_parts' => 'Forearm',
            'design_picture' => 'schedule-design.jpg',
            'service_date' => now()->addWeek(),
            'price_range' => 300,
            'status' => 'Approved',
        ]);

        $this->actingAs($admin)
            ->get(route('schedules'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Schedules')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $booking->id)
                ->where('bookings.data.0.user.name', 'Scheduled Client'));
    }

    public function test_admin_history_lists_completed_bookings_from_all_clients(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['name' => 'Past Client']);
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '22 History Street',
            'contact_number' => '+1 (818)-123-4567',
            'body_parts' => 'Shoulder',
            'design_picture' => 'history-design.jpg',
            'service_date' => now()->subDay(),
            'price_range' => 300,
            'status' => 'Completed',
        ]);

        $this->actingAs($admin)
            ->get(route('history'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('History')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $booking->id)
                ->where('bookings.data.0.user.name', 'Past Client'));
    }

    public function test_clients_cannot_access_admin_bookings_or_change_booking_status(): void
    {
        $client = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);

        $this->actingAs($client)->get(route('bookings'))->assertForbidden();
        $this->actingAs($client)
            ->patch(route('bookings.status', $booking), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_admin_can_approve_or_reject_a_booking(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
        ]);

        $this->actingAs($admin)
            ->patch(route('bookings.status', $booking), ['status' => 'approved'])
            ->assertRedirect(route('bookings'));
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'Approved']);

        $rejectedBooking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '99 Other Avenue',
            'contact_number' => '+639171234567',
            'service_date' => now()->addDays(8),
            'price_range' => 500,
        ]);

        $this->patch(route('bookings.status', $rejectedBooking), ['status' => 'rejected'])
            ->assertRedirect(route('bookings'));
        $this->assertDatabaseHas('bookings', ['id' => $rejectedBooking->id, 'status' => 'Rejected']);
        $this->assertDatabaseHas('bookings', [
            'id' => $rejectedBooking->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($client)
            ->get(route('book-a-service'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('BookAService')
                ->has('bookings.data', 1)
                ->where('bookings.data.0.id', $booking->id));

        $this->actingAs($client)
            ->get(route('history'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('History')
                ->where('bookings.data.0.id', $rejectedBooking->id)
                ->where('bookings.data.0.status', 'Rejected'));
    }

    public function test_admin_can_mark_an_approved_booking_as_completed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
            'status' => 'Approved',
        ]);

        $this->actingAs($admin)
            ->patch(route('bookings.status', $booking), ['status' => 'completed'])
            ->assertRedirect(route('schedules'));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'Completed',
        ]);
    }

    public function test_admin_cannot_mark_a_pending_booking_as_completed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $client->id,
            'detailed_address' => '12 Main Street, Quezon City',
            'contact_number' => '+639171234567',
            'service_date' => now()->addWeek(),
            'price_range' => 500,
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('bookings.status', $booking), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame('Pending', $booking->fresh()->status);
    }

    private function fakeCloudinary(): void
    {
        config([
            'services.cloudinary.cloud_name' => 'test-cloud',
            'services.cloudinary.api_key' => 'test-key',
            'services.cloudinary.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://api.cloudinary.com/*/image/upload' => Http::response([
                'public_id' => 'booking-designs/abcdefghijklmno',
                'format' => 'jpg',
            ]),
            'https://api.cloudinary.com/*' => Http::response('remote-image', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);
    }
}
