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

    public function test_bookings_component_mounts_with_current_date_and_7_day_strip(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today()->toDateString();

        $component = Volt::test('administrator.bookings-management')
            ->assertSet('selected_date', $today)
            ->assertSet('base_date', $today);

        $dates = $component->get('dates');
        $this->assertCount(7, $dates);
        $this->assertEquals($today, $dates[0]['full']);
        $this->assertTrue($dates[0]['is_today']);
    }

    public function test_prev_and_next_range_navigation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today();
        $expectedNext = $today->copy()->addDays(7)->toDateString();
        $expectedPrev = $today->copy()->subDays(7)->toDateString();

        $component = Volt::test('administrator.bookings-management')
            ->call('nextRange')
            ->assertSet('base_date', $expectedNext);

        $dates = $component->get('dates');
        $this->assertEquals($expectedNext, $dates[0]['full']);

        $component->call('prevRange')
            ->call('prevRange')
            ->assertSet('base_date', $expectedPrev);
    }

    public function test_go_to_today_and_select_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');
        $this->actingAs($admin);

        $today = Carbon::today()->toDateString();
        $otherDate = Carbon::today()->addDays(3)->toDateString();

        $component = Volt::test('administrator.bookings-management')
            ->call('nextRange')
            ->call('selectDate', $otherDate)
            ->assertSet('selected_date', $otherDate)
            ->call('goToToday')
            ->assertSet('selected_date', $today)
            ->assertSet('base_date', $today);
    }
}
