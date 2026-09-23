<?php

use App\Models\User;
use App\Models\Location;
use App\Models\Turf;
use App\Models\Slot;
use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    // Customer search
    public $mobile_number = '';
    public $selected_user = null;

    // Selection filters
    public $selected_date = '';
    public $dates = [];
    public $base_date = null;
    public $location_id = '';
    public $turf_id = '';

    // Slot selection
    public $selected_slots = []; // Array of slot IDs

    // Payment details
    public $payment_amount = '';
    public $payment_type = 'Cash';

    public function mount() {
        $this->selected_date = Carbon::today()->toDateString();
        $this->base_date = Carbon::today();
        $this->generateDates();
    }

    public function generateDates() {
        $this->dates = [];
        $current = clone $this->base_date;
        // Show 7 days starting from base_date
        for ($i = 0; $i < 7; $i++) {
            $this->dates[] = [
                'full' => $current->toDateString(),
                'day' => $current->format('d'),
                'short' => $current->format('D'),
                'month' => $current->format('M'),
            ];
            $current->addDay();
        }
    }

    public function prevRange() {
        $newBase = (clone $this->base_date)->subDays(7);
        if ($newBase->lt(Carbon::today())) {
            $this->base_date = Carbon::today();
        } else {
            $this->base_date = $newBase;
        }
        $this->generateDates();
    }

    public function nextRange() {
        $this->base_date = $this->base_date->addDays(7);
        $this->generateDates();
    }

    public function updatedMobileNumber($value) {
        if (strlen($value) >= 10) {
            $this->selected_user = User::where('mobile_number', $value)->first();
        } else {
            $this->selected_user = null;
        }
    }

    public function updatedLocationId() {
        $this->turf_id = '';
        $this->selected_slots = [];
    }

    public function updatedTurfId() {
        $this->selected_slots = [];
    }

    public function selectDate($date) {
        $this->selected_date = $date;
        $this->selected_slots = [];
        $this->calculateTotal();
    }

    public function toggleSlot($slotId) {
        if (in_array($slotId, $this->selected_slots)) {
            $this->selected_slots = array_diff($this->selected_slots, [$slotId]);
        } else {
            $this->selected_slots[] = $slotId;
        }
        $this->calculateTotal();
    }

    public function calculateTotal() {
        if (empty($this->selected_slots)) {
            $this->payment_amount = 0;
            return;
        }

        $dayOfWeek = strtolower(Carbon::parse($this->selected_date)->format('D'));
        $priceField = $dayOfWeek . '_amount';

        $this->payment_amount = Slot::whereIn('id', $this->selected_slots)
            ->sum($priceField);
    }

    public function locations() {
        return Location::orderBy('name')->get();
    }

    public function turfs() {
        return Turf::when($this->location_id, fn($q) => $q->where('location_id', $this->location_id))
            ->orderBy('name')
            ->get();
    }

    public function availableSlots() {
        if (!$this->turf_id) return collect();

        // Get day of week for pricing
        $dayOfWeek = strtolower(Carbon::parse($this->selected_date)->format('D'));
        $priceField = $dayOfWeek . '_amount';

        // Get already booked slot IDs for this date and turf
        $bookedSlots = Booking::where('date', $this->selected_date)
            ->where('turf_id', $this->turf_id)
            ->where('status', 'Success')
            ->with('slots')
            ->get()
            ->flatMap(function($b) {
                return $b->slots->map(fn($s) => ['id' => $s->id, 'type' => 'booked', 'reason' => 'Customer Booking']);
            })
            ->pluck(null, 'id')
            ->toArray();

        // Also get blocked slot IDs with reasons
        $blockedSlots = DB::table('block_booking_slots')
            ->join('block_bookings', 'block_booking_slots.block_booking_id', '=', 'block_bookings.id')
            ->where('block_bookings.date', $this->selected_date)
            ->where('block_bookings.turf_id', $this->turf_id)
            ->select('slot_id', 'reason')
            ->get()
            ->mapWithKeys(fn($b) => [$b->slot_id => ['type' => 'blocked', 'reason' => $b->reason ?: 'Maintenance']])
            ->toArray();

        // Merge both (blocks take precedence for visual clarity)
        $unavailableSlots = $blockedSlots + $bookedSlots;

        return Slot::where('turf_id', $this->turf_id)
            ->where('is_active', true)
            ->with('slotCategory')
            ->get()
            ->groupBy(function($slot) {
                return $slot->slotCategory->category_name ?? 'Other';
            })
            ->map(function($categorySlots) use ($priceField, $unavailableSlots) {
                return $categorySlots->map(function($slot) use ($priceField, $unavailableSlots) {
                    $status = $unavailableSlots[$slot->id] ?? null;
                    return [
                        'id' => $slot->id,
                        'time' => Carbon::parse($slot->from)->format('g:i A') . ' - ' . Carbon::parse($slot->to)->format('g:i A'),
                        'price' => $slot->$priceField,
                        'is_booked' => isset($status),
                        'status_type' => $status['type'] ?? null,
                        'reason' => $status['reason'] ?? null,
                    ];
                });
            });
    }

    public function bookNow() {
        $this->validate([
            'selected_user' => 'required',
            'location_id' => 'required',
            'turf_id' => 'required',
            'selected_slots' => 'required|array|min:1',
            'payment_amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:Cash,UPI,App,Other',
        ], [
            'selected_user.required' => 'Please enter a valid customer mobile number.',
            'selected_slots.required' => 'Please select at least one slot.',
        ]);

        try {
            DB::transaction(function () {
                // Get day of week for pricing
                $dayOfWeek = strtolower(Carbon::parse($this->selected_date)->format('D'));
                $priceField = $dayOfWeek . '_amount';
                
                $totalBookingAmount = Slot::whereIn('id', $this->selected_slots)->sum($priceField);

                // Determine payment status based on amount comparison
                $pType = 'Full';
                if ($this->payment_amount <= 0) {
                    $pType = 'PayAtLocation';
                } elseif ($this->payment_amount < $totalBookingAmount) {
                    $pType = 'Part';
                }

                $booking = Booking::create([
                    'date' => $this->selected_date,
                    'location_id' => $this->location_id,
                    'user_id' => $this->selected_user->id,
                    'turf_id' => $this->turf_id,
                    'amount' => $totalBookingAmount,
                    'payment_type' => $pType,
                    'status' => 'Success',
                ]);

                $booking->slots()->attach($this->selected_slots);

                if ($this->payment_amount > 0) {
                    BookingPayment::create([
                        'booking_id' => $booking->id,
                        'type' => $this->payment_type,
                        'amount' => $this->payment_amount,
                    ]);
                }
            });

            session()->flash('message', 'Booking successful!');
            $this->reset(['mobile_number', 'selected_user', 'location_id', 'turf_id', 'selected_slots', 'payment_amount', 'payment_type']);
            $this->selected_date = Carbon::today()->toDateString();

        } catch (\Exception $e) {
            session()->flash('error', 'Something went wrong: ' . $e->getMessage());
        }
    }
}; ?>

<div class="row g-4 text-dark">
    @if (session()->has('message'))
        <div class="col-12">
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-0 d-flex align-items-center justify-content-between">
                <span>{{ session('message') }}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="col-12">
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <!-- Left Column: Search & Filters -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-4">
                <h3 class="h6 fw-bold mb-0">Customer Lookup</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Mobile Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                            </svg>
                        </span>
                        <input wire:model.live.debounce.300ms="mobile_number" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Enter 10 digit mobile...">
                    </div>
                </div>

                @if($selected_user)
                    <div class="p-3 rounded-4 bg-success-subtle border border-success-subtle mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 2.5rem; height: 2.5rem;">
                                {{ strtoupper(substr($selected_user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-bold small">{{ $selected_user->name }}</div>
                                <div class="extra-small text-muted">{{ $selected_user->email }}</div>
                            </div>
                        </div>
                    </div>
                @elseif(strlen($mobile_number) >= 10)
                    <div class="alert alert-warning py-2 rounded-3 extra-small">
                        No customer found with this number.
                    </div>
                @endif
                @error('selected_user') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-4">
                <h3 class="h6 fw-bold mb-0">Location & Turf</h3>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Location</label>
                    <select wire:model.live="location_id" class="form-select rounded-3">
                        <option value="">Select Location...</option>
                        @foreach($this->locations() as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-semibold text-muted">Available Turfs</label>
                    <select wire:model.live="turf_id" class="form-select rounded-3" @disabled(!$location_id)>
                        <option value="">Select Turf...</option>
                        @foreach($this->turfs() as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Date & Slots -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-4">
                <h3 class="h6 fw-bold mb-0">Select Date</h3>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-center align-items-center gap-3">
                    <!-- Left Arrow -->
                    @php $isToday = $base_date->isToday(); @endphp
                    <button wire:click="prevRange" 
                        class="flex-shrink-0 border-0 rounded-4 p-3 d-flex align-items-center justify-content-center transition-all bg-light text-muted {{ $isToday ? 'opacity-25' : '' }}" 
                        style="width: 4.5rem; height: 4.5rem;"
                        @if($isToday) disabled @endif>
                        <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Dates -->
                    <div class="d-flex gap-3 no-scrollbar overflow-x-auto">
                        @foreach($dates as $date)
                            <button type="button" 
                                wire:key="date-{{ $date['full'] }}"
                                wire:click="selectDate('{{ $date['full'] }}')"
                                class="flex-shrink-0 border-0 rounded-4 p-3 d-flex flex-column align-items-center transition-all"
                                style="min-width: 4.5rem; {{ $selected_date == $date['full'] ? 'background: #16a34a; color: white !important; transform: translateY(-4px); box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);' : 'background: #f8fafc; color: #64748b;' }}">
                                <span class="extra-small fw-semibold opacity-75">{{ $date['short'] }}</span>
                                <span class="h4 fw-bold my-1">{{ $date['day'] }}</span>
                                <span class="extra-small fw-medium opacity-75">{{ $date['month'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Right Arrow -->
                    <button wire:click="nextRange" class="flex-shrink-0 border-0 rounded-4 p-3 d-flex align-items-center justify-content-center transition-all bg-light text-muted" style="width: 4.5rem; height: 4.5rem;">
                        <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="h6 fw-bold mb-0">Select Slots</h3>
                    @if(count($selected_slots) > 0)
                        <span class="badge rounded-pill bg-primary px-3">{{ count($selected_slots) }} Selected</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                @if(!$turf_id)
                    <div class="text-center py-5">
                        <svg class="text-light mb-3" style="width: 3rem; height: 3rem;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        <p class="text-muted">Please select a location and turf first.</p>
                    </div>
                @else
                    @forelse($this->availableSlots() as $categoryName => $slots)
                        <div class="mb-4 last:mb-0">
                            <h4 class="extra-small fw-bold text-uppercase text-muted tracking-wider mb-3 d-flex align-items-center gap-2">
                                <span class="bg-light px-2 py-1 rounded text-dark">{{ $categoryName }}</span>
                                <span class="flex-grow-1 border-bottom border-light"></span>
                            </h4>
                            <div class="row row-cols-2 row-cols-md-3 g-3">
                                @foreach($slots as $slot)
                                    <div class="col">
                                        @php
                                            $isUnavailable = $slot['is_booked'];
                                            $isBlocked = $slot['status_type'] === 'blocked';
                                            $isSelected = in_array($slot['id'], $this->selected_slots);
                                            
                                            $bgClass = 'background: #fefefe;';
                                            $borderClass = '';
                                            
                                            if ($isSelected) {
                                                $bgClass = 'background: #f0fdf4;';
                                                $borderClass = 'border-color: #16a34a !important;';
                                            } elseif ($isBlocked) {
                                                $bgClass = 'background: #fef2f2;';
                                                $borderClass = 'border-color: #ef4444 !important;';
                                            } elseif ($isUnavailable) {
                                                $bgClass = 'background: #f8fafc;';
                                                $borderClass = 'border-color: #e2e8f0;';
                                            }
                                        @endphp

                                        <div @if(!$isUnavailable) wire:click="toggleSlot({{ $slot['id'] }})" @endif 
                                            class="border rounded-4 p-3 d-flex flex-column gap-1 transition-all position-relative overflow-hidden {{ $isUnavailable ? 'cursor-not-allowed' : 'cursor-pointer' }}"
                                            style="{{ $bgClass }} {{ $borderClass }}">
                                            
                                            @if($isBlocked)
                                                <div class="position-absolute top-0 end-0 p-2">
                                                    <span class="badge rounded-pill bg-danger extra-small">{{ $slot['reason'] }}</span>
                                                </div>
                                            @elseif($isUnavailable)
                                                <div class="position-absolute top-0 end-0 p-2">
                                                    <span class="badge rounded-pill bg-secondary extra-small">Booked</span>
                                                </div>
                                            @elseif($isSelected)
                                                <div class="position-absolute top-0 end-0 p-2">
                                                    <svg style="color: #16a34a; width: 1rem; height: 1rem;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            @endif

                                            <span class="small fw-bold {{ $isBlocked ? 'text-danger' : 'text-dark' }}">{{ $slot['time'] }}</span>
                                            @if(!$isBlocked && !$isUnavailable)
                                                <span class="extra-small fw-semibold text-success">{{ number_format($slot['price'], 0) }} ₹</span>
                                            @else
                                                <span class="extra-small text-muted">{{ $isBlocked ? 'Blocked' : 'Occupied' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-4">
                            <p class="text-muted small">No slots found for this turf.</p>
                        </div>
                    @endforelse

                    @if(count($selected_slots) > 0)
                        <hr class="my-4">
                        <div class="row align-items-end g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Amount Paid</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input wire:model="payment_amount" type="number" step="0.01" class="form-control" placeholder="0.00">
                                </div>
                                @error('payment_amount') <div class="text-danger extra-small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Payment Type</label>
                                <select wire:model="payment_type" class="form-select">
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="App">App</option>
                                    <option value="Other">Other</option>
                                </select>
                                @error('payment_type') <div class="text-danger extra-small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <button wire:click="bookNow" wire:loading.attr="disabled" class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <span wire:loading.remove>Confirm Booking</span>
                                    <span wire:loading class="spinner-border spinner-border-sm"></span>
                                </button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .cursor-pointer { cursor: pointer; }
        .transition-all { transition: all 0.2s ease-in-out; }
        .extra-small { font-size: 0.7rem; }
        .no-scrollbar button:hover { transform: translateY(-2px); }
    </style>
</div>
