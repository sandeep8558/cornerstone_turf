<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Location;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerCollectPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Manager']);
    }

    private function createSetupData(float $amount = 1000): array
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');

        $customer = User::factory()->create([
            'name' => 'Amit Patel',
            'mobile_number' => '9876543210',
        ]);

        $location = Location::create([
            'name' => 'Central Hub',
            'is_active' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Pitch Alpha',
            'turf_type' => 'Synthetic',
            'is_active' => 'Yes',
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => Carbon::today()->toDateString(),
            'amount' => $amount,
            'payment_type' => 'PayAtLocation',
            'status' => 'Success',
            'came' => null,
            'players' => null,
        ]);

        return [$manager, $booking];
    }

    public function test_manager_can_collect_payment_for_booking(): void
    {
        [$manager, $booking] = $this->createSetupData(1200);

        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 500,
            'type' => 'Cash',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Payment collected successfully');

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'amount' => 500,
            'type' => 'Cash',
        ]);

        $this->assertEquals(500, $booking->fresh()->total_received);
    }

    public function test_rapid_duplicate_collect_clicks_do_not_create_multiple_payments(): void
    {
        [$manager, $booking] = $this->createSetupData(1000);

        // First click collects the remaining ₹1000
        $res1 = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 1000,
            'type' => 'UPI',
        ]);
        $res1->assertOk()
            ->assertJsonPath('message', 'Payment collected successfully');

        // Second click (duplicate double tap from mobile app)
        $res2 = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 1000,
            'type' => 'UPI',
        ]);
        $res2->assertOk()
            ->assertJsonPath('message', 'Payment already collected (Booking is fully paid)');

        // Third click
        $res3 = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 1000,
            'type' => 'UPI',
        ]);
        $res3->assertOk()
            ->assertJsonPath('message', 'Payment already collected (Booking is fully paid)');

        // Crucial assertions: Exactly 1 payment record exists and total received is 1000, NOT 3000!
        $this->assertEquals(1, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertEquals(1000, $booking->fresh()->total_received);
        $this->assertTrue($booking->fresh()->is_fully_paid);
    }

    public function test_collect_payment_caps_amount_to_remaining_due(): void
    {
        [$manager, $booking] = $this->createSetupData(1000);

        // Pre-existing advance payment of ₹700 (₹300 remaining due)
        BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 700,
            'type' => 'App',
        ]);

        // Manager sends collect request for ₹600 (exceeds ₹300 due)
        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 600,
            'type' => 'Cash',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Payment collected successfully');

        // The second payment should be capped at ₹300
        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'amount' => 300,
            'type' => 'Cash',
        ]);

        // Total received in DB is exactly ₹1000, not ₹1300
        $this->assertEquals(1000, $booking->fresh()->total_received);
        $this->assertTrue($booking->fresh()->is_fully_paid);
    }

    public function test_already_fully_paid_booking_returns_success_without_inserting_payment(): void
    {
        [$manager, $booking] = $this->createSetupData(800);

        // Booking is already fully paid
        BookingPayment::create([
            'booking_id' => $booking->id,
            'amount' => 800,
            'type' => 'App',
        ]);

        $this->assertTrue($booking->fresh()->is_fully_paid);

        // Manager attempts to collect payment again
        $response = $this->actingAs($manager, 'sanctum')->postJson("/api/manager/bookings/{$booking->id}/collect", [
            'amount' => 200,
            'type' => 'Cash',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Payment already collected (Booking is fully paid)');

        // Still only the original 1 payment exists
        $this->assertEquals(1, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertEquals(800, $booking->fresh()->total_received);
    }
}
