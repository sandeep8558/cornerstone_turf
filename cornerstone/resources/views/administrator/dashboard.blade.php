<x-administrator-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="heading">Dashboard</x-slot>

    <!-- Interactive Livewire Dashboard with Day/Month/Year filters and rich analytics -->
    <livewire:administrator.dashboard-metrics />

    <!-- Welcome & Quick Links card -->
    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white mt-4">
        <h3 class="h6 fw-semibold text-dark mb-2">Welcome back, {{ auth()->user()->name }}</h3>
        <p class="small text-secondary mb-4">You have full access to manage the Cornerstone Turf system.</p>

        <div class="row row-cols-1 row-cols-md-3 g-3">
            <div class="col">
                <a href="{{ route('administrator.users') }}"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle hover-bg-green-100 transition-all text-decoration-none">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-5.996-3.457M17 20H7m10 0v-1c0-.653-.1-1.283-.284-1.875M7 20H2v-1a4 4 0 015.996-3.457M7 20v-1c0-.653.1-1.283.284-1.875m9.432 0A5.97 5.97 0 0012 15a5.97 5.97 0 00-4.716 2.125M15 7a3 3 0 11-6 0 3 3 0 016 0Zm6 3a2 2 0 11-4 0 2 2 0 014 0ZM7 10a2 2 0 11-4 0 2 2 0 014 0Z"/>
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">User Manager</p>
                        <p class="extra-small text-success opacity-75 mb-0">Manage all users and roles</p>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('administrator.profile') }}"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle hover-bg-green-100 transition-all text-decoration-none">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0Z"/>
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">My Profile</p>
                        <p class="extra-small text-success opacity-75 mb-0">Update your account details</p>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('administrator.bookings') }}"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle hover-bg-green-100 transition-all text-decoration-none">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18m-3-12h15c.621 0 1.125.504 1.125 1.125v10.5c0 .621-.504 1.125-1.125 1.125H4.5c-.621 0-1.125-.504-1.125-1.125V7.125C3.375 6.504 3.879 6 4.5 6Z" />
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">Booking Manager</p>
                        <p class="extra-small text-success opacity-75 mb-0">Manage reservations & slots</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-administrator-layout>
