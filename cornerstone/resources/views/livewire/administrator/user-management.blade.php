<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $rolesByList;
    public $permissionsByList;
    protected $paginationTheme = 'bootstrap';
    
    public $showingUserModal = false;
    public $editingUser = null;
    
    public $name = '';
    public $email = '';
    public $mobile_number = '';
    public $password = '';
    public $selectedRoles = [];
    public $selectedPermissions = [];

    public function mount() {
        $this->rolesByList = Role::all();
        $this->permissionsByList = Permission::all();
    }

    public function updatingSearch() {
        $this->resetPage();
    }

    public function users() {
        return User::with(['roles', 'permissions'])
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('mobile_number', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function addUser() {
        $this->reset(['name', 'email', 'mobile_number', 'password', 'selectedRoles', 'selectedPermissions', 'editingUser']);
        $this->showingUserModal = true;
        $this->dispatch('open-modal', 'user-modal');
    }

    public function editUser(User $user) {
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile_number = $user->mobile_number;
        $this->password = '';
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->selectedPermissions = $user->permissions->pluck('name')->toArray();
        $this->showingUserModal = true;
        $this->dispatch('open-modal', 'user-modal');
    }

    public function saveUser() {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->editingUser?->id)],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'password' => [$this->editingUser ? 'nullable' : 'required', 'string', 'min:8'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedPermissions' => ['nullable', 'array'],
        ]);

        if ($this->editingUser) {
            $this->editingUser->update([
                'name' => $this->name,
                'email' => $this->email,
                'mobile_number' => $this->mobile_number,
            ]);
            if ($this->password) {
                $this->editingUser->update(['password' => Hash::make($this->password)]);
            }
            $this->editingUser->syncRoles($this->selectedRoles);
            $this->editingUser->syncPermissions($this->selectedPermissions);
            session()->flash('message', 'User updated successfully.');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'mobile_number' => $this->mobile_number,
                'password' => Hash::make($this->password),
            ]);
            $user->assignRole($this->selectedRoles);
            $user->syncPermissions($this->selectedPermissions);
            session()->flash('message', 'User created successfully.');
        }

        $this->showingUserModal = false;
        $this->dispatch('close-modal', 'user-modal');
    }

    public function deleteUser(User $user) {
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete yourself.');
            return;
        }
        $user->delete();
        session()->flash('message', 'User deleted successfully.');
    }
}; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden text-dark">
    <!-- Messages -->
    @if (session()->has('message'))
        <div class="alert alert-success border-0 rounded-0 mb-0 small py-2 px-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close small" data-bs-dismiss="alert" style="font-size: 0.5rem;"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger border-0 rounded-0 mb-0 small py-2 px-4">
            {{ session('error') }}
        </div>
    @endif

    @php
        $users = $this->users();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Recent Users ({{ $users->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by name, email or phone...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addUser" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add User</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Name / Contact</th>
                    <th class="px-4 py-3 text-start fw-semibold">Roles</th>
                    <th class="px-4 py-3 text-start fw-semibold" style="max-width: 200px;">Direct Capabilities</th>
                    <th class="px-4 py-3 text-start fw-semibold text-nowrap">Joined</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width: 2.25rem; height: 2.25rem; font-size: 0.85rem;">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark">{{ $user->name }}</span>
                                    <span class="extra-small text-muted">{{ $user->email }}</span>
                                    @if($user->mobile_number)
                                        <span class="extra-small text-primary fw-medium">{{ $user->mobile_number }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fw-medium" style="font-size: 0.6rem;">
                                        {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-muted extra-small italic">No roles</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3" style="max-width: 200px;">
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($user->permissions as $perm)
                                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fw-medium" style="font-size: 0.6rem;">
                                        {{ $perm->name }}
                                    </span>
                                @empty
                                    <span class="text-muted extra-small italic">None</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted text-nowrap">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editUser({{ $user->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteUser({{ $user->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-5 text-center text-muted">
                            <svg class="mx-auto mb-3 text-light" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            <p class="small mb-0">No users found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $users->links() }}
    </div>

    <!-- User Modal (Bootstrap 5) -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form wire:submit="saveUser" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden text-dark">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0" id="userModalLabel">
                        {{ $editingUser ? 'Edit User: ' . $editingUser->name : 'Add New User' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Full Name</label>
                            <input wire:model="name" type="text" class="form-control rounded-3" placeholder="John Doe">
                            @error('name') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Email Address</label>
                            <input wire:model="email" type="email" class="form-control rounded-3" placeholder="john@example.com">
                            @error('email') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Mobile Number</label>
                            <input wire:model="mobile_number" type="text" class="form-control rounded-3" placeholder="+1234567890">
                            @error('mobile_number') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Password {{ $editingUser ? '(Leave blank to keep current)' : '' }}</label>
                            <input wire:model="password" type="password" class="form-control rounded-3" placeholder="••••••••">
                            @error('password') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted mb-2">Assign Roles</label>
                            <div class="p-3 bg-light rounded-3 border overflow-y-auto" style="max-height: 150px;">
                                @foreach($rolesByList as $role)
                                    <div class="form-check mb-2">
                                        <input wire:model="selectedRoles" class="form-check-input" type="checkbox" value="{{ $role->name }}" id="role_{{ $role->id }}">
                                        <label class="form-check-label small fw-medium text-dark" for="role_{{ $role->id }}">
                                            {{ $role->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedRoles') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted mb-2">Direct Capabilities</label>
                            <div class="p-3 bg-light rounded-3 border overflow-y-auto" style="max-height: 150px;">
                                @foreach($permissionsByList as $perm)
                                    <div class="form-check mb-2">
                                        <input wire:model="selectedPermissions" class="form-check-input" type="checkbox" value="{{ $perm->name }}" id="perm_{{ $perm->id }}">
                                        <label class="form-check-label small fw-medium text-dark" for="perm_{{ $perm->id }}">
                                            {{ $perm->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold">
                        {{ $editingUser ? 'Update User' : 'Create User' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modalElement = document.getElementById('userModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        });

        $wire.on('close-modal', () => {
            const modalElement = document.getElementById('userModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    </script>
    @endscript
</div>
