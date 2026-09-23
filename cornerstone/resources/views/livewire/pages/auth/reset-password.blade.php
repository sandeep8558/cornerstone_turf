<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token'    => ['required'],
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password'       => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

@section('title', 'Reset Password')
@section('meta_description', 'Set a new password for your Cornerstone Turf account to protect your profile and booking details.')

<div>
    <h2>Reset password</h2>
    <p class="subtitle">Choose a strong new password for your account.</p>

    <form wire:submit="resetPassword">

        <div class="field">
            <label for="email">Email Address</label>
            <input wire:model="email" id="email" type="email" name="email"
                   required autofocus autocomplete="username"
                   placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="field-error" />
        </div>

        <div class="field">
            <label for="password">New Password</label>
            <input wire:model="password" id="password" type="password" name="password"
                   required autocomplete="new-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="field-error" />
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm New Password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password"
                   name="password_confirmation" required autocomplete="new-password"
                   placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="field-error" />
        </div>

        <button type="submit" class="btn-primary">Reset Password</button>

    </form>
</div>
