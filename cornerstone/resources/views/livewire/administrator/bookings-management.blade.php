<?php

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $selected_date = '';
    public $search = '';

    // Modals
    public $showPaymentModal = false;
    public $showAttendanceModal = false;
    public $activeBooking = null;

    // Payment Form
    public $payment_amount = '';
    public $payment_type = 'Cash';

    // Attendance Form
    public $players = '';
    public $came = 'No';

    public function mount()
    {
        $this->selected_date = Carbon::today()->toDateString();
    }

    public function prevDay()
    {
        $this->selected_date = Carbon::parse($this->selected_date)->subDay()->toDateString();
        $this->resetPage();
    }

    public function nextDay()
    {
        $this->selected_date = Carbon::parse($this->selected_date)->addDay()->toDateString();
        $this->resetPage();
    }

    public function prevRange()
    {
        $this->prevDay();
    }

    public function nextRange()
    {
        $this->nextDay();
    }

    public function goToToday()
    {
        $this->selected_date = Carbon::today()->toDateString();
        $this->resetPage();
    }

    public function updatedSelectedDate($value)
    {
        $this->resetPage();
    }

    public function selectDate($date)
    {
        $this->selected_date = $date;
        $this->resetPage();
    }

    public function formatBookingSlots($slots)
    {
        if (!$slots || $slots->isEmpty()) {
            return [];
        }

        $sorted = $slots->sortBy('from')->values();
        $ranges = [];
        $currentRange = null;

        foreach ($sorted as $slot) {
            $from = Carbon::parse($slot->from);
            $to = Carbon::parse($slot->to);
            $isMidnightEnd = ($slot->to === '23:59:59' || $to->format('H:i') === '23:59');
            $slotMinutes = (int) ($slot->minutes ?? 30);
            if ($slotMinutes <= 0) {
                $slotMinutes = 30;
            }

            if ($currentRange === null) {
                $currentRange = [
                    'start' => $from,
                    'end' => $to,
                    'is_midnight_end' => $isMidnightEnd,
                    'total_minutes' => $slotMinutes,
                    'slot_count' => 1,
                ];
            } else {
                if ($currentRange['end']->format('H:i') === $from->format('H:i')) {
                    $currentRange['end'] = $to;
                    $currentRange['is_midnight_end'] = $isMidnightEnd;
                    $currentRange['total_minutes'] += $slotMinutes;
                    $currentRange['slot_count']++;
                } else {
                    $ranges[] = $currentRange;
                    $currentRange = [
                        'start' => $from,
                        'end' => $to,
                        'is_midnight_end' => $isMidnightEnd,
                        'total_minutes' => $slotMinutes,
                        'slot_count' => 1,
                    ];
                }
            }
        }

        if ($currentRange !== null) {
            $ranges[] = $currentRange;
        }

        foreach ($ranges as &$range) {
            $hours = intdiv($range['total_minutes'], 60);
            $mins = $range['total_minutes'] % 60;

            if ($hours > 0 && $mins > 0) {
                $range['duration'] = "{$hours} hr {$mins} mins";
            } elseif ($hours > 0) {
                $range['duration'] = $hours === 1 ? '1 hr' : "{$hours} hrs";
            } else {
                $range['duration'] = "{$mins} mins";
            }

            $range['end_label'] = $range['is_midnight_end'] ? '12:00 AM' : $range['end']->format('g:i A');
        }

        return $ranges;
    }

    // --- Payment Logic ---
    public function openPaymentModal($bookingId)
    {
        $this->activeBooking = Booking::with('bookingPayments')->findOrFail($bookingId);
        $remaining = $this->activeBooking->amount - $this->activeBooking->total_received;
        $this->payment_amount = $remaining > 0 ? $remaining : 0;
        $this->payment_type = 'Cash';
        $this->showPaymentModal = true;
    }

    public function savePayment()
    {
        $this->validate([
            'payment_amount' => 'required|numeric|min:1',
            'payment_type' => 'required|in:Cash,UPI,App,Other',
        ]);

        DB::transaction(function () {
            BookingPayment::create([
                'booking_id' => $this->activeBooking->id,
                'amount' => $this->payment_amount,
                'type' => $this->payment_type,
            ]);

            // Update booking status if needed (refresh total_received)
            $this->activeBooking->refresh();
            if ($this->activeBooking->is_fully_paid) {
                $this->activeBooking->update(['payment_type' => 'Full']);
            }
        });

        session()->flash('message', 'Payment recorded successfully.');
        $this->closeModals();
    }

    // --- Attendance Logic ---
    public function openAttendanceModal($bookingId)
    {
        $this->activeBooking = Booking::with(['user', 'turf'])->findOrFail($bookingId);
        $this->players = $this->activeBooking->players;
        $this->came = $this->activeBooking->came ?? 'Yes';
        $this->showAttendanceModal = true;
    }

    public function saveAttendance()
    {
        $this->validate([
            'players' => 'nullable|integer|min:0',
            'came' => 'required|in:Yes,No',
        ]);

        $this->activeBooking->update([
            'players' => $this->players,
            'came' => $this->came,
        ]);

        $statusText = $this->came === 'Yes' ? 'Customer marked as Checked In.' : 'Customer marked as Not Arrived.';
        session()->flash('message', $statusText);
        $this->closeModals();
    }

    // --- Cancellation Logic ---
    public function cancelBooking($bookingId)
    {
        $booking = Booking::with(['slots', 'bookingPayments'])->findOrFail($bookingId);
        $settings = Setting::first();

        // If settings record doesn't exist, we create a temporary one with defaults for this check
        if (!$settings) {
            $settings = new Setting([
                'is_cancellation_active' => true,
                'cancellation_hours' => 0,
                'cancellation_fee' => 0,
                'is_refund_active' => true
            ]);
        }

        if ($settings->is_cancellation_active == false) {
            session()->flash('error', 'Cancellation is currently disabled in system settings.');
            return;
        }

        // Check window (using first slot - sorted by start time)
        $firstSlot = $booking->slots->sortBy('from')->first();
        if ($firstSlot) {
            $bookingTime = Carbon::parse($booking->date . ' ' . $firstSlot->from);
            if (Carbon::now()->addHours($settings->cancellation_hours)->gt($bookingTime)) {
                session()->flash('error', "Cancellation window closed ({$settings->cancellation_hours}h required).");
                return;
            }
        }

        try {
            DB::transaction(function () use ($booking, $settings) {
                $totalReceived = $booking->total_received;
                $refundAmount = 0;

                if ($settings->is_refund_active && $totalReceived > 0) {
                    $refundAmount = $totalReceived - $settings->cancellation_fee;
                    $refundAmount = $refundAmount > 0 ? $refundAmount : 0;

                    if ($refundAmount > 0) {
                        BookingPayment::create([
                            'booking_id' => $booking->id,
                            'amount' => -$refundAmount,
                            'type' => 'Other',
                        ]);
                    }
                }

                // Directly set and save to avoid any mass-assignment or instance issues
                $booking->status = 'Cancelled';
                $booking->cancelled_at = Carbon::now();
                $booking->refund_amount = $refundAmount;
                $booking->save();
            });

            session()->flash('message', "Booking #{$bookingId} has been successfully cancelled and slots are now open.");
        } catch (\Exception $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function closeModals()
    {
        $this->showPaymentModal = false;
        $this->showAttendanceModal = false;
        $this->activeBooking = null;
    }

    public function with()
    {
        $query = Booking::with(['user', 'turf', 'location', 'bookingPayments', 'slots'])
            ->where('date', $this->selected_date)
            ->whereIn('status', ['Success', 'Cancelled']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('id', 'like', "%{$this->search}%")
                    ->orWhereHas('user', function ($qu) {
                        $qu->where('name', 'like', "%{$this->search}%")
                            ->orWhere('mobile_number', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('turf', function ($qt) {
                        $qt->where('name', 'like', "%{$this->search}%");
                    });
            });
        }

        return [
            'bookings' => $query->orderBy('created_at', 'desc')->get(),
        ];
    }
}; ?>

<div class="text-dark">
    @if (session()->has('message'))
        <div
            class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div
            class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <span>{{ session('error') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        $isToday = ($selected_date === \Carbon\Carbon::today()->toDateString());
        $carbonDate = \Carbon\Carbon::parse($selected_date);
    @endphp

    <!-- Scoped Styles for Flutter-Matched Date Selector -->
    <style>
        .date-nav-flutter-btn {
            transition: all 0.15s ease-in-out;
        }
        .date-nav-flutter-btn:hover {
            background-color: #f0fdf4 !important;
            border-color: #16a34a !important;
            transform: scale(1.05);
        }
        .date-display-flutter-card {
            transition: all 0.15s ease-in-out;
        }
        .date-display-flutter-card:hover {
            border-color: #16a34a !important;
            background-color: #fafffc !important;
            box-shadow: 0 2px 10px rgba(22, 163, 74, 0.12);
        }
        .today-flutter-btn {
            transition: all 0.15s ease-in-out;
        }
        .today-flutter-btn:hover {
            background-color: #15803d !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
        }
    </style>

    <!-- Date Selector (Matches Flutter Manager App) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden"
        style="background: #f0fdf4; border: 1px solid #bbf7d0 !important;">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-2">
                <!-- Previous Date Button (<) -->
                <button type="button" wire:click="prevDay"
                    class="btn d-flex align-items-center justify-content-center p-0 flex-shrink-0 date-nav-flutter-btn"
                    style="width: 44px; height: 44px; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 12px;"
                    title="Previous Date"
                    aria-label="Previous Date">
                    <svg style="width: 1.5rem; height: 1.5rem; color: #16a34a;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Center Date Display Card (Clickable to pick date) -->
                <div class="position-relative flex-grow-1">
                    <div class="d-flex align-items-center justify-content-center px-3 date-display-flutter-card"
                        style="height: 44px; background: #ffffff; border: {{ $isToday ? '1.5px solid #16a34a' : '1px solid #bbf7d0' }}; border-radius: 12px; cursor: pointer;">
                        <!-- Calendar Icon -->
                        <svg style="width: 1.15rem; height: 1.15rem; color: {{ $isToday ? '#16a34a' : '#64748b' }}; margin-right: 0.5rem; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <!-- Formatted Date Label -->
                        <span class="fw-bold text-truncate" style="font-size: 0.875rem; color: {{ $isToday ? '#16a34a' : '#1e293b' }};">
                            {{ $isToday ? 'Today, ' . $carbonDate->format('d M Y') : $carbonDate->format('D, d M Y') }}
                        </span>
                        <!-- Dropdown Chevron Icon -->
                        <svg style="width: 1.15rem; height: 1.15rem; color: #64748b; margin-left: 0.35rem; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <!-- Native Date Picker Input (Invisible Overlay) -->
                    <input type="date"
                        wire:model.live="selected_date"
                        class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                        style="cursor: pointer;"
                        title="Click to select date">
                </div>

                <!-- Next Date Button (>) -->
                <button type="button" wire:click="nextDay"
                    class="btn d-flex align-items-center justify-content-center p-0 flex-shrink-0 date-nav-flutter-btn"
                    style="width: 44px; height: 44px; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 12px;"
                    title="Next Date"
                    aria-label="Next Date">
                    <svg style="width: 1.5rem; height: 1.5rem; color: #16a34a;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                <!-- Quick 'Today' Button when selected date is not today -->
                @if(!$isToday)
                    <button type="button" wire:click="goToToday"
                        class="btn d-flex align-items-center justify-content-center gap-1 px-3 flex-shrink-0 shadow-sm today-flutter-btn"
                        style="height: 44px; background: #16a34a; border: none; border-radius: 12px; color: #ffffff; font-weight: 700; font-size: 0.8125rem;"
                        title="Return to Today">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <span>Today</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Bookings List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4">
            <div class="row align-items-center justify-content-between g-3">
                <div class="col-md-6">
                    <h3 class="h6 fw-bold mb-0 text-dark">
                        @if($search)
                            Search Results for "{{ $search }}"
                        @else
                            Bookings for {{ Carbon::parse($selected_date)->format('M d, Y') }}
                        @endif
                    </h3>
                </div>
                <div class="col-md-5 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 ps-3">
                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" 
                            class="form-control bg-light border-0 py-2 small" 
                            placeholder="Search by ID, Customer or Turf...">
                        @if($search)
                            <button wire:click="$set('search', '')" class="btn btn-light border-0">
                                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0 small fw-bold text-muted text-uppercase"># ID</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Booking Info</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Slots</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-center">Check-In</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-center">Payment</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-end">Amount</th>
                        <th class="pe-4 py-3 border-0 small fw-bold text-muted text-uppercase text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($bookings as $booking)
                        @php $isCancelled = $booking->status === 'Cancelled'; @endphp
                        <tr wire:key="booking-{{ $booking->id }}" class="{{ $isCancelled ? 'bg-light bg-gradient text-muted' : '' }}">
                            <td class="ps-4">
                                <span class="fw-bold text-muted">#{{ $booking->id }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-{{ $isCancelled ? 'secondary' : 'success' }}-subtle text-{{ $isCancelled ? 'secondary' : 'success' }} rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-xs"
                                        style="width: 2.5rem; height: 2.5rem; flex-shrink: 0; font-size: 0.95rem;">
                                        {{ strtoupper(substr($booking->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold {{ $isCancelled ? 'text-decoration-line-through text-muted' : 'text-dark' }}">
                                            {{ $booking->user->name }}
                                        </div>
                                        <div class="mt-0.5">
                                            <a href="tel:{{ $booking->user->mobile_number }}" 
                                               class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1 fw-medium" 
                                               style="font-size: 0.775rem;"
                                               title="Call customer">
                                                <svg style="width: 0.8rem; height: 0.8rem; color: #16a34a;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                                </svg>
                                                <span>{{ $booking->user->mobile_number }}</span>
                                            </a>
                                        </div>
                                        <div class="mt-0.5">
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size: 0.7rem; font-weight: 600;">
                                                {{ $booking->turf->name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @php $slotRanges = $this->formatBookingSlots($booking->slots); @endphp
                                @forelse($slotRanges as $range)
                                    <div class="d-flex align-items-center gap-1.5 mb-1 flex-wrap">
                                        <span class="badge bg-white text-dark fw-bold border shadow-xs px-2.5 py-1.5 d-inline-flex align-items-center gap-1.5" style="font-size: 0.775rem;">
                                            <svg style="width: 0.85rem; height: 0.85rem; color: #16a34a;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>{{ $range['start']->format('g:i A') }} – {{ $range['end_label'] }}</span>
                                        </span>
                                        <span class="badge rounded-pill bg-light text-secondary border px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">
                                            {{ $range['duration'] }}
                                        </span>
                                    </div>
                                @empty
                                    <span class="text-muted extra-small">No slots</span>
                                @endforelse
                            </td>
                            {{-- Check-In / Attendance Status --}}
                            <td class="text-center">
                                @if($isCancelled)
                                    <span class="badge rounded-pill bg-light text-muted border px-2 py-1 extra-small">-</span>
                                @elseif($booking->came === 'Yes')
                                    <div class="d-inline-flex flex-column align-items-center">
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1 d-inline-flex align-items-center gap-1" style="font-weight: 600; font-size: 0.75rem;">
                                            <svg style="width: 0.75rem; height: 0.75rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            Checked In
                                        </span>
                                        @if($booking->players)
                                            <span class="extra-small text-muted fw-semibold mt-1">
                                                {{ $booking->players }} {{ Str::plural('player', $booking->players) }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 d-inline-flex align-items-center gap-1" style="font-weight: 500; font-size: 0.75rem;">
                                        <svg style="width: 0.75rem; height: 0.75rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Pending
                                    </span>
                                @endif
                            </td>
                            {{-- Payment Status (Pure Financial) --}}
                            <td class="text-center">
                                @if($isCancelled)
                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">Cancelled</span>
                                    @if($booking->cancelled_at)
                                        <div class="extra-small text-muted mt-1">{{ Carbon::parse($booking->cancelled_at)->format('d M, g:i A') }}</div>
                                    @endif
                                @elseif($booking->is_fully_paid)
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.75rem;">
                                        <svg style="width: 0.75rem; height: 0.75rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        Paid
                                    </span>
                                    @if($booking->payment_type)
                                        <div class="extra-small text-muted mt-0.5">{{ $booking->payment_type }}</div>
                                    @endif
                                @elseif($booking->payment_type === 'PayAtLocation')
                                    <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.75rem;">
                                        Pay At Location
                                    </span>
                                @else
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.75rem;">
                                        Partial ({{ number_format(($booking->total_received / max($booking->amount, 1)) * 100, 0) }}%)
                                    </span>
                                    @if($booking->payment_type)
                                        <div class="extra-small text-muted mt-0.5">{{ $booking->payment_type }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-end">
                                @if($isCancelled)
                                    <div class="small fw-bold text-muted text-decoration-line-through">₹{{ number_format($booking->amount, 2) }}</div>
                                    @if($booking->refund_amount > 0)
                                        <div class="extra-small text-success fw-bold">Ref: ₹{{ number_format($booking->refund_amount, 2) }}</div>
                                    @endif
                                @else
                                    <div class="small fw-bold text-dark">₹{{ number_format($booking->amount, 2) }}</div>
                                    <div class="extra-small text-muted">Rec: ₹{{ number_format($booking->total_received, 2) }}</div>
                                    @if(!$booking->is_fully_paid)
                                        <div class="extra-small text-danger fw-semibold">Due: ₹{{ number_format($booking->amount - $booking->total_received, 2) }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1.5 flex-wrap">
                                    @if(!$isCancelled)
                                        {{-- 1. Collect Due (Only shown if payment is pending) --}}
                                        @if(!$booking->is_fully_paid)
                                            @php $dueAmount = $booking->amount - $booking->total_received; @endphp
                                            <button wire:click="openPaymentModal({{ $booking->id }})"
                                                class="btn btn-sm btn-success text-white rounded-3 shadow-xs d-inline-flex align-items-center gap-1 action-btn-item"
                                                style="font-size: 0.8125rem; font-weight: 600;"
                                                title="Collect remaining balance: ₹{{ number_format($dueAmount, 2) }}">
                                                <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Collect ₹{{ number_format($dueAmount, 0) }}</span>
                                            </button>
                                        @endif

                                        {{-- 2. Check In Button (Renamed from Status, shows Checked In state) --}}
                                        @if($booking->came === 'Yes')
                                            <button wire:click="openAttendanceModal({{ $booking->id }})"
                                                class="btn btn-sm btn-outline-success rounded-3 d-inline-flex align-items-center gap-1 action-btn-item"
                                                style="font-size: 0.8125rem; font-weight: 600;"
                                                title="Update Check-in ({{ $booking->players ?? 0 }} players)">
                                                <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                                <span>Checked In</span>
                                            </button>
                                        @else
                                            <button wire:click="openAttendanceModal({{ $booking->id }})"
                                                class="btn btn-sm btn-outline-primary rounded-3 d-inline-flex align-items-center gap-1 action-btn-item"
                                                style="font-size: 0.8125rem; font-weight: 600;"
                                                title="Mark attendance / Check in customer">
                                                <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                </svg>
                                                <span>Check In</span>
                                            </button>
                                        @endif

                                        {{-- 3. Cancel Booking Button --}}
                                        <button wire:click="cancelBooking({{ $booking->id }})"
                                            wire:confirm="Are you sure you want to cancel this booking? Refund will be calculated based on system settings."
                                            wire:loading.attr="disabled"
                                            wire:target="cancelBooking({{ $booking->id }})"
                                            class="btn btn-sm btn-outline-danger rounded-3 d-inline-flex align-items-center gap-1 action-btn-item"
                                            style="font-size: 0.8125rem; font-weight: 500;"
                                            title="Cancel booking">
                                            <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span>Cancel</span>
                                        </button>
                                    @else
                                        <span class="badge rounded-pill bg-light text-muted border px-2.5 py-1.5" style="font-size: 0.75rem;">
                                            No actions
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center">
                                <p class="text-muted">No bookings found for this date.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade @if($showPaymentModal) show d-block @endif" tabindex="-1"
        style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom p-4">
                    <h5 class="modal-title fw-bold">Collect Remaining Payment</h5>
                    <button type="button" class="btn-close" wire:click="closeModals"></button>
                </div>
                <div class="modal-body p-4">
                    @if($activeBooking)
                        <div class="mb-4 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="extra-small text-muted text-uppercase fw-bold">Customer</div>
                                <div class="fw-bold">{{ $activeBooking->user->name }}</div>
                            </div>
                            <div class="text-end">
                                <div class="extra-small text-muted text-uppercase fw-bold">Due Amount</div>
                                <div class="h5 mb-0 fw-bold text-danger">
                                    ₹{{ number_format($activeBooking->amount - $activeBooking->total_received, 2) }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Amount to Collect</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0">₹</span>
                                <input wire:model="payment_amount" type="number" step="0.01"
                                    class="form-control border-start-0 ps-0 fw-bold text-success">
                            </div>
                            @error('payment_amount') <div class="text-danger extra-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Payment Type</label>
                            <select wire:model="payment_type" class="form-select rounded-3">
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="App">App</option>
                                <option value="Other">Other</option>
                            </select>
                            @error('payment_type') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-top p-4">
                    <button wire:click="savePayment"
                        class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm">Record Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div class="modal fade @if($showAttendanceModal) show d-block @endif" tabindex="-1"
        style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered shadow-lg">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-bottom p-4 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;">
                            <svg style="width: 1.1rem; height: 1.1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="font-size: 1.1rem;">Customer Check-In</h5>
                            <span class="text-muted extra-small">Verify arrival & head count</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModals"></button>
                </div>
                <div class="modal-body p-4">
                    @if($activeBooking)
                        <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark">{{ $activeBooking->user?->name ?? 'Guest User' }}</div>
                                    <div class="text-muted extra-small">{{ $activeBooking->user?->mobile_number ?? $activeBooking->user?->mobile ?? 'No phone' }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-white text-dark border">#BK-{{ $activeBooking->id }}</span>
                                    <div class="text-muted extra-small mt-1">{{ $activeBooking->turf?->name ?? 'Turf' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted d-block mb-2">Check-in Status</label>
                        <div class="d-flex gap-3">
                            <div class="flex-grow-1">
                                <input type="radio" wire:model="came" value="Yes" class="btn-check" id="came_yes">
                                <label class="btn btn-outline-success w-100 rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-1.5"
                                    for="came_yes">
                                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span>Arrived (Checked In)</span>
                                </label>
                            </div>
                            <div class="flex-grow-1">
                                <input type="radio" wire:model="came" value="No" class="btn-check" id="came_no">
                                <label class="btn btn-outline-danger w-100 rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-1.5"
                                    for="came_no">
                                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Not Arrived</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-muted">Total Players (Headcount)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </span>
                            <input wire:model="players" type="number" min="1" max="50" class="form-control form-control-lg border-start-0 rounded-end-3"
                                placeholder="e.g. 10">
                        </div>
                        @error('players') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer border-top p-4">
                    <button wire:click="saveAttendance"
                        class="btn btn-primary w-100 rounded-3 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <span>Confirm Check-In</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .extra-small {
            font-size: 0.7rem;
        }

        .transition-all {
            transition: all 0.2s ease-in-out;
        }

        .cursor-not-allowed {
            cursor: allowed;
        }

        .action-btn-item {
            transition: all 0.15s ease-in-out;
        }

        .action-btn-item:hover {
            transform: translateY(-1px);
        }

        .gap-1\.5 {
            gap: 0.375rem;
        }
    </style>
</div>