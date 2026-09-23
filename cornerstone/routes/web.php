<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;

Route::view('/', 'welcome');

// Dashboard: redirect to role-based workspace
Route::get('dashboard', function (Request $request) {
    $user = $request->user();

    if ($user->hasRole('Administrator')) {
        return redirect()->route('administrator.dashboard');
    }

    if ($user->hasRole('Manager')) {
        return redirect()->route('manager.dashboard');
    }

    // Client or any other role — log out and show no-access page
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('client.no-access');
})->middleware(['auth', 'verified'])->name('dashboard');

// Administrator workspace
Route::middleware(['auth', 'verified', 'role:Administrator'])->prefix('administrator')->name('administrator.')->group(function () {
    Route::get('dashboard', fn() => view('administrator.dashboard'))->name('dashboard');
    Route::get('slider', fn() => view('administrator.slider'))->name('slider');
    Route::get('users', fn() => view('administrator.users', ['users' => \App\Models\User::with('roles')->orderBy('name')->get()]))->name('users');
    Route::get('location-manager', fn() => view('administrator.location-manager'))->name('locations');
    Route::get('turfs-manager', fn() => view('administrator.turfs-manager'))->name('turfs');
    Route::get('turf-photos', fn() => view('administrator.turf-photos'))->name('photos');
    Route::get('turf-facilities', fn() => view('administrator.turf-facilities'))->name('facilities');
    Route::get('turf-sports', fn() => view('administrator.turf-sports'))->name('sports');
    Route::get('slot-categories', fn() => view('administrator.slot-categories'))->name('slot-categories');
    Route::get('slots-manager', fn() => view('administrator.slots'))->name('slots');
    Route::get('book-slots', fn() => view('administrator.book-slots'))->name('book-slots');
    Route::get('block-booking', fn() => view('administrator.block-booking'))->name('block-booking');
    Route::get('bookings', fn() => view('administrator.bookings'))->name('bookings');
    Route::get('payments', fn() => view('administrator.payments'))->name('payments');
    Route::get('coupons', fn() => view('administrator.coupons'))->name('coupons');
    Route::get('profile', fn() => view('administrator.profile'))->name('profile');
    Route::get('settings', fn() => view('administrator.settings'))->name('settings');
});

// Manager workspace
Route::get('manager/dashboard', fn() => view('manager.dashboard'))
    ->middleware(['auth', 'verified', 'role:Manager'])
    ->name('manager.dashboard');

// Client — no access page (no auth required, user is already logged out)
Route::get('client/no-access', fn() => view('client.no-access'))
    ->name('client.no-access');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::view('privacy-policy', 'privacy-policy')->name('privacy-policy');
Route::view('terms-and-conditions', 'terms-and-conditions')->name('terms-and-conditions');
Route::view('refund-policy', 'refund-policy')->name('refund-policy');

Route::get('demo', function () {
    $user = User::find(1);
    $user->assignRole('Administrator');
    $user->assignRole('Manager');
    return $user->roles()->get();
});

require __DIR__ . '/auth.php';