<?php

use App\Models\Coupon;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Form fields
    public $couponId = null;
    public $code = '';
    public $description = '';
    public $discount_type = 'percentage';
    public $discount_value = null;
    public $max_discount_amount = null;
    public $minimum_order_value = null;
    public $minimum_slots_to_be_ordered = null;
    public $usage_limit = null;
    public $usage_limit_per_user = null;
    public $starts_at = '';
    public $expires_at = '';
    public $is_active = true;
    public $mon = true;
    public $tue = true;
    public $wed = true;
    public $thu = true;
    public $fri = true;
    public $sat = true;
    public $sun = true;

    // UI state
    public $showFormModal = false;
    public $search = '';

    public function mount()
    {
        $this->starts_at = now()->toDateTimeLocalString();
        $this->expires_at = now()->addMonth()->toDateTimeLocalString();
        $this->mon = true;
        $this->tue = true;
        $this->wed = true;
        $this->thu = true;
        $this->fri = true;
        $this->sat = true;
        $this->sun = true;
    }

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->couponId = null;
        $this->showFormModal = true;
    }

    public function openEditModal($id)
    {
        $this->resetValidation();
        $coupon = Coupon::findOrFail($id);
        $this->couponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description;
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = $coupon->discount_value;
        $this->max_discount_amount = $coupon->max_discount_amount;
        $this->minimum_order_value = $coupon->minimum_order_value;
        $this->minimum_slots_to_be_ordered = $coupon->minimum_slots_to_be_ordered;
        $this->usage_limit = $coupon->usage_limit;
        $this->usage_limit_per_user = $coupon->usage_limit_per_user;
        $this->starts_at = $coupon->starts_at->toDateTimeLocalString();
        $this->expires_at = $coupon->expires_at->toDateTimeLocalString();
        $this->is_active = $coupon->is_active;
        $this->mon = (bool)$coupon->mon;
        $this->tue = (bool)$coupon->tue;
        $this->wed = (bool)$coupon->wed;
        $this->thu = (bool)$coupon->thu;
        $this->fri = (bool)$coupon->fri;
        $this->sat = (bool)$coupon->sat;
        $this->sun = (bool)$coupon->sun;

        $this->showFormModal = true;
    }

    public function closeModal()
    {
        $this->showFormModal = false;
    }

    public function resetForm()
    {
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = null;
        $this->max_discount_amount = null;
        $this->minimum_order_value = null;
        $this->minimum_slots_to_be_ordered = null;
        $this->usage_limit = null;
        $this->usage_limit_per_user = null;
        $this->starts_at = now()->toDateTimeLocalString();
        $this->expires_at = now()->addMonth()->toDateTimeLocalString();
        $this->is_active = true;
        $this->mon = true;
        $this->tue = true;
        $this->wed = true;
        $this->thu = true;
        $this->fri = true;
        $this->sat = true;
        $this->sun = true;
    }

    public function save()
    {
        $rules = [
            'code' => 'required|string|max:255|unique:coupons,code,' . $this->couponId,
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_order_value' => 'nullable|numeric|min:0',
            'minimum_slots_to_be_ordered' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'required|date',
            'expires_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'mon' => 'boolean',
            'tue' => 'boolean',
            'wed' => 'boolean',
            'thu' => 'boolean',
            'fri' => 'boolean',
            'sat' => 'boolean',
            'sun' => 'boolean',
        ];

        if ($this->discount_type === 'percentage') {
            $rules['discount_value'] .= '|max:100';
        }

        $validated = $this->validate($rules);

        // Convert empty strings to null for nullable fields
        foreach (['max_discount_amount', 'minimum_order_value', 'minimum_slots_to_be_ordered', 'usage_limit', 'usage_limit_per_user', 'description'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->couponId) {
            Coupon::findOrFail($this->couponId)->update($validated);
            session()->flash('message', 'Coupon updated successfully.');
        } else {
            Coupon::create($validated);
            session()->flash('message', 'Coupon created successfully.');
        }

        $this->closeModal();
    }

    public function toggleActive($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        session()->flash('message', 'Coupon status updated.');
    }

    public function deleteCoupon($id)
    {
        Coupon::findOrFail($id)->delete();
        session()->flash('message', 'Coupon deleted successfully.');
    }

    public function with()
    {
        return [
            'coupons' => Coupon::where('code', 'like', '%' . $this->search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ];
    }
}; ?>

<div class="text-dark">
    @if (session()->has('message'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Header Actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="max-width: 400px;">
            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </span>
            <input wire:model.live.debounce.300ms="search" type="text" class="form-control border-0 shadow-sm rounded-4 py-2 ps-5" placeholder="Search coupon code...">
        </div>
        <button wire:click="openCreateModal" class="btn btn-success border-0 shadow-sm rounded-4 px-4 py-2 d-flex align-items-center gap-2">
            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="fw-bold">Create Coupon</span>
        </button>
    </div>

    <!-- Coupons List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0 small fw-bold text-muted text-uppercase">Coupon Code</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Discount</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Usage</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Validity</th>
                        <th class="py-3 border-0 small fw-bold text-muted text-uppercase text-center">Status</th>
                        <th class="pe-4 py-3 border-0 small fw-bold text-muted text-uppercase text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($coupons as $coupon)
                        <tr wire:key="coupon-{{ $coupon->id }}">
                            <td class="ps-4">
                                <div class="fw-bold text-dark">{{ $coupon->code }}</div>
                                <div class="extra-small text-muted text-truncate" style="max-width: 200px;">{{ $coupon->description ?: 'No description' }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-success">
                                    @if($coupon->discount_type === 'percentage')
                                        {{ number_format($coupon->discount_value, 0) }}% OFF
                                    @else
                                        ₹{{ number_format($coupon->discount_value, 2) }} OFF
                                    @endif
                                </div>
                                <div class="extra-small text-muted">
                                    Min Order: ₹{{ number_format($coupon->minimum_order_value ?: 0, 0) }}
                                    @if($coupon->minimum_slots_to_be_ordered)
                                        | Min Slots: {{ $coupon->minimum_slots_to_be_ordered }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="extra-small fw-medium">
                                    Used: <span class="text-primary">{{ $coupon->used_count }}</span>
                                    @if($coupon->usage_limit)
                                        / <span class="text-muted">{{ $coupon->usage_limit }}</span>
                                    @endif
                                </div>
                                <div class="progress mt-1" style="height: 4px; width: 80px;">
                                    @php
                                        $percent = $coupon->usage_limit ? ($coupon->used_count / $coupon->usage_limit) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar bg-primary" style="width: {{ min($percent, 100) }}%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="extra-small">
                                    <span class="text-muted">From:</span> {{ $coupon->starts_at->format('M d, y') }}
                                </div>
                                <div class="extra-small">
                                    <span class="text-muted">Until:</span> 
                                    <span class="{{ $coupon->expires_at->isPast() ? 'text-danger fw-bold' : '' }}">
                                        {{ $coupon->expires_at->format('M d, y') }}
                                    </span>
                                </div>
                                <div class="mt-1 d-flex gap-1 flex-wrap">
                                    @php
                                        $allDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                                        $activeDays = [];
                                        foreach($allDays as $d) {
                                            if($coupon->$d) $activeDays[] = ucfirst(substr($d, 0, 2));
                                        }
                                    @endphp
                                    @if(count($activeDays) === 7)
                                        <span class="badge bg-light text-success border border-success-subtle extra-small fw-semibold">All Days</span>
                                    @elseif(count($activeDays) === 0)
                                        <span class="badge bg-light text-danger border border-danger-subtle extra-small fw-semibold">No Days</span>
                                    @else
                                        <span class="badge bg-light text-secondary border border-secondary-subtle extra-small fw-semibold" style="font-size: 0.65rem;" title="{{ implode(', ', $activeDays) }}">
                                            {{ implode(', ', $activeDays) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                @if($coupon->expires_at->isPast())
                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">EXPIRED</span>
                                @elseif(!$coupon->is_active)
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2">INACTIVE</span>
                                @elseif($coupon->starts_at->isFuture())
                                    <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle px-3 py-2">UPCOMING</span>
                                @else
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">ACTIVE</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button wire:click="toggleActive({{ $coupon->id }})" class="btn btn-sm btn-outline-{{ $coupon->is_active ? 'warning' : 'success' }} rounded-3" title="{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}">
                                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            @if($coupon->is_active)
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 18.364a9 9 0 0 1 0-12.728m12.728 0a9 9 0 0 1 0 12.728m-9.9-2.829a5 5 0 0 1 0-7.07m7.072 0a5 5 0 0 1 0 7.07M13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            @endif
                                        </svg>
                                    </button>
                                    <button wire:click="openEditModal({{ $coupon->id }})" class="btn btn-sm btn-outline-primary rounded-3">
                                        Edit
                                    </button>
                                    <button wire:click="deleteCoupon({{ $coupon->id }})" wire:confirm="Are you sure you want to delete this coupon?" class="btn btn-sm btn-outline-danger rounded-3">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
                                <div class="text-muted">No coupons found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 bg-light border-top">
            {{ $coupons->links() }}
        </div>
    </div>

    <!-- Form Modal -->
    <div class="modal fade @if($showFormModal) show d-block @endif" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom p-4">
                    <h5 class="modal-title fw-bold">{{ $couponId ? 'Edit Coupon' : 'Create New Coupon' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Coupon Code</label>
                            <input wire:model="code" type="text" class="form-control rounded-3" placeholder="e.g. SUMMER50">
                            @error('code') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Discount Type</label>
                            <select wire:model.live="discount_type" class="form-select rounded-3">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                            @error('discount_type') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Discount Value</label>
                            <div class="input-group">
                                <input wire:model="discount_value" type="number" step="0.01" class="form-control rounded-3" placeholder="0.00">
                                <span class="input-group-text bg-light border-start-0 rounded-end-3">{{ $discount_type === 'percentage' ? '%' : '₹' }}</span>
                            </div>
                            @error('discount_value') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Max Discount Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3">₹</span>
                                <input wire:model="max_discount_amount" type="number" step="0.01" class="form-control rounded-3" placeholder="Optional">
                            </div>
                            @error('max_discount_amount') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Description</label>
                            <textarea wire:model="description" class="form-control rounded-3" rows="2" placeholder="Describe the offer..."></textarea>
                            @error('description') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Minimum Order Value</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3">₹</span>
                                <input wire:model="minimum_order_value" type="number" step="0.01" class="form-control rounded-3" placeholder="Optional">
                            </div>
                            @error('minimum_order_value') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Minimum Slots to Order</label>
                            <input wire:model="minimum_slots_to_be_ordered" type="number" class="form-control rounded-3" placeholder="Optional">
                            @error('minimum_slots_to_be_ordered') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Usage Limit</label>
                            <input wire:model="usage_limit" type="number" class="form-control rounded-3" placeholder="Total">
                            @error('usage_limit') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Per User</label>
                            <input wire:model="usage_limit_per_user" type="number" class="form-control rounded-3" placeholder="Limit">
                            @error('usage_limit_per_user') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Starts At</label>
                            <input wire:model="starts_at" type="datetime-local" class="form-control rounded-3">
                            @error('starts_at') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted text-uppercase">Expires At</label>
                            <input wire:model="expires_at" type="datetime-local" class="form-control rounded-3">
                            @error('expires_at') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted text-uppercase d-block mb-2">Applicable Days</label>
                            <div class="d-flex flex-wrap gap-2 justify-content-between p-3 bg-light rounded-4">
                                @foreach(['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                                    <div class="flex-grow-1 text-center">
                                        <input type="checkbox" class="btn-check" id="day-{{ $key }}" wire:model="{{ $key }}" autocomplete="off">
                                        <label class="btn btn-outline-success w-100 rounded-3 py-2 fw-bold d-flex flex-column align-items-center justify-content-center day-toggle-btn" for="day-{{ $key }}" style="min-width: 50px;">
                                            <span class="small">{{ $label }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('mon') <div class="text-danger extra-small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch p-3 bg-light rounded-4 d-flex align-items-center justify-content-between">
                                <label class="form-check-label fw-bold text-dark mb-0 ms-2" for="is_active">Active Status</label>
                                <input wire:model="is_active" class="form-check-input" type="checkbox" id="is_active" style="width: 3rem; height: 1.5rem;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-4">
                    <button type="button" class="btn btn-light rounded-4 px-4 py-2" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-success rounded-4 px-4 py-2 fw-bold shadow-sm" wire:click="save">
                        {{ $couponId ? 'Update Coupon' : 'Create Coupon' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .extra-small {
            font-size: 0.75rem;
        }
        .form-label {
            letter-spacing: 0.025em;
        }
        .day-toggle-btn {
            transition: all 0.2s ease-in-out;
            border-color: #cbd5e1;
            background-color: white;
            color: #475569;
        }
        .btn-check:checked + .day-toggle-btn {
            background-color: #198754 !important;
            border-color: #198754 !important;
            color: white !important;
            transform: scale(1.03);
            box-shadow: 0 4px 6px -1px rgba(25, 135, 84, 0.15), 0 2px 4px -2px rgba(25, 135, 84, 0.15);
        }
        .day-toggle-btn:hover {
            border-color: #198754;
            color: #198754;
        }
    </style>
</div>
