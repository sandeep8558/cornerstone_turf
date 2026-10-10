<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingsManagementDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Manager']);
    }

    public function test_admin_can_access_bookings_management_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/administrator/bookings');

        $response->assertOk()
            ->assertSeeVolt('administrator.bookings-management');
    }

    public function test_bookings_component_mounts_with_current_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today()->toDateString();

        Volt::test('administrator.bookings-management')
            ->assertSet('selected_date', $today)
            ->assertSee('Today, ' . Carbon::today()->format('d M Y'));
    }

    public function test_prev_and_next_day_navigation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today();
        $expectedNext = $today->copy()->addDay()->toDateString();
        $expectedPrev = $today->copy()->subDay()->toDateString();

        $component = Volt::test('administrator.bookings-management')
            ->call('nextDay')
            ->assertSet('selected_date', $expectedNext);

        $component->call('prevDay')
            ->call('prevDay')
            ->assertSet('selected_date', $expectedPrev);
    }

    public function test_go_to_today_and_select_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today()->toDateString();
        $otherDate = Carbon::today()->addDays(5)->toDateString();

        $component = Volt::test('administrator.bookings-management')
            ->call('nextDay')
            ->call('selectDate', $otherDate)
            ->assertSet('selected_date', $otherDate)
            ->call('goToToday')
            ->assertSet('selected_date', $today);
    }
}
