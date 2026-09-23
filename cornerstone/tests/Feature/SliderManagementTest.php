<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Slider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SliderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the Administrator role required by role:Administrator middleware
        Role::create(['name' => 'Administrator']);
    }

    public function test_guest_cannot_access_slider_management(): void
    {
        $response = $this->get('/administrator/slider');
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_slider_management(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/administrator/slider');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_slider_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $response = $this->actingAs($user)->get('/administrator/slider');
        $response->assertOk()
            ->assertSeeVolt('administrator.slider-management');
    }

    public function test_admin_can_create_slider(): void
    {
        Storage::fake('public_folder');
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('slide1.jpg');

        $component = Volt::test('administrator.slider-management')
            ->set('title', 'Summer Campaign')
            ->set('image', $file)
            ->set('sort_order', 5)
            ->set('is_active', true)
            ->call('saveSlider');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('sliders', [
            'title' => 'Summer Campaign',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $slider = Slider::first();
        $this->assertNotNull($slider->image);
        Storage::disk('public_folder')->assertExists($slider->image);
    }

    public function test_admin_can_update_slider(): void
    {
        Storage::fake('public_folder');
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user);

        $slider = Slider::create([
            'title' => 'Old Title',
            'image' => 'sliders/old.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Mock old file in storage
        Storage::disk('public_folder')->put('sliders/old.jpg', 'fake content');

        $newFile = UploadedFile::fake()->image('new_slide.jpg');

        $component = Volt::test('administrator.slider-management')
            ->call('editSlider', $slider)
            ->set('title', 'New Title')
            ->set('image', $newFile)
            ->set('sort_order', 2)
            ->call('saveSlider');

        $component->assertHasNoErrors();

        $slider->refresh();
        $this->assertEquals('New Title', $slider->title);
        $this->assertEquals(2, $slider->sort_order);

        // Assert old photo was deleted and new photo exists
        Storage::disk('public_folder')->assertMissing('sliders/old.jpg');
        Storage::disk('public_folder')->assertExists($slider->image);
    }

    public function test_admin_can_delete_slider(): void
    {
        Storage::fake('public_folder');
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user);

        $slider = Slider::create([
            'title' => 'To Delete',
            'image' => 'sliders/delete-me.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Storage::disk('public_folder')->put('sliders/delete-me.jpg', 'fake content');

        $component = Volt::test('administrator.slider-management')
            ->call('deleteSlider', $slider);

        $component->assertHasNoErrors();

        $this->assertDatabaseMissing('sliders', [
            'id' => $slider->id,
        ]);

        Storage::disk('public_folder')->assertMissing('sliders/delete-me.jpg');
    }

    public function test_api_returns_active_sliders(): void
    {
        Slider::create([
            'title' => 'Active 1',
            'image' => 'sliders/1.jpg',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Slider::create([
            'title' => 'Inactive',
            'image' => 'sliders/2.jpg',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        Slider::create([
            'title' => 'Active 2',
            'image' => 'sliders/3.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/sliders');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.title', 'Active 2') // Sorted by sort_order
            ->assertJsonPath('1.title', 'Active 1');
    }

    public function test_admin_can_reorder_sliders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Administrator');
        $this->actingAs($user);

        $sliderA = Slider::create([
            'title' => 'Slider A',
            'image' => 'sliders/a.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $sliderB = Slider::create([
            'title' => 'Slider B',
            'image' => 'sliders/b.jpg',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // Reorder so Slider B is first, Slider A is second
        Volt::test('administrator.slider-management')
            ->call('updateSliderOrder', [$sliderB->id, $sliderA->id])
            ->assertHasNoErrors();

        $this->assertEquals(1, $sliderB->fresh()->sort_order);
        $this->assertEquals(2, $sliderA->fresh()->sort_order);
    }
}
