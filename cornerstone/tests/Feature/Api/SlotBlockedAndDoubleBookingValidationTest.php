<?php

namespace Tests\Feature\Api;

use App\Models\BlockBooking;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SlotBlockedAndDoubleBookingValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'Manager']);

        Setting::create([
            'is_part_payment_active' => true,
            'is_pay_at_location_active' => true,
            'min_part_payment' => 200,
        ]);
    }

    private function createTurfAndSlots(): array
    {
        $location = Location::create([
            'name' => 'Main Hub',
            'is_active' => true,
        ]);

        $turf = Turf::create([
            'location_id' => $location->id,
            'name' => 'Turf Arena',
            'turf_type' => 'Synthetic',
            'is_active' => 'Yes',
        ]);

        $category = \App\Models\SlotCategory::create([
            'category_name' => 'Morning',
            'is_active' => true,
        ]);

        $slot1 = Slot::create([
            'turf_id' => $turf->id,
            'location_id' => $location->id,
            'slot_category_id' => $category->id,
            'from' => '10:00:00',
            'to' => '10:30:00',
            'minutes' => 30,
            'mon_amount' => 500,
            'tue_amount' => 500,
            'wed_amount' => 500,
            'thu_amount' => 500,
            'fri_amount' => 500,
            'sat_amount' => 600,
            'sun_amount' => 600,
            'is_active' => true,
        ]);

        $slot2 = Slot::create([
            'turf_id' => $turf->id,
            'location_id' => $location->id,
            'slot_category_id' => $category->id,
            'from' => '10:30:00',
            'to' => '11:00:00',
            'minutes' => 30,
            'mon_amount' => 500,
            'tue_amount' => 500,
            'wed_amount' => 500,
            'thu_amount' => 500,
            'fri_amount' => 500,
            'sat_amount' => 600,
            'sun_amount' => 600,
            'is_active' => true,
        ]);

        return [$location, $turf, $slot1, $slot2];
    }

    public function test_customer_cannot_book_an_already_booked_slot(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $existingCustomer = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $existingCustomer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'amount' => 500,
            'payment_type' => 'Full',
            'status' => 'Success',
        ]);
        $booking->slots()->attach([$slot1->id]);

        $newCustomer = User::factory()->create();
        $response = $this->actingAs($newCustomer, 'sanctum')->postJson('/api/bookings', [
            'turf_id' => $turf->id,
            'date' => $date,
            'slot_ids' => [$slot1->id],
            'payment_type' => 'Full',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Slot 10:00:00 - 10:30:00 is already booked.",
        ]);
    }

    public function test_customer_cannot_book_an_administratively_blocked_slot(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $block = BlockBooking::create([
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'reason' => 'Pitch Maintenance',
        ]);
        $block->slots()->attach([$slot1->id]);

        $customer = User::factory()->create();
        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'turf_id' => $turf->id,
            'date' => $date,
            'slot_ids' => [$slot1->id],
            'payment_type' => 'Full',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Slot 10:00:00 - 10:30:00 is blocked by administration (Pitch Maintenance).",
        ]);
    }

    public function test_calculate_long_booking_identifies_blocked_slots_as_unavailable(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $block = BlockBooking::create([
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'reason' => 'Tournament',
        ]);
        $block->slots()->attach([$slot1->id]);

        $customer = User::factory()->create();
        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings/calculate-long-booking', [
            'turf_id' => $turf->id,
            'dates' => [$date],
            'slot_ids' => [$slot1->id, $slot2->id],
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['available_slots'][$date]);
        $this->assertEquals($slot2->id, $data['available_slots'][$date][0]['id']);

        $this->assertCount(1, $data['unavailable_slots'][$date]);
        $this->assertEquals($slot1->id, $data['unavailable_slots'][$date][0]['id']);
        $this->assertEquals('Blocked (Tournament)', $data['unavailable_slots'][$date][0]['reason']);
    }

    public function test_create_long_booking_skips_blocked_and_booked_slots(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $block = BlockBooking::create([
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'reason' => 'Renovation',
        ]);
        $block->slots()->attach([$slot1->id]);

        $customer = User::factory()->create();
        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings/long', [
            'turf_id' => $turf->id,
            'dates' => [$date],
            'slot_ids' => [$slot1->id, $slot2->id],
            'payment_type' => 'Full',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'turf_id' => $turf->id,
            'date' => $date,
            'amount' => 500, // Only slot2's amount
        ]);

        $booking = Booking::where('turf_id', $turf->id)->where('date', $date)->first();
        $this->assertFalse($booking->slots->contains($slot1->id));
        $this->assertTrue($booking->slots->contains($slot2->id));
    }

    public function test_admin_cannot_block_a_slot_that_is_already_booked(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $customer = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $customer->id,
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'amount' => 500,
            'payment_type' => 'Full',
            'status' => 'Success',
        ]);
        $booking->slots()->attach([$slot1->id]);

        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        Livewire::actingAs($admin)
            ->test('administrator.block-booking-management')
            ->set('location_id', $location->id)
            ->set('turf_id', $turf->id)
            ->set('date', $date)
            ->set('selected_slots', [$slot1->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Cannot block slot(s)');

        $this->assertDatabaseMissing('block_bookings', [
            'turf_id' => $turf->id,
            'date' => $date,
        ]);
    }

    public function test_admin_book_slots_management_prevents_booking_already_blocked_or_booked_slots(): void
    {
        [$location, $turf, $slot1, $slot2] = $this->createTurfAndSlots();
        $date = Carbon::tomorrow()->toDateString();

        $block = BlockBooking::create([
            'location_id' => $location->id,
            'turf_id' => $turf->id,
            'date' => $date,
            'reason' => 'Grass Trimming',
        ]);
        $block->slots()->attach([$slot1->id]);

        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $customer = User::factory()->create(['mobile_number' => '9988776655']);

        Livewire::actingAs($admin)
            ->test('administrator.book-slots-management')
            ->set('selected_user', $customer)
            ->set('mobile_number', '9988776655')
            ->set('location_id', $location->id)
            ->set('turf_id', $turf->id)
            ->set('selected_date', $date)
            ->set('selected_slots', [$slot1->id])
            ->set('payment_amount', 500)
            ->set('payment_type', 'Cash')
            ->call('bookNow')
            ->assertSee('blocked by administration');

        $this->assertDatabaseMissing('bookings', [
            'turf_id' => $turf->id,
            'date' => $date,
        ]);
    }
}
