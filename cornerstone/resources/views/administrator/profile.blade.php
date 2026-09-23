<x-administrator-layout>
    <x-slot name="title">Profile</x-slot>
    <x-slot name="heading">My Profile</x-slot>

    <div style="max-width: 42rem;">

        <!-- Profile info card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h2 class="h6 fw-semibold text-dark mb-4">Profile Information</h2>

            <!-- Avatar -->
            <div class="d-flex align-items-center gap-4 mb-4">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width: 4rem; height: 4rem; font-size: 1.5rem;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div>
                    <p class="fw-bold text-dark mb-0">{{ auth()->user()->name }}</p>
                    <p class="small text-secondary mb-0">{{ auth()->user()->email }}</p>
                    @foreach (auth()->user()->roles as $role)
                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fw-medium mt-1" style="font-size: 0.7rem;">
                            {{ $role->name }}
                        </span>
                    @endforeach
                </div>
            </div>

            <livewire:profile.update-profile-information-form />
        </div>

        <!-- Update password card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h2 class="h6 fw-semibold text-dark mb-4">Update Password</h2>
            <livewire:profile.update-password-form />
        </div>

        <!-- Delete account card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 border-start border-danger border-4">
            <h2 class="h6 fw-semibold text-danger mb-1">Danger Zone</h2>
            <p class="small text-secondary mb-4">Once your account is deleted, all of its resources and data will be permanently deleted.</p>
            <livewire:profile.delete-user-form />
        </div>

    </div>

</x-administrator-layout>
