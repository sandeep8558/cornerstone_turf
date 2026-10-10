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
    public $dates = [];
    public $base_date = null;
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
        $this->base_date = Carbon::today()->toDateString();
        $this->generateDates();
    }

    protected function getBaseCarbon(): Carbon
    {
        return $this->base_date ? Carbon::parse($this->base_date) : Carbon::today();
    }

    public function generateDates()
    {
        $base = $this->getBaseCarbon();
        $this->dates = [];
        $current = $base->copy();
        $dateStrings = [];
        for ($i = 0; $i < 7; $i++) {
            $dateStrings[] = $current->toDateString();
            $current->addDay();
        }

        $counts = Booking::whereIn('date', $dateStrings)
            ->whereIn('status', ['Success', 'Cancelled'])
            ->selectRaw('date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $todayStr = Carbon::today()->toDateString();
        $current = $base->copy();
        for ($i = 0; $i < 7; $i++) {
            $full = $current->toDateString();
            $this->dates[] = [
                'full' => $full,
                'day' => $current->format('d'),
                'short' => $current->format('D'),
                'month' => $current->format('M'),
                'is_today' => ($full === $todayStr),
                'booking_count' => (int) ($counts[$full] ?? 0),
            ];
            $current->addDay();
        }
    }

    public function prevRange()
    {
        $this->base_date = $this->getBaseCarbon()->subDays(7)->toDateString();
        $this->generateDates();
    }

    public function nextRange()
    {
        $this->base_date = $this->getBaseCarbon()->addDays(7)->toDateString();
        $this->generateDates();
    }

    public function goToToday()
    {
        $today = Carbon::today()->toDateString();
        $this->selected_date = $today;
        $this->base_date = $today;
        $this->generateDates();
        $this->resetPage();
    }

    public function updatedSelectedDate($value)
    {
        if ($value) {
            try {
                $this->base_date = Carbon::parse($value)->toDateString();
                $this->generateDates();
                $this->resetPage();
            } catch (\Exception $e) {
                // Ignore parse errors
            }
        }
    }

    public function selectDate($date)
    {
        $this->selected_date = $date;
        $this->resetPage();
    }

    public function getRangeTitleProperty()
    {
        $start = $this->getBaseCarbon();
        $end = $start->copy()->addDays(6);
        if ($start->format('M Y') === $end->format('M Y')) {
            return $start->format('F Y');
        }
        return $start->format('M Y') . ' — ' . $end->format('M Y');
    }

    public function getRangeSubtitleProperty()
    {
        $start = $this->getBaseCarbon();
        $end = $start->copy()->addDays(6);
        return $start->format('D, M d') . ' – ' . $end->format('D, M d, Y');
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
        $this->activeBooking = Booking::findOrFail($bookingId);
        $this->players = $this->activeBooking->players;
        $this->came = $this->activeBooking->came ?? 'No';
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

        session()->flash('message', 'Attendance updated.');
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

    <!-- Scoped Styles for Modern Date Picker -->
    <style>
        .date-card-item {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .date-card-item:hover:not(.active-date-card) {
            border-color: #86efac !important;
            background-color: #f0fdf4 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px -2px rgba(22, 163, 74, 0.15) !important;
        }
        .date-nav-btn {
            transition: all 0.15s ease;
            background-color: #ffffff;
            color: #475569;
        }
        .date-nav-btn:hover {
            background-color: #16a34a !important;
            color: #ffffff !important;
            border-color: #16a34a !important;
            transform: scale(1.06);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
        }
        .date-strip-container::-webkit-scrollbar {
            display: none;
        }
        .date-strip-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <!-- Modern Horizontal Date Picker -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-body p-3 p-md-4">
            <!-- Header: Date Context & Action Controls -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom border-light-subtle">
                <!-- Left: Calendar Icon + Month/Year + Date Range -->
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 shadow-xs"
                        style="width: 2.75rem; height: 2.75rem; background: #ecfdf5; color: #16a34a;">
                        <svg style="width: 1.35rem; height: 1.35rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            {{ $this->range_title }}
                        </h5>
                        <div class="text-muted extra-small fw-medium">
                            {{ $this->range_subtitle }}
                        </div>
                    </div>
                </div>

                <!-- Right: Quick Jump Actions -->
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- "Today" quick button -->
                    <button type="button" wire:click="goToToday"
                        class="btn btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-1.5 fw-medium {{ $selected_date === Carbon::today()->toDateString() ? 'btn-success text-white shadow-xs' : 'btn-outline-secondary' }}"
                        style="font-size: 0.8125rem;">
                        <svg style="width: 0.95rem; height: 0.95rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Today</span>
                    </button>

                    <!-- Direct Calendar Date Picker -->
                    <div class="position-relative">
                        <input type="date" wire:model.live="selected_date"
                            class="form-control form-control-sm rounded-pill px-3 text-secondary border-secondary-subtle"
                            style="font-size: 0.8125rem; height: 32px;"
                            title="Jump to date">
                    </div>
                </div>
            </div>

            <!-- Main Row: Left Chevron + 7 Date Cards + Right Chevron -->
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <!-- Previous 7 Days Arrow -->
                <button type="button" wire:click="prevRange"
                    class="flex-shrink-0 btn btn-light border border-secondary-subtle rounded-circle p-0 d-flex align-items-center justify-content-center shadow-xs date-nav-btn"
                    style="width: 2.75rem; height: 2.75rem;"
                    title="Previous 7 days"
                    aria-label="Previous 7 days">
                    <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- 7-Day Cards Strip -->
                <div class="flex-grow-1 d-flex align-items-stretch gap-2 gap-md-3 overflow-x-auto py-1 px-1 date-strip-container">
                    @foreach($dates as $date)
                        @php
                            $isSelected = ($selected_date === $date['full']);
                        @endphp
                        <button type="button" wire:key="date-{{ $date['full'] }}"
                            wire:click="selectDate('{{ $date['full'] }}')"
                            class="flex-fill flex-shrink-0 border-0 rounded-4 py-3 px-2 d-flex flex-column align-items-center justify-content-between position-relative date-card-item {{ $isSelected ? 'active-date-card' : '' }}"
                            style="min-width: 5.25rem; min-height: 6.25rem; cursor: pointer;
                                {{ $isSelected
                                    ? 'background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: #ffffff; box-shadow: 0 10px 22px -3px rgba(22, 163, 74, 0.4); transform: translateY(-3px);'
                                    : 'background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.03);' }}">
                            
                            <!-- Day of week or Today badge -->
                            <div class="mb-1 w-100 text-center">
                                @if($date['is_today'])
                                    <span class="badge rounded-pill fw-bold {{ $isSelected ? 'bg-white text-success' : 'bg-success-subtle text-success border border-success-subtle' }}"
                                        style="font-size: 0.65rem; padding: 2px 7px; letter-spacing: 0.5px;">
                                        TODAY
                                    </span>
                                @else
                                    <span class="text-uppercase fw-semibold {{ $isSelected ? 'text-white-50' : 'text-secondary' }}"
                                        style="font-size: 0.725rem; letter-spacing: 0.75px;">
                                        {{ $date['short'] }}
                                    </span>
                                @endif
                            </div>

                            <!-- Day Number -->
                            <div class="my-1">
                                <span class="fw-bold {{ $isSelected ? 'text-white' : 'text-dark' }}" style="font-size: 1.55rem; line-height: 1;">
                                    {{ $date['day'] }}
                                </span>
                            </div>

                            <!-- Month and Bookings Count -->
                            <div class="d-flex flex-column align-items-center gap-1 w-100 mt-1">
                                <span class="fw-medium {{ $isSelected ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.725rem;">
                                    {{ $date['month'] }}
                                </span>

                                @if($date['booking_count'] > 0)
                                    <span class="badge rounded-pill d-inline-flex align-items-center gap-1 {{ $isSelected ? 'bg-white bg-opacity-25 text-white' : 'bg-success-subtle text-success border border-success-subtle' }}"
                                        style="font-size: 0.65rem; padding: 2px 7px;"
                                        title="{{ $date['booking_count'] }} booking{{ $date['booking_count'] > 1 ? 's' : '' }}">
                                        <span class="rounded-circle {{ $isSelected ? 'bg-white' : 'bg-success' }}" style="width: 5px; height: 5px;"></span>
                                        <span>{{ $date['booking_count'] }} {{ $date['booking_count'] === 1 ? 'booking' : 'bookings' }}</span>
                                    </span>
                                @else
                                    <span class="extra-small {{ $isSelected ? 'text-white-50' : 'text-muted' }}" style="font-size: 0.65rem; opacity: 0.6;">
                                        0 bookings
                                    </span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>

                <!-- Next 7 Days Arrow -->
                <button type="button" wire:click="nextRange"
                    class="flex-shrink-0 btn btn-light border border-secondary-subtle rounded-circle p-0 d-flex align-items-center justify-content-center shadow-xs date-nav-btn"
                    style="width: 2.75rem; height: 2.75rem;"
                    title="Next 7 days"
                    aria-label="Next 7 days">
                    <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
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
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-center">Payment Status</th>
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
                                    <div class="bg-{{ $isCancelled ? 'secondary' : 'success' }}-subtle text-{{ $isCancelled ? 'secondary' : 'success' }} rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        {{ strtoupper(substr($booking->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold {{ $isCancelled ? 'text-decoration-line-through text-muted' : '' }}">{{ $booking->user->name }}</div>
                                        <div class="extra-small text-muted">{{ $booking->user->mobile_number }}</div>
                                        <div class="extra-small text-primary fw-medium">{{ $booking->turf->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @foreach($booking->slots as $slot)
                                    <div class="badge bg-light text-dark fw-normal border mb-1" style="font-size: 0.65rem;">
                                        {{ Carbon::parse($slot->from)->format('g:i A') }} -
                                        {{ Carbon::parse($slot->to)->format('g:i A') }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-center">
                                @if($isCancelled)
                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">CANCELLED</span>
                                    @if($booking->cancelled_at)
                                        <div class="extra-small text-muted mt-1">{{ Carbon::parse($booking->cancelled_at)->format('d M, g:i A') }}</div>
                                    @endif
                                @elseif($booking->is_fully_paid)
                                    <span
                                        class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">Full
                                        Payment Received</span>
                                @elseif($booking->payment_type === 'PayAtLocation')
                                    <span
                                        class="badge rounded-pill bg-info-subtle text-info border border-info-subtle px-3 py-2">Pay
                                        At Location</span>
                                @else
                                    <span
                                        class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">Part
                                        Payment
                                        ({{ number_format(($booking->total_received / $booking->amount) * 100, 0) }}%)</span>
                                @endif

                                @if(!$isCancelled && $booking->came === 'Yes')
                                    <div
                                        class="mt-1 extra-small text-success fw-bold px-1 d-flex align-items-center justify-content-center gap-1">
                                        <svg style="width: 0.75rem; height: 0.75rem;" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        {{ $booking->players }} Players Present
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($isCancelled)
                                    <div class="small fw-bold text-muted text-decoration-line-through">{{ number_format($booking->amount, 2) }} ₹</div>
                                    @if($booking->refund_amount > 0)
                                        <div class="extra-small text-success fw-bold">Ref: {{ number_format($booking->refund_amount, 2) }} ₹</div>
                                    @endif
                                @else
                                    <div class="small fw-bold">{{ number_format($booking->amount, 2) }} ₹</div>
                                    <div class="extra-small text-muted">Rec: {{ number_format($booking->total_received, 2) }} ₹
                                    </div>
                                    @if(!$booking->is_fully_paid)
                                        <div class="extra-small text-danger fw-medium">Due:
                                            {{ number_format($booking->amount - $booking->total_received, 2) }} ₹
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    @if(!$isCancelled)
                                        <button wire:click="openPaymentModal({{ $booking->id }})"
                                            class="btn btn-sm btn-outline-success rounded-3 @if($booking->is_fully_paid) disabled @endif"
                                            @if($booking->is_fully_paid) disabled title="Full payment already received" @endif>
                                            Collect
                                        </button>
                                        <button wire:click="openAttendanceModal({{ $booking->id }})"
                                            class="btn btn-sm btn-outline-primary rounded-3">
                                            Status
                                        </button>
                                        <button wire:click="cancelBooking({{ $booking->id }})"
                                            wire:confirm="Are you sure you want to cancel this booking? Refund will be calculated based on system settings."
                                            wire:loading.attr="disabled"
                                            wire:target="cancelBooking({{ $booking->id }})"
                                            class="btn btn-sm btn-outline-danger rounded-3">
                                            Cancel
                                        </button>
                                    @else
                                        <span class="text-muted extra-small fst-italic py-1 px-2">No actions available</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
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
                                    {{ number_format($activeBooking->amount - $activeBooking->total_received, 2) }} ₹
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
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-bottom p-4">
                    <h5 class="modal-title fw-bold">Update Attendance</h5>
                    <button type="button" class="btn-close" wire:click="closeModals"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Did they show up?</label>
                        <div class="d-flex gap-3">
                            <div class="flex-grow-1">
                                <input type="radio" wire:model="came" value="Yes" class="btn-check" id="came_yes">
                                <label class="btn btn-outline-success w-100 rounded-3 py-2 fw-bold"
                                    for="came_yes">YES</label>
                            </div>
                            <div class="flex-grow-1">
                                <input type="radio" wire:model="came" value="No" class="btn-check" id="came_no">
                                <label class="btn btn-outline-danger w-100 rounded-3 py-2 fw-bold"
                                    for="came_no">NO</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-muted">Total Players</label>
                        <input wire:model="players" type="number" class="form-control form-control-lg rounded-3"
                            placeholder="Enter number of players...">
                        @error('players') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer border-top p-4">
                    <button wire:click="saveAttendance"
                        class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm">Update Attendance</button>
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
    </style>
</div>