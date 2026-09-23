<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $status = Password::sendResetLink($this->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

@section('title', 'Forgot Password')
@section('meta_description', 'Recover your password for your Cornerstone Turf account to resume booking your favorite sports slots.')

<div>
    <h2>Forgot password?</h2>
    <p class="subtitle">Enter your email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="status-box">{{ session('status') }}</div>
    @endif

    <form wire:submit="sendPasswordResetLink">

        <div class="field">
            <label for="email">Email Address</label>
            <input wire:model="email" id="email" type="email" name="email"
                   required autofocus placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <button type="submit" class="btn-primary">Send Reset Link</button>

    </form>

    <hr class="auth-divider">
    <div class="auth-footer">
        <a class="auth-link" href="{{ route('login') }}" wire:navigate>&larr; Back to Sign In</a>
    </div>
</div>
