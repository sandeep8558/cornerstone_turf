<?php

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Location;
use App\Models\Slot;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public string $period = 'month'; // 'day', 'month', 'year', 'all'
    public string $currentDate = ''; // 'YYYY-MM-DD'

    public function mount()
    {
        $this->currentDate = Carbon::today()->toDateString();
    }

    public function setPeriod(string $period)
    {
        if (in_array($period, ['day', 'month', 'year', 'all'])) {
            $this->period = $period;
        }
    }

    public function previousPeriod()
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->period === 'day') {
            $this->currentDate = $date->subDay()->toDateString();
        } elseif ($this->period === 'month') {
            $this->currentDate = $date->subMonth()->startOfMonth()->toDateString();
        } elseif ($this->period === 'year') {
            $this->currentDate = $date->subYear()->startOfYear()->toDateString();
        }
    }

    public function nextPeriod()
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->period === 'day') {
            $this->currentDate = $date->addDay()->toDateString();
        } elseif ($this->period === 'month') {
            $this->currentDate = $date->addMonth()->startOfMonth()->toDateString();
        } elseif ($this->period === 'year') {
            $this->currentDate = $date->addYear()->startOfYear()->toDateString();
        }
    }

    public function goToCurrent()
    {
        $this->currentDate = Carbon::today()->toDateString();
    }

    public function getDateRangeProperty(): array
    {
        if ($this->period === 'all') {
            return [null, null];
        }

        $date = Carbon::parse($this->currentDate);

        if ($this->period === 'day') {
            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        if ($this->period === 'month') {
            return [$date->copy()->startOfMonth()->startOfDay(), $date->copy()->endOfMonth()->endOfDay()];
        }

        if ($this->period === 'year') {
            return [$date->copy()->startOfYear()->startOfDay(), $date->copy()->endOfYear()->endOfDay()];
        }

        return [null, null];
    }

    public function getPeriodLabelProperty(): string
    {
        if ($this->period === 'all') {
            return 'All Time';
        }

        $date = Carbon::parse($this->currentDate);

        if ($this->period === 'day') {
            return $date->isToday()
                ? 'Today (' . $date->format('d M Y') . ')'
                : $date->format('D, d M Y');
        }

        if ($this->period === 'month') {
            return $date->isCurrentMonth() && $date->isCurrentYear()
                ? 'This Month (' . $date->format('F Y') . ')'
                : $date->format('F Y');
        }

        if ($this->period === 'year') {
            return $date->isCurrentYear()
                ? 'This Year (' . $date->format('Y') . ')'
                : $date->format('Y');
        }

        return '';
    }

    public function with(): array
    {
        [$startDate, $endDate] = $this->dateRange;

        // Base bookings query for the period
        $periodBookingsQuery = Booking::query()
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
            });

        // Financial active bookings (Success or Pending)
        $activeBookings = (clone $periodBookingsQuery)
            ->whereIn('status', ['Success', 'Pending'])
            ->with(['bookingPayments', 'turf', 'user', 'slots'])
            ->get();

        $totalBilling = $activeBookings->sum('amount');
        $totalReceived = $activeBookings->sum(function ($b) {
            return $b->total_received;
        });
        $pendingPayments = $activeBookings->sum(function ($b) {
            return max(0, $b->amount - $b->total_received);
        });

        // Booking Counts
        $totalBookings = (clone $periodBookingsQuery)->where('status', '!=', 'Failed')->count();
        $attendedBookings = (clone $periodBookingsQuery)->where('status', '!=', 'Failed')->where('came', true)->count();
        $cancelledBookings = (clone $periodBookingsQuery)->where('status', 'Cancelled')->count();

        // Average Booking Value
        $avgBookingValue = $totalBookings > 0 ? ($totalBilling / $totalBookings) : 0;

        // Customer Attendance Rate
        $attendanceRate = $totalBookings > 0 ? round(($attendedBookings / $totalBookings) * 100, 1) : 0;

        // Slot Occupancy Rate Calculation
        $activeSlotsCount = Slot::where('is_active', true)->count();
        $daysCount = 1;
        if ($this->period === 'month' && $startDate && $endDate) {
            $daysCount = $startDate->daysInMonth;
        } elseif ($this->period === 'year' && $startDate && $endDate) {
            $daysCount = $startDate->isLeapYear() ? 366 : 365;
        } elseif ($this->period === 'all') {
            $firstBooking = Booking::orderBy('date', 'asc')->first();
            $daysCount = $firstBooking ? max(1, Carbon::parse($firstBooking->date)->diffInDays(now()) + 1) : 30;
        }

        $totalAvailableSlotSlots = max(1, $activeSlotsCount * $daysCount);
        $totalBookedSlots = DB::table('booking_slot')
            ->join('bookings', 'booking_slot.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', ['Success', 'Pending'])
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bookings.date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->count();

        $occupancyRate = min(100, round(($totalBookedSlots / $totalAvailableSlotSlots) * 100, 1));

        // Turf Breakdown Performance
        $turfs = Turf::whereIn('is_active', ['Yes', '1', 1, true])->get();
        $turfStats = $turfs->map(function ($turf) use ($activeBookings, $totalBilling) {
            $turfBookings = $activeBookings->where('turf_id', $turf->id);
            $rev = $turfBookings->sum('amount');
            $count = $turfBookings->count();
            $share = $totalBilling > 0 ? round(($rev / $totalBilling) * 100, 1) : 0;

            return [
                'id' => $turf->id,
                'name' => $turf->name,
                'type' => $turf->turf_type,
                'revenue' => $rev,
                'bookings_count' => $count,
                'share' => $share,
            ];
        })->sortByDesc('revenue');

        // Payment Method Split
        $paymentTypeStats = $activeBookings->groupBy('payment_type')->map(function ($items, $type) use ($totalBilling) {
            $sum = $items->sum('amount');
            $share = $totalBilling > 0 ? round(($sum / $totalBilling) * 100, 1) : 0;
            return [
                'type' => $type ?: 'Other',
                'amount' => $sum,
                'count' => $items->count(),
                'share' => $share,
            ];
        })->values();

        // Recent / Today's Bookings Spotlight
        $spotlightBookings = (clone $periodBookingsQuery)
            ->where('status', '!=', 'Failed')
            ->with(['user', 'turf', 'slots', 'bookingPayments'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        // Users stats
        $totalUsers = User::count();
        $totalManagers = User::role('Manager')->count();
        $newUsersInPeriod = User::query()
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->count();

        return [
            'totalBilling' => $totalBilling,
            'totalReceived' => $totalReceived,
            'pendingPayments' => $pendingPayments,
            'avgBookingValue' => $avgBookingValue,
            'totalBookings' => $totalBookings,
            'attendedBookings' => $attendedBookings,
            'attendanceRate' => $attendanceRate,
            'cancelledBookings' => $cancelledBookings,
            'totalBookedSlots' => $totalBookedSlots,
            'occupancyRate' => $occupancyRate,
            'turfStats' => $turfStats,
            'paymentTypeStats' => $paymentTypeStats,
            'spotlightBookings' => $spotlightBookings,
            'totalUsers' => $totalUsers,
            'totalManagers' => $totalManagers,
            'newUsersInPeriod' => $newUsersInPeriod,
        ];
    }
}; ?>

<div>
    <!-- Interactive Date Filter Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            
            <!-- Period Tabs: Day, Month, Year, All -->
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted extra-small fw-bold text-uppercase d-none d-md-inline" style="letter-spacing: 0.05em;">Period:</span>
                <div class="btn-group p-1 bg-light rounded-pill border" role="group">
                    <button type="button" 
                            wire:click="setPeriod('day')" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-semibold transition-all {{ $period === 'day' ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-muted' }}">
                        Day
                    </button>
                    <button type="button" 
                            wire:click="setPeriod('month')" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-semibold transition-all {{ $period === 'month' ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-muted' }}">
                        Month
                    </button>
                    <button type="button" 
                            wire:click="setPeriod('year')" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-semibold transition-all {{ $period === 'year' ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-muted' }}">
                        Year
                    </button>
                    <button type="button" 
                            wire:click="setPeriod('all')" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-semibold transition-all {{ $period === 'all' ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-muted' }}">
                        All Time
                    </button>
                </div>
            </div>

            <!-- Navigation Controls: Prev / Current Period Badge / Next / Jump to Today -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($period !== 'all')
                    <button type="button" 
                            wire:click="previousPeriod" 
                            class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1.5 d-inline-flex align-items-center gap-1 shadow-sm"
                            title="Previous {{ ucfirst($period) }}">
                        <svg style="width: 0.95rem; height: 0.95rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                        </svg>
                        <span class="d-none d-sm-inline small">Prev</span>
                    </button>

                    <div class="px-3 py-1.5 bg-light rounded-pill border fw-bold text-dark small d-flex align-items-center gap-2 shadow-sm">
                        <svg style="width: 0.95rem; height: 0.95rem;" class="text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        <span wire:loading.remove>{{ $this->periodLabel }}</span>
                        <div wire:loading class="spinner-border spinner-border-sm text-primary" role="status" style="width: 0.85rem; height: 0.85rem;"></div>
                    </div>

                    <button type="button" 
                            wire:click="nextPeriod" 
                            class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1.5 d-inline-flex align-items-center gap-1 shadow-sm"
                            title="Next {{ ucfirst($period) }}">
                        <span class="d-none d-sm-inline small">Next</span>
                        <svg style="width: 0.95rem; height: 0.95rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                    </button>

                    <button type="button" 
                            wire:click="goToCurrent" 
                            class="btn btn-sm btn-subtle-primary rounded-pill px-3 py-1.5 fw-semibold shadow-sm ms-1"
                            title="Reset to current {{ $period }}">
                        Today
                    </button>
                @else
                    <div class="px-3 py-1.5 bg-light rounded-pill border fw-bold text-dark small d-flex align-items-center gap-2">
                        <span class="badge bg-secondary rounded-pill">All Historical Records</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Section 1: Financial Summary -->
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="h6 fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.08em; font-size: 0.8rem;">
                Financial Summary <span class="text-primary fw-medium text-capitalize">({{ $this->periodLabel }})</span>
            </h4>
        </div>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 g-lg-4">
            <!-- Total Billing -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #6366f1 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Billing</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background: #e0e7ff; color: #4f46e5;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($totalBilling, 2) }}</h3>
                        <span class="text-muted extra-small">Billed in selected period</span>
                    </div>
                </div>
            </div>

            <!-- Total Received -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #10b981 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Received</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background: #d1fae5; color: #059669;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($totalReceived, 2) }}</h3>
                        <span class="text-success extra-small fw-semibold">
                            {{ $totalBilling > 0 ? round(($totalReceived / $totalBilling) * 100, 1) : 100 }}% collected
                        </span>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #f43f5e !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Pending Payments</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background: #ffe4e6; color: #e11d48;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($pendingPayments, 2) }}</h3>
                        <span class="text-danger extra-small fw-semibold">Uncollected balance</span>
                    </div>
                </div>
            </div>

            <!-- Avg Booking Value -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #8b5cf6 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Avg Booking Value</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background: #ede9fe; color: #7c3aed;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($avgBookingValue, 2) }}</h3>
                        <span class="text-muted extra-small">Per booking ticket size</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Bookings & Operations -->
    <div class="mb-5">
        <h4 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing: 0.08em; font-size: 0.8rem;">
            Bookings & Pitch Operations
        </h4>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 g-lg-4">
            <!-- Total Bookings -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #0ea5e9 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Bookings</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.25rem; height: 2.25rem; background: #e0f2fe; color: #0284c7;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18m-3-12h15c.621 0 1.125.504 1.125 1.125v10.5c0 .621-.504 1.125-1.125 1.125H4.5c-.621 0-1.125-.504-1.125-1.125V7.125C3.375 6.504 3.879 6 4.5 6Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $totalBookings }}</h3>
                        <span class="text-muted extra-small">Confirmed reservations</span>
                    </div>
                </div>
            </div>

            <!-- Turf Occupancy Rate -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Pitch Occupancy</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.25rem; height: 2.25rem; background: #fef3c7; color: #d97706;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $occupancyRate }}%</h3>
                        <span class="text-muted extra-small">{{ $totalBookedSlots }} slot(s) utilized</span>
                    </div>
                </div>
            </div>

            <!-- Customer Attendance / Checked In -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #0d9488 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Show-up Rate</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.25rem; height: 2.25rem; background: #ccfbf1; color: #0f766e;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $attendanceRate }}%</h3>
                        <span class="text-muted extra-small">{{ $attendedBookings }} players checked-in</span>
                    </div>
                </div>
            </div>

            <!-- Cancellations -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 stat-card" style="border-left: 4px solid #64748b !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Cancellations</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.25rem; height: 2.25rem; background: #f1f5f9; color: #475569;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $cancelledBookings }}</h3>
                        <span class="text-muted extra-small">Cancelled bookings</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Visual Breakdowns (Turf Revenue & Payment Methods) -->
    <div class="row g-4 mb-5">
        <!-- Revenue by Turf -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="h6 fw-bold text-dark mb-1">Turf Performance Breakdown</h5>
                        <p class="text-muted extra-small mb-0">Revenue & volume distributed across active turfs</p>
                    </div>
                    <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 extra-small fw-semibold">
                        {{ $turfStats->count() }} Turfs
                    </span>
                </div>

                <div class="mt-3">
                    @forelse($turfStats as $ts)
                        <div class="mb-3.5 pb-2">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-dark small">{{ $ts['name'] }}</span>
                                    <span class="badge bg-light text-muted border rounded-pill px-2 py-0.5 extra-small">
                                        {{ $ts['type'] }}
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="fw-extrabold text-dark small">₹{{ number_format($ts['revenue'], 2) }}</span>
                                    <span class="text-muted extra-small ms-1">({{ $ts['bookings_count'] }} bookings)</span>
                                </div>
                            </div>
                            <div class="progress rounded-pill" style="height: 8px; background-color: #f1f5f9;">
                                <div class="progress-bar rounded-pill bg-primary" 
                                     role="progressbar" 
                                     style="width: {{ $ts['share'] }}%;" 
                                     aria-valuenow="{{ $ts['share'] }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-muted small">No active turfs found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Payment Method Breakdown & Users Snapshot -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="h6 fw-bold text-dark mb-1">Payment Modes</h5>
                        <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 extra-small fw-semibold">
                            Split
                        </span>
                    </div>

                    @forelse($paymentTypeStats as $pts)
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 mb-2 bg-light bg-opacity-50 border border-light">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-white shadow-sm p-1.5 d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem;">
                                    @if(strtolower($pts['type']) === 'online' || strtolower($pts['type']) === 'razorpay')
                                        <svg style="width: 1rem; height: 1rem;" class="text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                        </svg>
                                    @else
                                        <svg style="width: 1rem; height: 1rem;" class="text-success" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v10.5m0 0h1.5a.75.75 0 0 1 .75.75v.75m0 0h12m-12 0a2.25 2.25 0 0 0 2.25 2.25h12M3.75 4.5h16.5m0 0v.75a.75.75 0 0 0 .75.75h.75m0 0v10.5m0 0h-.75a.75.75 0 0 0-.75.75v.75m0 0H6.75" />
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <span class="fw-bold text-dark small text-capitalize">{{ $pts['type'] }}</span>
                                    <div class="text-muted extra-small">{{ $pts['count'] }} booking(s)</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-extrabold text-dark small">₹{{ number_format($pts['amount'], 2) }}</div>
                                <span class="badge bg-success-subtle text-success extra-small">{{ $pts['share'] }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-3 text-center text-muted small">No payment data in this period.</div>
                    @endforelse
                </div>

                <!-- Users & Customer Base in period -->
                <div class="pt-3 border-top mt-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <span class="text-muted extra-small fw-semibold d-block">Total App Users</span>
                                <span class="fw-extrabold text-dark fs-5">{{ $totalUsers }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <span class="text-muted extra-small fw-semibold d-block">New in Period</span>
                                <span class="fw-extrabold text-primary fs-5">+{{ $newUsersInPeriod }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Period Bookings Spotlight -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white">
        <div class="card-header bg-white border-bottom border-light px-4 py-3 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="h6 fw-bold text-dark mb-0">Bookings Spotlight</h5>
                <span class="text-muted extra-small">Latest reservations scheduled in this period</span>
            </div>
            <a href="{{ route('administrator.bookings') }}" class="btn btn-sm btn-link text-decoration-none fw-semibold text-primary">
                View All Bookings &rarr;
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Date & Slot</th>
                        <th class="text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Customer</th>
                        <th class="text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Turf</th>
                        <th class="text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Billing</th>
                        <th class="text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Payment</th>
                        <th class="pe-4 text-end text-muted extra-small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($spotlightBookings as $b)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark small">{{ \Carbon\Carbon::parse($b->date)->format('d M Y') }}</div>
                                <div class="text-muted extra-small">
                                    @if($b->slots->isNotEmpty())
                                        {{ $b->slots->first()->from }} - {{ $b->slots->last()->to }}
                                    @else
                                        Standard Slot
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small">{{ $b->user?->name ?? 'Guest User' }}</div>
                                <div class="text-muted extra-small">{{ $b->user?->mobile ?? '—' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 extra-small fw-semibold">
                                    {{ $b->turf?->name ?? 'Turf' }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small">₹{{ number_format($b->amount, 2) }}</div>
                                @if($b->additional_discount > 0)
                                    <span class="text-success extra-small">-₹{{ number_format($b->additional_discount, 2) }} off</span>
                                @endif
                            </td>
                            <td>
                                @if($b->total_received >= $b->amount)
                                    <span class="badge bg-success text-white rounded-pill px-2.5 py-1 extra-small fw-semibold">Paid</span>
                                @elseif($b->total_received > 0)
                                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 extra-small fw-semibold">Partial (₹{{ number_format($b->total_received, 0) }})</span>
                                @else
                                    <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 extra-small fw-semibold">Unpaid</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                @if($b->came)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 extra-small fw-semibold">
                                        Attended
                                    </span>
                                @elseif($b->status === 'Cancelled')
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 extra-small fw-semibold">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 extra-small fw-semibold">
                                        Scheduled
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted small">
                                No bookings found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06) !important;
        }
    </style>
</div>
