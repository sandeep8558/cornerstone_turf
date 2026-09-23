<?php

use App\Models\BookingPayment;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function with() {
        $query = BookingPayment::with(['booking.user', 'booking.turf'])
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
            'payments' => $query->paginate(5),
        ];
    }
}; ?>

<div class="text-dark">
    <!-- Header/Search Area -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <h3 class="h4 fw-bold mb-1">Payment Ledger</h3>
                <p class="text-muted small mb-0">Track all transaction history and customer payments.</p>
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
                        <th class="pe-4 py-3 border-0 small fw-bold text-muted text-uppercase text-end">Date / Time</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($payments as $payment)
                        <tr>
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
                            <td class="pe-4 text-end">
                                <div class="small fw-medium">{{ Carbon::parse($payment->created_at)->format('M d, Y') }}</div>
                                <div class="extra-small text-muted">{{ Carbon::parse($payment->created_at)->format('g:i A') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <div class="py-4">No payment records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="card-footer bg-white border-top-0 p-4">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    <style>
        .extra-small { font-size: 0.7rem; }
        .h-auto { height: auto !important; }
    </style>
</div>
