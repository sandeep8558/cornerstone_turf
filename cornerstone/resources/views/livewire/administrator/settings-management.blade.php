<?php

use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component {
    public $booking_open_days;
    public $is_cancellation_active;
    public $cancellation_hours;
    public $cancellation_fee;
    public $is_refund_active;
    public $razorpay_key;
    public $razorpay_secret;
    public $sms_gateway_token;

    public $is_part_payment_active;
    public $min_part_payment;
    public $is_pay_at_location_active;

    public function mount() {
        $settings = Setting::first() ?: new Setting();
        
        $this->booking_open_days = $settings->booking_open_days ?? 90;
        $this->is_cancellation_active = (bool)($settings->is_cancellation_active ?? false);
        $this->cancellation_hours = $settings->cancellation_hours ?? 24;
        $this->cancellation_fee = $settings->cancellation_fee ?? 100;
        $this->is_refund_active = (bool)($settings->is_refund_active ?? false);
        $this->is_part_payment_active = (bool)($settings->is_part_payment_active ?? false);
        $this->min_part_payment = $settings->min_part_payment ?? 500;
        $this->is_pay_at_location_active = (bool)($settings->is_pay_at_location_active ?? false);
        $this->razorpay_key = $settings->razorpay_key;
        $this->razorpay_secret = $settings->razorpay_secret;
        $this->sms_gateway_token = $settings->sms_gateway_token;
    }

    public function save() {
        $this->validate([
            'booking_open_days' => 'required|integer|min:1',
            'cancellation_hours' => 'required|integer|min:0',
            'cancellation_fee' => 'required|numeric|min:0',
            'min_part_payment' => 'required|numeric|min:0',
            'razorpay_key' => 'nullable|string',
            'razorpay_secret' => 'nullable|string',
            'sms_gateway_token' => 'nullable|string',
        ]);

        $settings = Setting::first() ?: new Setting();
        $settings->fill([
            'booking_open_days' => $this->booking_open_days,
            'is_cancellation_active' => $this->is_cancellation_active,
            'cancellation_hours' => $this->cancellation_hours,
            'cancellation_fee' => $this->cancellation_fee,
            'is_refund_active' => $this->is_refund_active,
            'is_part_payment_active' => $this->is_part_payment_active,
            'min_part_payment' => $this->min_part_payment,
            'is_pay_at_location_active' => $this->is_pay_at_location_active,
            'razorpay_key' => $this->razorpay_key,
            'razorpay_secret' => $this->razorpay_secret,
            'sms_gateway_token' => $this->sms_gateway_token,
        ])->save();

        session()->flash('message', 'Settings updated successfully.');
    }
}; ?>

<div class="text-dark">
    @if (session()->has('message'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Booking & Cancellation -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <h3 class="h6 fw-bold mb-0">Booking & Cancellation Rules</h3>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Booking Open For (Days)</label>
                        <input type="number" wire:model="booking_open_days" class="form-control form-control-lg rounded-3" placeholder="e.g. 90">
                        <div class="form-text extra-small mt-1">Number of days in advance customers can book.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between">
                            <label class="form-label small fw-semibold text-muted mb-0">Enable Cancellation</label>
                            <input class="form-check-input" type="checkbox" wire:model="is_cancellation_active" role="switch" style="width: 2.5rem; height: 1.25rem;">
                        </div>
                    </div>

                    <div class="row g-3 @if(!$is_cancellation_active) opacity-50 @endif">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Cancellation Hours</label>
                            <input type="number" wire:model="cancellation_hours" class="form-control rounded-3" @if(!$is_cancellation_active) disabled @endif>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Cancellation Fee (₹)</label>
                            <input type="number" wire:model="cancellation_fee" class="form-control rounded-3" @if(!$is_cancellation_active) disabled @endif>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold text-muted mb-0">Enable Auto-Refund</label>
                        <input class="form-check-input" type="checkbox" wire:model="is_refund_active" role="switch" style="width: 2.5rem; height: 1.25rem;">
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h3 class="h6 fw-bold mb-0">Payment Methods & Configuration</h3>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between">
                            <label class="form-label small fw-semibold text-muted mb-0">Accept Part Payments</label>
                            <input class="form-check-input" type="checkbox" wire:model="is_part_payment_active" role="switch" style="width: 2.5rem; height: 1.25rem;">
                        </div>
                    </div>

                    <div class="mb-4 @if(!$is_part_payment_active) opacity-50 @endif">
                        <label class="form-label small fw-semibold text-muted">Minimum Part Payment (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">₹</span>
                            <input type="number" wire:model="min_part_payment" class="form-control rounded-end-3" @if(!$is_part_payment_active) disabled @endif>
                        </div>
                        <div class="form-text extra-small mt-1">Minimum amount required to be paid at the time of booking.</div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold text-muted mb-0">Enable Pay at Location</label>
                        <input class="form-check-input" type="checkbox" wire:model="is_pay_at_location_active" role="switch" style="width: 2.5rem; height: 1.25rem;">
                    </div>
                    <div class="form-text extra-small mt-1 text-muted">Allow customers to book slots and pay directly at the turf.</div>
                </div>
            </div>
        </div>

        <!-- Integrations -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h3 class="h6 fw-bold mb-0">API & Integrations</h3>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted font-monospace">RAZORPAY_KEY</label>
                        <input type="text" wire:model="razorpay_key" class="form-control rounded-3" placeholder="rzp_live_...">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted font-monospace">RAZORPAY_SECRET</label>
                        <input type="password" wire:model="razorpay_secret" class="form-control rounded-3" placeholder="••••••••••••">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-muted font-monospace">SMS_GATEWAY_TOKEN</label>
                        <input type="password" wire:model="sms_gateway_token" class="form-control rounded-3" placeholder="Your API Token">
                    </div>
                </div>
                <div class="card-footer bg-light border-top p-4 mt-auto text-end">
                    <button wire:click="save" class="btn btn-primary px-5 rounded-3 py-2 fw-bold shadow-sm">Save All Settings</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .extra-small { font-size: 0.7rem; }
    </style>
</div>
