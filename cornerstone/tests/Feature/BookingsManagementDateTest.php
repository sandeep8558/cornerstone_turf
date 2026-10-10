<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
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

    public function test_format_booking_slots_combines_consecutive_slots(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $component = Volt::test('administrator.bookings-management');

        // Two 30-minute slots: 14:00 - 14:30 and 14:30 - 15:00
        $slots = collect([
            (object)['from' => '14:00:00', 'to' => '14:30:00', 'minutes' => 30],
            (object)['from' => '14:30:00', 'to' => '15:00:00', 'minutes' => 30],
        ]);

        $ranges = $component->instance()->formatBookingSlots($slots);

        $this->assertCount(1, $ranges);
        $this->assertEquals('02:00 PM', $ranges[0]['start']->format('h:i A'));
        $this->assertEquals('3:00 PM', $ranges[0]['end_label']);
        $this->assertEquals('1 hr', $ranges[0]['duration']);
        $this->assertEquals(2, $ranges[0]['slot_count']);
    }

    public function test_format_booking_slots_handles_midnight_end(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $component = Volt::test('administrator.bookings-management');

        // Four 30-minute slots ending at midnight
        $slots = collect([
            (object)['from' => '22:00:00', 'to' => '22:30:00', 'minutes' => 30],
            (object)['from' => '22:30:00', 'to' => '23:00:00', 'minutes' => 30],
            (object)['from' => '23:00:00', 'to' => '23:30:00', 'minutes' => 30],
            (object)['from' => '23:30:00', 'to' => '23:59:59', 'minutes' => 30],
        ]);

        $ranges = $component->instance()->formatBookingSlots($slots);

        $this->assertCount(1, $ranges);
        $this->assertEquals('10:00 PM', $ranges[0]['start']->format('h:i A'));
        $this->assertEquals('12:00 AM', $ranges[0]['end_label']);
        $this->assertEquals('2 hrs', $ranges[0]['duration']);
    }

    public function test_format_booking_slots_keeps_non_consecutive_slots_separate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $component = Volt::test('administrator.bookings-management');

        $slots = collect([
            (object)['from' => '07:00:00', 'to' => '07:30:00', 'minutes' => 30],
            (object)['from' => '18:00:00', 'to' => '19:00:00', 'minutes' => 60],
        ]);

        $ranges = $component->instance()->formatBookingSlots($slots);

        $this->assertCount(2, $ranges);
        $this->assertEquals('07:00 AM', $ranges[0]['start']->format('h:i A'));
        $this->assertEquals('7:30 AM', $ranges[0]['end_label']);
        $this->assertEquals('30 mins', $ranges[0]['duration']);

        $this->assertEquals('06:00 PM', $ranges[1]['start']->format('h:i A'));
        $this->assertEquals('7:00 PM', $ranges[1]['end_label']);
        $this->assertEquals('1 hr', $ranges[1]['duration']);
    }

    public function test_check_in_modal_and_attendance_flow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $customer = User::factory()->create(['name' => 'Rahul Sharma', 'mobile_number' => '9876543210']);

        $location = \App\Models\Location::create([
            'name' => 'Main Center',
            'is_active' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Main Turf',
            'turf_type' => 'Synthetic',
            'is_active' => 'Yes',
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => Carbon::today()->toDateString(),
            'amount' => 1200,
            'payment_type' => 'Offline',
            'status' => 'Success',
            'came' => null,
            'players' => null,
        ]);

        $component = Volt::test('administrator.bookings-management')
            ->assertSee('Check-In')
            ->assertSee('Payment')
            ->assertSee('Pending')
            ->assertSee('Check In')
            ->assertSee('Collect ₹1,200')
            ->call('openAttendanceModal', $booking->id)
            ->assertSet('showAttendanceModal', true)
            ->assertSet('came', 'Yes')
            ->set('players', 10)
            ->call('saveAttendance')
            ->assertSet('showAttendanceModal', false);

        $booking->refresh();
        $this->assertEquals('Yes', $booking->came);
        $this->assertEquals(10, $booking->players);

        // Now that came is 'Yes', the component should display 'Checked In' and '10 players'
        $component->assertSee('Checked In')
            ->assertSee('10 players');
    }
}

