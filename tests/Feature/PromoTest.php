<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\User;
use Database\Seeders\PromoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_promos_are_seeded(): void
    {
        $this->seed(PromoSeeder::class);

        $this->assertDatabaseCount('promos', 4);
        $this->assertDatabaseHas('promos', [
            'title' => 'Birthday Promo',
            'description' => 'Celebrate your birthday with a custom piece that feels as personal as the memory behind it.',
            'discount' => 10,
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);

        $birthday = Promo::query()->where('title', 'Birthday Promo')->firstOrFail();
        $this->assertSame(
            ['Available in birthday month', 'Valid ID With Printed Birthdate'],
            $birthday->requirements,
        );
    }

    public function test_welcome_page_lists_only_active_promos(): void
    {
        Promo::query()->create([
            'title' => 'Birthday Promo',
            'description' => 'Celebrate your birthday with a custom piece.',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);
        Promo::query()->create([
            'title' => 'Retired Promo',
            'discount' => 5,
            'requirements' => ['Expired'],
            'usage' => 'Unavailable',
            'image' => '/storage/images/promos/retired.png',
            'is_active' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('promos', 1)
                ->where('promos.0.title', 'Birthday Promo')
                ->where('promos.0.description', 'Celebrate your birthday with a custom piece.')
                ->where('promos.0.discount', 10)
                ->where('promos.0.requirements', ['Available in birthday month']));
    }

    public function test_admin_can_view_promos_and_clients_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        Promo::query()->create([
            'title' => 'Birthday Promo',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);

        $this->actingAs($client)->get(route('promos'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('promos'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Promos')
                ->has('promos', 1)
                ->where('promos.0.title', 'Birthday Promo')
                ->where('promos.0.discount', 10)
                ->where('filters.search', ''));
    }

    public function test_admin_can_search_promos(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Promo::query()->create([
            'title' => 'Birthday Promo',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);
        Promo::query()->create([
            'title' => 'Christmas Promo',
            'discount' => 20,
            'requirements' => ['Available in month of December'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/christmas.png',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('promos', ['search' => 'Christmas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('promos', 1)
                ->where('promos.0.title', 'Christmas Promo')
                ->where('filters.search', 'Christmas'));
    }

    public function test_admin_can_add_a_promo_and_clients_cannot(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);

        $this->actingAs($client)->post(route('promos.store'), [
            'title' => 'Summer Promo',
            'description' => 'A warm-weather session special.',
            'discount' => 15,
            'requirements' => ['Valid ID'],
            'usage' => 'Unlimited Availability',
            'image' => UploadedFile::fake()->image('summer.png'),
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('promos.store'), [
            'title' => 'Summer Promo',
            'description' => 'A warm-weather session special.',
            'discount' => 15,
            'requirements' => ['Valid ID', 'Weekdays only'],
            'usage' => 'Unlimited Availability',
            'image' => UploadedFile::fake()->image('summer.png'),
        ])->assertRedirect();

        $promo = Promo::query()->where('title', 'Summer Promo')->firstOrFail();
        $this->assertSame('A warm-weather session special.', $promo->description);
        $this->assertSame(15, $promo->discount);
        $this->assertSame(['Valid ID', 'Weekdays only'], $promo->requirements);
        $this->assertSame('Unlimited Availability', $promo->usage);
        $this->assertTrue($promo->is_active);
        $this->assertStringStartsWith('/storage/images/promos/', $promo->image);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $promo->image), '/'));
    }

    public function test_admin_can_turn_a_promo_on_or_off(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $promo = Promo::query()->create([
            'title' => 'Birthday Promo',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);

        $this->actingAs($client)
            ->patch(route('promos.update', $promo), ['is_active' => 0])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('promos.update', $promo), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($promo->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('promos.update', $promo), ['is_active' => 1])
            ->assertRedirect();

        $this->assertTrue($promo->fresh()->is_active);
    }

    public function test_admin_can_edit_a_promo_and_keep_the_existing_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $promo = Promo::query()->create([
            'title' => 'Birthday Promo',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);

        $this->actingAs($client)->post(route('promos.revise', $promo), [
            'title' => 'Birthday Special',
            'description' => 'Updated birthday offer.',
            'discount' => 12,
            'requirements' => ['Valid ID'],
            'usage' => 'Unlimited Availability',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('promos.revise', $promo), [
            'title' => 'Birthday Special',
            'description' => 'Updated birthday offer.',
            'discount' => 12,
            'requirements' => ['Valid ID'],
            'usage' => 'Unlimited Availability',
        ])->assertRedirect();

        $promo->refresh();
        $this->assertSame('Birthday Special', $promo->title);
        $this->assertSame('Updated birthday offer.', $promo->description);
        $this->assertSame(12, $promo->discount);
        $this->assertSame(['Valid ID'], $promo->requirements);
        $this->assertSame('Unlimited Availability', $promo->usage);
        $this->assertSame('/storage/images/promos/birthday.png', $promo->image);
        $this->assertTrue($promo->is_active);
    }

    public function test_admin_can_soft_delete_a_promo(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create(['is_admin' => false]);
        $promo = Promo::query()->create([
            'title' => 'Birthday Promo',
            'discount' => 10,
            'requirements' => ['Available in birthday month'],
            'usage' => 'Limited Availability',
            'image' => '/storage/images/promos/birthday.png',
            'is_active' => true,
        ]);

        $this->actingAs($client)->delete(route('promos.destroy', $promo))->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('promos.destroy', $promo))
            ->assertRedirect();

        $this->assertSoftDeleted('promos', ['id' => $promo->id]);
        $this->assertNull(Promo::query()->find($promo->id));

        $this->actingAs($admin)
            ->get(route('promos'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('promos', 0));

        Auth::logout();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('promos', 0));
    }
}
