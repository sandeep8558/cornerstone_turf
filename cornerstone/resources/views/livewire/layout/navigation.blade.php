<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="navbar navbar-expand-lg navbar-light bg-white border-bottom border-light">
    <!-- Primary Navigation Menu -->
    <div class="container-xl">
        <div class="d-flex justify-content-between align-items-center w-100" style="height: 4rem;">
            <div class="d-flex align-items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 d-flex align-items-center me-4">
                    <a href="{{ route('dashboard') }}" wire:navigate class="navbar-brand">
                        <x-application-logo class="d-block py-1" style="height: 2.25rem; width: auto;" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="d-none d-lg-flex gap-4 h-100 align-items-center">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="d-none d-lg-flex align-items-center gap-3">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="btn btn-link text-decoration-none text-secondary d-inline-flex align-items-center gap-1 dropdown-toggle px-3 py-2 border rounded-2 bg-white small fw-medium">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="d-lg-none">
                <button @click="open = ! open" class="btn btn-light border-0 p-2 text-secondary">
                    <svg style="width: 1.5rem; height: 1.5rem;" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'d-none': open, 'd-inline-flex': ! open }" class="d-inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'d-none': ! open, 'd-inline-flex': open }" class="d-none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         class="d-lg-none bg-white border-top border-light py-2">
        <div class="px-2 pb-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-3 pb-2 border-top border-light">
            <div class="px-4 mb-2">
                <div class="fw-bold text-dark" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="small text-secondary">{{ auth()->user()->email }}</div>
            </div>

            <div class="px-2">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="btn btn-link nav-link text-start w-100 border-0 px-3 py-2">
                    {{ __('Log Out') }}
                </button>
            </div>
        </div>
    </div>
</nav>
