<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    public function confirmPassword(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        if (! Auth::guard('web')->validate([
            'email'    => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);
        $this->redirectIntended(default: route('dashboard', absolute: false));
    }
}; ?>

@section('title', 'Confirm Password')
@section('meta_description', 'Please confirm your password to access this secure area of Cornerstone Turf.')

<div>
    <div class="icon-circle" style="background:#fee2e2;">
        <svg width="24" height="24" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>

    <h2>Confirm password</h2>
    <p class="subtitle">This is a secure area. Please confirm your password before continuing.</p>

    <form wire:submit="confirmPassword">

        <div class="field">
            <label for="password">Password</label>
            <input wire:model="password" id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <button type="submit" class="btn-primary">Confirm</button>

    </form>
</div>
