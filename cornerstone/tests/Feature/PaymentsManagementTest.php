<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Manager']);
    }

    public function test_admin_can_access_payments_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $response = $this->actingAs($admin)->get('/administrator/payments');

        $response->assertOk()
            ->assertSeeVolt('administrator.payments-management');
    }

    public function test_payments_table_lists_payments_and_renders_delete_button(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $customer = User::factory()->create([
            'name' => 'Abhinav',
            'mobile_number' => '9699784437',
        ]);

        $location = Location::create([
            'name' => 'Cornerstone Turf',
            'is_active' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf 1',
            'turf_type' => 'Synthetic',
            'is_active' => 'Yes',
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => Carbon::today()->toDateString(),
            'amount' => 1100,
            'payment_type' => 'Full',
            'status' => 'Success',
        ]);

        $payment1 = BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 1100,
            'type' => 'Cash',
        ]);

        // A duplicate false payment created by accident
        $payment2 = BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 1100,
            'type' => 'Cash',
        ]);

        $component = Volt::test('administrator.payments-management')
            ->assertSee('#' . $payment1->id)
            ->assertSee('#' . $payment2->id)
            ->assertSee('Booking #' . $booking->id)
            ->assertSee('Abhinav')
            ->assertSee('Delete');

        // Delete the duplicate false payment
        $component->call('deletePayment', $payment2->id)
            ->assertSee("Payment record #{$payment2->id}")
            ->assertSee("deleted successfully");

        // Assert payment2 is deleted from DB, but payment1 remains
        $this->assertDatabaseMissing('booking_payments', ['id' => $payment2->id]);
        $this->assertDatabaseHas('booking_payments', ['id' => $payment1->id]);

        // Booking total received should now be 1100 (not 2200)
        $this->assertEquals(1100, $booking->fresh()->total_received);
        $this->assertEquals('Full', $booking->fresh()->payment_type);
    }

    public function test_deleting_payment_updates_booking_payment_type_to_part_or_pay_at_location(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $customer = User::factory()->create(['name' => 'Sameer']);
        $location = Location::create(['name' => 'Turf Hub', 'is_active' => true]);
        $turf = Turf::create(['location_id' => $location->id, 'name' => 'Turf 2', 'turf_type' => 'Synthetic', 'is_active' => 'Yes']);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => Carbon::today()->toDateString(),
            'amount' => 1000,
            'payment_type' => 'Full',
            'status' => 'Success',
        ]);

        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 1000,
            'type' => 'Cash',
        ]);

        // Delete the only payment
        Volt::test('administrator.payments-management')
            ->call('deletePayment', $payment->id);

        $this->assertDatabaseMissing('booking_payments', ['id' => $payment->id]);
        $this->assertEquals(0, $booking->fresh()->total_received);
        $this->assertEquals('PayAtLocation', $booking->fresh()->payment_type);
    }

    public function test_infinite_scroll_load_more_increments_per_page_and_loads_more_records(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $customer = User::factory()->create(['name' => 'Karan']);
        $location = Location::create(['name' => 'Turf Hub', 'is_active' => true]);
        $turf = Turf::create(['location_id' => $location->id, 'name' => 'Turf 3', 'turf_type' => 'Synthetic', 'is_active' => 'Yes']);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => Carbon::today()->toDateString(),
            'amount' => 5000,
            'payment_type' => 'Full',
            'status' => 'Success',
        ]);

        // Create 20 payment records
        for ($i = 1; $i <= 20; $i++) {
            BookingPayment::create([
                'booking_id' => $booking->id,
                'amount' => 100,
                'type' => 'Cash',
            ]);
        }

        // Component starts with perPage = 15
        $component = Volt::test('administrator.payments-management')
            ->assertSet('perPage', 15)
            ->assertSee('Loading more payments')
            ->assertSee('showing 15 of 20')
            ->call('loadMore')
            ->assertSet('perPage', 30)
            ->assertSee('Showing all 20 payments');
    }
}

