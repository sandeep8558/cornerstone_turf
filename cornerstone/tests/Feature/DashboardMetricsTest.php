<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Manager']);
    }

    public function test_admin_can_access_dashboard_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/administrator/dashboard');

        $response->assertOk()
            ->assertSeeVolt('administrator.dashboard-metrics')
            ->assertSee('Financial Summary');
    }

    public function test_dashboard_metrics_component_mounts_with_current_month(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $this->actingAs($admin);

        $component = Volt::test('administrator.dashboard-metrics');

        $component->assertSet('period', 'month');
        $component->assertSee(Carbon::today()->format('F Y'));
    }

    public function test_admin_can_switch_periods(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $component = Volt::test('administrator.dashboard-metrics');

        // Switch to Day
        $component->call('setPeriod', 'day');
        $component->assertSet('period', 'day');

        // Switch to Year
        $component->call('setPeriod', 'year');
        $component->assertSet('period', 'year');

        // Switch to All
        $component->call('setPeriod', 'all');
        $component->assertSet('period', 'all');
        $component->assertSee('All Time');
    }

    public function test_previous_and_next_period_navigation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $component = Volt::test('administrator.dashboard-metrics');

        // Day navigation
        $component->call('setPeriod', 'day');
        $initialDay = $component->get('currentDate');

        $component->call('previousPeriod');
        $this->assertEquals(
            Carbon::parse($initialDay)->subDay()->toDateString(),
            $component->get('currentDate')
        );

        $component->call('nextPeriod');
        $this->assertEquals($initialDay, $component->get('currentDate'));

        // Month navigation
        $component->call('setPeriod', 'month');
        $component->call('previousPeriod');
        $this->assertEquals(
            Carbon::parse($initialDay)->subMonth()->startOfMonth()->toDateString(),
            $component->get('currentDate')
        );

        // Reset to Today
        $component->call('goToCurrent');
        $this->assertEquals(Carbon::today()->toDateString(), $component->get('currentDate'));
    }

    public function test_metrics_correctly_filter_by_period(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $location = \App\Models\Location::create([
            'name' => 'Main Location',
            'is_active' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Test Pitch',
            'turf_type' => 'Synthetic',
            'is_active' => 'Yes',
        ]);

        $todayStr = Carbon::today()->toDateString();
        $lastMonthStr = Carbon::today()->subMonths(2)->toDateString();

        // Booking today
        $bookingToday = Booking::create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $todayStr,
            'amount' => 1500,
            'status' => 'Success',
            'payment_type' => 'Full',
        ]);

        // Booking two months ago
        $bookingPast = Booking::create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $lastMonthStr,
            'amount' => 3000,
            'status' => 'Success',
            'payment_type' => 'Part',
        ]);

        // Payment on today's booking
        BookingPayment::create([
            'booking_id' => $bookingToday->id,
            'type' => 'App',
            'amount' => 1500,
        ]);

        // When viewing "day" for today
        $component = Volt::test('administrator.dashboard-metrics')
            ->call('setPeriod', 'day');

        $component->assertViewHas('totalBilling', 1500.00);
        $component->assertViewHas('totalReceived', 1500.00);
        $component->assertViewHas('totalBookings', 1);

        // When viewing "all"
        $component->call('setPeriod', 'all');
        $component->assertViewHas('totalBilling', 4500.00);
        $component->assertViewHas('totalBookings', 2);
    }
}
