<?php

use App\Models\BookingPayment;
use Livewire\Volt\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $search = '';
    public $perPage = 15;

    public function updatingSearch() {
        $this->perPage = 15;
    }

    public function loadMore() {
        $this->perPage += 15;
    }

    public function deletePayment($paymentId) {
        DB::transaction(function () use ($paymentId) {
            $payment = BookingPayment::findOrFail($paymentId);
            $booking = $payment->booking;
            $amount = $payment->amount;

            $payment->delete();

            if ($booking) {
                $totalReceived = $booking->bookingPayments()->sum('amount');
                $totalAmount = floatval($booking->amount);

                if ($totalReceived >= $totalAmount && $totalAmount > 0) {
                    $booking->update(['payment_type' => 'Full']);
                } elseif ($totalReceived > 0) {
                    $booking->update(['payment_type' => 'Part']);
                } else {
                    $booking->update(['payment_type' => 'PayAtLocation']);
                }
            }

            session()->flash('message', "Payment record #{$paymentId} of ₹" . number_format($amount, 2) . " deleted successfully. Booking balance recalculated.");
        });
    }

    public function with() {
        $query = BookingPayment::with(['booking.user', 'booking.turf', 'booking.location'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function($q) {
                // Search by Booking ID
                $q->where('booking_id', 'like', '%' . $this->search . '%')
                  // Search by Payment ID
                  ->orWhere('id', 'like', '%' . $this->search . '%')
                  // Search by User Name via Booking
                  ->orWhereHas('booking.user', function($qu) {
                      $qu->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('mobile_number', 'like', '%' . $this->search . '%');
                  })
                  // Search by Payment Method (type)
                  ->orWhere('type', 'like', '%' . $this->search . '%');
            });
        }

        return [
            'payments' => $query->paginate($this->perPage),
        ];
    }
}; ?>

<div class="text-dark">
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 d-flex align-items-center justify-content-between p-3" role="alert">
            <div class="d-flex align-items-center gap-2">
                <svg style="width: 1.25rem; height: 1.25rem; color: #16a34a;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="fw-semibold small">{{ session('message') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Header/Search Area -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="h4 fw-bold mb-0">Payment Ledger</h3>
                    <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill extra-small fw-semibold">
                        {{ $payments->total() }} Total
                    </span>
                </div>
                <p class="text-muted small mb-0">Track all transaction history, customer payments, and manage records.</p>
            </div>
            <div class="col-md-6">
                <div class="input-group input-group-lg border rounded-4 overflow-hidden">
                    <span class="input-group-text bg-white border-0 ps-4">
                        <svg style="width: 1.25rem; height: 1.25rem; color: #94a3b8;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </span>
                    <input wire:model.live="search" type="text" class="form-control border-0 px-2 py-3 fs-6 h-auto" placeholder="Search by Booking ID, Name or Mobile...">
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0 small fw-bold text-muted text-uppercase">Payment ID</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Booking Ref</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Customer</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Method</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-end">Amount</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-end">Date / Time</th>
                        <th class="pe-4 py-3 border-0 small fw-bold text-muted text-uppercase text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="ps-4 fw-bold text-muted">#{{ $payment->id }}</td>
                            <td>
                                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill mb-1">
                                    Booking #{{ $payment->booking_id }}
                                </div>
                                <div class="extra-small fw-bold text-dark">{{ $payment->booking->location->name ?? 'N/A' }}</div>
                                <div class="extra-small text-success">{{ $payment->booking->turf->name ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $payment->booking->user->name ?? 'Unknown' }}</div>
                                <div class="extra-small text-muted">{{ $payment->booking->user->mobile_number ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge rounded-3 px-3 py-2 fw-semibold {{ $payment->type === 'Cash' ? 'bg-success text-white' : 'bg-info-subtle text-info border border-info' }}">
                                    {{ $payment->type }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="h6 mb-0 fw-bold text-dark">{{ number_format($payment->amount, 2) }} ₹</div>
                            </td>
                            <td class="text-end">
                                <div class="small fw-medium">{{ Carbon::parse($payment->created_at)->format('M d, Y') }}</div>
                                <div class="extra-small text-muted">{{ Carbon::parse($payment->created_at)->format('g:i A') }}</div>
                            </td>
                            <td class="pe-4 text-end">
                                <button wire:click="deletePayment({{ $payment->id }})"
                                    wire:confirm="Are you sure you want to delete Payment #{{ $payment->id }} of ₹{{ number_format($payment->amount, 2) }}? Booking balance will be recalculated."
                                    wire:loading.attr="disabled"
                                    class="btn btn-sm btn-outline-danger rounded-3 d-inline-flex align-items-center gap-1 action-delete-btn"
                                    style="font-size: 0.8rem; font-weight: 500;"
                                    title="Delete false or duplicate payment entry">
                                    <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    <span>Delete</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center text-muted">
                                <div class="py-4">No payment records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasMorePages())
            <div x-data="{
                    loading: false,
                    observe() {
                        const observer = new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting && !this.loading) {
                                this.loading = true;
                                $wire.loadMore().then(() => {
                                    this.loading = false;
                                }).catch(() => {
                                    this.loading = false;
                                });
                            }
                        }, { rootMargin: '250px' });
                        observer.observe($el);
                    }
                }" 
                x-init="observe()" 
                class="card-footer bg-white border-top-0 py-4 text-center">
                
                <button wire:click="loadMore" wire:loading.attr="disabled"
                    class="btn btn-sm btn-outline-success rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs transition-all">
                    <span wire:loading.remove wire:target="loadMore" class="d-inline-flex align-items-center gap-2">
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
                        </svg>
                        <span>Load More Payments ({{ $payments->count() }} of {{ $payments->total() }})</span>
                    </span>
                    <span wire:loading wire:target="loadMore" class="d-inline-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm text-success" role="status" aria-hidden="true"></span>
                        <span>Loading more payments...</span>
                    </span>
                </button>
            </div>
        @else
            @if($payments->total() > 0)
                <div class="card-footer bg-white border-top-0 py-3 text-center text-muted extra-small">
                    <span>Showing all {{ $payments->total() }} payments</span>
                </div>
            @endif
        @endif
    </div>

    <style>
        .extra-small { font-size: 0.7rem; }
        .h-auto { height: auto !important; }
        .action-delete-btn {
            transition: all 0.15s ease-in-out;
        }
        .action-delete-btn:hover {
            transform: translateY(-1px);
        }
    </style>
</div>
