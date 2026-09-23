<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }
        Auth::user()->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/');
    }
}; ?>

@section('title', 'Verify Email')
@section('meta_description', 'Please verify your email address to activate your Cornerstone Turf account and secure slot bookings.')

<div>
    <div class="icon-circle" style="background:#dcfce7;">
        <svg width="24" height="24" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>

    <h2>Verify your email</h2>
    <p class="subtitle">
        Thanks for signing up! Click the verification link we emailed you. Didn't receive it? We can resend it.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="status-box">A new verification link has been sent to your email address.</div>
    @endif

    <button wire:click="sendVerification" type="button" class="btn-primary">
        Resend Verification Email
    </button>

    <hr class="auth-divider">
    <div class="auth-footer">
        <button wire:click="logout" type="button"
            style="background:none; border:none; cursor:pointer; color:#dc2626; font-size:.875rem; font-weight:600; text-decoration:underline;">
            Log Out
        </button>
    </div>
</div>
