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
        $this->base_date = Carbon::today();
        $this->generateDates();
    }

    public function generateDates()
    {
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

    public function prevRange()
    {
        $this->base_date = $this->base_date->subDays(7);
        $this->generateDates();
    }

    public function nextRange()
    {
        $this->base_date = $this->base_date->addDays(7);
        $this->generateDates();
    }

    public function selectDate($date)
    {
        $this->selected_date = $date;
        $this->resetPage();
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

    public function clearAllBookings()
    {
        try {
            DB::transaction(function () {
                Booking::query()->delete();
            });

            session()->flash('message', 'All orders have been successfully cleared.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear orders: ' . $e->getMessage());
        }
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

    <!-- Horizontal Date Picker -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-center align-items-center gap-3">
                <!-- Left Arrow -->
                <button wire:click="prevRange"
                    class="flex-shrink-0 border-0 rounded-4 p-3 d-flex align-items-center justify-content-center transition-all bg-light text-muted"
                    style="width: 4.5rem; height: 4.5rem;">
                    <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Dates -->
                <div class="d-flex gap-3 no-scrollbar overflow-x-auto">
                    @foreach($dates as $date)
                        <button type="button" wire:key="date-{{ $date['full'] }}"
                            wire:click="selectDate('{{ $date['full'] }}')"
                            class="flex-shrink-0 border-0 rounded-4 p-3 d-flex flex-column align-items-center transition-all"
                            style="min-width: 4.5rem; {{ $selected_date == $date['full'] ? 'background: #16a34a; color: white; transform: translateY(-4px); box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);' : 'background: #f8fafc; color: #64748b;' }}">
                            <span class="extra-small fw-semibold opacity-75">{{ $date['short'] }}</span>
                            <span class="h4 fw-bold my-1">{{ $date['day'] }}</span>
                            <span class="extra-small fw-medium opacity-75">{{ $date['month'] }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Right Arrow -->
                <button wire:click="nextRange"
                    class="flex-shrink-0 border-0 rounded-4 p-3 d-flex align-items-center justify-content-center transition-all bg-light text-muted"
                    style="width: 4.5rem; height: 4.5rem;">
                    <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Bookings List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h3 class="h6 fw-bold mb-0">
                        @if($search)
                            Search Results for "{{ $search }}"
                        @else
                            Bookings for {{ Carbon::parse($selected_date)->format('M d, Y') }}
                        @endif
                    </h3>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
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
                <div class="col-md-3 mt-3 mt-md-0 text-end">
                    <button wire:click="clearAllBookings"
                        wire:confirm="⚠️ WARNING: This will permanently delete ALL bookings and orders from the database! This action cannot be undone. Are you sure you want to proceed?"
                        class="btn btn-danger btn-sm rounded-3 py-2 px-3 fw-semibold shadow-sm d-flex align-items-center gap-2 float-end">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Clear All Orders
                    </button>
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