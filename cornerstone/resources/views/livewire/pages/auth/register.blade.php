<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $mobile_number = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $user->assignRole('Client');

        Auth::login($user, remember: true);

        $this->redirect(route('dashboard', absolute: false));
    }
}; ?>

@section('title', 'Create Account')
@section('meta_description', 'Register a new account with Cornerstone Turf to easily book premium football and box cricket turf slots in Ambernath 24/7.')

<div>
    <h2>Create account</h2>
    <p class="subtitle">Fill in the details below to get started</p>

    <form wire:submit="register">

        <div class="field">
            <label for="name">Full Name</label>
            <input wire:model="name" id="name" type="text" name="name"
                   required autofocus autocomplete="name"
                   placeholder="John Smith" />
            <x-input-error :messages="$errors->get('name')" class="field-error" />
        </div>

        <div class="field">
            <label for="mobile_number">Mobile Number</label>
            <input wire:model="mobile_number" id="mobile_number" type="tel" name="mobile_number"
                   required autocomplete="tel"
                   placeholder="+1 234 567 8900" />
            <x-input-error :messages="$errors->get('mobile_number')" class="field-error" />
        </div>

        <div class="field">
            <label for="email">Email Address</label>
            <input wire:model="email" id="email" type="email" name="email"
                   required autocomplete="username"
                   placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input wire:model="password" id="password" type="password" name="password"
                   required autocomplete="new-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password"
                   name="password_confirmation" required autocomplete="new-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="field-error" />
        </div>

        <button type="submit" class="btn-primary" style="margin-top:.5rem;">Create Account</button>

    </form>

    <hr class="auth-divider">
    <div class="auth-footer">
        Already have an account?
        <a class="auth-link" href="{{ route('login') }}" wire:navigate>Sign In</a>
    </div>
</div>
