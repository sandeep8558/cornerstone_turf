<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false));
    }
}; ?>

@section('title', 'Login')
@section('meta_description', 'Sign in to your Cornerstone Turf account to manage bookings, payments, and secure your football or cricket slot.')

<div>
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your account to continue</p>

    <x-auth-session-status class="status-box" :status="session('status')" />

    <form wire:submit="login">

        <div class="field">
            <label for="login">Email or Mobile Number</label>
            <input wire:model="form.login" id="login" type="text" name="login"
                   required autofocus autocomplete="username"
                   placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('form.login')" class="field-error" />
        </div>

        <div class="field">
            <div class="label-row">
                <label for="password">Password</label>
                @if (Route::has('password.request'))
                    <a class="auth-link" style="font-size:.8rem;" href="{{ route('password.request') }}" wire:navigate>
                        Forgot password?
                    </a>
                @endif
            </div>
            <input wire:model="form.password" id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('form.password')" class="field-error" />
        </div>

        <div class="check-row">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
            <label for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn-primary">Sign In</button>

    </form>

    @if (Route::has('register'))
        <hr class="auth-divider">
        <div class="auth-footer">
            Don't have an account?
            <a class="auth-link" href="{{ route('register') }}" wire:navigate>Create Account</a>
        </div>
    @endif
</div>
